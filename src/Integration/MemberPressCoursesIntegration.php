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
    private const COURSE_VIRTUAL_FIELDS = [
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
     * Virtual/computed fields for lessons
     */
    private const LESSON_VIRTUAL_FIELDS = [
        'mpcs_lesson_position' => [
            'label' => 'Lesson Position (auto)',
            'type' => 'number',
            'description' => 'Global position of the lesson within the entire course curriculum (counting all sections). Maps to position.',
        ],
        'mpcs_parent_course_name' => [
            'label' => 'Parent Course Name (auto)',
            'type' => 'text',
            'description' => 'Name of the parent course. Auto-detected from lesson hierarchy.',
        ],
        'mpcs_parent_course_url' => [
            'label' => 'Parent Course URL (auto)',
            'type' => 'url',
            'description' => 'URL of the parent course. Auto-detected from lesson hierarchy.',
        ],
        'mpcs_learning_resource_type' => [
            'label' => 'Learning Resource Type (auto)',
            'type' => 'text',
            'description' => 'Auto-detected content type: Video (embedded videos), Quiz (forms/assessments), Tutorial (step-by-step), Reading (text-heavy), Lecture (video+text), Lesson (default). Maps to learningResourceType.',
        ],
        'mpcs_interactivity_type' => [
            'label' => 'Interactivity Type (auto)',
            'type' => 'text',
            'description' => 'Auto-detected interactivity: active (quizzes/forms), expositive (video/reading), mixed (both). Maps to interactivityType.',
        ],
        'mpcs_lesson_transcription' => [
            'label' => 'Video Transcription (auto)',
            'type' => 'textarea',
            'description' => 'Video transcript from lesson_transcription meta field. Auto-extracted and cleaned for VideoObject schema.',
        ],
        'mpcs_video_chapters' => [
            'label' => 'Video Chapters (auto)',
            'type' => 'repeater',
            'description' => 'Video chapters/segments from video_chapters or lesson_video_chapters meta field. Creates Clip elements for hasPart in VideoObject schema.',
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
        add_filter('smg_learning_resource_position', [$this, 'getLessonPosition'], 10, 3);
        add_filter('smg_course_schema_data', [$this, 'enhanceCourseSchema'], 10, 3);

        // Add course fields to discovery
        add_filter('smg_discovered_fields', [$this, 'addCourseFields'], 10, 2);

        // Resolve course field values
        add_filter('smg_resolve_field_value', [$this, 'resolveFieldValue'], 10, 4);

        // Provide video chapters for lessons
        add_filter('smg_video_chapters', [$this, 'provideVideoChapters'], 10, 3);

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
     * Get lesson position for a MemberPress Courses lesson
     *
     * @param int|null $position Current position (may be null)
     * @param WP_Post  $post     The lesson post
     * @param array    $mapping  Field mapping configuration
     * @return int|null Position (1-based) or null
     */
    public function getLessonPosition(?int $position, WP_Post $post, array $mapping): ?int
    {
        // If already resolved by mapping, return that
        if ($position !== null) {
            return $position;
        }

        // Check availability at runtime
        if (!$this->isAvailable()) {
            return null;
        }

        // Only handle MemberPress Courses lessons
        if ($post->post_type !== self::LESSON_POST_TYPE) {
            return null;
        }

        return $this->calculateGlobalLessonPosition($post);
    }

    /**
     * Calculate the global position of a lesson within the entire course curriculum
     *
     * This counts all lessons in previous sections plus the lesson's position
     * in its current section to give a course-wide position number.
     *
     * @param WP_Post $lesson The lesson post
     * @return int|null Global position (1-based) or null
     */
    private function calculateGlobalLessonPosition(WP_Post $lesson): ?int
    {
        global $wpdb;
        $tableName = $wpdb->prefix . 'mpcs_sections';

        // Get the lesson's section ID and order within section
        $sectionId = get_post_meta($lesson->ID, '_mpcs_lesson_section_id', true);
        $lessonOrder = get_post_meta($lesson->ID, '_mpcs_lesson_lesson_order', true);

        if (!$sectionId || $lessonOrder === '' || $lessonOrder === false) {
            return null;
        }

        $sectionId = (int) $sectionId;
        $lessonOrder = (int) $lessonOrder;

        // Get section info including course_id and section_order
        $section = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT course_id, section_order FROM {$tableName} WHERE id = %d",
                $sectionId
            )
        );

        if (!$section) {
            return null;
        }

        $courseId = (int) $section->course_id;
        $currentSectionOrder = (int) $section->section_order;

        // Get all section IDs that come before this section (lower section_order)
        $previousSectionIds = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM {$tableName} WHERE course_id = %d AND section_order < %d",
                $courseId,
                $currentSectionOrder
            )
        );

        // Count lessons in all previous sections
        $lessonsInPreviousSections = 0;

        if (!empty($previousSectionIds)) {
            $previousLessons = get_posts([
                'post_type' => self::LESSON_POST_TYPE,
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'meta_query' => [
                    [
                        'key' => '_mpcs_lesson_section_id',
                        'value' => $previousSectionIds,
                        'compare' => 'IN',
                    ],
                ],
                'fields' => 'ids',
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
            ]);

            $lessonsInPreviousSections = count($previousLessons);
        }

        // Global position = lessons in previous sections + position in current section (1-based)
        return $lessonsInPreviousSections + $lessonOrder + 1;
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
     * Returns a complete Course object with all required properties for Google validation:
     * - @type, @id, name, url (identification)
     * - description (required by Google)
     * - provider (required by Google)
     * - hasCourseInstance with offers (required by Google)
     *
     * @param WP_Post $course The course post
     * @return array Course schema data
     */
    private function buildCourseData(WP_Post $course): array
    {
        $courseUrl = get_permalink($course);
        $siteName = get_bloginfo('name');
        $siteUrl = home_url('/');
        $currency = $this->getCourseCurrency();

        return [
            '@type' => 'Course',
            '@id' => $courseUrl . '#course',
            'name' => html_entity_decode(get_the_title($course), ENT_QUOTES, 'UTF-8'),
            'url' => $courseUrl,
            'description' => $this->getCourseDescription($course),
            'provider' => [
                '@type' => 'Organization',
                'name' => $siteName,
                'sameAs' => $siteUrl,
            ],
            'hasCourseInstance' => [
                '@type' => 'CourseInstance',
                'courseMode' => 'online',
                'courseSchedule' => [
                    '@type' => 'Schedule',
                    'repeatFrequency' => 'P1D',
                    'repeatCount' => 365,
                ],
                'offers' => [
                    '@type' => 'Offer',
                    'price' => 0,
                    'priceCurrency' => $currency,
                    'availability' => 'https://schema.org/InStock',
                ],
            ],
        ];
    }

    /**
     * Get currency for course offers
     * 
     * Tries MemberPress currency first, then WooCommerce, then defaults to EUR.
     */
    private function getCourseCurrency(): string
    {
        // Try MemberPress currency
        if (class_exists('MeprOptions')) {
            $options = \MeprOptions::fetch();
            if (isset($options->currency_code) && !empty($options->currency_code)) {
                return $options->currency_code;
            }
        }

        // Try WooCommerce currency
        if (function_exists('get_woocommerce_currency')) {
            return get_woocommerce_currency();
        }

        // Default to EUR
        return 'EUR';
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
     * Add course/lesson fields to discovered fields
     *
     * @param array  $fields   Current discovered fields
     * @param string $postType The post type being queried
     * @return array Modified fields array
     */
    public function addCourseFields(array $fields, string $postType): array
    {
        // Check availability
        if (!$this->isAvailable()) {
            return $fields;
        }

        // Add virtual/computed fields for courses
        if ($postType === self::COURSE_POST_TYPE) {
            foreach (self::COURSE_VIRTUAL_FIELDS as $key => $config) {
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
        }

        // Add virtual/computed fields for lessons
        if ($postType === self::LESSON_POST_TYPE) {
            foreach (self::LESSON_VIRTUAL_FIELDS as $key => $config) {
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
        }

        return $fields;
    }

    /**
     * Resolve course/lesson field values
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

        $post = get_post($postId);
        if (!$post) {
            return $value;
        }

        // Check availability
        if (!$this->isAvailable()) {
            return $value;
        }

        // Handle course fields
        if ($post->post_type === self::COURSE_POST_TYPE) {
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

        // Handle lesson fields
        if ($post->post_type === self::LESSON_POST_TYPE) {
            return match ($fieldKey) {
                'mpcs_lesson_position' => $this->getLessonPositionValue($post),
                'mpcs_parent_course_name' => $this->getParentCourseName($post),
                'mpcs_parent_course_url' => $this->getParentCourseUrl($post),
                'mpcs_learning_resource_type' => $this->detectLearningResourceType($post),
                'mpcs_interactivity_type' => $this->detectInteractivityType($post),
                'mpcs_lesson_transcription' => $this->getLessonTranscription($post),
                'mpcs_video_chapters' => $this->getVideoChapters($post),
                default => $value,
            };
        }

        return $value;
    }

    /**
     * Get lesson position within the entire course curriculum
     *
     * @param WP_Post $lesson The lesson post
     * @return int|null Global position (1-based) or null
     */
    private function getLessonPositionValue(WP_Post $lesson): ?int
    {
        return $this->calculateGlobalLessonPosition($lesson);
    }

    /**
     * Get parent course name for a lesson
     *
     * @param WP_Post $lesson The lesson post
     * @return string|null Course name or null
     */
    private function getParentCourseName(WP_Post $lesson): ?string
    {
        $course = $this->getCourseByLessonId($lesson->ID);
        
        if (!$course) {
            return null;
        }

        return html_entity_decode(get_the_title($course), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Get parent course URL for a lesson
     *
     * @param WP_Post $lesson The lesson post
     * @return string|null Course URL or null
     */
    private function getParentCourseUrl(WP_Post $lesson): ?string
    {
        $course = $this->getCourseByLessonId($lesson->ID);
        
        if (!$course) {
            return null;
        }

        return get_permalink($course);
    }

    /**
     * Get lesson transcription from meta field
     *
     * Retrieves and cleans the video transcript from the 'lesson_transcription' meta field.
     * Removes HTML, timestamps, and speaker labels for clean schema output.
     *
     * @param WP_Post $lesson The lesson post
     * @return string|null Cleaned transcript or null
     */
    private function getLessonTranscription(WP_Post $lesson): ?string
    {
        $maxLength = 5000;

        // Get transcript from meta field
        $transcript = get_post_meta($lesson->ID, 'lesson_transcription', true);

        if (empty($transcript)) {
            // Try ACF field as fallback
            if (function_exists('get_field')) {
                $transcript = get_field('lesson_transcription', $lesson->ID);
            }
        }

        if (empty($transcript) || !is_string($transcript)) {
            return null;
        }

        // Clean the transcript
        $cleaned = $this->cleanTranscriptText($transcript);

        if (empty($cleaned) || strlen($cleaned) < 50) {
            return null;
        }

        // Truncate if too long
        if (strlen($cleaned) > $maxLength) {
            $truncated = substr($cleaned, 0, $maxLength);
            $lastSpace = strrpos($truncated, ' ');
            if ($lastSpace !== false) {
                $truncated = substr($truncated, 0, $lastSpace);
            }
            return $truncated . '...';
        }

        return $cleaned;
    }

    /**
     * Provide video chapters for MemberPress Courses lessons
     *
     * Filter callback for smg_video_chapters.
     *
     * @param array|null $chapters  Current chapters (may be null)
     * @param WP_Post    $post      The post object
     * @param array      $embedData Embed data with platform info
     * @return array|null Chapters array or null
     */
    public function provideVideoChapters(?array $chapters, WP_Post $post, array $embedData): ?array
    {
        // If already resolved, return
        if (!empty($chapters)) {
            return $chapters;
        }

        // Check availability at runtime
        if (!$this->isAvailable()) {
            return null;
        }

        // Only handle MemberPress Courses lessons
        if ($post->post_type !== self::LESSON_POST_TYPE) {
            return null;
        }

        return $this->getVideoChapters($post);
    }

    /**
     * Get video chapters from meta field
     *
     * Retrieves video chapters from various meta fields:
     * 1. 'video_chapters' (standard meta field)
     * 2. 'lesson_video_chapters' (MemberPress specific)
     * 3. ACF fields 'video_chapters' or 'lesson_video_chapters'
     *
     * Supports formats:
     * - Array of objects: [{name: "Intro", startOffset: 0}, ...]
     * - Array of strings: ["0:00 Intro", "1:30 Main Topic", ...]
     * - JSON string of the above
     * - Plain text with timestamp format (one per line)
     *
     * @param WP_Post $lesson The lesson post
     * @return array|null Array of chapter data or null
     */
    private function getVideoChapters(WP_Post $lesson): ?array
    {
        $chapters = null;

        // Meta field names to check
        $metaFields = ['video_chapters', 'lesson_video_chapters', '_video_chapters', '_lesson_video_chapters'];

        // 1. Try standard meta fields
        foreach ($metaFields as $field) {
            $value = get_post_meta($lesson->ID, $field, true);
            if (!empty($value)) {
                $chapters = $this->parseChaptersValue($value, $lesson);
                if (!empty($chapters)) {
                    break;
                }
            }
        }

        // 2. Try ACF fields if available and no chapters found yet
        if (empty($chapters) && function_exists('get_field')) {
            $acfFields = ['video_chapters', 'lesson_video_chapters', 'chapters'];
            foreach ($acfFields as $field) {
                $value = get_field($field, $lesson->ID);
                if (!empty($value)) {
                    $chapters = $this->parseChaptersValue($value, $lesson);
                    if (!empty($chapters)) {
                        break;
                    }
                }
            }
        }

        // 3. Try to extract from post content if no meta field found
        if (empty($chapters)) {
            $chapters = $this->extractChaptersFromContent($lesson->post_content, $lesson);
        }

        return !empty($chapters) ? $chapters : null;
    }

    /**
     * Parse chapters value from various formats
     *
     * @param mixed   $value  Raw chapters value
     * @param WP_Post $lesson The lesson post (for URL generation)
     * @return array Parsed chapters array
     */
    private function parseChaptersValue(mixed $value, WP_Post $lesson): array
    {
        $chapters = [];
        $lessonUrl = get_permalink($lesson);

        // JSON string
        if (is_string($value) && (str_starts_with(trim($value), '[') || str_starts_with(trim($value), '{'))) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        // Plain text with timestamps (one per line or comma-separated)
        if (is_string($value)) {
            $lines = preg_split('/[\n,]/', $value);
            $position = 1;

            foreach ($lines as $line) {
                $parsed = $this->parseChapterLine(trim($line), $lessonUrl, $position);
                if ($parsed) {
                    $chapters[] = $parsed;
                    $position++;
                }
            }

            return $chapters;
        }

        // Array of chapters
        if (is_array($value)) {
            $position = 1;

            foreach ($value as $chapter) {
                $parsed = $this->parseChapterItem($chapter, $lessonUrl, $position);
                if ($parsed) {
                    $chapters[] = $parsed;
                    $position++;
                }
            }
        }

        return $chapters;
    }

    /**
     * Parse a single chapter line in timestamp format
     *
     * Supports formats:
     * - "0:00 Introduction"
     * - "1:30 - Getting Started"
     * - "00:05:30 Advanced Topics"
     * - "80 Crypto becoming investable" (seconds only)
     *
     * @param string $line      Raw chapter line
     * @param string $lessonUrl Base lesson URL
     * @param int    $position  Chapter position
     * @return array|null Parsed chapter or null
     */
    private function parseChapterLine(string $line, string $lessonUrl, int $position): ?array
    {
        if (empty($line)) {
            return null;
        }

        // Pattern: HH:MM:SS or MM:SS followed by optional separator and title
        if (preg_match('/^(?:(\d{1,2}):)?(\d{1,2}):(\d{2})\s*[-–—:]?\s*(.+)$/u', $line, $match)) {
            $hours = !empty($match[1]) ? (int) $match[1] : 0;
            $minutes = (int) $match[2];
            $seconds = (int) $match[3];
            $title = trim($match[4]);

            if (strlen($title) < 3) {
                return null;
            }

            $startOffset = ($hours * 3600) + ($minutes * 60) + $seconds;

            return [
                '@type' => 'Clip',
                'name' => $title,
                'startOffset' => $startOffset,
                'position' => $position,
                'url' => $lessonUrl . '#t=' . $startOffset,
            ];
        }

        // Pattern: seconds only followed by title (e.g., "80 Crypto becoming investable")
        if (preg_match('/^(\d+)\s+(.+)$/u', $line, $match)) {
            $startOffset = (int) $match[1];
            $title = trim($match[2]);

            if (strlen($title) < 3 || $startOffset > 86400) { // Max 24 hours
                return null;
            }

            return [
                '@type' => 'Clip',
                'name' => $title,
                'startOffset' => $startOffset,
                'position' => $position,
                'url' => $lessonUrl . '#t=' . $startOffset,
            ];
        }

        return null;
    }

    /**
     * Parse a single chapter item from array/object format
     *
     * Supports formats:
     * - {name: "Intro", startOffset: 0}
     * - {title: "Intro", time: "0:00"}
     * - {name: "Intro", time: 0, url: "..."}
     *
     * @param mixed  $chapter   Chapter data
     * @param string $lessonUrl Base lesson URL
     * @param int    $position  Chapter position
     * @return array|null Parsed chapter or null
     */
    private function parseChapterItem(mixed $chapter, string $lessonUrl, int $position): ?array
    {
        // String format (treat as line)
        if (is_string($chapter)) {
            return $this->parseChapterLine($chapter, $lessonUrl, $position);
        }

        if (!is_array($chapter)) {
            return null;
        }

        // Get name
        $name = $chapter['name'] ?? $chapter['title'] ?? $chapter['label'] ?? null;
        if (empty($name) || strlen($name) < 3) {
            return null;
        }

        // Get start offset
        $startOffset = null;

        if (isset($chapter['startOffset'])) {
            $startOffset = $this->parseTimeToSeconds($chapter['startOffset']);
        } elseif (isset($chapter['time'])) {
            $startOffset = $this->parseTimeToSeconds($chapter['time']);
        } elseif (isset($chapter['start'])) {
            $startOffset = $this->parseTimeToSeconds($chapter['start']);
        } elseif (isset($chapter['seconds'])) {
            $startOffset = (int) $chapter['seconds'];
        }

        if ($startOffset === null) {
            return null;
        }

        $result = [
            '@type' => 'Clip',
            'name' => $name,
            'startOffset' => $startOffset,
            'position' => $position,
        ];

        // URL: use provided or generate from lesson URL
        if (!empty($chapter['url'])) {
            $result['url'] = $chapter['url'];
        } else {
            $result['url'] = $lessonUrl . '#t=' . $startOffset;
        }

        // Optional end offset
        if (isset($chapter['endOffset'])) {
            $result['endOffset'] = $this->parseTimeToSeconds($chapter['endOffset']);
        } elseif (isset($chapter['end'])) {
            $result['endOffset'] = $this->parseTimeToSeconds($chapter['end']);
        }

        return $result;
    }

    /**
     * Extract video chapters from post content
     *
     * Looks for timestamp patterns in content like:
     * - 0:00 Introduction
     * - 1:30 - Getting Started
     * - 00:05:30 Advanced Topics
     * - <strong>00:00</strong> – Title (with HTML tags around timestamp)
     * - <p class="video-chapters">...</p> (dedicated chapters element)
     * - Video Chapters section with list of timestamps
     *
     * @param string  $content Post content
     * @param WP_Post $lesson  The lesson post
     * @return array Array of chapter data
     */
    private function extractChaptersFromContent(string $content, WP_Post $lesson): array
    {
        $chapters = [];
        $lessonUrl = get_permalink($lesson);

        // 1. First, try to find elements with "video-chapters" class
        // Matches: <p class="video-chapters">...</p>, <div class="video-chapters">...</div>
        $classPattern = '/<(?:p|div|ul|ol)[^>]*class=["\'][^"\']*video-chapters[^"\']*["\'][^>]*>(.*?)<\/(?:p|div|ul|ol)>/is';

        if (preg_match($classPattern, $content, $classMatch)) {
            $chaptersContent = $classMatch[1];
            $extractedFromClass = $this->extractTimestampsFromHtml($chaptersContent, $lessonUrl);
            if (!empty($extractedFromClass)) {
                return $extractedFromClass;
            }
        }

        // 2. Try to find a dedicated "chapters" or "timestamps" heading section
        // Look for headings like "Video Chapters", "Chapters", "Timestamps", "Indice"
        $sectionPattern = '/<h[2-6][^>]*>.*?(?:Video\s+)?(?:Chapters?|Timestamps?|Indice|Capitoli|Key\s+Moments?).*?<\/h[2-6]>(.*?)(?=<h[2-6]|$)/is';

        if (preg_match($sectionPattern, $content, $sectionMatch)) {
            $sectionContent = $sectionMatch[1];
            $extractedFromSection = $this->extractTimestampsFromHtml($sectionContent, $lessonUrl);
            if (!empty($extractedFromSection)) {
                return $extractedFromSection;
            }
        }

        // 3. Look for timestamps anywhere in content (generic pattern)
        $chapters = $this->extractTimestampsFromHtml($content, $lessonUrl);

        return $chapters;
    }

    /**
     * Extract timestamps from HTML content
     *
     * Supports various formats:
     * - Plain text: 0:00 Introduction
     * - With separator: 1:30 - Getting Started
     * - With HTML tags: <strong>00:00</strong> – Title
     * - In lists: <li>0:00 Introduction</li>
     * - With line breaks: 0:00 Intro<br>1:30 Next
     *
     * @param string $html      HTML content
     * @param string $lessonUrl Base lesson URL
     * @return array Array of chapters
     */
    private function extractTimestampsFromHtml(string $html, string $lessonUrl): array
    {
        $chapters = [];

        // Pattern 1: Timestamps wrapped in HTML tags (e.g., <strong>00:00</strong> – Title)
        // This catches: <strong>00:00</strong> – Why crypto and why now<br>
        $htmlPattern = '/<(?:strong|b|span)[^>]*>\s*(?:(\d{1,2}):)?(\d{1,2}):(\d{2})\s*<\/(?:strong|b|span)>\s*[-–—:]*\s*([^<\n]+)/i';

        if (preg_match_all($htmlPattern, $html, $matches, PREG_SET_ORDER)) {
            if (count($matches) >= 2) {
                $position = 1;

                foreach ($matches as $match) {
                    $hours = !empty($match[1]) ? (int) $match[1] : 0;
                    $minutes = (int) $match[2];
                    $seconds = (int) $match[3];
                    $title = trim(strip_tags($match[4]));

                    if (strlen($title) < 3 || preg_match('/^\d+:\d+/', $title)) {
                        continue;
                    }

                    $startOffset = ($hours * 3600) + ($minutes * 60) + $seconds;

                    $chapters[] = [
                        '@type' => 'Clip',
                        'name' => $title,
                        'startOffset' => $startOffset,
                        'position' => $position++,
                        'url' => $lessonUrl . '#t=' . $startOffset,
                    ];
                }

                if (!empty($chapters)) {
                    return $chapters;
                }
            }
        }

        // Pattern 2: Plain text timestamps (possibly with <br> or newlines as separators)
        // Matches: 0:00 Introduction, 1:30 - Getting Started, etc.
        $plainPattern = '/(?:^|\n|<br\s*\/?>\s*|<li[^>]*>)\s*(?:(\d{1,2}):)?(\d{1,2}):(\d{2})\s*[-–—:]?\s*([^\n<]+)/mi';

        if (preg_match_all($plainPattern, $html, $matches, PREG_SET_ORDER)) {
            if (count($matches) >= 2) {
                $position = 1;

                foreach ($matches as $match) {
                    $hours = !empty($match[1]) ? (int) $match[1] : 0;
                    $minutes = (int) $match[2];
                    $seconds = (int) $match[3];
                    $title = trim(strip_tags($match[4]));

                    if (strlen($title) < 3 || preg_match('/^\d+:\d+/', $title)) {
                        continue;
                    }

                    $startOffset = ($hours * 3600) + ($minutes * 60) + $seconds;

                    $chapters[] = [
                        '@type' => 'Clip',
                        'name' => $title,
                        'startOffset' => $startOffset,
                        'position' => $position++,
                        'url' => $lessonUrl . '#t=' . $startOffset,
                    ];
                }
            }
        }

        return $chapters;
    }

    /**
     * Parse time string or number to seconds
     *
     * @param mixed $time Time value (seconds, MM:SS, HH:MM:SS)
     * @return int Seconds
     */
    private function parseTimeToSeconds(mixed $time): int
    {
        if (is_numeric($time)) {
            return (int) $time;
        }

        if (is_string($time)) {
            // HH:MM:SS format
            if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2})$/', $time, $match)) {
                return ((int) $match[1] * 3600) + ((int) $match[2] * 60) + (int) $match[3];
            }

            // MM:SS format
            if (preg_match('/^(\d{1,3}):(\d{2})$/', $time, $match)) {
                return ((int) $match[1] * 60) + (int) $match[2];
            }
        }

        return 0;
    }

    /**
     * Clean transcript text by removing HTML, timestamps, and speaker labels
     *
     * @param string $text Raw transcript text
     * @return string Cleaned text
     */
    private function cleanTranscriptText(string $text): string
    {
        // Remove HTML tags
        $text = wp_strip_all_tags($text);

        // Remove timestamp patterns: [00:00:01.20] or [00:00:01]
        $text = preg_replace('/\[\d{2}:\d{2}:\d{2}(?:\.\d{2})?\]/', '', $text);

        // Remove speaker labels: "- Speaker 1", "Speaker 1:", etc.
        $text = preg_replace('/(?:^|\n)\s*-?\s*Speaker\s*\d+\s*:?\s*/i', ' ', $text);

        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Detect learning resource type from lesson content
     *
     * Priority:
     * 1. Quiz - If quiz/assessment elements detected
     * 2. Video - If video is the primary content
     * 3. Exercise - If has interactive elements with code blocks
     * 4. Tutorial - If has step-by-step structure
     * 5. Lecture - If video exists but with substantial text
     * 6. Reading - If primarily text
     * 7. Lesson - Default fallback
     *
     * @param WP_Post $lesson The lesson post
     * @return string Learning resource type
     */
    private function detectLearningResourceType(WP_Post $lesson): string
    {
        $content = $lesson->post_content;
        $contentAnalysis = $this->analyzeContent($content);
        $hasVideo = $this->hasVideoContent($content);

        // 1. Quiz takes priority
        if ($contentAnalysis['has_quiz']) {
            return 'Quiz';
        }

        // 2. Video dominant
        if ($hasVideo && $contentAnalysis['word_count'] < 300) {
            return 'Video';
        }

        // 3. Exercise - Interactive with code
        if ($contentAnalysis['has_interactive'] && $contentAnalysis['code_blocks'] >= 2) {
            return 'Exercise';
        }

        // 4. Tutorial - Step-by-step
        if ($contentAnalysis['has_tutorial_structure']) {
            return 'Tutorial';
        }

        // 5. Lecture - Video + substantial text
        if ($hasVideo && $contentAnalysis['word_count'] > 300) {
            return 'Lecture';
        }

        // 6. Reading - Text heavy
        if (!$hasVideo && $contentAnalysis['word_count'] > 500 && $contentAnalysis['headings'] >= 2) {
            return 'Reading';
        }

        return 'Lesson';
    }

    /**
     * Detect interactivity type from lesson content
     *
     * @param WP_Post $lesson The lesson post
     * @return string Interactivity type (active, expositive, mixed)
     */
    private function detectInteractivityType(WP_Post $lesson): string
    {
        $content = $lesson->post_content;
        $contentAnalysis = $this->analyzeContent($content);
        $hasVideo = $this->hasVideoContent($content);

        $hasActiveElements = $contentAnalysis['has_quiz']
            || $contentAnalysis['has_interactive']
            || $contentAnalysis['code_blocks'] >= 2;

        $hasExpositiveElements = $hasVideo || $contentAnalysis['word_count'] > 200;

        if ($hasActiveElements && $hasExpositiveElements) {
            return 'mixed';
        }

        if ($hasActiveElements) {
            return 'active';
        }

        return 'expositive';
    }

    /**
     * Analyze content characteristics
     *
     * @param string $content Post content
     * @return array Analysis results
     */
    private function analyzeContent(string $content): array
    {
        return [
            'has_quiz' => $this->detectQuizContent($content),
            'has_tutorial_structure' => $this->detectTutorialStructure($content),
            'has_interactive' => $this->detectInteractiveElements($content),
            'word_count' => str_word_count(wp_strip_all_tags($content)),
            'headings' => preg_match_all('/<h[2-4][^>]*>/i', $content),
            'code_blocks' => preg_match_all('/```|<pre[^>]*>|<code[^>]*>|<!-- wp:code/i', $content),
        ];
    }

    /**
     * Check if content has video
     *
     * @param string $content Post content
     * @return bool True if video detected
     */
    private function hasVideoContent(string $content): bool
    {
        return $this->extractYouTubeUrl($content) !== null
            || $this->extractVimeoUrl($content) !== null;
    }

    /**
     * Detect quiz/assessment content
     *
     * @param string $content Post content
     * @return bool True if quiz elements detected
     */
    private function detectQuizContent(string $content): bool
    {
        $patterns = [
            '/\[quiz[^\]]*\]/i',
            '/\[qmn_quiz[^\]]*\]/i',
            '/\[ld_quiz[^\]]*\]/i',
            '/\[qsm[^\]]*\]/i',
            '/\[gravityform[^\]]*\]/i',
            '/\[wpforms[^\]]*\]/i',
            '/<!-- wp:quiz/i',
            '/<!-- wp:learndash\/ld-quiz/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect tutorial structure
     *
     * @param string $content Post content
     * @return bool True if tutorial structure detected
     */
    private function detectTutorialStructure(string $content): bool
    {
        $score = 0;

        // Step-based headings
        if (preg_match_all('/step\s*[0-9]+|fase\s*[0-9]+|passo\s*[0-9]+/i', $content) >= 2) {
            $score += 2;
        }

        // "How to" style content
        if (preg_match('/how\s+to|come\s+fare|guida\s+a|tutorial/i', $content)) {
            $score += 1;
        }

        // Multiple ordered lists
        if (preg_match_all('/<ol[^>]*>/i', $content) >= 2) {
            $score += 1;
        }

        // Multiple code blocks
        if (preg_match_all('/```|<pre[^>]*>|<!-- wp:code/i', $content) >= 3) {
            $score += 1;
        }

        return $score >= 3;
    }

    /**
     * Detect interactive elements
     *
     * @param string $content Post content
     * @return bool True if interactive elements detected
     */
    private function detectInteractiveElements(string $content): bool
    {
        $patterns = [
            '/<form[^>]*>/i',
            '/<!-- wp:button/i',
            '/<!-- wp:file/i',
            '/<!-- wp:accordion/i',
            '/\[download[^\]]*\]/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
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
        // Uses hasPart with LearningResource (semantically correct for course modules/content)
        if ($includeCurriculum) {
            $curriculum = $this->getCourseCurriculum($post->ID);

            if (!empty($curriculum)) {
                $data['hasPart'] = $curriculum;
            }
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
     * Get course curriculum (sections with lessons) as LearningResource items
     *
     * Returns LearningResource objects for hasPart property (semantically correct).
     * Sections are marked as "module" type, lessons as individual LearningResource items.
     *
     * @param int $courseId The course ID
     * @return array Curriculum data as LearningResource array
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
        $position = 1;

        foreach ($sections as $section) {
            // Sections are LearningResource with type "module" or "unit"
            $sectionData = [
                '@type' => 'LearningResource',
                'name' => $section->title,
                'learningResourceType' => 'module',
                'position' => $position++,
            ];

            // Get lessons in this section
            $lessons = $this->getLessonsInSection((int) $section->id);

            if (!empty($lessons)) {
                $lessonPosition = 1;
                $sectionData['hasPart'] = array_map(function ($lesson) use (&$lessonPosition) {
                    return [
                        '@type' => 'LearningResource',
                        'name' => html_entity_decode(get_the_title($lesson), ENT_QUOTES, 'UTF-8'),
                        'url' => get_permalink($lesson),
                        'learningResourceType' => 'Lesson',
                        'position' => $lessonPosition++,
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

