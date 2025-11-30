<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Schema\Types;

use flavor\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * LearningResource Schema
 *
 * For educational resources like lessons, tutorials, quizzes.
 * Optimized for LLM understanding and SEO.
 *
 * @package flavor\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <info@metodo.dev>
 */
class LearningResourceSchema extends AbstractSchema
{
    public function getType(): string
    {
        return 'LearningResource';
    }

    public function getLabel(): string
    {
        return __('Learning Resource', 'schema-markup-generator');
    }

    public function getDescription(): string
    {
        return __('For educational resources like lessons, tutorials, and quizzes. Ideal for course content with LLM and AI search optimization.', 'schema-markup-generator');
    }

    public function build(WP_Post $post, array $mapping = []): array
    {
        $data = $this->buildBase($post);

        // Core properties
        $data['name'] = html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8');
        $data['description'] = $this->getPostDescription($post);
        $data['url'] = $this->getPostUrl($post);

        // Image
        $image = $this->getFeaturedImage($post);
        if ($image) {
            $data['image'] = $image;
        }

        // Learning Resource Type (Lesson, Video, Quiz, Tutorial, etc.)
        $resourceType = $this->getMappedValue($post, $mapping, 'learningResourceType');
        if ($resourceType) {
            $data['learningResourceType'] = $resourceType;
        } else {
            $data['learningResourceType'] = 'Lesson';
        }

        // isPartOf - Link to parent Course (crucial for hierarchy)
        $parentCourse = $this->buildParentCourse($post, $mapping);
        if ($parentCourse) {
            $data['isPartOf'] = $parentCourse;
        }

        // Teaches - What this resource teaches (very important for LLMs)
        $teaches = $this->getMappedValue($post, $mapping, 'teaches');
        if ($teaches) {
            $data['teaches'] = is_array($teaches) ? $teaches : [$teaches];
        }

        // Assesses - Skills/competencies assessed
        $assesses = $this->getMappedValue($post, $mapping, 'assesses');
        if ($assesses) {
            $data['assesses'] = is_array($assesses) ? $assesses : [$assesses];
        }

        // Educational Level
        $educationalLevel = $this->getMappedValue($post, $mapping, 'educationalLevel');
        if ($educationalLevel) {
            $data['educationalLevel'] = $educationalLevel;
        }

        // Educational Use
        $educationalUse = $this->getMappedValue($post, $mapping, 'educationalUse');
        if ($educationalUse) {
            $data['educationalUse'] = $educationalUse;
        }

        // Time Required (ISO 8601 duration)
        $timeRequired = $this->getMappedValue($post, $mapping, 'timeRequired');
        if ($timeRequired) {
            $data['timeRequired'] = $this->formatDuration($timeRequired);
        }

        // Interactivity Type
        $interactivityType = $this->getMappedValue($post, $mapping, 'interactivityType');
        if ($interactivityType) {
            $data['interactivityType'] = $interactivityType;
        }

        // Language
        $inLanguage = $this->getMappedValue($post, $mapping, 'inLanguage');
        if ($inLanguage) {
            $data['inLanguage'] = $inLanguage;
        } else {
            $data['inLanguage'] = get_bloginfo('language');
        }

        // Author/Creator
        $author = $this->getMappedValue($post, $mapping, 'author');
        if ($author) {
            $data['author'] = [
                '@type' => 'Person',
                'name' => is_array($author) ? ($author['name'] ?? '') : $author,
            ];
        } else {
            $data['author'] = $this->getAuthor($post);
        }

        // Provider (usually the organization/school)
        $provider = $this->getMappedValue($post, $mapping, 'provider');
        if ($provider) {
            $data['provider'] = [
                '@type' => 'Organization',
                'name' => is_array($provider) ? ($provider['name'] ?? '') : $provider,
                'sameAs' => is_array($provider) ? ($provider['url'] ?? home_url('/')) : home_url('/'),
            ];
        } else {
            $data['provider'] = $this->getPublisher();
        }

        // Dates
        $data['dateCreated'] = $this->formatDate($post->post_date);
        $data['dateModified'] = $this->formatDate($post->post_modified);

        // Content URL (if it's a downloadable resource)
        $contentUrl = $this->getMappedValue($post, $mapping, 'contentUrl');
        if ($contentUrl) {
            $data['contentUrl'] = $contentUrl;
        }

        // Encoding Format (MIME type)
        $encodingFormat = $this->getMappedValue($post, $mapping, 'encodingFormat');
        if ($encodingFormat) {
            $data['encodingFormat'] = $encodingFormat;
        }

        // Video (if lesson contains video)
        $video = $this->buildVideo($post, $mapping);
        if ($video) {
            $data['video'] = $video;
        }

        // Keywords (useful for LLMs)
        $keywords = $this->getMappedValue($post, $mapping, 'keywords');
        if ($keywords) {
            $data['keywords'] = is_array($keywords) ? implode(', ', $keywords) : $keywords;
        } else {
            // Try to get from post tags
            $tags = get_the_tags($post->ID);
            if ($tags && !is_wp_error($tags)) {
                $data['keywords'] = implode(', ', wp_list_pluck($tags, 'name'));
            }
        }

        // About - Main topics (helps LLMs understand context)
        $about = $this->getMappedValue($post, $mapping, 'about');
        if ($about) {
            $data['about'] = $this->buildAboutTopics($about);
        }

        // Audience
        $audience = $this->getMappedValue($post, $mapping, 'audience');
        if ($audience) {
            $data['audience'] = [
                '@type' => 'EducationalAudience',
                'educationalRole' => is_array($audience) ? ($audience['role'] ?? $audience) : $audience,
            ];
        }

        // Position in course (lesson number)
        $position = $this->getMappedValue($post, $mapping, 'position');
        if ($position) {
            $data['position'] = (int) $position;
        }

        // Learning Outcome
        $learningOutcome = $this->getMappedValue($post, $mapping, 'competencyRequired');
        if ($learningOutcome) {
            $data['competencyRequired'] = $learningOutcome;
        }

        /**
         * Filter learning resource schema data
         */
        $data = apply_filters('smg_learning_resource_schema_data', $data, $post, $mapping);

        return $this->cleanData($data);
    }

