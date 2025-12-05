<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Discovery;

/**
 * Schema Recommender
 *
 * Recommends schema types based on post type names using pattern matching.
 *
 * @package Metodo\SchemaMarkupGenerator\Discovery
 * @author  Michele Marri <plugins@metodo.dev>
 */
class SchemaRecommender
{
    /**
     * Pattern matching to suggest schema based on post type name.
     * All patterns in English (WordPress standard).
     * 
     * @var array<string, string>
     */
    private const PATTERNS = [
        // Content types
        'recipe|recipes' => 'Recipe',
        'product|products|shop|store|wc_product' => 'Product',
        'event|events' => 'Event',
        'course|courses|lesson|lessons' => 'Course',
        'faq|faqs|question|questions' => 'FAQPage',
        
        // Educational/Guide content -> LearningResource
        'guide|guides|learn|learning|education|educational|resource|resources' => 'LearningResource',
        
        // Step-by-step content -> HowTo
        'how-?to|tutorial|tutorials|step|steps|instructions' => 'HowTo',
        
        // Media
        'video|videos|media' => 'VideoObject',
        
        // Reviews & Articles
        'review|reviews' => 'Review',
        'news|article|articles|blog' => 'Article',
        
        // Entities
        'person|people|team|member|members|author|authors|staff' => 'Person',
        'organization|company|business|agency|agencies' => 'Organization',
        
        // Software
        'software|app|apps|plugin|plugins|tool|tools' => 'SoftwareApplication',
    ];

    /**
     * Post types that should not have automatic recommendations (too generic)
     * 
     * @var array<string>
     */
    private const EXCLUDED_POST_TYPES = [
        'post',
        'page',
        'attachment',
        'revision',
        'nav_menu_item',
        'custom_css',
        'customize_changeset',
        'oembed_cache',
        'user_request',
        'wp_block',
        'wp_template',
        'wp_template_part',
        'wp_global_styles',
        'wp_navigation',
    ];

    /**
     * Get recommended schema type for a post type
     *
     * @param string $postType The post type slug
     * @return string|null The recommended schema type or null if none
     */
    public function getRecommendedSchema(string $postType): ?string
    {
        // Skip excluded post types
        if (in_array($postType, self::EXCLUDED_POST_TYPES, true)) {
            return null;
        }

        // Normalize post type name for matching
        $normalizedPostType = strtolower(str_replace(['_', '-'], ' ', $postType));

        foreach (self::PATTERNS as $pattern => $schemaType) {
            // Convert pattern to regex
            $regex = '/\b(' . $pattern . ')\b/i';
            
            if (preg_match($regex, $normalizedPostType)) {
                return $schemaType;
            }
        }

        return null;
    }

    /**
     * Check if a schema type is recommended for a post type
     *
     * @param string $postType The post type slug
     * @param string $schemaType The schema type to check
     * @return bool True if recommended
     */
    public function isRecommended(string $postType, string $schemaType): bool
    {
        $recommended = $this->getRecommendedSchema($postType);
        
        return $recommended !== null && $recommended === $schemaType;
    }

    /**
     * Get all pattern mappings (useful for debugging/admin display)
     *
     * @return array<string, string>
     */
    public function getPatterns(): array
    {
        return self::PATTERNS;
    }

    /**
     * Get human-readable description of why a schema was recommended
     *
     * @param string $postType The post type slug
     * @return string|null Description or null if no recommendation
     */
    public function getRecommendationReason(string $postType): ?string
    {
        $recommended = $this->getRecommendedSchema($postType);
        
        if ($recommended === null) {
            return null;
        }

        $normalizedPostType = strtolower(str_replace(['_', '-'], ' ', $postType));

        foreach (self::PATTERNS as $pattern => $schemaType) {
            if ($schemaType === $recommended) {
                $regex = '/\b(' . $pattern . ')\b/i';
                if (preg_match($regex, $normalizedPostType, $matches)) {
                    return sprintf(
                        /* translators: 1: matched pattern, 2: schema type */
                        __('Post type contains "%1$s" - %2$s schema recommended', 'schema-markup-generator'),
                        $matches[1],
                        $schemaType
                    );
                }
            }
        }

        return null;
    }
}


