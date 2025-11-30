<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Schema\Types;

use flavor\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * Course Schema
 *
 * For online courses and educational content.
 *
 * @package flavor\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <info@metodo.dev>
 */
class CourseSchema extends AbstractSchema
{
    public function getType(): string
    {
        return 'Course';
    }

    public function getLabel(): string
    {
        return __('Course', 'schema-markup-generator');
    }

    public function getDescription(): string
    {
        return __('For online courses and educational content. Enables course rich results with provider and pricing info.', 'schema-markup-generator');
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
            $data['image'] = $image['url'];
        }

        // Provider
        $provider = $this->getMappedValue($post, $mapping, 'provider');
        if ($provider) {
            $data['provider'] = [
                '@type' => 'Organization',
                'name' => is_array($provider) ? ($provider['name'] ?? '') : $provider,
                'sameAs' => is_array($provider) ? ($provider['url'] ?? home_url('/')) : home_url('/'),
            ];
        } else {
            $data['provider'] = [
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'sameAs' => home_url('/'),
            ];
        }

        // Instructor
        $instructor = $this->getMappedValue($post, $mapping, 'instructor');
        if ($instructor) {
            $data['instructor'] = [
                '@type' => 'Person',
                'name' => is_array($instructor) ? ($instructor['name'] ?? '') : $instructor,
            ];
        } else {
            $data['instructor'] = $this->getAuthor($post);
        }

        // Duration
        $duration = $this->getMappedValue($post, $mapping, 'duration');
        if ($duration) {
            $data['timeRequired'] = $this->formatDuration($duration);
        }

        // Educational level
        $educationalLevel = $this->getMappedValue($post, $mapping, 'educationalLevel');
        if ($educationalLevel) {
            $data['educationalLevel'] = $educationalLevel;
        }

        // Course mode (online, onsite, blended)
        $courseMode = $this->getMappedValue($post, $mapping, 'courseMode');
        if ($courseMode) {
            $data['courseMode'] = $courseMode;
        }

        // Language
        $inLanguage = $this->getMappedValue($post, $mapping, 'inLanguage');
        if ($inLanguage) {
            $data['inLanguage'] = $inLanguage;
        } else {
            $data['inLanguage'] = get_bloginfo('language');
        }

        // Offers (pricing)
        $offers = $this->buildOffers($post, $mapping);
        if (!empty($offers)) {
            $data['offers'] = $offers;
        }

        // Course instances (specific occurrences)
        $instances = $this->getMappedValue($post, $mapping, 'hasCourseInstance');
        if (is_array($instances) && !empty($instances)) {
            $data['hasCourseInstance'] = $this->buildCourseInstances($instances);
        }

