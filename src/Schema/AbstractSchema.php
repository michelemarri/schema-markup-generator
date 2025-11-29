<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Schema;

use WP_Post;

/**
 * Abstract Schema Base Class
 *
 * Provides common functionality for all schema implementations.
 *
 * @package flavor\SchemaMarkupGenerator\Schema
 * @author  Michele Marri <info@metodo.dev>
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
     * Get mapped field value
     */
    protected function getMappedValue(WP_Post $post, array $mapping, string $property, mixed $default = null): mixed
    {
        if (!isset($mapping[$property])) {
            return $default;
        }

        $fieldKey = $mapping[$property];

        // Check ACF first
        if (function_exists('get_field')) {
            $value = get_field($fieldKey, $post->ID);
            if ($value !== null && $value !== false) {
                return $value;
            }
        }

        // Fallback to post meta
        $value = get_post_meta($post->ID, $fieldKey, true);

        return $value ?: $default;
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

