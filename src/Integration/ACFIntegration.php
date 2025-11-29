<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Integration;

/**
 * ACF Integration
 *
 * Integration with Advanced Custom Fields for field mapping.
 *
 * @package flavor\SchemaMarkupGenerator\Integration
 * @author  Michele Marri <info@metodo.dev>
 */
class ACFIntegration
{
    /**
     * Cached field groups
     */
    private array $fieldGroupsCache = [];

    /**
     * Initialize integration
     */
    public function init(): void
    {
        if (!$this->isAvailable()) {
            return;
        }

        // Add ACF field value resolver
        add_filter('smg_resolve_field_value', [$this, 'resolveFieldValue'], 10, 4);

        // Add ACF fields to discovery
        add_filter('smg_discovered_fields', [$this, 'addACFFields'], 10, 2);
    }

    /**
     * Check if ACF is active
     */
    public function isAvailable(): bool
    {
        return class_exists('ACF') || function_exists('get_field');
    }

    /**
     * Check if ACF Pro is active
     */
    public function isProActive(): bool
    {
        return class_exists('ACF') && defined('ACF_PRO');
    }

    /**
     * Get ACF fields for a post type
     *
     * @param string $postType The post type slug
     * @return array Array of field definitions
     */
    public function getFieldsForPostType(string $postType): array
    {
        if (!$this->isAvailable() || !function_exists('acf_get_field_groups')) {
            return [];
        }

        if (isset($this->fieldGroupsCache[$postType])) {
            return $this->fieldGroupsCache[$postType];
        }

        $fields = [];
        $fieldGroups = acf_get_field_groups(['post_type' => $postType]);

        foreach ($fieldGroups as $group) {
            $groupFields = acf_get_fields($group['key']);

            if (!is_array($groupFields)) {
                continue;
            }

            foreach ($groupFields as $field) {
                $fields[] = $this->formatField($field, $group);

                // Handle nested fields (repeater, group, flexible content)
                if (in_array($field['type'], ['repeater', 'group', 'flexible_content'], true)) {
                    $subFields = $this->getSubFields($field);
                    $fields = array_merge($fields, $subFields);
                }
            }
        }

        $this->fieldGroupsCache[$postType] = $fields;

        return $fields;
    }

    /**
     * Format ACF field for schema mapping
     */
    private function formatField(array $field, array $group): array
    {
        return [
            'key' => $field['name'],
            'name' => $field['name'],
            'label' => $field['label'],
            'type' => $this->mapFieldType($field['type']),
            'source' => 'acf',
            'acf_type' => $field['type'],
            'acf_key' => $field['key'],
            'group' => $group['title'],
            'required' => !empty($field['required']),
            'return_format' => $field['return_format'] ?? null,
        ];
    }

    /**
     * Get sub-fields from repeater/group/flexible content
     */
    private function getSubFields(array $field, string $prefix = ''): array
    {
        $subFields = [];
        $fieldPrefix = $prefix ? $prefix . '_' . $field['name'] : $field['name'];

        if ($field['type'] === 'repeater' || $field['type'] === 'group') {
            $children = $field['sub_fields'] ?? [];

            foreach ($children as $subField) {
                $subFields[] = [
                    'key' => $fieldPrefix . '_' . $subField['name'],
                    'name' => $fieldPrefix . '.' . $subField['name'],
                    'label' => $field['label'] . ' → ' . $subField['label'],
                    'type' => $this->mapFieldType($subField['type']),
                    'source' => 'acf',
                    'acf_type' => $subField['type'],
                    'parent_type' => $field['type'],
                ];

                // Recursively get nested sub-fields
                if (in_array($subField['type'], ['repeater', 'group'], true)) {
                    $subFields = array_merge(
                        $subFields,
                        $this->getSubFields($subField, $fieldPrefix)
                    );
                }
            }
        } elseif ($field['type'] === 'flexible_content') {
            $layouts = $field['layouts'] ?? [];

            foreach ($layouts as $layout) {
                $layoutFields = $layout['sub_fields'] ?? [];

                foreach ($layoutFields as $layoutField) {
                    $subFields[] = [
                        'key' => $fieldPrefix . '_' . $layout['name'] . '_' . $layoutField['name'],
                        'name' => $fieldPrefix . '.' . $layout['name'] . '.' . $layoutField['name'],
                        'label' => $field['label'] . ' → ' . $layout['label'] . ' → ' . $layoutField['label'],
                        'type' => $this->mapFieldType($layoutField['type']),
                        'source' => 'acf',
                        'acf_type' => $layoutField['type'],
                        'parent_type' => 'flexible_content',
                    ];
                }
            }
        }

        return $subFields;
    }

    /**
     * Map ACF field type to generic type
     */
    private function mapFieldType(string $acfType): string
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
     * Resolve ACF field value for schema
     */
    public function resolveFieldValue($value, int $postId, string $fieldKey, string $source): mixed
    {
        if ($source !== 'acf' || !$this->isAvailable()) {
            return $value;
        }

        $acfValue = get_field($fieldKey, $postId);

        if ($acfValue === null || $acfValue === false) {
            return $value;
        }

        // Handle image fields
        if (is_array($acfValue) && isset($acfValue['url'])) {
            return $acfValue['url'];
        }

        // Handle gallery fields
        if (is_array($acfValue) && isset($acfValue[0]['url'])) {
            return array_map(fn($img) => $img['url'], $acfValue);
        }

        // Handle link fields
        if (is_array($acfValue) && isset($acfValue['url']) && isset($acfValue['title'])) {
            return $acfValue['url'];
        }

        // Handle post object fields
        if ($acfValue instanceof \WP_Post) {
            return [
                'name' => $acfValue->post_title,
                'url' => get_permalink($acfValue),
            ];
        }

        // Handle user fields
        if ($acfValue instanceof \WP_User) {
            return [
                'name' => $acfValue->display_name,
                'url' => get_author_posts_url($acfValue->ID),
            ];
        }

        // Handle term fields
        if ($acfValue instanceof \WP_Term) {
            return $acfValue->name;
        }

        return $acfValue;
    }

    /**
     * Add ACF fields to discovered fields
     */
    public function addACFFields(array $fields, string $postType): array
    {
        $acfFields = $this->getFieldsForPostType($postType);
        return array_merge($fields, $acfFields);
    }

    /**
     * Get all field groups
     */
    public function getAllFieldGroups(): array
    {
        if (!$this->isAvailable() || !function_exists('acf_get_field_groups')) {
            return [];
        }

        return acf_get_field_groups();
    }

    /**
     * Reset cache
     */
    public function resetCache(): void
    {
        $this->fieldGroupsCache = [];
    }
}

