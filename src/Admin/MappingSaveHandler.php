<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Admin;

/**
 * Mapping Save Handler
 *
 * Handles AJAX requests for auto-saving schema and field mappings.
 *
 * @package Metodo\SchemaMarkupGenerator\Admin
 * @author  Michele Marri <plugins@metodo.dev>
 */
class MappingSaveHandler
{
    /**
     * Handle AJAX request for saving schema mapping
     */
    public function handleSaveSchemaMapping(): void
    {
        check_ajax_referer('smg_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'schema-markup-generator')]);
        }

        $postType = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : '';
        $schemaType = isset($_POST['schema_type']) ? sanitize_text_field($_POST['schema_type']) : '';

        if (empty($postType)) {
            wp_send_json_error(['message' => __('Invalid post type', 'schema-markup-generator')]);
        }

        // Force fresh read from database (bypass alloptions cache)
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('smg_post_type_mappings', 'options');
        
        // Get current mappings
        $mappings = get_option('smg_post_type_mappings', []);
        
        // Ensure it's an array
        if (!is_array($mappings)) {
            $mappings = [];
        }

        // Update the mapping for this post type
        if (empty($schemaType)) {
            unset($mappings[$postType]);
        } else {
            $mappings[$postType] = $schemaType;
        }

        // Clear cache before save
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('smg_post_type_mappings', 'options');
        
        // Use update_option which handles both create and update
        $saved = update_option('smg_post_type_mappings', $mappings, true);
        
        // Clear cache after save
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('smg_post_type_mappings', 'options');

        // update_option returns false if value is unchanged, so check if save worked
        $verify = get_option('smg_post_type_mappings', []);
        $success = isset($verify[$postType]) ? ($verify[$postType] === $schemaType) : empty($schemaType);

        if ($success) {
            wp_send_json_success([
                'message' => __('Schema mapping saved', 'schema-markup-generator'),
                'post_type' => $postType,
                'schema_type' => $schemaType,
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to save mapping', 'schema-markup-generator'),
            ]);
        }
    }

    /**
     * Handle AJAX request for saving field mapping
     */
    public function handleSaveFieldMapping(): void
    {
        check_ajax_referer('smg_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'schema-markup-generator')]);
        }

        $postType = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : '';
        // Use sanitize_text_field for property to preserve camelCase (e.g., learningResourceType)
        $property = isset($_POST['property']) ? sanitize_text_field($_POST['property']) : '';
        $fieldKey = isset($_POST['field_key']) ? sanitize_text_field($_POST['field_key']) : '';

        if (empty($postType) || empty($property)) {
            wp_send_json_error(['message' => __('Invalid parameters', 'schema-markup-generator')]);
        }

        // Force fresh read from database
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('smg_field_mappings', 'options');

        // Get current field mappings
        $fieldMappings = get_option('smg_field_mappings', []);
        
        // Ensure it's an array
        if (!is_array($fieldMappings)) {
            $fieldMappings = [];
        }

        // Initialize post type array if not exists
        if (!isset($fieldMappings[$postType]) || !is_array($fieldMappings[$postType])) {
            $fieldMappings[$postType] = [];
        }

        // Update the field mapping for this property
        if (empty($fieldKey)) {
            unset($fieldMappings[$postType][$property]);
        } else {
            $fieldMappings[$postType][$property] = $fieldKey;
        }

        // Clean up empty arrays
        if (empty($fieldMappings[$postType])) {
            unset($fieldMappings[$postType]);
        }

        // Clear cache before save
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('smg_field_mappings', 'options');
        
        // Use update_option which handles both create and update
        update_option('smg_field_mappings', $fieldMappings, true);

        // Clear cache after save
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('smg_field_mappings', 'options');

        // Verify save worked
        $verify = get_option('smg_field_mappings', []);
        $actualValue = $verify[$postType][$property] ?? null;
        $expectedValue = empty($fieldKey) ? null : $fieldKey;
        $success = ($actualValue === $expectedValue);

        if ($success) {
            wp_send_json_success([
                'message' => __('Field mapping saved', 'schema-markup-generator'),
                'post_type' => $postType,
                'property' => $property,
                'field_key' => $fieldKey,
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to save mapping', 'schema-markup-generator'),
            ]);
        }
    }

