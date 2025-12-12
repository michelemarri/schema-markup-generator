<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Integration;

use WP_Post;

/**
 * MemberPress Courses Integration
 *
 * Integration with MemberPress Courses for lesson/course hierarchy.
 * Provides parent course detection for LearningResource schema.
 *
 * @package Metodo\SchemaMarkupGenerator\Integration
 * @author  Michele Marri <plugins@metodo.dev>
 */
class MemberPressCoursesIntegration
{
    /**
     * Post types handled by MemberPress Courses
     */
    public const LESSON_POST_TYPE = 'mpcs-lesson';
    public const COURSE_POST_TYPE = 'mpcs-course';

    /**
     * Virtual/computed fields for courses
     */
    private const VIRTUAL_FIELDS = [
        'mpcs_curriculum' => [
            'label' => 'Course Curriculum',
            'type' => 'text',
            'description' => 'Auto-generated course curriculum (sections and lessons). Ideal for mapping to syllabus.',
        ],
        'mpcs_curriculum_html' => [
            'label' => 'Course Curriculum (HTML)',
            'type' => 'text',
            'description' => 'Course curriculum formatted as HTML list with sections and lessons.',
        ],
        'mpcs_lesson_count' => [
            'label' => 'Lesson Count',
            'type' => 'number',
            'description' => 'Total number of lessons in the course.',
        ],
        'mpcs_section_count' => [
            'label' => 'Section Count',
            'type' => 'number',
            'description' => 'Total number of sections in the course.',
        ],
        'mpcs_enrollment_count' => [
            'label' => 'Total Enrollment',
            'type' => 'number',
            'description' => 'Total number of students enrolled in the course. Maps to totalHistoricalEnrollment.',
        ],
        'mpcs_total_duration' => [
            'label' => 'Total Duration (auto-calculated)',
            'type' => 'number',
            'description' => 'Total course duration in minutes. Auto-calculated from lesson video durations (YouTube/Vimeo). Maps to duration.',
        ],
        'mpcs_total_duration_hours' => [
            'label' => 'Total Duration in Hours (auto-calculated)',
            'type' => 'number',
            'description' => 'Total course duration in hours. Auto-calculated from lesson video durations. Maps to duration.',
        ],
        // Default values for Course schema
        'mpcs_course_mode' => [
            'label' => 'Course Mode (online)',
            'type' => 'text',
            'description' => 'Default course delivery mode: online. Maps to courseMode.',
        ],
        'mpcs_availability' => [
            'label' => 'Availability (InStock)',
            'type' => 'text',
            'description' => 'Default availability status: InStock (always available). Maps to availability.',
        ],
        'mpcs_price_free' => [
            'label' => 'Price (Free)',
            'type' => 'number',
            'description' => 'Default price: 0 (Free course). Maps to price.',
        ],
        'mpcs_is_free' => [
            'label' => 'Is Free (true)',
            'type' => 'boolean',
            'description' => 'Indicates this is a free course. Maps to isAccessibleForFree.',
        ],
    ];

    /**
     * Initialize integration
     */
    public function init(): void
    {
        // Register filters always - availability is checked when filters are called
        // This avoids timing issues with post type registration
        add_filter('smg_learning_resource_parent_course', [$this, 'getParentCourse'], 10, 3);
        add_filter('smg_course_schema_data', [$this, 'enhanceCourseSchema'], 10, 3);

        // Add course fields to discovery
        add_filter('smg_discovered_fields', [$this, 'addCourseFields'], 10, 2);

        // Resolve course field values
        add_filter('smg_resolve_field_value', [$this, 'resolveFieldValue'], 10, 4);

        // Recalculate course duration when lessons are saved
        add_action('save_post_' . self::LESSON_POST_TYPE, [$this, 'onLessonSave'], 10, 1);

        // Register cron hook for background duration calculation
        add_action('smg_calculate_course_duration', [$this, 'calculateAndSaveDuration'], 10, 1);

        // AJAX handler for manual duration calculation from admin
        add_action('wp_ajax_smg_calculate_course_durations', [$this, 'handleAjaxCalculateDurations']);
    }

    /**
     * Check if MemberPress Courses is active
     * Called when filters are executed, not during init
     */
    public function isAvailable(): bool
    {
        return post_type_exists(self::LESSON_POST_TYPE) && $this->sectionsTableExists();
    }

