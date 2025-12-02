<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Tools;

/**
 * Exporter
 *
 * Exports plugin settings to JSON.
 *
 * @package Metodo\SchemaMarkupGenerator\Tools
 * @author  Michele Marri <plugins@metodo.dev>
 */
class Exporter
{
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
            'version' => SMG_VERSION,
            'exported_at' => current_time('c'),
            'site_url' => home_url('/'),
            'general_settings' => get_option('smg_general_settings', []),
            'advanced_settings' => get_option('smg_advanced_settings', []),
            'integrations_settings' => get_option('smg_integrations_settings', []),
            'update_settings' => get_option('smg_update_settings', []),
        ];

        if ($includeMappings) {
            $data['post_type_mappings'] = get_option('smg_post_type_mappings', []);
            $data['page_mappings'] = get_option('smg_page_mappings', []);
        }

        if ($includeFieldMappings) {
            $data['field_mappings'] = get_option('smg_field_mappings', []);
        }

        /**
         * Filter export data
         *
         * @param array $data The export data
         */
        return apply_filters('smg_export_data', $data);
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