    /**
     * Build parent course reference
     */
    private function buildParentCourse(WP_Post $post, array $mapping): ?array
    {
        // Check for mapped course
        $course = $this->getMappedValue($post, $mapping, 'isPartOf');

        if ($course) {
            if (is_array($course)) {
                return [
                    '@type' => 'Course',
                    'name' => $course['name'] ?? '',
                    'url' => $course['url'] ?? '',
                    'description' => $course['description'] ?? '',
                ];
            }

            // If it's a post ID
            if (is_numeric($course)) {
                $coursePost = get_post((int) $course);
                if ($coursePost) {
                    return [
                        '@type' => 'Course',
                        'name' => html_entity_decode(get_the_title($coursePost), ENT_QUOTES, 'UTF-8'),
                        'url' => get_permalink($coursePost),
                        'description' => $this->getPostDescription($coursePost),
                    ];
                }
            }

            // If it's just a name
            return [
                '@type' => 'Course',
                'name' => $course,
            ];
        }

        // Try to get parent post if hierarchical
        if ($post->post_parent) {
            $parent = get_post($post->post_parent);
            if ($parent) {
                return [
                    '@type' => 'Course',
                    'name' => html_entity_decode(get_the_title($parent), ENT_QUOTES, 'UTF-8'),
                    'url' => get_permalink($parent),
                ];
            }
        }

        /**
         * Filter to get parent course from integrations (e.g., MemberPress Courses, LearnDash)
         *
         * @param array|null $parentCourse Current parent course data
         * @param WP_Post    $post         The lesson post
         * @param array      $mapping      Field mapping configuration
         */
        return apply_filters('smg_learning_resource_parent_course', null, $post, $mapping);
    }

    /**
     * Build video data if present
     */
    private function buildVideo(WP_Post $post, array $mapping): ?array
    {
        $videoUrl = $this->getMappedValue($post, $mapping, 'videoUrl');

        if (!$videoUrl) {
            return null;
        }

        $video = [
            '@type' => 'VideoObject',
            'contentUrl' => $videoUrl,
            'name' => $this->getMappedValue($post, $mapping, 'videoName')
                ?: html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8'),
        ];

        $videoDuration = $this->getMappedValue($post, $mapping, 'videoDuration');
        if ($videoDuration) {
            $video['duration'] = $this->formatDuration($videoDuration);
        }

        $videoThumbnail = $this->getMappedValue($post, $mapping, 'videoThumbnail');
        if ($videoThumbnail) {
            $video['thumbnailUrl'] = $videoThumbnail;
        } else {
            $image = $this->getFeaturedImage($post);
            if ($image) {
                $video['thumbnailUrl'] = $image['url'];
            }
        }

        $uploadDate = $this->getMappedValue($post, $mapping, 'videoUploadDate');
        if ($uploadDate) {
            $video['uploadDate'] = $uploadDate;
        } else {
            $video['uploadDate'] = $this->formatDate($post->post_date);
        }

        return $video;
    }

