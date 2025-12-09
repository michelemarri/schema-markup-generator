<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Discovery;

/**
 * Custom Field Discovery Service
 *
 * Discovers custom fields (meta) registered for posts,
 * including support for ACF, native meta, and other popular field plugins.
 * Fields are automatically categorized by their source plugin.
 *
 * @package Metodo\SchemaMarkupGenerator\Discovery
 * @author  Michele Marri <plugins@metodo.dev>
 */
class CustomFieldDiscovery
{
    /**
     * Cached fields per post type
     */
    private array $fieldsCache = [];

    /**
     * Known plugin prefixes for field identification
     * Format: prefix => [label, priority (lower = first)]
     */
    private const PLUGIN_PREFIXES = [
        'rank_math' => ['Rank Math', 100],
        'wafp' => ['Affiliate WP', 80],
        'wpf' => ['WPFunnels', 80],
        'mpgft' => ['MemberPress Gifting', 70],
        'mepr' => ['MemberPress', 70],
        'wc' => ['WooCommerce', 60],
        'yoast' => ['Yoast SEO', 100],
        'aioseo' => ['All in One SEO', 100],
        'jetpack' => ['Jetpack', 90],
        'elementor' => ['Elementor', 90],
        'divi' => ['Divi', 90],
        'learndash' => ['LearnDash', 70],
        'llms' => ['LifterLMS', 70],
        'sensei' => ['Sensei LMS', 70],
        'edd' => ['Easy Digital Downloads', 60],
        'give' => ['GiveWP', 80],
        'tribe' => ['The Events Calendar', 80],
        'et' => ['Elegant Themes', 90],
    ];

    /**
     * Get all custom fields for a post type
     *
     * @param string $postType The post type slug
     * @return array Array of field definitions
     */
    public function getFieldsForPostType(string $postType): array
    {
        if (isset($this->fieldsCache[$postType])) {
            return $this->fieldsCache[$postType];
        }

        $fields = [];
        $knownKeys = [];

        // Note: ACF fields are added via ACFIntegration filter (smg_discovered_fields)
        // to avoid duplication and centralize ACF logic

        // Get native WordPress meta keys
        $nativeFields = $this->getNativeMetaKeys($postType);
        foreach ($nativeFields as $field) {
            if (!isset($knownKeys[$field['key']])) {
                $fields[] = $field;
                $knownKeys[$field['key']] = true;
            }
        }

        // Get registered meta fields (skip already known)
        $registeredFields = $this->getRegisteredMeta($postType);
        foreach ($registeredFields as $field) {
            if (!isset($knownKeys[$field['key']])) {
                $fields[] = $field;
                $knownKeys[$field['key']] = true;
            }
        }

        /**
         * Filter discovered custom fields
         *
         * @param array  $fields   Array of field definitions
         * @param string $postType The post type
         */
        $this->fieldsCache[$postType] = apply_filters('smg_discovered_fields', $fields, $postType);

        return $this->fieldsCache[$postType];
    }

    /**
     * Check if ACF/SCF is active
     */
    public function isACFActive(): bool
    {
        return class_exists('ACF') || function_exists('get_field');
    }

    /**
     * Get native WordPress meta keys for a post type
     */
    private function getNativeMetaKeys(string $postType): array
    {
        global $wpdb;

        // Get sample posts to discover meta keys
        $posts = get_posts([
            'post_type' => $postType,
            'posts_per_page' => 10,
            'post_status' => 'any',
        ]);

        $metaKeys = [];

        foreach ($posts as $post) {
            $meta = get_post_meta($post->ID);
            foreach (array_keys($meta) as $key) {
                // Skip private meta (starting with _)
                if (str_starts_with($key, '_')) {
                    continue;
                }
                // Skip ACF fields (already handled)
                if (str_starts_with($key, 'field_')) {
                    continue;
                }
                $metaKeys[$key] = true;
            }
        }

        $fields = [];
        foreach (array_keys($metaKeys) as $key) {
            $pluginSource = $this->identifyPluginSource($key);
            $fields[] = [
                'key' => $key,
                'name' => $key,
                'label' => $this->humanizeFieldName($key),
                'type' => 'text',
                'source' => 'native',
                'plugin' => $pluginSource['plugin'],
                'plugin_label' => $pluginSource['label'],
                'plugin_priority' => $pluginSource['priority'],
            ];
        }

        return $fields;
    }

