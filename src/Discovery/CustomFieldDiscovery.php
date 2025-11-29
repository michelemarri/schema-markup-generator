<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Discovery;

/**
 * Custom Field Discovery Service
 *
 * Discovers custom fields (meta) registered for posts,
 * including support for ACF, native meta, and other popular field plugins.
 *
 * @package flavor\SchemaMarkupGenerator\Discovery
 * @author  Michele Marri <info@metodo.dev>
 */
class CustomFieldDiscovery
{
    /**
     * Cached fields per post type
     */
    private array $fieldsCache = [];

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

        // Get ACF fields if available
        if ($this->isACFActive()) {
            $fields = array_merge($fields, $this->getACFFields($postType));
        }

        // Get native WordPress meta keys
        $fields = array_merge($fields, $this->getNativeMetaKeys($postType));

        // Get registered meta fields
        $fields = array_merge($fields, $this->getRegisteredMeta($postType));

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
     * Check if ACF is active
     */
    public function isACFActive(): bool
    {
        return class_exists('ACF') || function_exists('get_field');
    }

    /**
     * Get ACF fields for a post type
     */
    private function getACFFields(string $postType): array
    {
        if (!function_exists('acf_get_field_groups')) {
            return [];
        }

        $fields = [];
        $fieldGroups = acf_get_field_groups(['post_type' => $postType]);

        foreach ($fieldGroups as $group) {
            $groupFields = acf_get_fields($group['key']);

            if (!is_array($groupFields)) {
                continue;
            }

            foreach ($groupFields as $field) {
                $fields[] = [
                    'key' => $field['name'],
                    'name' => $field['name'],
                    'label' => $field['label'],
                    'type' => $this->mapACFFieldType($field['type']),
                    'source' => 'acf',
                    'acf_type' => $field['type'],
                    'group' => $group['title'],
                    'required' => !empty($field['required']),
                ];
            }
        }

        return $fields;
    }

    /**
     * Map ACF field types to generic types
     */
    private function mapACFFieldType(string $acfType): string
    {
        return match ($acfType) {
            'text', 'textarea', 'wysiwyg', 'message' => 'text',
            'number', 'range' => 'number',
            'email' => 'email',
            'url', 'link', 'page_link' => 'url',
            'image', 'file' => 'file',
            'gallery' => 'gallery',
            'select', 'checkbox', 'radio', 'button_group' => 'select',
            'true_false' => 'boolean',
            'date_picker' => 'date',
            'date_time_picker' => 'datetime',
            'time_picker' => 'time',
            'color_picker' => 'color',
            'post_object', 'relationship' => 'post',
            'taxonomy' => 'taxonomy',
            'user' => 'user',
            'repeater', 'flexible_content' => 'repeater',
            'group' => 'group',
            'google_map' => 'location',
            'oembed' => 'embed',
            default => 'text',
        };
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
            $fields[] = [
                'key' => $key,
                'name' => $key,
                'label' => $this->humanizeFieldName($key),
                'type' => 'text',
                'source' => 'native',
            ];
        }

        return $fields;
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

