<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Integration;

use WP_Post;

/**
 * MemberPress Courses Integration
 *
 * Integration with MemberPress Courses for lesson/course hierarchy.
 * Provides parent course detection for LearningResource schema.
 *
 * @package flavor\SchemaMarkupGenerator\Integration
 * @author  Michele Marri <info@metodo.dev>
 */
class MemberPressCoursesIntegration
{
    /**
     * Post types handled by MemberPress Courses
     */
    public const LESSON_POST_TYPE = 'mpcs-lesson';
    public const COURSE_POST_TYPE = 'mpcs-course';

    /**
     * Initialize integration
     */
    public function init(): void
    {
        // Register filters always - availability is checked when filters are called
        // This avoids timing issues with post type registration
        add_filter('smg_learning_resource_parent_course', [$this, 'getParentCourse'], 10, 3);
        add_filter('smg_course_schema_data', [$this, 'enhanceCourseSchema'], 10, 3);
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

        // Add course curriculum (sections and lessons)
        $curriculum = $this->getCourseCurriculum($post->ID);

        if (!empty($curriculum)) {
            $data['hasCourseInstance'] = $curriculum;
        }

        // Add lesson count
        $lessonCount = $this->getLessonCount($post->ID);
        if ($lessonCount > 0) {
            $data['numberOfLessons'] = $lessonCount;
        }

        return $data;
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
}

