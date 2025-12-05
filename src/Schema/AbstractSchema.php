<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Schema;

use WP_Post;

/**
 * Abstract Schema Base Class
 *
 * Provides common functionality for all schema implementations.
 *
 * @package Metodo\SchemaMarkupGenerator\Schema
 * @author  Michele Marri <plugins@metodo.dev>
 */
abstract class AbstractSchema implements SchemaInterface
{
    /**
     * The schema.org context URL
     */
    protected const CONTEXT = 'https://schema.org';

    /**
     * Build base schema structure
     */
    protected function buildBase(WP_Post $post): array
    {
        return [
            '@context' => self::CONTEXT,
            '@type' => $this->getType(),
        ];
    }

    /**
     * Get post URL
     */
    protected function getPostUrl(WP_Post $post): string
    {
        return get_permalink($post);
    }

    /**
     * Get post featured image data
     */
    protected function getFeaturedImage(WP_Post $post): ?array
    {
        $thumbnailId = get_post_thumbnail_id($post);

        if (!$thumbnailId) {
            return null;
        }

        $image = wp_get_attachment_image_src($thumbnailId, 'full');

        if (!$image) {
            return null;
        }

        return [
            '@type' => 'ImageObject',
            'url' => $image[0],
            'width' => $image[1],
            'height' => $image[2],
        ];
    }

    /**
     * Get author data
     */
    protected function getAuthor(WP_Post $post): array
    {
        $author = get_userdata($post->post_author);

        if (!$author) {
            return [
                '@type' => 'Person',
                'name' => __('Unknown', 'schema-markup-generator'),
            ];
        }

        $authorData = [
            '@type' => 'Person',
            'name' => $author->display_name,
        ];

        // Add author URL if available
        $authorUrl = get_author_posts_url($author->ID);
        if ($authorUrl) {
            $authorData['url'] = $authorUrl;
        }

        return $authorData;
    }

    /**
     * Get publisher/organization data
     */
    protected function getPublisher(): array
    {
        $publisher = [
            '@type' => 'Organization',
            'name' => get_bloginfo('name'),
            'url' => home_url('/'),
        ];

        // Try to get site logo
        $customLogoId = get_theme_mod('custom_logo');
        if ($customLogoId) {
            $logo = wp_get_attachment_image_src($customLogoId, 'full');
            if ($logo) {
                $publisher['logo'] = [
                    '@type' => 'ImageObject',
                    'url' => $logo[0],
                    'width' => $logo[1],
                    'height' => $logo[2],
                ];
            }
        }

        return $publisher;
    }

    /**
     * Format date to ISO 8601
     */
    protected function formatDate(string $date): string
    {
        return date('c', strtotime($date));
    }

    /**
     * Get excerpt or generate from content
     */
    protected function getPostDescription(WP_Post $post, int $maxLength = 160): string
    {
        $description = $post->post_excerpt;

        if (empty($description)) {
            $description = wp_strip_all_tags($post->post_content);
            $description = wp_trim_words($description, 30, '...');
        }

        if (mb_strlen($description) > $maxLength) {
            $description = mb_substr($description, 0, $maxLength - 3) . '...';
        }

        return $description;
    }

    /**
     * Get word count from content
     */
    protected function getWordCount(WP_Post $post): int
    {
        $content = wp_strip_all_tags($post->post_content);
        return str_word_count($content);
    }

    /**
     * Sanitize text by stripping HTML tags and normalizing whitespace
     * 
     * Useful for cleaning custom field values that may contain HTML.
     */
    protected function sanitizeText(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }

