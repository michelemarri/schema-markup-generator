<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Mapper;

use Metodo\SchemaMarkupGenerator\Discovery\CustomFieldDiscovery;
use Metodo\SchemaMarkupGenerator\Discovery\TaxonomyDiscovery;
use WP_Post;

/**
 * Field Mapper
 *
 * Maps WordPress fields to schema.org properties.
 *
 * @package Metodo\SchemaMarkupGenerator\Mapper
 * @author  Michele Marri <plugins@metodo.dev>
 */
class FieldMapper
{
    private CustomFieldDiscovery $customFieldDiscovery;
    private TaxonomyDiscovery $taxonomyDiscovery;

    public function __construct(
        CustomFieldDiscovery $customFieldDiscovery,
        TaxonomyDiscovery $taxonomyDiscovery
    ) {
        $this->customFieldDiscovery = $customFieldDiscovery;
        $this->taxonomyDiscovery = $taxonomyDiscovery;
    }

    /**
     * Get mapped data for a post
     *
     * @param WP_Post $post    The post object
     * @param array   $mapping Field mapping configuration
     * @return array Mapped data with schema property keys
     */
    public function mapFields(WP_Post $post, array $mapping): array
    {
        $data = [];

        foreach ($mapping as $schemaProperty => $source) {
            $value = $this->resolveValue($post, $source);

            if ($value !== null && $value !== '') {
                $data[$schemaProperty] = $value;
            }
        }

        /**
         * Filter mapped field data
         *
         * @param array   $data    The mapped data
         * @param WP_Post $post    The post object
         * @param array   $mapping The mapping configuration
         */
        return apply_filters('smg_mapped_fields', $data, $post, $mapping);
    }

    /**
     * Resolve value from source
     *
     * @param WP_Post $post   The post object
     * @param mixed   $source The source configuration
     * @return mixed The resolved value
     */
    private function resolveValue(WP_Post $post, mixed $source): mixed
    {
        // If source is an array, it's a complex mapping
        if (is_array($source)) {
            return $this->resolveComplexValue($post, $source);
        }

        // Simple string source
        return $this->resolveSimpleValue($post, $source);
    }

    /**
     * Resolve simple string source
     */
    private function resolveSimpleValue(WP_Post $post, string $source): mixed
    {
        // WordPress core fields
        if (str_starts_with($source, 'post_')) {
            return $this->resolvePostField($post, $source);
        }

        // Site fields
        if (str_starts_with($source, 'site_')) {
            return $this->resolveSiteField($source);
        }

        // Featured image
        if ($source === 'featured_image') {
            return $this->getFeaturedImageUrl($post);
        }

        // Author
        if ($source === 'author') {
            return $this->getAuthorName($post);
        }

        // Categories
        if ($source === 'category' || $source === 'categories') {
            return $this->getCategories($post);
        }

        // Tags
        if ($source === 'tags') {
            return $this->getTags($post);
        }

        // Taxonomy field
        if (str_starts_with($source, 'taxonomy:')) {
            $taxonomy = substr($source, 9);
            return $this->getTaxonomyTerms($post, $taxonomy);
        }

        // ACF field
        if (str_starts_with($source, 'acf:')) {
            $fieldName = substr($source, 4);
            return $this->getAcfField($post, $fieldName);
        }

        // Meta field
        if (str_starts_with($source, 'meta:')) {
            $metaKey = substr($source, 5);
            return get_post_meta($post->ID, $metaKey, true);
        }

        // Default: try as meta key
        return $this->customFieldDiscovery->getFieldValue($post->ID, $source);
    }

    /**
     * Resolve complex source configuration
     */
    private function resolveComplexValue(WP_Post $post, array $source): mixed
    {
        $type = $source['type'] ?? 'value';

        return match ($type) {
            'concat' => $this->resolveConcatValue($post, $source),
            'conditional' => $this->resolveConditionalValue($post, $source),
            'transform' => $this->resolveTransformValue($post, $source),
            'nested' => $this->resolveNestedValue($post, $source),
            default => $this->resolveSimpleValue($post, $source['source'] ?? ''),
        };
    }

    /**
     * Resolve concatenated value
     */
    private function resolveConcatValue(WP_Post $post, array $source): string
    {
        $parts = [];
        $separator = $source['separator'] ?? ' ';

        foreach ($source['sources'] as $partSource) {
            $value = $this->resolveValue($post, $partSource);
            if ($value !== null && $value !== '') {
                $parts[] = $value;
            }
        }

        return implode($separator, $parts);
    }

    /**
     * Resolve conditional value
     */
    private function resolveConditionalValue(WP_Post $post, array $source): mixed
    {
        $condition = $source['condition'] ?? [];
        $field = $this->resolveValue($post, $condition['field'] ?? '');
        $operator = $condition['operator'] ?? '==';
        $compareValue = $condition['value'] ?? '';

        $conditionMet = match ($operator) {
            '==' => $field == $compareValue,
            '===' => $field === $compareValue,
            '!=' => $field != $compareValue,
            '!==' => $field !== $compareValue,
            '>' => $field > $compareValue,
            '<' => $field < $compareValue,
            'empty' => empty($field),
            'not_empty' => !empty($field),
            default => false,
        };

        if ($conditionMet) {
            return $this->resolveValue($post, $source['then'] ?? '');
        }

        return $this->resolveValue($post, $source['else'] ?? '');
    }

