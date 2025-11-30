<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Integration;

/**
 * Rank Math Integration
 *
 * Prevents duplicate schemas when Rank Math is active.
 *
 * @package flavor\SchemaMarkupGenerator\Integration
 * @author  Michele Marri <info@metodo.dev>
 */
class RankMathIntegration
{
    /**
     * Schema types to check for duplicates
     */
    private const DUPLICATE_TYPES = [
        'Article',
        'BlogPosting',
        'NewsArticle',
        'Product',
        'LocalBusiness',
        'Organization',
        'Person',
        'FAQPage',
        'HowTo',
        'Recipe',
        'Event',
        'Review',
        'VideoObject',
        'Course',
        'BreadcrumbList',
        'WebSite',
        'WebPage',
    ];

    /**
     * Initialize integration
     */
    public function init(): void
    {
        if (!$this->isAvailable()) {
            return;
        }

        // Filter our schemas to avoid duplicates
        add_filter('smg_post_schemas', [$this, 'filterDuplicateSchemas'], 10, 2);

        // Optionally disable Rank Math schema for specific types
        add_filter('rank_math/json_ld', [$this, 'filterRankMathSchema'], 99, 2);
    }

    /**
     * Check if Rank Math is active
     */
    public function isAvailable(): bool
    {
        return class_exists('RankMath');
    }

    /**
     * Check if Rank Math schema is enabled
     */
    public function isSchemaEnabled(): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        // Check if schema module is active
        if (function_exists('rank_math')) {
            $modules = \RankMath\Helper::get_active_modules();
            return in_array('schema', $modules, true);
        }

        return false;
    }

    /**
     * Filter our schemas to remove duplicates with Rank Math
     */
    public function filterDuplicateSchemas(array $schemas, \WP_Post $post): array
    {
        if (!$this->isSchemaEnabled()) {
            return $schemas;
        }

        $settings = \flavor\SchemaMarkupGenerator\smg_get_settings('integrations');
        $avoidDuplicates = $settings['rankmath_avoid_duplicates'] ?? true;

        if (!$avoidDuplicates) {
            return $schemas;
        }

        // Get Rank Math schemas for this post
        $rankMathTypes = $this->getRankMathSchemaTypes($post);

        // Filter out our schemas that Rank Math already handles
        return array_filter($schemas, function ($schema) use ($rankMathTypes) {
            $type = $schema['@type'] ?? '';

            // Always keep our schemas if not a duplicate type
            if (!in_array($type, self::DUPLICATE_TYPES, true)) {
                return true;
            }

            // Remove if Rank Math already has this type
            return !in_array($type, $rankMathTypes, true);
        });
    }

    /**
     * Get schema types that Rank Math generates for a post
     */
    private function getRankMathSchemaTypes(\WP_Post $post): array
    {
        $types = [];

        // Check post meta for Rank Math schema
        $schemaType = get_post_meta($post->ID, 'rank_math_schema_' . $post->post_type, true);
        if ($schemaType) {
            $types[] = $schemaType;
        }

        // Check for default schema type
        if (function_exists('rank_math_get_settings')) {
            $defaultSchema = \RankMath\Helper::get_default_schema_type($post);
            if ($defaultSchema) {
                $types[] = $defaultSchema;
            }
        }

        // Rank Math always adds these
        if ($this->isSchemaEnabled()) {
            $types[] = 'WebSite';
            $types[] = 'WebPage';
            $types[] = 'BreadcrumbList';
        }

        return array_unique($types);
    }

    /**
     * Optionally filter Rank Math schema
     *
     * Can be used to let SMG handle specific schema types
     */
    public function filterRankMathSchema(array $data, $jsonld): array
    {
        $settings = \flavor\SchemaMarkupGenerator\smg_get_settings('integrations');
        $takeOver = $settings['rankmath_takeover_types'] ?? [];

        if (empty($takeOver)) {
            return $data;
        }

        // Remove schema types that we want to handle
        foreach ($data as $key => $schema) {
            $type = $schema['@type'] ?? '';
            if (in_array($type, $takeOver, true)) {
                unset($data[$key]);
            }
        }

        return array_values($data);
    }

    /**
     * Get Rank Math primary category for a post
     */
    public function getPrimaryCategory(int $postId): ?int
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $primaryCat = get_post_meta($postId, 'rank_math_primary_category', true);
        return $primaryCat ? (int) $primaryCat : null;
    }

    /**
     * Get Rank Math primary term for a taxonomy
     */
    public function getPrimaryTerm(int $postId, string $taxonomy): ?int
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $primaryTerm = get_post_meta($postId, 'rank_math_primary_' . $taxonomy, true);
        return $primaryTerm ? (int) $primaryTerm : null;
    }
}