        // Strip all HTML tags
        $text = wp_strip_all_tags($value);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }

    /**
     * Sanitize array values by stripping HTML from each element
     */
    protected function sanitizeArrayValues(array $values): array
    {
        return array_values(array_filter(array_map(function ($value) {
            if (is_string($value)) {
                return $this->sanitizeText($value);
            }
            return null;
        }, $values), function ($v) {
            return !empty($v);
        }));
    }

    /**
     * Get mapped field value
     * 
     * Automatically sanitizes string values by stripping HTML tags.
     * URLs are preserved. Use the 'smg_sanitize_mapped_value' filter to customize.
     */
    protected function getMappedValue(WP_Post $post, array $mapping, string $property, mixed $default = null): mixed
    {
        if (!isset($mapping[$property])) {
            return $default;
        }

        $fieldKey = $mapping[$property];
        $value = null;

        // Check ACF first
        if (function_exists('get_field')) {
            $value = get_field($fieldKey, $post->ID);
        }

        // Fallback to post meta if ACF didn't return a value
        if ($value === null || $value === false) {
            $value = get_post_meta($post->ID, $fieldKey, true);
        }

        // Return default if no value found
        if ($value === null || $value === false || $value === '') {
            return $default;
        }

        // Sanitize the value
        $value = $this->sanitizeMappedValue($value, $property);

        /**
         * Filter the sanitized mapped value
         * 
         * @param mixed   $value    The sanitized value
         * @param string  $property The schema property name
         * @param string  $fieldKey The source field key
         * @param WP_Post $post     The post object
         */
        return apply_filters('smg_sanitize_mapped_value', $value, $property, $fieldKey, $post);
    }

    /**
     * Sanitize a mapped value based on its type
     * 
     * - Strings: Strip HTML (unless it's a URL)
     * - Arrays: Recursively sanitize string values
     * - Other types: Return as-is
     */
    protected function sanitizeMappedValue(mixed $value, string $property): mixed
    {
        // Handle strings
        if (is_string($value)) {
            // Don't sanitize URLs
            if (filter_var($value, FILTER_VALIDATE_URL)) {
                return $value;
            }
            
            // Don't sanitize values that look like email addresses
            if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $value;
            }

            return $this->sanitizeText($value);
        }

        // Handle arrays
        if (is_array($value)) {
            // Skip associative arrays that look like raw meta dumps
            if ($this->isRawMetaDump($value)) {
                return null;
            }

            // Recursively sanitize array values
            return $this->sanitizeArrayRecursive($value, $property);
        }

        // Numbers, booleans, etc. - return as-is
        return $value;
    }

    /**
     * Recursively sanitize array values
     */
    protected function sanitizeArrayRecursive(array $values, string $property): array
    {
        $sanitized = [];

        foreach ($values as $key => $value) {
            if (is_string($value)) {
                // Don't sanitize URLs
                if (filter_var($value, FILTER_VALIDATE_URL)) {
                    $sanitized[$key] = $value;
                } else {
                    $cleanValue = $this->sanitizeText($value);
                    if (!empty($cleanValue)) {
                        $sanitized[$key] = $cleanValue;
                    }
                }
            } elseif (is_array($value)) {
                $cleanValue = $this->sanitizeArrayRecursive($value, $property);
                if (!empty($cleanValue)) {
                    $sanitized[$key] = $cleanValue;
                }
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Check if an array looks like a raw WordPress meta dump
     * 
     * These contain internal WordPress meta keys that shouldn't be in schema
     */
    protected function isRawMetaDump(array $arr): bool
    {
        // Common WordPress internal meta keys
        $internalKeys = [
            '_edit_lock',
            '_edit_last',
            '_thumbnail_id',
            '_wp_page_template',
            'rank_math_',
            '_mepr_',
        ];

        foreach ($internalKeys as $key) {
            foreach (array_keys($arr) as $arrKey) {
                if (str_starts_with((string) $arrKey, $key)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Clean and filter empty values from array
     */
    protected function cleanData(array $data): array
    {
        return array_filter($data, function ($value) {
            if (is_array($value)) {
                return !empty($value);
            }
            return $value !== null && $value !== '';
        });
    }

    /**
     * Get default validation result
     */
    public function validate(array $data): array
    {
        $errors = [];
        $requiredProperties = $this->getRequiredProperties();

        foreach ($requiredProperties as $property) {
            if (!isset($data[$property]) || empty($data[$property])) {
                $errors[] = sprintf(
                    __('Missing required property: %s', 'schema-markup-generator'),
                    $property
                );
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $this->getWarnings($data),
        ];
    }

    /**
     * Get warnings for missing recommended properties
     */
    protected function getWarnings(array $data): array
    {
        $warnings = [];
        $recommendedProperties = $this->getRecommendedProperties();

        foreach ($recommendedProperties as $property) {
            if (!isset($data[$property]) || empty($data[$property])) {
                $warnings[] = sprintf(
                    __('Missing recommended property: %s', 'schema-markup-generator'),
                    $property
                );
            }
        }

        return $warnings;
    }

    /**
     * Get default recommended properties
     */
    public function getRecommendedProperties(): array
    {
        return [];
    }

    /**
     * Get default property definitions
     */
    public function getPropertyDefinitions(): array
    {
        return [];
    }
}