    /**
     * Identify the plugin source from field name prefix
     */
    private function identifyPluginSource(string $fieldName): array
    {
        $normalizedName = strtolower($fieldName);
        
        // Remove leading underscore if present
        if (str_starts_with($normalizedName, '_')) {
            $normalizedName = substr($normalizedName, 1);
        }

        foreach (self::PLUGIN_PREFIXES as $prefix => [$label, $priority]) {
            if (str_starts_with($normalizedName, $prefix . '_')) {
                return [
                    'plugin' => $prefix,
                    'label' => $label,
                    'priority' => $priority,
                ];
            }
        }

        // No known plugin prefix found
        return [
            'plugin' => 'custom',
            'label' => __('Custom Fields', 'schema-markup-generator'),
            'priority' => 50,
        ];
    }

    /**
     * Get fields grouped by source/plugin
     * 
     * Returns fields organized by their source plugin for better UI organization.
     *
     * @param string $postType The post type slug
     * @return array Array of field groups: ['group_key' => ['label' => '', 'priority' => 0, 'fields' => []]]
     */
    public function getFieldsGroupedBySource(string $postType): array
    {
        $fields = $this->getFieldsForPostType($postType);
        $groups = [];

        foreach ($fields as $field) {
            // Determine group key
            if ($field['source'] === 'acf') {
                // ACF/SCF fields: group by their field group
                $groupKey = 'acf_' . sanitize_key($field['group'] ?? 'general');
                $groupLabel = $field['group'] ?? __('Custom Fields', 'schema-markup-generator');
                $priority = 10; // Custom fields first
            } else {
                // Other fields: group by plugin
                $groupKey = $field['plugin'] ?? 'custom';
                $groupLabel = $field['plugin_label'] ?? __('Custom Fields', 'schema-markup-generator');
                $priority = $field['plugin_priority'] ?? 50;
            }

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'label' => $groupLabel,
                    'priority' => $priority,
                    'fields' => [],
                ];
            }

            $groups[$groupKey]['fields'][] = $field;
        }

        // Sort groups by priority (lower first), then by label
        uasort($groups, function ($a, $b) {
            if ($a['priority'] !== $b['priority']) {
                return $a['priority'] <=> $b['priority'];
            }
            return strcasecmp($a['label'], $b['label']);
        });

        return $groups;
    }

    /**
     * Get registered meta fields
     */
    private function getRegisteredMeta(string $postType): array
    {
        $registered = get_registered_meta_keys('post', $postType);
        $fields = [];

        foreach ($registered as $key => $args) {
            // Skip if already discovered
            if (str_starts_with($key, '_')) {
                continue;
            }

            $fields[] = [
                'key' => $key,
                'name' => $key,
                'label' => $args['description'] ?: $this->humanizeFieldName($key),
                'type' => $this->mapMetaType($args['type'] ?? 'string'),
                'source' => 'registered',
            ];
        }

        return $fields;
    }

    /**
     * Map WordPress meta types to generic types
     */
    private function mapMetaType(string $type): string
    {
        return match ($type) {
            'integer', 'number' => 'number',
            'boolean' => 'boolean',
            'array', 'object' => 'array',
            default => 'text',
        };
    }

    /**
     * Convert field key to human readable label
     */
    private function humanizeFieldName(string $name): string
    {
        $name = str_replace(['_', '-'], ' ', $name);
        return ucwords($name);
    }

    /**
     * Get field value for a post
     *
     * @param int    $postId   The post ID
     * @param string $fieldKey The field key
     * @param string $source   The field source (acf, native, registered)
     * @return mixed The field value
     */
    public function getFieldValue(int $postId, string $fieldKey, string $source = 'auto'): mixed
    {
        // Try ACF first if available
        if (($source === 'auto' || $source === 'acf') && $this->isACFActive()) {
            $value = get_field($fieldKey, $postId);
            if ($value !== null && $value !== false) {
                return $value;
            }
        }

        // Fallback to native meta
        return get_post_meta($postId, $fieldKey, true);
    }

    /**
     * Reset cache
     */
    public function reset(): void
    {
        $this->fieldsCache = [];
    }
}

