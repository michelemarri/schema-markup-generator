<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Schema\Types;

use Metodo\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * Course Schema
 *
 * For online courses and educational content.
 *
 * @package Metodo\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <plugins@metodo.dev>
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
        $data = $this->buildBase($post, $mapping);

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

        // Language - default to site language
        $inLanguage = $this->getMappedValue($post, $mapping, 'inLanguage');
        $data['inLanguage'] = $inLanguage ?: get_bloginfo('language');

        // Build offers data (needed for both Course root and CourseInstance)
        $offersData = $this->buildOffers($post, $mapping);

        // offers at root level (required by Semrush and other SEO tools)
        $data['offers'] = $offersData['offer'];

        // isAccessibleForFree - Google recommended property for free courses (stays on Course level)
        if ($offersData['isFree']) {
            $data['isAccessibleForFree'] = true;
        }

        // ========================================
        // CourseInstance (instructor, courseMode also have offers per schema.org)
        // ========================================
        $courseInstance = $this->buildDefaultCourseInstance($post, $mapping, $offersData);

        // Course instances - merge default instance with any explicitly mapped instances
        $explicitInstances = $this->getMappedValue($post, $mapping, 'hasCourseInstance');
        if (is_array($explicitInstances) && !empty($explicitInstances)) {
            // User provided explicit instances - add instructor/courseMode from default to each
            $data['hasCourseInstance'] = $this->buildCourseInstances($explicitInstances, $courseInstance);
        } else {
            // Use the auto-generated default instance
            $data['hasCourseInstance'] = $courseInstance;
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
     * Default: Free course (price=0) with InStock availability
     *
     * @return array{offer: array, isFree: bool}
     */
    private function buildOffers(WP_Post $post, array $mapping): array
    {
        $price = $this->getMappedValue($post, $mapping, 'price');

        // Default to 0 (Free) if no price is mapped
        $priceValue = ($price !== null) ? (float) $price : 0.0;
        $isFree = $priceValue === 0.0;

        // Get currency - check mapped value, then try to get from integrations
        $currency = $this->getMappedValue($post, $mapping, 'priceCurrency');
        if (!$currency) {
            $currency = $this->getDefaultCurrency();
        }

        $offer = [
            '@type' => 'Offer',
            'price' => $priceValue,
            'priceCurrency' => $currency,
            'url' => $this->getPostUrl($post),
        ];

        // Category - required by Semrush and other SEO validators for nested Offers
        // Default to "Fees" which is a standard schema.org category for educational courses
        $offerCategory = $this->getMappedValue($post, $mapping, 'offerCategory');
        $offer['category'] = $offerCategory ?: 'Fees';

        // Availability - default to InStock (always available)
        $availability = $this->getMappedValue($post, $mapping, 'availability');
        $offer['availability'] = 'https://schema.org/' . ($availability ?: 'InStock');

        return [
            'offer' => $offer,
            'isFree' => $isFree,
        ];
    }

    /**
     * Get default currency from integrations or fallback to USD
     */
    private function getDefaultCurrency(): string
    {
        return $this->getSiteCurrency();
    }

    /**
     * Build default CourseInstance with instructor, courseMode, and offers
     * These properties belong to CourseInstance per schema.org specification
     *
     * @return array The default CourseInstance object
     */
    private function buildDefaultCourseInstance(WP_Post $post, array $mapping, array $offersData): array
    {
        $courseInstance = [
            '@type' => 'CourseInstance',
        ];

        // Course mode (online, onsite, blended) - default to online
        $courseMode = $this->getMappedValue($post, $mapping, 'courseMode');
        $courseInstance['courseMode'] = $courseMode ?: 'online';

        // Instructor
        $instructor = $this->getMappedValue($post, $mapping, 'instructor');
        if ($instructor) {
            $instructorData = [
                '@type' => 'Person',
                'name' => is_array($instructor) ? ($instructor['name'] ?? '') : $instructor,
            ];
            // Add instructor URL if available
            if (is_array($instructor) && !empty($instructor['url'])) {
                $instructorData['url'] = $instructor['url'];
            }
            $courseInstance['instructor'] = $instructorData;
        } else {
            $courseInstance['instructor'] = $this->getAuthor($post);
        }

        // Offers (pricing) - belongs to CourseInstance
        $courseInstance['offers'] = $offersData['offer'];

        // Course workload - required by Google for CourseInstance
        // Use mapped value, or generate from timeRequired, or use default
        $workload = $this->getMappedValue($post, $mapping, 'courseWorkload');
        if ($workload) {
            $courseInstance['courseWorkload'] = $workload;
        } else {
            // Try to generate from duration
            $duration = $this->getMappedValue($post, $mapping, 'duration');
            if ($duration && is_numeric($duration)) {
                $hours = (int) $duration;
                $courseInstance['courseWorkload'] = sprintf(
                    'Approximately %d %s of self-paced learning',
                    $hours,
                    $hours === 1 ? 'hour' : 'hours'
                );
            } else {
                // Default for online self-paced courses
                $courseInstance['courseWorkload'] = 'Self-paced online learning';
            }
        }

        return $courseInstance;
    }

    /**
     * Build course instances from explicit mapping
     * Merges default instance data (instructor, courseMode) into each explicit instance
     */
    private function buildCourseInstances(array $instances, array $defaultInstance): array
    {
        $result = [];

        foreach ($instances as $instance) {
            $courseInstance = [
                '@type' => 'CourseInstance',
            ];

            // Merge instructor from default if not explicitly provided
            if (!empty($instance['instructor'])) {
                $courseInstance['instructor'] = is_array($instance['instructor'])
                    ? $instance['instructor']
                    : ['@type' => 'Person', 'name' => $instance['instructor']];
            } elseif (!empty($defaultInstance['instructor'])) {
                $courseInstance['instructor'] = $defaultInstance['instructor'];
            }

            // Merge courseMode from default if not explicitly provided
            if (!empty($instance['courseMode'])) {
                $courseInstance['courseMode'] = $instance['courseMode'];
            } elseif (!empty($defaultInstance['courseMode'])) {
                $courseInstance['courseMode'] = $defaultInstance['courseMode'];
            }

            // Merge offers from default if not explicitly provided
            if (!empty($instance['offers'])) {
                $courseInstance['offers'] = $instance['offers'];
            } elseif (!empty($defaultInstance['offers'])) {
                $courseInstance['offers'] = $defaultInstance['offers'];
            }

            // Instance-specific properties
            if (!empty($instance['startDate'])) {
                $courseInstance['startDate'] = $instance['startDate'];
            }
            if (!empty($instance['endDate'])) {
                $courseInstance['endDate'] = $instance['endDate'];
            }
            if (!empty($instance['location'])) {
                $courseInstance['location'] = [
                    '@type' => 'Place',
                    'name' => $instance['location'],
                ];
            }
            if (!empty($instance['courseWorkload'])) {
                $courseInstance['courseWorkload'] = $instance['courseWorkload'];
            }
            if (!empty($instance['courseSchedule'])) {
                $courseInstance['courseSchedule'] = $instance['courseSchedule'];
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
            'instructor', // Goes to CourseInstance
            'courseMode', // Goes to CourseInstance
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
        return array_merge(self::getAdditionalTypeDefinition(), [
            // ========================================
            // Core Properties (Required by Google)
            // ========================================
            'name' => [
                'type' => 'text',
                'description' => __('Course title. Shown in Google rich results and AI answers.', 'schema-markup-generator'),
                'description_long' => __('The name of the course. This is the primary text displayed in course rich results and educational search features. Be specific and include key details like the technology or skill taught.', 'schema-markup-generator'),
                'example' => __('Complete Python Bootcamp, Advanced SEO Masterclass, Introduction to Machine Learning', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/name',
                'auto' => 'post_title',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('Course summary. Used by search engines and LLMs to understand and present your course.', 'schema-markup-generator'),
                'description_long' => __('A comprehensive description of what the course covers, who it is for, and what students will achieve. This is heavily used by AI and search engines to match courses with user queries.', 'schema-markup-generator'),
                'example' => __('Master Python programming from scratch. This comprehensive course covers fundamentals to advanced topics including web development, data analysis, and automation.', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/description',
                'auto' => 'post_excerpt',
            ],
            'provider' => [
                'type' => 'text',
                'description' => __('Organization/school offering the course. Builds brand recognition in search results.', 'schema-markup-generator'),
                'description_long' => __('The organization, school, or platform providing the course. This builds brand recognition and helps establish credibility in search results.', 'schema-markup-generator'),
                'example' => __('Udemy, Coursera, Harvard University, Your Academy Name', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/provider',
                'auto' => 'site_name',
                'auto_description' => __('Defaults to WordPress site name', 'schema-markup-generator'),
            ],

            // ========================================
            // High-Impact Properties (SEO & LLM)
            // ========================================
            'teaches' => [
                'type' => 'textarea',
                'description' => __('CRITICAL for AI: List what students will learn (comma-separated). LLMs use this to match courses with user queries.', 'schema-markup-generator'),
                'description_long' => __('The skills, concepts, or competencies that the course teaches. This is one of the most important properties for AI and LLM matching - be specific and comprehensive. List both broad topics and specific skills.', 'schema-markup-generator'),
                'example' => __('Python programming, Object-oriented programming, Web scraping, Data visualization, API integration, Django web framework', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/teaches',
            ],
            'about' => [
                'type' => 'text',
                'description' => __('Main topics covered (comma-separated). Helps AI understand the course subject area for semantic matching.', 'schema-markup-generator'),
                'description_long' => __('The main subject areas or topics that the course is about. This helps search engines and AI understand the semantic context of your course.', 'schema-markup-generator'),
                'example' => __('Software Development, Web Development, Data Science, Digital Marketing', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/about',
            ],
            'keywords' => [
                'type' => 'text',
                'description' => __('Keywords for search visibility (comma-separated). Used by search engines and AI for indexing.', 'schema-markup-generator'),
                'description_long' => __('Keywords and tags that describe the course content. Include both technical terms and common search phrases users might use to find this type of course.', 'schema-markup-generator'),
                'example' => __('python course, learn programming, coding bootcamp, web development, beginner friendly', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/keywords',
                'auto' => 'post_tags',
                'auto_description' => __('Falls back to post tags if not mapped', 'schema-markup-generator'),
            ],
            'syllabus' => [
                'type' => 'textarea',
                'description' => __('Course program/curriculum description. Helps LLMs understand the learning path and structure.', 'schema-markup-generator'),
                'description_long' => __('A detailed outline of the course curriculum. Include modules, units, or sections with their topics. This helps AI understand the depth and structure of your course.', 'schema-markup-generator'),
                'example' => __('Module 1: Python Basics (variables, data types, operators). Module 2: Control Flow (if statements, loops). Module 3: Functions and Modules...', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/syllabusSections',
            ],

            // ========================================
            // CourseInstance Properties (automatically placed in hasCourseInstance)
            // ========================================
            'instructor' => [
                'type' => 'text',
                'description' => __('Instructor/teacher name. Goes into CourseInstance per schema.org.', 'schema-markup-generator'),
                'description_long' => __('The instructor or teacher delivering the course. This property belongs to CourseInstance (not Course) per schema.org specification. Including instructor credentials helps establish E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness).', 'schema-markup-generator'),
                'example' => __('Dr. John Smith, Jane Doe (Senior Developer at Google), Prof. Maria Garcia', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/instructor',
                'auto' => 'author',
                'auto_description' => __('Defaults to post author. Placed in CourseInstance.', 'schema-markup-generator'),
            ],
            'ratingValue' => [
                'type' => 'number',
                'description' => __('Average rating (1-5). Shows star rating in search results - major CTR boost.', 'schema-markup-generator'),
                'description_long' => __('The average student rating for the course. Star ratings in search results significantly improve click-through rates and help users choose courses.', 'schema-markup-generator'),
                'example' => __('4.8, 4.5, 4.9', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/ratingValue',
            ],
            'ratingCount' => [
                'type' => 'number',
                'description' => __('Number of reviews. Shown alongside rating for social proof.', 'schema-markup-generator'),
                'description_long' => __('The total number of student ratings. Higher numbers provide stronger social proof and indicate course popularity.', 'schema-markup-generator'),
                'example' => __('2547, 891, 15420', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/ratingCount',
            ],
            'totalHistoricalEnrollment' => [
                'type' => 'number',
                'description' => __('Total students enrolled. Social proof signal used by AI to assess popularity.', 'schema-markup-generator'),
                'description_long' => __('The total number of students who have ever enrolled in the course. This is a powerful social proof signal that AI systems use to assess course quality and popularity.', 'schema-markup-generator'),
                'example' => __('15000, 52000, 125000', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/totalHistoricalEnrollment',
            ],

            // ========================================
            // Pricing & Availability (placed in both Course root and CourseInstance.offers)
            // ========================================
            'price' => [
                'type' => 'number',
                'description' => __('Course price. Use 0 for free courses.', 'schema-markup-generator'),
                'description_long' => __('The price of the course. Use 0 for free courses (this will set isAccessibleForFree on Course). Price information is placed at Course root level and in CourseInstance.offers for maximum SEO tool compatibility.', 'schema-markup-generator'),
                'example' => __('0 (free), 49.99, 199.00, 499.00', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/price',
                'auto' => '0 (Free)',
                'auto_description' => __('Defaults to 0 (Free). Placed in Course.offers and CourseInstance.offers.', 'schema-markup-generator'),
            ],
            'priceCurrency' => [
                'type' => 'text',
                'description' => __('Currency code (EUR, USD, etc.).', 'schema-markup-generator'),
                'description_long' => __('The currency of the course price in ISO 4217 format. Placed at Course root level and in CourseInstance.offers for maximum SEO tool compatibility.', 'schema-markup-generator'),
                'example' => __('EUR, USD, GBP', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/priceCurrency',
                'auto' => 'site_currency',
                'auto_description' => __('Auto-detected from WooCommerce/MemberPress or EUR. Placed in Course.offers and CourseInstance.offers.', 'schema-markup-generator'),
            ],
            'availability' => [
                'type' => 'select',
                'description' => __('Course availability status.', 'schema-markup-generator'),
                'description_long' => __('The availability status of the course. Use InStock for open enrollment, PreOrder for upcoming courses, or SoldOut if enrollment is closed. Placed at Course root level and in CourseInstance.offers for maximum SEO tool compatibility.', 'schema-markup-generator'),
                'example' => __('InStock (open enrollment), PreOrder (coming soon)', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/availability',
                'options' => ['InStock', 'SoldOut', 'PreOrder', 'Discontinued'],
                'auto' => 'InStock',
                'auto_description' => __('Defaults to InStock. Placed in Course.offers and CourseInstance.offers.', 'schema-markup-generator'),
            ],
            'offerCategory' => [
                'type' => 'text',
                'description' => __('Offer category. Required by Semrush and SEO validators.', 'schema-markup-generator'),
                'description_long' => __('A category for the offer. Required by Semrush and other SEO validation tools for nested Offers. For courses, common values include "Fees", "Online Course", "Certification", "Workshop", "Bootcamp".', 'schema-markup-generator'),
                'example' => __('Fees, Online Course, Certification, Workshop, Bootcamp', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/category',
                'auto' => 'Fees',
                'auto_description' => __('Defaults to "Fees" (standard category for course pricing)', 'schema-markup-generator'),
            ],

            // ========================================
            // Course Details
            // ========================================
            'duration' => [
                'type' => 'number',
                'description' => __('Total course duration in hours. Helps users understand time commitment.', 'schema-markup-generator'),
                'description_long' => __('The total duration of all course content in hours. This helps students understand the time commitment required to complete the course.', 'schema-markup-generator'),
                'example' => __('12, 40, 100 (hours)', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/timeRequired',
            ],
            'educationalLevel' => [
                'type' => 'select',
                'description' => __('Difficulty level. Helps match courses to appropriate learners.', 'schema-markup-generator'),
                'description_long' => __('The difficulty or complexity level of the course. This helps users find courses appropriate for their current skill level.', 'schema-markup-generator'),
                'example' => __('Beginner, Intermediate, Advanced', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/educationalLevel',
                'options' => ['Beginner', 'Intermediate', 'Advanced', 'Expert'],
            ],
            'courseMode' => [
                'type' => 'select',
                'description' => __('Delivery format. Goes into CourseInstance per schema.org.', 'schema-markup-generator'),
                'description_long' => __('How the course is delivered: online (fully remote), onsite (in-person), or blended (combination). This property belongs to CourseInstance (not Course) per schema.org specification.', 'schema-markup-generator'),
                'example' => __('online, onsite, blended', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/courseMode',
                'options' => ['online', 'onsite', 'blended'],
                'auto' => 'online',
                'auto_description' => __('Defaults to online. Placed in CourseInstance.', 'schema-markup-generator'),
            ],
            'courseWorkload' => [
                'type' => 'text',
                'description' => __('Expected workload description. Required by Google. Auto-generated if not set.', 'schema-markup-generator'),
                'description_long' => __('The expected workload for students. Required by Google for CourseInstance. If not mapped, auto-generates based on course duration (e.g., "Approximately 10 hours of self-paced learning") or defaults to "Self-paced online learning".', 'schema-markup-generator'),
                'example' => __('2 hours of lectures, 1 hour of lab per week; Approximately 10 hours of self-paced learning', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/courseWorkload',
                'auto' => 'duration_based',
                'auto_description' => __('Auto-generated from course duration or defaults to "Self-paced online learning"', 'schema-markup-generator'),
            ],
            'inLanguage' => [
                'type' => 'text',
                'description' => __('Course language (e.g., "it", "en"). Auto-detected from WordPress if not set.', 'schema-markup-generator'),
                'description_long' => __('The language in which the course is taught. Use ISO 639-1 codes (e.g., "en" for English, "it" for Italian). Automatically detected from WordPress settings if not specified.', 'schema-markup-generator'),
                'example' => __('en, it, es, de, fr', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/inLanguage',
                'auto' => 'site_language',
                'auto_description' => __('Auto-detected from WordPress site language', 'schema-markup-generator'),
            ],
            'numberOfCredits' => [
                'type' => 'number',
                'description' => __('Academic credits awarded. Relevant for formal education courses.', 'schema-markup-generator'),
                'description_long' => __('The number of academic credits (ECTS, credit hours, etc.) awarded upon completion. Relevant for accredited courses or formal education programs.', 'schema-markup-generator'),
                'example' => __('3, 6, 12 (credits)', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/numberOfCredits',
            ],

            // ========================================
            // Prerequisites & Requirements
            // ========================================
            'coursePrerequisites' => [
                'type' => 'textarea',
                'description' => __('Required knowledge before starting (text description). Helps users self-assess readiness.', 'schema-markup-generator'),
                'description_long' => __('A description of knowledge, skills, or experience students should have before taking the course. Helps users assess if they are ready for the course.', 'schema-markup-generator'),
                'example' => __('Basic understanding of HTML and CSS, familiarity with any programming language, no prior experience required', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/coursePrerequisites',
            ],
            'competencyRequired' => [
                'type' => 'text',
                'description' => __('Specific skills needed (comma-separated). More structured than prerequisites for AI matching.', 'schema-markup-generator'),
                'description_long' => __('Specific competencies or skills required as prerequisites. This is a more structured way to describe prerequisites that helps AI systems match courses to learners.', 'schema-markup-generator'),
                'example' => __('HTML, CSS, Basic JavaScript, Command line basics', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/competencyRequired',
            ],

            // ========================================
            // Target Audience & Outcomes
            // ========================================
            'audience' => [
                'type' => 'text',
                'description' => __('Target audience (e.g., "developer", "student", "professional"). Helps AI recommend to right users.', 'schema-markup-generator'),
                'description_long' => __('The intended audience for the course. Be specific about roles, career stages, or backgrounds that would benefit most from this course.', 'schema-markup-generator'),
                'example' => __('aspiring web developers, marketing professionals, data analysts, career changers', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/audience',
            ],
            'educationalCredentialAwarded' => [
                'type' => 'text',
                'description' => __('Certificate/credential name awarded upon completion. Important for career-focused searches.', 'schema-markup-generator'),
                'description_long' => __('The credential, certificate, or badge awarded upon successful completion. This is important for users searching for courses that provide recognized credentials.', 'schema-markup-generator'),
                'example' => __('Google Data Analytics Professional Certificate, AWS Certified Developer, Completion Certificate', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/educationalCredentialAwarded',
            ],
        ]);
    }
}