        // Aggregate rating
        $rating = $this->getMappedValue($post, $mapping, 'ratingValue');
        if ($rating) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (float) $rating,
                'ratingCount' => (int) ($this->getMappedValue($post, $mapping, 'ratingCount') ?: 1),
            ];
        }

        // Number of credits
        $credits = $this->getMappedValue($post, $mapping, 'numberOfCredits');
        if ($credits) {
            $data['numberOfCredits'] = (int) $credits;
        }

        // Prerequisites
        $prerequisites = $this->getMappedValue($post, $mapping, 'coursePrerequisites');
        if ($prerequisites) {
            $data['coursePrerequisites'] = is_array($prerequisites)
                ? implode(', ', $prerequisites)
                : $prerequisites;
        }

        // ========================================
        // LLM-Optimized Properties
        // ========================================

        // Teaches - Critical for LLM understanding (what students will learn)
        $teaches = $this->getMappedValue($post, $mapping, 'teaches');
        if ($teaches) {
            $data['teaches'] = is_array($teaches)
                ? $teaches
                : array_map('trim', explode(',', $teaches));
        }

        // About - Semantic topics for better LLM matching
        $about = $this->getMappedValue($post, $mapping, 'about');
        if ($about) {
            $data['about'] = $this->buildAboutTopics($about);
        }

        // Keywords - Important for search and LLM indexing
        $keywords = $this->getMappedValue($post, $mapping, 'keywords');
        if ($keywords) {
            $data['keywords'] = is_array($keywords) ? implode(', ', $keywords) : $keywords;
        } else {
            // Fallback to post tags
            $tags = get_the_tags($post->ID);
            if ($tags && !is_wp_error($tags)) {
                $data['keywords'] = implode(', ', wp_list_pluck($tags, 'name'));
            }
        }

        // Syllabus - Course program description
        $syllabus = $this->getMappedValue($post, $mapping, 'syllabus');
        if ($syllabus) {
            $data['syllabus'] = $syllabus;
        }

        // Target Audience
        $audience = $this->getMappedValue($post, $mapping, 'audience');
        if ($audience) {
            $data['audience'] = [
                '@type' => 'EducationalAudience',
                'educationalRole' => is_array($audience) ? ($audience['role'] ?? $audience) : $audience,
            ];
        }

        // Educational Credential Awarded (certificate/diploma)
        $credential = $this->getMappedValue($post, $mapping, 'educationalCredentialAwarded');
        if ($credential) {
            $data['educationalCredentialAwarded'] = [
                '@type' => 'EducationalOccupationalCredential',
                'name' => is_array($credential) ? ($credential['name'] ?? $credential) : $credential,
            ];
        }

        // Total Historical Enrollment (social proof for LLM)
        $enrollment = $this->getMappedValue($post, $mapping, 'totalHistoricalEnrollment');
        if ($enrollment) {
            $data['totalHistoricalEnrollment'] = (int) $enrollment;
        }

        // Competency Required (structured prerequisites)
        $competency = $this->getMappedValue($post, $mapping, 'competencyRequired');
        if ($competency) {
            $data['competencyRequired'] = is_array($competency)
                ? $competency
                : array_map('trim', explode(',', $competency));
        }

        // Date metadata - Important for content freshness
        $data['dateCreated'] = $this->formatDate($post->post_date);
        $data['dateModified'] = $this->formatDate($post->post_modified);

        /**
         * Filter course schema data
         */
        $data = apply_filters('smg_course_schema_data', $data, $post, $mapping);

        return $this->cleanData($data);
    }

    /**
     * Build about topics for semantic matching
     */
    private function buildAboutTopics(mixed $about): array
    {
        if (!is_array($about)) {
            $topics = array_map('trim', explode(',', $about));
            if (count($topics) === 1) {
                return [
                    '@type' => 'Thing',
                    'name' => $topics[0],
                ];
            }
            return array_map(fn($topic) => ['@type' => 'Thing', 'name' => $topic], $topics);
        }

        $result = [];
        foreach ($about as $topic) {
            if (is_array($topic)) {
                $result[] = [
                    '@type' => $topic['type'] ?? 'Thing',
                    'name' => $topic['name'] ?? '',
                ];
            } else {
                $result[] = [
                    '@type' => 'Thing',
                    'name' => $topic,
                ];
            }
        }

        return count($result) === 1 ? $result[0] : $result;
    }

    /**
     * Format duration to ISO 8601
     */
    private function formatDuration(mixed $duration): string
    {
        if (is_string($duration) && str_starts_with($duration, 'P')) {
            return $duration;
        }

        // Assume hours if numeric
        if (is_numeric($duration)) {
            $hours = (int) $duration;
            return "PT{$hours}H";
        }

        return "PT{$duration}";
    }

    /**
     * Build offers data
     */
    private function buildOffers(WP_Post $post, array $mapping): array
    {
        $price = $this->getMappedValue($post, $mapping, 'price');

        if ($price === null && $price !== 0 && $price !== '0') {
            return [];
        }

        $offers = [
            '@type' => 'Offer',
            'price' => (float) $price,
            'priceCurrency' => $this->getMappedValue($post, $mapping, 'priceCurrency') ?: 'EUR',
            'url' => $this->getPostUrl($post),
            'category' => 'Paid',
        ];

        if ((float) $price === 0.0) {
            $offers['category'] = 'Free';
        }

        $availability = $this->getMappedValue($post, $mapping, 'availability');
        if ($availability) {
            $offers['availability'] = 'https://schema.org/' . $availability;
        }

        return $offers;
    }

    /**
     * Build course instances
     */
    private function buildCourseInstances(array $instances): array
    {
        $result = [];

        foreach ($instances as $instance) {
            $courseInstance = [
                '@type' => 'CourseInstance',
            ];

            if (!empty($instance['startDate'])) {
                $courseInstance['startDate'] = $instance['startDate'];
            }
            if (!empty($instance['endDate'])) {
                $courseInstance['endDate'] = $instance['endDate'];
            }
            if (!empty($instance['courseMode'])) {
                $courseInstance['courseMode'] = $instance['courseMode'];
            }
            if (!empty($instance['location'])) {
                $courseInstance['location'] = [
                    '@type' => 'Place',
                    'name' => $instance['location'],
                ];
            }

            $result[] = $courseInstance;
        }

        return $result;
    }

    public function getRequiredProperties(): array
    {
        return ['name', 'description', 'provider'];
    }

    public function getRecommendedProperties(): array
    {
        return [
            'instructor',
            'offers',
            'image',
            'aggregateRating',
            'educationalLevel',
            'teaches',
            'about',
            'keywords',
            'syllabus',
        ];
    }

    public function getPropertyDefinitions(): array
    {
        return [
            // ========================================
            // Core Properties (Required by Google)
            // ========================================
            'name' => [
                'type' => 'text',
                'description' => __('Course title. Shown in Google rich results and AI answers.', 'schema-markup-generator'),
                'auto' => 'post_title',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('Course summary. Used by search engines and LLMs to understand and present your course.', 'schema-markup-generator'),
                'auto' => 'post_excerpt',
            ],
            'provider' => [
                'type' => 'text',
                'description' => __('Organization/school offering the course. Builds brand recognition in search results.', 'schema-markup-generator'),
            ],

            // ========================================
            // High-Impact Properties (SEO & LLM)
            // ========================================
            'teaches' => [
                'type' => 'textarea',
                'description' => __('CRITICAL for AI: List what students will learn (comma-separated). LLMs use this to match courses with user queries.', 'schema-markup-generator'),
            ],
            'about' => [
                'type' => 'text',
                'description' => __('Main topics covered (comma-separated). Helps AI understand the course subject area for semantic matching.', 'schema-markup-generator'),
            ],
            'keywords' => [
                'type' => 'text',
                'description' => __('Keywords for search visibility (comma-separated). Used by search engines and AI for indexing.', 'schema-markup-generator'),
            ],
            'syllabus' => [
                'type' => 'textarea',
                'description' => __('Course program/curriculum description. Helps LLMs understand the learning path and structure.', 'schema-markup-generator'),
            ],

            // ========================================
            // Instructor & Credibility
            // ========================================
            'instructor' => [
                'type' => 'text',
                'description' => __('Instructor/teacher name. Adds credibility and may appear in rich results.', 'schema-markup-generator'),
            ],
            'ratingValue' => [
                'type' => 'number',
                'description' => __('Average rating (1-5). Shows star rating in search results - major CTR boost.', 'schema-markup-generator'),
            ],
            'ratingCount' => [
                'type' => 'number',
                'description' => __('Number of reviews. Shown alongside rating for social proof.', 'schema-markup-generator'),
            ],
            'totalHistoricalEnrollment' => [
                'type' => 'number',
                'description' => __('Total students enrolled. Social proof signal used by AI to assess popularity.', 'schema-markup-generator'),
            ],

            // ========================================
            // Pricing & Availability
            // ========================================
            'price' => [
                'type' => 'number',
                'description' => __('Course price. Enables price display in search results. Use 0 for free courses.', 'schema-markup-generator'),
            ],
            'priceCurrency' => [
                'type' => 'text',
                'description' => __('Currency code (EUR, USD, etc.). Required if price is set.', 'schema-markup-generator'),
            ],
            'availability' => [
                'type' => 'select',
                'description' => __('Course availability status. Affects how the course appears in search.', 'schema-markup-generator'),
                'options' => ['InStock', 'SoldOut', 'PreOrder', 'Discontinued'],
            ],

            // ========================================
            // Course Details
            // ========================================
            'duration' => [
                'type' => 'number',
                'description' => __('Total course duration in hours. Helps users understand time commitment.', 'schema-markup-generator'),
            ],
            'educationalLevel' => [
                'type' => 'select',
                'description' => __('Difficulty level. Helps match courses to appropriate learners.', 'schema-markup-generator'),
                'options' => ['Beginner', 'Intermediate', 'Advanced', 'Expert'],
            ],
            'courseMode' => [
                'type' => 'select',
                'description' => __('Delivery format. Important for users searching specific course types.', 'schema-markup-generator'),
                'options' => ['online', 'onsite', 'blended'],
            ],
            'inLanguage' => [
                'type' => 'text',
                'description' => __('Course language (e.g., "it", "en"). Auto-detected from WordPress if not set.', 'schema-markup-generator'),
            ],
            'numberOfCredits' => [
                'type' => 'number',
                'description' => __('Academic credits awarded. Relevant for formal education courses.', 'schema-markup-generator'),
            ],

            // ========================================
            // Prerequisites & Requirements
            // ========================================
            'coursePrerequisites' => [
                'type' => 'textarea',
                'description' => __('Required knowledge before starting (text description). Helps users self-assess readiness.', 'schema-markup-generator'),
            ],
            'competencyRequired' => [
                'type' => 'text',
                'description' => __('Specific skills needed (comma-separated). More structured than prerequisites for AI matching.', 'schema-markup-generator'),
            ],

            // ========================================
            // Target Audience & Outcomes
            // ========================================
            'audience' => [
                'type' => 'text',
                'description' => __('Target audience (e.g., "developer", "student", "professional"). Helps AI recommend to right users.', 'schema-markup-generator'),
            ],
            'educationalCredentialAwarded' => [
                'type' => 'text',
                'description' => __('Certificate/credential name awarded upon completion. Important for career-focused searches.', 'schema-markup-generator'),
            ],
        ];
    }
}

