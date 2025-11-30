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

        // Import new separated settings (preferred)
        if (isset($data['general_settings'])) {
            $this->importOption('smg_general_settings', $data['general_settings'], $mergeExisting);
        }
        if (isset($data['advanced_settings'])) {
            $this->importOption('smg_advanced_settings', $data['advanced_settings'], $mergeExisting);
        }
        if (isset($data['integrations_settings'])) {
            $this->importOption('smg_integrations_settings', $data['integrations_settings'], $mergeExisting);
        }
        if (isset($data['update_settings'])) {
            $this->importOption('smg_update_settings', $data['update_settings'], $mergeExisting);
        }

        // Import legacy settings (for backward compatibility)
        if (isset($data['settings']) && !isset($data['general_settings'])) {
            // Only import legacy if new format is not present
            $this->importLegacySettings($data['settings'], $mergeExisting);
        }

        // Import post type mappings
        if (isset($data['post_type_mappings'])) {
            if ($mergeExisting) {
                $existing = get_option('smg_post_type_mappings', []);
                $data['post_type_mappings'] = array_merge($existing, $data['post_type_mappings']);
            }
            update_option('smg_post_type_mappings', $this->sanitizeMappings($data['post_type_mappings']));
        }

        // Import page mappings
        if (isset($data['page_mappings'])) {
            if ($mergeExisting) {
                $existing = get_option('smg_page_mappings', []);
                $data['page_mappings'] = array_merge($existing, $data['page_mappings']);
            }
            update_option('smg_page_mappings', $this->sanitizeMappings($data['page_mappings']));
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
     * Import a single option
     */
    private function importOption(string $optionName, array $value, bool $merge): void
    {
        if ($merge) {
            $existing = get_option($optionName, []);
            if (is_array($existing)) {
                $value = array_merge($existing, $value);
            }
        }
        update_option($optionName, $value);
    }

    /**
     * Import legacy settings to new format
     */
    private function importLegacySettings(array $settings, bool $merge): void
    {
        // General settings
        $general = [
            'enabled' => $settings['enabled'] ?? true,
            'enable_website_schema' => $settings['enable_website_schema'] ?? true,
            'enable_breadcrumb_schema' => $settings['enable_breadcrumb_schema'] ?? true,
            'output_format' => $settings['output_format'] ?? 'json-ld',
        ];
        $this->importOption('smg_general_settings', $general, $merge);

        // Advanced settings
        $advanced = [
            'cache_enabled' => $settings['cache_enabled'] ?? true,
            'cache_ttl' => $settings['cache_ttl'] ?? 3600,
            'debug_mode' => $settings['debug_mode'] ?? false,
        ];
        $this->importOption('smg_advanced_settings', $advanced, $merge);

        // Integrations settings
        $integrationKeys = [
            'rankmath_avoid_duplicates', 'rankmath_takeover_types',
            'integration_rankmath_enabled', 'integration_acf_enabled',
            'integration_woocommerce_enabled', 'integration_memberpress_courses_enabled',
            'acf_auto_discover', 'acf_include_nested',
            'mpcs_auto_parent_course', 'mpcs_include_curriculum',
            'woo_auto_product', 'woo_include_reviews', 'woo_include_offers',
        ];
        $integrations = [];
        foreach ($integrationKeys as $key) {
            if (isset($settings[$key])) {
                $integrations[$key] = $settings[$key];
            }
        }
        if (!empty($integrations)) {
            $this->importOption('smg_integrations_settings', $integrations, $merge);
        }
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
               isset($data['general_settings']) ||
               isset($data['advanced_settings']) ||
               isset($data['integrations_settings']) ||
               isset($data['post_type_mappings']) ||
               isset($data['page_mappings']) ||
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