    /**
     * Resolve transformed value
     */
    private function resolveTransformValue(WP_Post $post, array $source): mixed
    {
        $value = $this->resolveValue($post, $source['source'] ?? '');
        $transform = $source['transform'] ?? '';

        return match ($transform) {
            'uppercase' => is_string($value) ? strtoupper($value) : $value,
            'lowercase' => is_string($value) ? strtolower($value) : $value,
            'trim' => is_string($value) ? trim($value) : $value,
            'strip_tags' => is_string($value) ? wp_strip_all_tags($value) : $value,
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'date' => is_string($value) ? date('c', strtotime($value)) : $value,
            'excerpt' => is_string($value) ? wp_trim_words($value, 30) : $value,
            default => $value,
        };
    }

    /**
     * Resolve nested object value
     */
    private function resolveNestedValue(WP_Post $post, array $source): array
    {
        $result = [];
        $properties = $source['properties'] ?? [];

        foreach ($properties as $key => $propertySource) {
            $value = $this->resolveValue($post, $propertySource);
            if ($value !== null && $value !== '') {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Resolve WordPress post field
     */
    private function resolvePostField(WP_Post $post, string $field): mixed
    {
        return match ($field) {
            'post_title' => html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8'),
            'post_content' => $post->post_content,
            'post_excerpt' => $this->getExcerpt($post),
            'post_date' => date('c', strtotime($post->post_date_gmt)),
            'post_modified' => date('c', strtotime($post->post_modified_gmt)),
            'post_name' => $post->post_name,
            'post_author' => $this->getAuthorName($post),
            'post_url' => get_permalink($post),
            default => $post->$field ?? null,
        };
    }

    /**
     * Resolve WordPress site field
     */
    private function resolveSiteField(string $field): mixed
    {
        return match ($field) {
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url('/'),
            'site_description' => get_bloginfo('description'),
            'site_language' => get_bloginfo('language'),
            'site_language_code' => $this->getLanguageCode(),
            'site_currency' => $this->getSiteCurrency(),
            default => null,
        };
    }

    /**
     * Get ISO 639-1 language code (e.g., "it" from "it-IT")
     */
    private function getLanguageCode(): string
    {
        $locale = get_bloginfo('language');
        // Extract just the language part (e.g., "it" from "it-IT")
        return explode('-', $locale)[0];
    }

    /**
     * Get site currency from available sources
     */
    private function getSiteCurrency(): string
    {
        // Try WooCommerce first
        if (function_exists('get_woocommerce_currency')) {
            return get_woocommerce_currency();
        }

        // Try MemberPress
        if (class_exists('MeprOptions')) {
            $options = \MeprOptions::fetch();
            return $options->currency_code ?? 'EUR';
        }

        // Default to EUR
        return 'EUR';
    }

    /**
     * Get post excerpt or generate from content
     */
    private function getExcerpt(WP_Post $post): string
    {
        if (!empty($post->post_excerpt)) {
            return $post->post_excerpt;
        }

        return wp_trim_words(wp_strip_all_tags($post->post_content), 30);
    }

    /**
     * Get featured image URL
     */
    private function getFeaturedImageUrl(WP_Post $post): ?string
    {
        $thumbnailId = get_post_thumbnail_id($post);

        if (!$thumbnailId) {
            return null;
        }

        $image = wp_get_attachment_image_url($thumbnailId, 'full');

        return $image ?: null;
    }

    /**
     * Get author name
     */
    private function getAuthorName(WP_Post $post): string
    {
        $author = get_userdata($post->post_author);
        return $author ? $author->display_name : '';
    }

    /**
     * Get categories
     */
    private function getCategories(WP_Post $post): array
    {
        $categories = get_the_category($post->ID);
        return array_map(fn($cat) => $cat->name, $categories);
    }

    /**
     * Get tags
     */
    private function getTags(WP_Post $post): array
    {
        $tags = get_the_tags($post->ID);
        return $tags ? array_map(fn($tag) => $tag->name, $tags) : [];
    }

    /**
     * Get taxonomy terms
     */
    private function getTaxonomyTerms(WP_Post $post, string $taxonomy): array
    {
        $terms = $this->taxonomyDiscovery->getPostTerms($post->ID, $taxonomy);
        return array_map(fn($term) => $term->name, $terms);
    }

    /**
     * Get ACF field value
     */
    private function getAcfField(WP_Post $post, string $fieldName): mixed
    {
        if (!function_exists('get_field')) {
            return null;
        }

        $value = get_field($fieldName, $post->ID);

        // Handle ACF image fields
        if (is_array($value) && isset($value['url'])) {
            return $value['url'];
        }

        return $value;
    }
}