    /**
     * Check if the sections table exists
     */
    private function sectionsTableExists(): bool
    {
        global $wpdb;
        $tableName = $wpdb->prefix . 'mpcs_sections';

        $tableExists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $tableName)
        );

        return (bool) $tableExists;
    }

    /**
     * Get parent course for a MemberPress Courses lesson
     *
     * @param array|null $parentCourse Current parent course data (may be null)
     * @param WP_Post    $post         The lesson post
     * @param array      $mapping      Field mapping configuration
     * @return array|null Course data or null
     */
    public function getParentCourse(?array $parentCourse, WP_Post $post, array $mapping): ?array
    {
        // If already resolved by mapping, return that
        if ($parentCourse !== null) {
            return $parentCourse;
        }

        // Check availability at runtime
        if (!$this->isAvailable()) {
            return null;
        }

        // Only handle MemberPress Courses lessons
        if ($post->post_type !== self::LESSON_POST_TYPE) {
            return null;
        }

        return $this->getCourseFromLesson($post);
    }

    /**
     * Get course data from a lesson post
     *
     * MemberPress Courses structure: Lesson → Section → Course
     *
     * @param WP_Post $lesson The lesson post
     * @return array|null Course schema data
     */
    public function getCourseFromLesson(WP_Post $lesson): ?array
    {
        $sectionId = get_post_meta($lesson->ID, '_mpcs_lesson_section_id', true);

        if (!$sectionId) {
            return null;
        }

        $courseId = $this->getCourseIdFromSection((int) $sectionId);

        if (!$courseId) {
            return null;
        }

        $coursePost = get_post($courseId);

        if (!$coursePost) {
            return null;
        }

        return $this->buildCourseData($coursePost);
    }

    /**
     * Get course ID from section ID
     *
     * @param int $sectionId The section ID
     * @return int|null Course ID or null
     */
    public function getCourseIdFromSection(int $sectionId): ?int
    {
        global $wpdb;
        $tableName = $wpdb->prefix . 'mpcs_sections';

        $courseId = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT course_id FROM {$tableName} WHERE id = %d",
                $sectionId
            )
        );

        return $courseId ? (int) $courseId : null;
    }

    /**
     * Build course schema data from post
     *
     * @param WP_Post $course The course post
     * @return array Course schema data
     */
    private function buildCourseData(WP_Post $course): array
    {
        return [
            '@type' => 'Course',
            'name' => html_entity_decode(get_the_title($course), ENT_QUOTES, 'UTF-8'),
            'url' => get_permalink($course),
            'description' => $this->getCourseDescription($course),
        ];
    }

    /**
     * Get course description
     *
     * @param WP_Post $course The course post
     * @return string Course description
     */
    private function getCourseDescription(WP_Post $course): string
    {
        $excerpt = $course->post_excerpt;

        if (empty($excerpt)) {
            $excerpt = wp_trim_words(
                wp_strip_all_tags($course->post_content),
                30,
                '...'
            );
        }

        return $excerpt;
    }

    /**
     * Add course fields to discovered fields for the course post type
     *
     * @param array  $fields   Current discovered fields
     * @param string $postType The post type being queried
     * @return array Modified fields array
     */
    public function addCourseFields(array $fields, string $postType): array
    {
        // Only add fields for course post type
        if ($postType !== self::COURSE_POST_TYPE) {
            return $fields;
        }

        // Check availability
        if (!$this->isAvailable()) {
            return $fields;
        }

        // Add virtual/computed fields
        foreach (self::VIRTUAL_FIELDS as $key => $config) {
            $fields[] = [
                'key' => $key,
                'name' => $key,
                'label' => $config['label'],
                'type' => $config['type'],
                'source' => 'mpcs_virtual',
                'plugin' => 'memberpress_courses',
                'plugin_label' => 'MemberPress Courses (Computed)',
                'plugin_priority' => 15,
                'description' => $config['description'] ?? '',
                'virtual' => true,
            ];
        }

        return $fields;
    }

    /**
     * Resolve course field values
     *
     * @param mixed  $value    Current resolved value
     * @param int    $postId   The post ID
     * @param string $fieldKey The field key
     * @param string $source   The field source
     * @return mixed Resolved value
     */
    public function resolveFieldValue(mixed $value, int $postId, string $fieldKey, string $source): mixed
    {
        // Only handle mpcs_virtual source
        if ($source !== 'mpcs_virtual') {
            return $value;
        }

        // Check if this is a course post
        $post = get_post($postId);
        if (!$post || $post->post_type !== self::COURSE_POST_TYPE) {
            return $value;
        }

        // Check availability
        if (!$this->isAvailable()) {
            return $value;
        }

        return match ($fieldKey) {
            'mpcs_curriculum' => $this->getCurriculumText($postId),
            'mpcs_curriculum_html' => $this->getCurriculumHtml($postId),
            'mpcs_lesson_count' => $this->getLessonCount($postId),
            'mpcs_section_count' => $this->getSectionCount($postId),
            'mpcs_enrollment_count' => $this->getEnrollmentCount($postId),
            'mpcs_total_duration' => $this->getTotalDuration($postId),
            'mpcs_total_duration_hours' => $this->getTotalDurationHours($postId),
            // Default values for Course schema properties
            'mpcs_course_mode' => 'online',
            'mpcs_availability' => 'InStock',
            'mpcs_price_free' => 0,
            'mpcs_is_free' => true,
            default => $value,
        };
    }

    /**
     * Get total enrollment count for a course
     *
     * Uses MemberPress Courses user_progress table to count unique users who started the course.
     *
     * @param int $courseId The course ID
     * @return int Enrollment count
     */
    public function getEnrollmentCount(int $courseId): int
    {
        global $wpdb;
        $tableName = $wpdb->prefix . 'mpcs_user_progress';

        // Check if table exists
        $tableExists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $tableName)
        );

        if (!$tableExists) {
            return 0;
        }

        // Count unique users who have any progress on this course
        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM {$tableName} WHERE course_id = %d",
                $courseId
            )
        );

        return (int) $count;
    }

    /**
     * Get curriculum as plain text for syllabus mapping
     *
     * @param int $courseId The course ID
     * @return string Curriculum text
     */
    public function getCurriculumText(int $courseId): string
    {
        global $wpdb;
        $tableName = $wpdb->prefix . 'mpcs_sections';

        $sections = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, section_order FROM {$tableName} WHERE course_id = %d ORDER BY section_order ASC",
                $courseId
            )
        );

        if (empty($sections)) {
            return '';
        }

        $output = [];

        foreach ($sections as $index => $section) {
            $sectionNum = $index + 1;
            $sectionTitle = html_entity_decode($section->title, ENT_QUOTES, 'UTF-8');
            $output[] = "Section {$sectionNum}: {$sectionTitle}";

            // Get lessons in this section
            $lessons = $this->getLessonsInSection((int) $section->id);

            foreach ($lessons as $lessonIndex => $lesson) {
                $lessonNum = $lessonIndex + 1;
                $lessonTitle = html_entity_decode(get_the_title($lesson), ENT_QUOTES, 'UTF-8');
                $output[] = "  {$sectionNum}.{$lessonNum} {$lessonTitle}";
            }
        }

        return implode('. ', $output);
    }

    /**
     * Get curriculum as HTML list
     *
     * @param int $courseId The course ID
     * @return string Curriculum HTML
     */
    public function getCurriculumHtml(int $courseId): string
    {
        global $wpdb;
        $tableName = $wpdb->prefix . 'mpcs_sections';

        $sections = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, section_order FROM {$tableName} WHERE course_id = %d ORDER BY section_order ASC",
                $courseId
            )
        );

        if (empty($sections)) {
            return '';
        }

        $html = '<ol class="course-curriculum">';

        foreach ($sections as $section) {
            $sectionTitle = esc_html(html_entity_decode($section->title, ENT_QUOTES, 'UTF-8'));
            $html .= "<li><strong>{$sectionTitle}</strong>";

            // Get lessons in this section
            $lessons = $this->getLessonsInSection((int) $section->id);

            if (!empty($lessons)) {
                $html .= '<ol>';
                foreach ($lessons as $lesson) {
                    $lessonTitle = esc_html(html_entity_decode(get_the_title($lesson), ENT_QUOTES, 'UTF-8'));
                    $html .= "<li>{$lessonTitle}</li>";
                }
                $html .= '</ol>';
            }

            $html .= '</li>';
        }

        $html .= '</ol>';

        return $html;
    }

    /**
     * Get section count for a course
     *
     * @param int $courseId The course ID
     * @return int Section count
     */
    public function getSectionCount(int $courseId): int
    {
        global $wpdb;
        $tableName = $wpdb->prefix . 'mpcs_sections';

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$tableName} WHERE course_id = %d",
                $courseId
            )
        );

        return (int) $count;
    }

    /**
     * Enhance Course schema with MemberPress Courses data
     *
     * @param array   $data    Current schema data
     * @param WP_Post $post    The course post
     * @param array   $mapping Field mapping configuration
     * @return array Enhanced schema data
     */
    public function enhanceCourseSchema(array $data, WP_Post $post, array $mapping): array
    {
        // Check availability at runtime
        if (!$this->isAvailable()) {
            return $data;
        }

        if ($post->post_type !== self::COURSE_POST_TYPE) {
            return $data;
        }

        // Get integration settings
        $settings = \Metodo\SchemaMarkupGenerator\smg_get_settings('integrations');
        $includeCurriculum = $settings['mpcs_include_curriculum'] ?? false;

        // Add course curriculum (sections and lessons) only if setting is enabled
        if ($includeCurriculum) {
            $curriculum = $this->getCourseCurriculum($post->ID);

            if (!empty($curriculum)) {
                $data['hasCourseInstance'] = $curriculum;
            }
        }

        // Add lesson count (always useful, lightweight)
        $lessonCount = $this->getLessonCount($post->ID);
        if ($lessonCount > 0) {
            $data['numberOfLessons'] = $lessonCount;
        }

        // Auto-calculate total duration from lesson videos if not already set
        if (empty($data['timeRequired'])) {
            $totalMinutes = $this->getTotalDuration($post->ID);
            if ($totalMinutes > 0) {
                $data['timeRequired'] = $this->formatDurationISO8601($totalMinutes);
            }
        }

        return $data;
    }

    /**
     * Format duration in minutes to ISO 8601 format
     *
     * @param int $minutes Duration in minutes
     * @return string ISO 8601 duration (e.g., PT2H30M)
     */
    private function formatDurationISO8601(int $minutes): string
    {
        if ($minutes <= 0) {
            return '';
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        $duration = 'PT';
        if ($hours > 0) {
            $duration .= "{$hours}H";
        }
        if ($mins > 0) {
            $duration .= "{$mins}M";
        }

        return $duration;
    }

    /**
     * Get course curriculum (sections with lessons)
     *
     * @param int $courseId The course ID
     * @return array Curriculum data
     */
    public function getCourseCurriculum(int $courseId): array
    {
        global $wpdb;
        $tableName = $wpdb->prefix . 'mpcs_sections';

        $sections = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, title, section_order FROM {$tableName} WHERE course_id = %d ORDER BY section_order ASC",
                $courseId
            )
        );

        if (empty($sections)) {
            return [];
        }

        $curriculum = [];

        foreach ($sections as $section) {
            $sectionData = [
                '@type' => 'CourseInstance',
                'name' => $section->title,
                'courseMode' => 'online',
            ];

            // Get lessons in this section
            $lessons = $this->getLessonsInSection((int) $section->id);

            if (!empty($lessons)) {
                $sectionData['hasPart'] = array_map(function ($lesson) {
                    return [
                        '@type' => 'LearningResource',
                        'name' => html_entity_decode(get_the_title($lesson), ENT_QUOTES, 'UTF-8'),
                        'url' => get_permalink($lesson),
                    ];
                }, $lessons);
            }

            $curriculum[] = $sectionData;
        }

        return $curriculum;
    }

    /**
     * Get lessons in a section
     *
     * @param int $sectionId The section ID
     * @return array Array of lesson posts
     */
    public function getLessonsInSection(int $sectionId): array
    {
        $lessons = get_posts([
            'post_type' => self::LESSON_POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_mpcs_lesson_section_id',
                    'value' => $sectionId,
                    'compare' => '=',
                ],
            ],
            'orderby' => 'meta_value_num',
            'meta_key' => '_mpcs_lesson_lesson_order',
            'order' => 'ASC',
        ]);

        return $lessons;
    }

    /**
     * Get total lesson count for a course
     *
     * @param int $courseId The course ID
     * @return int Lesson count
     */
    public function getLessonCount(int $courseId): int
    {
        global $wpdb;
        $tableName = $wpdb->prefix . 'mpcs_sections';

        // Get all section IDs for this course
        $sectionIds = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$tableName} WHERE course_id = %d",
                $courseId
            )
        );

        if (empty($sectionIds)) {
            return 0;
        }

        // Count lessons in all sections
        $count = get_posts([
            'post_type' => self::LESSON_POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_mpcs_lesson_section_id',
                    'value' => $sectionIds,
                    'compare' => 'IN',
                ],
            ],
            'fields' => 'ids',
        ]);

        return count($count);
    }

    /**
     * Get all courses
     *
     * @return array Array of course posts
     */
    public function getAllCourses(): array
    {
        return get_posts([
            'post_type' => self::COURSE_POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
    }

    /**
     * Get course by lesson ID
     *
     * @param int $lessonId The lesson ID
     * @return WP_Post|null Course post or null
     */
    public function getCourseByLessonId(int $lessonId): ?WP_Post
    {
        $sectionId = get_post_meta($lessonId, '_mpcs_lesson_section_id', true);

        if (!$sectionId) {
            return null;
        }

        $courseId = $this->getCourseIdFromSection((int) $sectionId);

        if (!$courseId) {
            return null;
        }

        $course = get_post($courseId);

        return $course instanceof WP_Post ? $course : null;
    }

    /**
     * Get total duration of all lessons in a course (in minutes)
     *
     * Returns the pre-calculated duration stored in post meta.
     * Duration is calculated and saved when lessons are saved (via onLessonSave hook).
     * This ensures fast page loads - no oEmbed calls during page render.
     *
     * @param int $courseId The course ID
     * @return int Total duration in minutes
     */
    public function getTotalDuration(int $courseId): int
    {
        // Get pre-calculated duration from post meta (fast!)
        $savedDuration = get_post_meta($courseId, '_smg_total_duration_minutes', true);

        if ($savedDuration !== '' && is_numeric($savedDuration)) {
            return (int) $savedDuration;
        }

        // If not calculated yet, schedule background calculation and return 0
        // This prevents slow page loads on first visit
        $this->scheduleBackgroundCalculation($courseId);

        return 0;
    }

    /**
     * Calculate and save total duration for a course (expensive operation)
     *
     * This should only be called:
     * 1. When a lesson is saved (via onLessonSave hook)
     * 2. Via background/cron job
     * 3. Manually via admin action
     *
     * @param int $courseId The course ID
     * @return int Total duration in minutes
     */
    public function calculateAndSaveDuration(int $courseId): int
    {
        $totalSeconds = 0;
        $allLessons = $this->getAllLessonsInCourse($courseId);
        $lessonDurations = [];

        foreach ($allLessons as $lesson) {
            $duration = $this->getLessonDuration($lesson);
            $totalSeconds += $duration;

            // Also save individual lesson duration for future quick access
            if ($duration > 0) {
                update_post_meta($lesson->ID, '_smg_video_duration_seconds', $duration);
                $lessonDurations[$lesson->ID] = $duration;
            }
        }

        $totalMinutes = (int) ceil($totalSeconds / 60);

        // Save to post meta for fast retrieval
        update_post_meta($courseId, '_smg_total_duration_minutes', $totalMinutes);
        update_post_meta($courseId, '_smg_total_duration_seconds', $totalSeconds);
        update_post_meta($courseId, '_smg_duration_last_calculated', time());

        return $totalMinutes;
    }

    /**
     * Schedule background calculation for a course
     *
     * Uses WP-Cron to calculate duration without blocking page load
     *
     * @param int $courseId The course ID
     */
    private function scheduleBackgroundCalculation(int $courseId): void
    {
        $hookName = 'smg_calculate_course_duration';

        // Don't schedule if already scheduled
        if (wp_next_scheduled($hookName, [$courseId])) {
            return;
        }

        // Schedule for immediate execution (next cron run)
        wp_schedule_single_event(time(), $hookName, [$courseId]);
    }

    /**
     * Get total duration of all lessons in a course (in hours, rounded)
     *
     * @param int $courseId The course ID
     * @return float Total duration in hours (rounded to 1 decimal)
     */
    public function getTotalDurationHours(int $courseId): float
    {
        $minutes = $this->getTotalDuration($courseId);
        return round($minutes / 60, 1);
    }

    /**
     * Get all lessons in a course (across all sections)
     *
     * @param int $courseId The course ID
     * @return array Array of lesson posts
     */
    public function getAllLessonsInCourse(int $courseId): array
    {
        global $wpdb;
        $tableName = $wpdb->prefix . 'mpcs_sections';

        // Get all section IDs for this course
        $sectionIds = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$tableName} WHERE course_id = %d",
                $courseId
            )
        );

        if (empty($sectionIds)) {
            return [];
        }

        // Get all lessons in all sections
        return get_posts([
            'post_type' => self::LESSON_POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_mpcs_lesson_section_id',
                    'value' => $sectionIds,
                    'compare' => 'IN',
                ],
            ],
            'orderby' => 'meta_value_num',
            'meta_key' => '_mpcs_lesson_lesson_order',
            'order' => 'ASC',
        ]);
    }

    /**
     * Get duration of a single lesson (in seconds)
     *
     * Priority:
     * 1. Previously calculated and saved duration (fastest)
     * 2. Meta field '_smg_lesson_duration' (user-defined)
     * 3. ACF field 'lesson_duration' or 'video_duration'
     * 4. Extracted from embedded video (YouTube/Vimeo) via oEmbed (slowest)
     *
     * @param WP_Post $lesson The lesson post
     * @return int Duration in seconds
     */
    private function getLessonDuration(WP_Post $lesson): int
    {
        // 1. Check for previously calculated and saved duration (fastest!)
        $savedDuration = get_post_meta($lesson->ID, '_smg_video_duration_seconds', true);
        if ($savedDuration !== '' && is_numeric($savedDuration)) {
            return (int) $savedDuration;
        }

        // 2. Check for explicit user-defined duration meta field (in seconds)
        $duration = get_post_meta($lesson->ID, '_smg_lesson_duration', true);
        if (!empty($duration) && is_numeric($duration)) {
            return (int) $duration;
        }

        // 3. Check ACF fields if available
        if (function_exists('get_field')) {
            // Try common field names for duration
            $acfFields = ['lesson_duration', 'video_duration', 'duration', 'durata', 'video_length'];
            foreach ($acfFields as $fieldName) {
                $acfDuration = get_field($fieldName, $lesson->ID);
                if (!empty($acfDuration)) {
                    return $this->parseDurationToSeconds($acfDuration);
                }
            }
        }

        // 4. Extract from embedded video in content (slow - makes HTTP request)
        $videoDuration = $this->extractVideoDurationFromContent($lesson);
        if ($videoDuration > 0) {
            return $videoDuration;
        }

        return 0;
    }

    /**
     * Extract video duration from lesson content (YouTube/Vimeo)
     *
     * Priority:
     * 1. YouTube Data API v3 (if API key configured) - most accurate
     * 2. Vimeo oEmbed (includes duration)
     * 3. WordPress oEmbed fallback (doesn't work for YouTube duration)
     *
     * @param WP_Post $lesson The lesson post
     * @return int Duration in seconds (0 if not found)
     */
    private function extractVideoDurationFromContent(WP_Post $lesson): int
    {
        $content = $lesson->post_content;

        // Try YouTube first
        $youtubeUrl = $this->extractYouTubeUrl($content);
        if ($youtubeUrl) {
            // Try YouTube Data API first (accurate)
            $duration = $this->getYouTubeDuration($youtubeUrl);
            if ($duration > 0) {
                return $duration;
            }
        }

        // Try Vimeo (oEmbed includes duration)
        $vimeoUrl = $this->extractVimeoUrl($content);
        if ($vimeoUrl) {
            $duration = $this->getOEmbedDuration($vimeoUrl);
            if ($duration > 0) {
                return $duration;
            }
        }

        return 0;
    }

    /**
     * Get YouTube video duration using YouTube Data API v3
     *
     * Falls back to 0 if API key not configured.
     *
     * @param string $url YouTube URL
     * @return int Duration in seconds (0 if not available)
     */
    private function getYouTubeDuration(string $url): int
    {
        // Get YouTube integration instance
        $youtubeIntegration = $this->getYouTubeIntegration();

        if (!$youtubeIntegration || !$youtubeIntegration->isAvailable()) {
            return 0;
        }

        return $youtubeIntegration->getVideoDuration($url);
    }

    /**
     * Get YouTube Integration instance (lazy loaded)
     *
     * @return YouTubeIntegration|null
     */
    private function getYouTubeIntegration(): ?YouTubeIntegration
    {
        static $instance = null;

        if ($instance === null) {
            $instance = new YouTubeIntegration();
        }

        return $instance;
    }

    /**
     * Extract YouTube URL from content
     *
     * Searches for YouTube videos in various formats:
     * - Standard URLs (youtube.com/watch?v=...)
     * - Short URLs (youtu.be/...)
     * - Embed URLs (youtube.com/embed/...)
     * - iframes
     * - WordPress/Gutenberg embed blocks
     * - Plain text URLs
     *
     * @param string $content Post content
     * @return string|null YouTube URL or null
     */
    private function extractYouTubeUrl(string $content): ?string
    {
        // First, try to find any YouTube URL in the content (most flexible)
        // This catches URLs in Gutenberg blocks, plain text, or any format
        
        // Pattern 1: youtu.be short URLs (very common in Gutenberg)
        if (preg_match('/https?:\/\/youtu\.be\/([a-zA-Z0-9_-]{11})/', $content, $matches)) {
            return 'https://www.youtube.com/watch?v=' . $matches[1];
        }

        // Pattern 2: youtube.com/watch URLs
        if (preg_match('/https?:\/\/(?:www\.)?youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/', $content, $matches)) {
            return 'https://www.youtube.com/watch?v=' . $matches[1];
        }

        // Pattern 3: youtube.com/embed URLs (in iframes)
        if (preg_match('/https?:\/\/(?:www\.)?youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/', $content, $matches)) {
            return 'https://www.youtube.com/watch?v=' . $matches[1];
        }

        // Pattern 4: youtube-nocookie.com URLs
        if (preg_match('/https?:\/\/(?:www\.)?youtube-nocookie\.com\/embed\/([a-zA-Z0-9_-]{11})/', $content, $matches)) {
            return 'https://www.youtube.com/watch?v=' . $matches[1];
        }

        // Pattern 5: Look in Gutenberg block JSON (catches various attribute orders)
        if (preg_match('/<!-- wp:(?:embed|core-embed\/youtube)[^>]*"url"\s*:\s*"(https?:\/\/[^"]*(?:youtube\.com|youtu\.be)[^"]*)"/', $content, $matches)) {
            $url = $matches[1];
            // Extract video ID from the URL
            if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $url, $idMatch)) {
                return 'https://www.youtube.com/watch?v=' . $idMatch[1];
            }
            return $url;
        }

        return null;
    }

    /**
     * Extract Vimeo URL from content
     *
     * @param string $content Post content
     * @return string|null Vimeo URL or null
     */
    private function extractVimeoUrl(string $content): ?string
    {
        $patterns = [
            // Standard Vimeo URLs
            '/(?:https?:\/\/)?(?:www\.)?vimeo\.com\/(\d+)/',
            // Vimeo player embed
            '/(?:https?:\/\/)?player\.vimeo\.com\/video\/(\d+)/',
            // Vimeo in iframe
            '/<iframe[^>]+src=["\'](?:https?:\/\/)?player\.vimeo\.com\/video\/(\d+)[^"\']*["\'][^>]*>/',
            // WordPress Vimeo embed block
            '/<!-- wp:embed {"url":"(https?:\/\/(?:www\.)?vimeo\.com\/\d+[^"]*)"[^}]*"type":"video"/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                // Return full URL if captured, otherwise build it
                if (str_starts_with($matches[1], 'http')) {
                    return $matches[1];
                }
                return 'https://vimeo.com/' . $matches[1];
            }
        }

        return null;
    }

    /**
     * Get video duration via oEmbed
     *
     * @param string $url Video URL
     * @return int Duration in seconds (0 if not found)
     */
    private function getOEmbedDuration(string $url): int
    {
        // Use WordPress oEmbed to get data
        $oEmbed = _wp_oembed_get_object();
        $provider = $oEmbed->get_provider($url);

        if (!$provider) {
            return 0;
        }

        $data = $oEmbed->fetch($provider, $url);

        if (!$data || !isset($data->duration)) {
            return 0;
        }

        return (int) $data->duration;
    }

    /**
     * Parse various duration formats to seconds
     *
     * Supports:
     * - Numeric (assumes minutes if < 1000, seconds otherwise)
     * - HH:MM:SS format
     * - MM:SS format
     * - ISO 8601 (PT1H30M)
     *
     * @param mixed $duration Duration value
     * @return int Duration in seconds
     */
    private function parseDurationToSeconds(mixed $duration): int
    {
        if (is_numeric($duration)) {
            $num = (int) $duration;
            // Assume minutes if small number, seconds if large
            return $num < 1000 ? $num * 60 : $num;
        }

        $duration = (string) $duration;

        // ISO 8601 format (PT1H30M45S)
        if (preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/i', $duration, $matches)) {
            $hours = !empty($matches[1]) ? (int) $matches[1] : 0;
            $minutes = !empty($matches[2]) ? (int) $matches[2] : 0;
            $seconds = !empty($matches[3]) ? (int) $matches[3] : 0;
            return ($hours * 3600) + ($minutes * 60) + $seconds;
        }

        // HH:MM:SS format
        if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $duration, $matches)) {
            return ((int) $matches[1] * 3600) + ((int) $matches[2] * 60) + (int) $matches[3];
        }

        // MM:SS format
        if (preg_match('/^(\d{1,3}):(\d{2})$/', $duration, $matches)) {
            return ((int) $matches[1] * 60) + (int) $matches[2];
        }

        return 0;
    }

    /**
     * Invalidate calculated duration for a course
     *
     * Removes saved duration, forcing recalculation on next request
     *
     * @param int $courseId The course ID
     */
    public function invalidateDuration(int $courseId): void
    {
        delete_post_meta($courseId, '_smg_total_duration_minutes');
        delete_post_meta($courseId, '_smg_total_duration_seconds');
        delete_post_meta($courseId, '_smg_duration_last_calculated');
    }

    /**
     * Recalculate duration for all courses
     *
     * Useful for initial setup or after bulk lesson imports
     *
     * @return array Results with course ID => duration
     */
    public function recalculateAllCourseDurations(): array
    {
        $results = [];
        $courses = $this->getAllCourses();

        foreach ($courses as $course) {
            $duration = $this->calculateAndSaveDuration($course->ID);
            $results[$course->ID] = [
                'title' => get_the_title($course),
                'duration_minutes' => $duration,
                'duration_formatted' => $this->formatDurationHuman($duration),
            ];
        }

        return $results;
    }

    /**
     * Format duration for human display
     *
     * @param int $minutes Duration in minutes
     * @return string Formatted duration (e.g., "2h 30m")
     */
    private function formatDurationHuman(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0m';
        }

        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        if ($hours > 0 && $mins > 0) {
            return "{$hours}h {$mins}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$mins}m";
        }
    }

    /**
     * Handle AJAX request to calculate all course durations
     *
     * Called from the MemberPress Courses integration modal.
     * Calculates duration for each course and returns results.
     */
    public function handleAjaxCalculateDurations(): void
    {
        // Security check
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied.', 'schema-markup-generator')], 403);
        }

        // Verify nonce
        if (!check_ajax_referer('smg_admin_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => __('Invalid security token.', 'schema-markup-generator')], 403);
        }

        // Check availability
        if (!$this->isAvailable()) {
            wp_send_json_error(['message' => __('MemberPress Courses is not available.', 'schema-markup-generator')], 400);
        }

        // Get all courses
        $courses = $this->getAllCourses();

        if (empty($courses)) {
            wp_send_json_success([
                'message' => __('No courses found.', 'schema-markup-generator'),
                'courses' => [],
                'total_duration' => '0m',
            ]);
        }

        $results = [];
        $totalMinutes = 0;
        $coursesWithVideo = 0;
        $debugInfo = [];

        foreach ($courses as $course) {
            $lessonCount = $this->getLessonCount($course->ID);
            
            // Get detailed info for debugging
            $courseDebug = $this->calculateAndSaveDurationWithDebug($course->ID);
            $duration = $courseDebug['duration'];

            $results[] = [
                'id' => $course->ID,
                'title' => html_entity_decode(get_the_title($course), ENT_QUOTES, 'UTF-8'),
                'lessons' => $lessonCount,
                'lessons_with_video' => $courseDebug['lessons_with_video'],
                'duration_minutes' => $duration,
                'duration_formatted' => $this->formatDurationHuman($duration),
                'has_video' => $duration > 0,
            ];

            $totalMinutes += $duration;
            if ($duration > 0) {
                $coursesWithVideo++;
            }

            // Add debug info for first few courses
            if (count($debugInfo) < 3) {
                $debugInfo[] = $courseDebug['debug'];
            }
        }

        wp_send_json_success([
            'message' => sprintf(
                __('Calculated duration for %d courses (%d with video content).', 'schema-markup-generator'),
                count($courses),
                $coursesWithVideo
            ),
            'courses' => $results,
            'total_courses' => count($courses),
            'courses_with_video' => $coursesWithVideo,
            'total_duration' => $this->formatDurationHuman($totalMinutes),
            'total_minutes' => $totalMinutes,
            'debug' => $debugInfo,
        ]);
    }

    /**
     * Calculate duration with debug information
     *
     * @param int $courseId The course ID
     * @return array Duration and debug info
     */
    private function calculateAndSaveDurationWithDebug(int $courseId): array
    {
        $totalSeconds = 0;
        $allLessons = $this->getAllLessonsInCourse($courseId);
        $lessonsWithVideo = 0;
        $debugLessons = [];

        foreach ($allLessons as $lesson) {
            $duration = $this->getLessonDuration($lesson);
            $totalSeconds += $duration;

            // Debug: check what we found in this lesson
            $youtubeUrl = $this->extractYouTubeUrl($lesson->post_content);
            $vimeoUrl = $this->extractVimeoUrl($lesson->post_content);
            $contentLength = strlen($lesson->post_content);
            $hasYoutubeInContent = stripos($lesson->post_content, 'youtube') !== false || stripos($lesson->post_content, 'youtu.be') !== false;
            $hasVimeoInContent = stripos($lesson->post_content, 'vimeo') !== false;

            if ($duration > 0) {
                $lessonsWithVideo++;
                update_post_meta($lesson->ID, '_smg_video_duration_seconds', $duration);
            }

            // Only add debug for first 5 lessons
            if (count($debugLessons) < 5) {
                $debugLessons[] = [
                    'id' => $lesson->ID,
                    'title' => get_the_title($lesson),
                    'content_length' => $contentLength,
                    'has_youtube_text' => $hasYoutubeInContent,
                    'has_vimeo_text' => $hasVimeoInContent,
                    'youtube_url_found' => $youtubeUrl,
                    'vimeo_url_found' => $vimeoUrl,
                    'duration_seconds' => $duration,
                    'content_preview' => substr($lesson->post_content, 0, 500),
                ];
            }
        }

        $totalMinutes = (int) ceil($totalSeconds / 60);

        // Save to post meta
        update_post_meta($courseId, '_smg_total_duration_minutes', $totalMinutes);
        update_post_meta($courseId, '_smg_total_duration_seconds', $totalSeconds);
        update_post_meta($courseId, '_smg_duration_last_calculated', time());

        return [
            'duration' => $totalMinutes,
            'lessons_with_video' => $lessonsWithVideo,
            'debug' => [
                'course_id' => $courseId,
                'course_title' => get_the_title($courseId),
                'total_lessons' => count($allLessons),
                'lessons_with_video' => $lessonsWithVideo,
                'lessons' => $debugLessons,
            ],
        ];
    }

    /**
     * Recalculate course duration when a lesson is saved
     *
     * This is the correct place to do expensive oEmbed calls - in the admin,
     * not during frontend page render.
     *
     * @param int $postId The lesson post ID
     */
    public function onLessonSave(int $postId): void
    {
        // Don't run during autosave or AJAX (except our specific actions)
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $post = get_post($postId);
        if (!$post || $post->post_type !== self::LESSON_POST_TYPE) {
            return;
        }

        // Only published lessons affect duration
        if ($post->post_status !== 'publish') {
            return;
        }

        $course = $this->getCourseByLessonId($postId);
        if ($course) {
            // Calculate and save duration (this is where oEmbed calls happen)
            // It's OK to be slow here - we're in the admin saving a post
            $this->calculateAndSaveDuration($course->ID);
        }
    }
}