    /**
     * Build about topics
     */
    private function buildAboutTopics(mixed $about): array
    {
        if (!is_array($about)) {
            return [
                '@type' => 'Thing',
                'name' => $about,
            ];
        }

        $topics = [];
        foreach ($about as $topic) {
            if (is_array($topic)) {
                $topics[] = [
                    '@type' => $topic['type'] ?? 'Thing',
                    'name' => $topic['name'] ?? '',
                ];
            } else {
                $topics[] = [
                    '@type' => 'Thing',
                    'name' => $topic,
                ];
            }
        }

        return count($topics) === 1 ? $topics[0] : $topics;
    }

    /**
     * Format duration to ISO 8601
     */
    private function formatDuration(mixed $duration): string
    {
        if (is_string($duration) && str_starts_with($duration, 'P')) {
            return $duration;
        }

        // Assume minutes if numeric
        if (is_numeric($duration)) {
            $minutes = (int) $duration;
            if ($minutes >= 60) {
                $hours = floor($minutes / 60);
                $mins = $minutes % 60;
                return $mins > 0 ? "PT{$hours}H{$mins}M" : "PT{$hours}H";
            }
            return "PT{$minutes}M";
        }

        return "PT{$duration}";
    }

    public function getRequiredProperties(): array
    {
        return ['name', 'description', 'learningResourceType'];
    }

    public function getRecommendedProperties(): array
    {
        return ['isPartOf', 'teaches', 'timeRequired', 'educationalLevel', 'author', 'image'];
    }

    public function getPropertyDefinitions(): array
    {
        return [
            'name' => [
                'type' => 'text',
                'description' => __('Lesson/resource title. Displayed in educational content rich results.', 'schema-markup-generator'),
                'auto' => 'post_title',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('What this lesson covers. Helps search engines and AI understand the content.', 'schema-markup-generator'),
                'auto' => 'post_excerpt',
            ],
            'learningResourceType' => [
                'type' => 'select',
                'description' => __('Content format. Helps categorize and match with user search intent.', 'schema-markup-generator'),
                'options' => ['Lesson', 'Video', 'Quiz', 'Tutorial', 'Exercise', 'Lecture', 'Reading', 'Assignment'],
            ],
            'isPartOf' => [
                'type' => 'post',
                'description' => __('Parent course link. Critical for establishing content hierarchy for LLMs.', 'schema-markup-generator'),
            ],
            'teaches' => [
                'type' => 'textarea',
                'description' => __('CRITICAL for AI: Skills/concepts taught (comma-separated). LLMs use this to match with user queries.', 'schema-markup-generator'),
            ],
            'assesses' => [
                'type' => 'textarea',
                'description' => __('Competencies evaluated (for quizzes/tests). Helps match assessment-seeking users.', 'schema-markup-generator'),
            ],
            'timeRequired' => [
                'type' => 'number',
                'description' => __('Completion time in minutes. Helps users plan their learning sessions.', 'schema-markup-generator'),
            ],
            'educationalLevel' => [
                'type' => 'select',
                'description' => __('Difficulty level. Helps match content to appropriate learners.', 'schema-markup-generator'),
                'options' => ['Beginner', 'Intermediate', 'Advanced', 'Expert'],
            ],
            'educationalUse' => [
                'type' => 'select',
                'description' => __('How content is meant to be used. Improves educational content categorization.', 'schema-markup-generator'),
                'options' => ['instruction', 'assessment', 'professional development', 'self-study'],
            ],
            'interactivityType' => [
                'type' => 'select',
                'description' => __('Engagement style. Helps match user preferences for learning formats.', 'schema-markup-generator'),
                'options' => ['active', 'expositive', 'mixed'],
            ],
            'author' => [
                'type' => 'text',
                'description' => __('Content creator. Builds E-E-A-T signals for educational content.', 'schema-markup-generator'),
            ],
            'provider' => [
                'type' => 'text',
                'description' => __('Organization offering the content. Adds institutional credibility.', 'schema-markup-generator'),
            ],
            'position' => [
                'type' => 'number',
                'description' => __('Order within course. Helps LLMs understand learning sequence.', 'schema-markup-generator'),
            ],
            'keywords' => [
                'type' => 'text',
                'description' => __('Topic keywords (comma-separated). Used by search engines and AI for indexing.', 'schema-markup-generator'),
            ],
            'videoUrl' => [
                'type' => 'url',
                'description' => __('Video content URL. Enables video rich results and better content matching.', 'schema-markup-generator'),
            ],
            'videoDuration' => [
                'type' => 'number',
                'description' => __('Video length in minutes. Shown in video rich results.', 'schema-markup-generator'),
            ],
        ];
    }
}

