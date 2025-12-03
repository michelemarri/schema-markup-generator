<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Tools;

/**
 * Exporter
 *
 * Exports plugin settings to JSON with auto-discovery.
 *
 * @package Metodo\SchemaMarkupGenerator\Tools
 * @author  Michele Marri <plugins@metodo.dev>
 */
class Exporter
{
    /**
     * Option prefix for auto-discovery
     */
    private const OPTION_PREFIX = 'smg_';

    /**
     * Options to exclude from export (sensitive data, temporary data)
     */
    private const EXCLUDED_OPTIONS = [
        'smg_settings_backup', // Backup data (can be large)
    ];

    /**
     * Keys within options that contain sensitive data to mask/exclude
     */
    private const SENSITIVE_KEYS = [
        'github_token',
        'github_token_encrypted',
        'api_key',
        'api_secret',
        'password',
        'token',
    ];

    /**
     * Export plugin settings
     *
     * @param bool $includeMappings      Include post type mappings
     * @param bool $includeFieldMappings Include field mappings
     * @return array Export data
     */
    public function export(bool $includeMappings = true, bool $includeFieldMappings = true): array
    {
        $data = [
            'export_format' => '2.0', // New format version for auto-discovery
            'plugin_version' => SMG_VERSION,
            'exported_at' => current_time('c'),
            'site_url' => home_url('/'),
            'options' => $this->discoverAndExportOptions(),
        ];

        // Filter out mappings if not requested
        if (!$includeMappings) {
            unset($data['options']['smg_post_type_mappings']);
            unset($data['options']['smg_page_mappings']);
        }

        if (!$includeFieldMappings) {
            unset($data['options']['smg_field_mappings']);
        }

        /**
         * Filter export data
         *
         * @param array $data The export data
         */
        return apply_filters('smg_export_data', $data);
    }

    /**
     * Auto-discover and export all plugin options
     *
     * @return array All options with smg_ prefix
     */
    private function discoverAndExportOptions(): array
    {
        global $wpdb;

        // Find all options with our prefix
        $options = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s",
                $wpdb->esc_like(self::OPTION_PREFIX) . '%',
                '_transient%'
            ),
            ARRAY_A
        );

        $exported = [];

        foreach ($options as $option) {
            $optionName = $option['option_name'];

            // Skip excluded options
            if (in_array($optionName, self::EXCLUDED_OPTIONS, true)) {
                continue;
            }

            $value = maybe_unserialize($option['option_value']);

            // Sanitize sensitive data
            $value = $this->sanitizeSensitiveData($value);

            $exported[$optionName] = $value;
        }

        // Sort by key for consistent output
        ksort($exported);

        return $exported;
    }

    /**
     * Recursively sanitize sensitive data from arrays
     *
     * @param mixed $data The data to sanitize
     * @return mixed Sanitized data
     */
    private function sanitizeSensitiveData(mixed $data): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            // Check if key contains sensitive data indicators
            foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
                if (stripos($key, $sensitiveKey) !== false) {
                    // Remove sensitive data entirely
                    unset($data[$key]);
                    continue 2;
                }
            }

            // Recursively process nested arrays
            if (is_array($value)) {
                $data[$key] = $this->sanitizeSensitiveData($value);
            }
        }

        return $data;
    }

    /**
     * Get list of all exportable options (for UI display)
     *
     * @return array List of option names that would be exported
     */
    public function getExportableOptions(): array
    {
        global $wpdb;

        $options = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s ORDER BY option_name",
                $wpdb->esc_like(self::OPTION_PREFIX) . '%',
                '_transient%'
            )
        );

        return array_filter($options, function ($name) {
            return !in_array($name, self::EXCLUDED_OPTIONS, true);
        });
    }

    /**
     * Export to file
     *
     * @param string $filename The filename
     * @return string File path
     */
    public function exportToFile(string $filename = ''): string
    {
        if (empty($filename)) {
            $filename = 'smg-settings-' . date('Y-m-d-His') . '.json';
        }

        $data = $this->export();
        $uploadDir = wp_upload_dir();
        $filePath = $uploadDir['basedir'] . '/smg-exports/' . $filename;

        // Create directory if needed
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        file_put_contents($filePath, wp_json_encode($data, JSON_PRETTY_PRINT));

        return $filePath;
    }
}
