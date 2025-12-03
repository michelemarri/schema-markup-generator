<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Tools;

/**
 * Importer
 *
 * Imports plugin settings from JSON with support for auto-discovered options.
 *
 * @package Metodo\SchemaMarkupGenerator\Tools
 * @author  Michele Marri <plugins@metodo.dev>
 */
class Importer
{
    /**
     * Option prefix for validation
     */
    private const OPTION_PREFIX = 'smg_';

    /**
     * Options that should never be imported
     */
    private const BLOCKED_OPTIONS = [
        'smg_settings_backup',
    ];

    /**
     * Import settings from data array
     *
     * @param array $data         The import data
     * @param bool  $mergeExisting Merge with existing settings
     * @return bool Success
     */
    public function import(array $data, bool $mergeExisting = false): bool
    {
        // Detect format version
        $isNewFormat = isset($data['export_format']) && version_compare($data['export_format'], '2.0', '>=');

        /**
         * Filter import data before processing
         *
         * @param array $data The import data
         */
        $data = apply_filters('smg_import_data', $data);

        if ($isNewFormat) {
            return $this->importNewFormat($data, $mergeExisting);
        }

        // Legacy format support
        return $this->importLegacyFormat($data, $mergeExisting);
    }

    /**
     * Import new format (2.0+) with auto-discovered options
     *
     * @param array $data Import data
     * @param bool $mergeExisting Merge with existing
     * @return bool Success
     */
    private function importNewFormat(array $data, bool $mergeExisting): bool
    {
        if (!isset($data['options']) || !is_array($data['options'])) {
            return false;
        }

        foreach ($data['options'] as $optionName => $value) {
            // Validate option name starts with our prefix
            if (!str_starts_with($optionName, self::OPTION_PREFIX)) {
                continue;
            }

            // Skip blocked options
            if (in_array($optionName, self::BLOCKED_OPTIONS, true)) {
                continue;
            }

            // Sanitize the value
            $value = $this->sanitizeOptionValue($optionName, $value);

            if ($mergeExisting) {
                $existing = get_option($optionName, []);
                if (is_array($existing) && is_array($value)) {
                    $value = $this->deepMerge($existing, $value);
                }
            }

            update_option($optionName, $value);
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
     * Import legacy format (pre-2.0)
     *
     * @param array $data Import data
     * @param bool $mergeExisting Merge with existing
     * @return bool Success
     */
    private function importLegacyFormat(array $data, bool $mergeExisting): bool
    {
        // Validate data structure
        if (!$this->validateLegacyData($data)) {
            return false;
        }

        // Import settings
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
                $data['field_mappings'] = $this->deepMerge($existing, $data['field_mappings']);
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
                $value = $this->deepMerge($existing, $value);
            }
        }
        update_option($optionName, $value);
    }

    /**
     * Deep merge two arrays recursively
     *
     * @param array $existing Existing array
     * @param array $new New array
     * @return array Merged array
     */
    private function deepMerge(array $existing, array $new): array
    {
        foreach ($new as $key => $value) {
            if (is_array($value) && isset($existing[$key]) && is_array($existing[$key])) {
                $existing[$key] = $this->deepMerge($existing[$key], $value);
            } else {
                $existing[$key] = $value;
            }
        }

        return $existing;
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
     * Validate legacy import data structure
     */
    private function validateLegacyData(array $data): bool
    {
        // Must have at least one settings section or mappings
        return isset($data['general_settings']) ||
               isset($data['advanced_settings']) ||
               isset($data['integrations_settings']) ||
               isset($data['post_type_mappings']) ||
               isset($data['page_mappings']) ||
               isset($data['field_mappings']);
    }

    /**
     * Sanitize option value based on option name
     *
     * @param string $optionName Option name
     * @param mixed $value Option value
     * @return mixed Sanitized value
     */
    private function sanitizeOptionValue(string $optionName, mixed $value): mixed
    {
        // For array values, sanitize recursively
        if (is_array($value)) {
            return $this->sanitizeArray($value);
        }

        // For scalar values, sanitize based on type
        if (is_string($value)) {
            return sanitize_text_field($value);
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        return $value;
    }

    /**
     * Recursively sanitize array values
     *
     * @param array $array Array to sanitize
     * @return array Sanitized array
     */
    private function sanitizeArray(array $array): array
    {
        $sanitized = [];

        foreach ($array as $key => $value) {
            $sanitizedKey = is_string($key) ? sanitize_key($key) : $key;

            if (is_array($value)) {
                $sanitized[$sanitizedKey] = $this->sanitizeArray($value);
            } elseif (is_string($value)) {
                $sanitized[$sanitizedKey] = sanitize_text_field($value);
            } elseif (is_bool($value) || is_int($value) || is_float($value)) {
                $sanitized[$sanitizedKey] = $value;
            } else {
                $sanitized[$sanitizedKey] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize post type/page mappings
     */
    private function sanitizeMappings(array $mappings): array
    {
        $sanitized = [];

        foreach ($mappings as $key => $value) {
            $sanitized[sanitize_key($key)] = sanitize_text_field($value);
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
     * Validate import file
     *
     * @param array $data Data to validate
     * @return array Validation result with 'valid' (bool) and 'errors' (array)
     */
    public function validate(array $data): array
    {
        $errors = [];

        // Check for new format
        if (isset($data['export_format'])) {
            if (!isset($data['options']) || !is_array($data['options'])) {
                $errors[] = __('Invalid export file: missing options data.', 'schema-markup-generator');
            }
        } else {
            // Legacy format validation
            if (!$this->validateLegacyData($data)) {
                $errors[] = __('Invalid export file: no valid settings found.', 'schema-markup-generator');
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'format' => isset($data['export_format']) ? $data['export_format'] : '1.0',
            'plugin_version' => $data['plugin_version'] ?? $data['version'] ?? 'unknown',
            'exported_at' => $data['exported_at'] ?? 'unknown',
        ];
    }
}
