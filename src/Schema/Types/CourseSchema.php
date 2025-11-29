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

        /**
         * Filter course schema data
         */
        $data = apply_filters('smg_course_schema_data', $data, $post, $mapping);

        return $this->cleanData($data);
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
        return ['instructor', 'offers', 'image', 'aggregateRating', 'educationalLevel'];
    }

    public function getPropertyDefinitions(): array
    {
        return [
            'name' => [
                'type' => 'text',
                'description' => __('Course name', 'schema-markup-generator'),
                'auto' => 'post_title',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('Course description', 'schema-markup-generator'),
                'auto' => 'post_excerpt',
            ],
            'provider' => [
                'type' => 'text',
                'description' => __('Course provider/institution', 'schema-markup-generator'),
            ],
            'instructor' => [
                'type' => 'text',
                'description' => __('Instructor name', 'schema-markup-generator'),
            ],
            'duration' => [
                'type' => 'number',
                'description' => __('Course duration (hours)', 'schema-markup-generator'),
            ],
            'price' => [
                'type' => 'number',
                'description' => __('Course price', 'schema-markup-generator'),
            ],
            'priceCurrency' => [
                'type' => 'text',
                'description' => __('Currency code', 'schema-markup-generator'),
            ],
            'educationalLevel' => [
                'type' => 'select',
                'description' => __('Educational level', 'schema-markup-generator'),
                'options' => ['Beginner', 'Intermediate', 'Advanced', 'Expert'],
            ],
            'courseMode' => [
                'type' => 'select',
                'description' => __('Course mode', 'schema-markup-generator'),
                'options' => ['online', 'onsite', 'blended'],
            ],
        ];
    }
}