    /**
     * Handle AJAX request for saving taxonomy schema mapping
     */
    public function handleSaveTaxonomyMapping(): void
    {
        check_ajax_referer('smg_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'schema-markup-generator')]);
        }

        $taxonomy = isset($_POST['taxonomy']) ? sanitize_key($_POST['taxonomy']) : '';
        $schemaType = isset($_POST['schema_type']) ? sanitize_text_field($_POST['schema_type']) : '';

        if (empty($taxonomy)) {
            wp_send_json_error(['message' => __('Invalid taxonomy', 'schema-markup-generator')]);
        }

        // Force fresh read from database (bypass alloptions cache)
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('smg_taxonomy_mappings', 'options');
        
        // Get current mappings
        $mappings = get_option('smg_taxonomy_mappings', []);
        
        // Ensure it's an array
        if (!is_array($mappings)) {
            $mappings = [];
        }

        // Update the mapping for this taxonomy
        if (empty($schemaType)) {
            unset($mappings[$taxonomy]);
        } else {
            $mappings[$taxonomy] = $schemaType;
        }

        // Clear cache before save
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('smg_taxonomy_mappings', 'options');
        
        // Use update_option which handles both create and update
        $saved = update_option('smg_taxonomy_mappings', $mappings, true);
        
        // Clear cache after save
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('smg_taxonomy_mappings', 'options');

        // update_option returns false if value is unchanged, so check if save worked
        $verify = get_option('smg_taxonomy_mappings', []);
        $success = isset($verify[$taxonomy]) ? ($verify[$taxonomy] === $schemaType) : empty($schemaType);

        if ($success) {
            wp_send_json_success([
                'message' => __('Taxonomy schema mapping saved', 'schema-markup-generator'),
                'taxonomy' => $taxonomy,
                'schema_type' => $schemaType,
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to save mapping', 'schema-markup-generator'),
            ]);
        }
    }

    /**
     * Handle AJAX request for saving integration setting
     * 
     * Auto-saves individual integration settings when modified in modal.
     */
    public function handleSaveIntegrationSetting(): void
    {
        check_ajax_referer('smg_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'schema-markup-generator')]);
        }

        $settingKey = isset($_POST['setting_key']) ? sanitize_text_field($_POST['setting_key']) : '';
        $settingValue = $_POST['setting_value'] ?? '';

        if (empty($settingKey)) {
            wp_send_json_error(['message' => __('Invalid setting key', 'schema-markup-generator')]);
        }

        // Force fresh read from database (bypass alloptions cache)
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('smg_integrations_settings', 'options');
        
        // Get current settings
        $settings = get_option('smg_integrations_settings', []);
        
        // Ensure it's an array
        if (!is_array($settings)) {
            $settings = [];
        }

        // Handle array values (like rankmath_takeover_types)
        if (is_array($settingValue)) {
            $settings[$settingKey] = array_map('sanitize_text_field', $settingValue);
        } elseif ($settingValue === 'true' || $settingValue === '1') {
            // Handle boolean true
            $settings[$settingKey] = true;
        } elseif ($settingValue === 'false' || $settingValue === '0' || $settingValue === '') {
            // Handle boolean false
            $settings[$settingKey] = false;
        } else {
            // Handle string values
            $settings[$settingKey] = sanitize_text_field($settingValue);
        }

        // Clear cache before save
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('smg_integrations_settings', 'options');
        
        // Save the settings
        update_option('smg_integrations_settings', $settings, true);

        // Clear cache after save
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete('smg_integrations_settings', 'options');

        // Verify save worked
        $verify = get_option('smg_integrations_settings', []);
        $success = isset($verify[$settingKey]);

        if ($success) {
            wp_send_json_success([
                'message' => __('Setting saved', 'schema-markup-generator'),
                'setting_key' => $settingKey,
                'setting_value' => $settings[$settingKey],
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to save setting', 'schema-markup-generator'),
            ]);
        }
    }
}
