<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Tools;

/**
 * Importer
 *
 * Imports plugin settings from JSON.
 *
 * @package flavor\SchemaMarkupGenerator\Tools
 * @author  Michele Marri <info@metodo.dev>
 */
class Importer
{
    /**
     * Import settings from data array
     *
     * @param array $data         The import data
     * @param bool  $mergeExisting Merge with existing settings
     * @return bool Success
     */
    public function import(array $data, bool $mergeExisting = false): bool
    {
        // Validate data structure
        if (!$this->validateData($data)) {
            return false;
        }

        /**
         * Filter import data before processing
         *
         * @param array $data The import data
         */
        $data = apply_filters('smg_import_data', $data);

        // Import settings
        if (isset($data['settings'])) {
            if ($mergeExisting) {
                $existing = get_option('smg_settings', []);
                $data['settings'] = array_merge($existing, $data['settings']);
            }
            update_option('smg_settings', $this->sanitizeSettings($data['settings']));
        }

        // Import post type mappings
        if (isset($data['post_type_mappings'])) {
            if ($mergeExisting) {
                $existing = get_option('smg_post_type_mappings', []);
                $data['post_type_mappings'] = array_merge($existing, $data['post_type_mappings']);
            }
            update_option('smg_post_type_mappings', $this->sanitizeMappings($data['post_type_mappings']));
        }

        // Import field mappings
        if (isset($data['field_mappings'])) {
            if ($mergeExisting) {
                $existing = get_option('smg_field_mappings', []);
                $data['field_mappings'] = $this->mergeFieldMappings($existing, $data['field_mappings']);
            }
            update_option('smg_field_mappings', $this->sanitizeFieldMappings($data['field_mappings']));
        }

        /**
         * Action after import is complete
         *
         * @param array $data The imported data
         */
        do_action('smg_after_import', $data);

        return true;
    }

    /**
     * Import from file
     *
     * @param string $filePath The file path
     * @param bool   $merge    Merge with existing
     * @return bool Success
     */
    public function importFromFile(string $filePath, bool $merge = false): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $content = file_get_contents($filePath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        return $this->import($data, $merge);
    }

    /**
     * Validate import data structure
     */
    private function validateData(array $data): bool
    {
        // Must have at least settings or mappings
        return isset($data['settings']) ||
               isset($data['post_type_mappings']) ||
               isset($data['field_mappings']);
    }

    /**
     * Sanitize settings array
     */
    private function sanitizeSettings(array $settings): array
    {
        $sanitized = [];

        $sanitized['enabled'] = !empty($settings['enabled']);
        $sanitized['debug_mode'] = !empty($settings['debug_mode']);
        $sanitized['cache_enabled'] = !empty($settings['cache_enabled']);
        $sanitized['cache_ttl'] = absint($settings['cache_ttl'] ?? 3600);
        $sanitized['enable_website_schema'] = !empty($settings['enable_website_schema']);
        $sanitized['enable_breadcrumb_schema'] = !empty($settings['enable_breadcrumb_schema']);

        return $sanitized;
    }

    /**
     * Sanitize post type mappings
     */
    private function sanitizeMappings(array $mappings): array
    {
        $sanitized = [];

        foreach ($mappings as $postType => $schemaType) {
            $sanitized[sanitize_key($postType)] = sanitize_text_field($schemaType);
        }

        return $sanitized;
    }

    /**
     * Sanitize field mappings
     */
    private function sanitizeFieldMappings(array $mappings): array
    {
        $sanitized = [];

        foreach ($mappings as $postType => $fields) {
            if (!is_array($fields)) {
                continue;
            }

            $sanitized[sanitize_key($postType)] = [];

            foreach ($fields as $property => $field) {
                $sanitized[sanitize_key($postType)][sanitize_key($property)] = sanitize_text_field($field);
            }
        }

        return $sanitized;
    }

    /**
     * Merge field mappings deeply
     */
    private function mergeFieldMappings(array $existing, array $new): array
    {
        foreach ($new as $postType => $fields) {
            if (!isset($existing[$postType])) {
                $existing[$postType] = [];
            }
            $existing[$postType] = array_merge($existing[$postType], $fields);
        }

        return $existing;
    }
}

