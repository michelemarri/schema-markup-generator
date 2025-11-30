<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Admin;

use flavor\SchemaMarkupGenerator\Schema\SchemaFactory;
use flavor\SchemaMarkupGenerator\Discovery\CustomFieldDiscovery;

/**
 * Schema Properties Handler
 *
 * Handles AJAX requests for loading schema properties dynamically.
 *
 * @package flavor\SchemaMarkupGenerator\Admin
 * @author  Michele Marri <info@metodo.dev>
 */
class SchemaPropertiesHandler
{
    private SchemaFactory $schemaFactory;
    private CustomFieldDiscovery $customFieldDiscovery;

    public function __construct(
        SchemaFactory $schemaFactory,
        CustomFieldDiscovery $customFieldDiscovery
    ) {
        $this->schemaFactory = $schemaFactory;
        $this->customFieldDiscovery = $customFieldDiscovery;
    }

    /**
     * Handle AJAX request for schema properties
     */
    public function handle(): void
    {
        check_ajax_referer('smg_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'schema-markup-generator')]);
        }

        $schemaType = isset($_POST['schema_type']) ? sanitize_text_field($_POST['schema_type']) : '';
        $postType = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : '';

        if (empty($postType)) {
            wp_send_json_error(['message' => __('Invalid post type', 'schema-markup-generator')]);
        }

        // Get current field mappings for this post type
        $fieldMappings = get_option('smg_field_mappings', []);
        $currentFieldMapping = $fieldMappings[$postType] ?? [];

        // Get available fields for this post type
        $postTypeFields = $this->customFieldDiscovery->getFieldsForPostType($postType);

        // If no schema type selected, return empty
        if (empty($schemaType)) {
            wp_send_json_success([
                'html' => $this->renderNoSchemaMessage(),
                'properties' => [],
            ]);
        }

        // Get schema properties
        $schema = $this->schemaFactory->create($schemaType);
        
        if (!$schema) {
            wp_send_json_error(['message' => __('Invalid schema type', 'schema-markup-generator')]);
        }

        $schemaProps = $schema->getPropertyDefinitions();

        if (empty($schemaProps)) {
            wp_send_json_success([
                'html' => $this->renderNoPropertiesMessage(),
                'properties' => [],
            ]);
        }

        // Render the mapping table HTML
        $html = $this->renderMappingTable($postType, $schemaProps, $postTypeFields, $currentFieldMapping);

        wp_send_json_success([
            'html' => $html,
            'properties' => array_keys($schemaProps),
            'schema_type' => $schemaType,
        ]);
    }

    /**
     * Render the field mapping table
     */
    private function renderMappingTable(
        string $postType,
        array $schemaProps,
        array $postTypeFields,
        array $currentFieldMapping
    ): string {
        ob_start();
        ?>
        <p class="smg-fields-description">
            <?php esc_html_e('Map your custom fields to schema properties for richer structured data.', 'schema-markup-generator'); ?>
        </p>

        <table class="smg-mapping-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Schema Property', 'schema-markup-generator'); ?></th>
                    <th><?php esc_html_e('Source Field', 'schema-markup-generator'); ?></th>
                    <th><?php esc_html_e('Auto', 'schema-markup-generator'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($schemaProps as $propName => $propDef): ?>
                    <tr class="smg-mapping-row smg-animate-fade-in">
                        <td>
                            <strong><?php echo esc_html($propName); ?></strong>
                            <br>
                            <small><?php echo esc_html($propDef['description'] ?? ''); ?></small>
                        </td>
                        <td>
                            <select name="smg_field_mappings[<?php echo esc_attr($postType); ?>][<?php echo esc_attr($propName); ?>]"
                                    class="smg-field-select">
                                <option value=""><?php esc_html_e('— Select Field —', 'schema-markup-generator'); ?></option>
                                <optgroup label="<?php esc_attr_e('WordPress Fields', 'schema-markup-generator'); ?>">
                                    <option value="post_title" <?php selected($currentFieldMapping[$propName] ?? '', 'post_title'); ?>>
                                        <?php esc_html_e('Post Title', 'schema-markup-generator'); ?>
                                    </option>
                                    <option value="post_excerpt" <?php selected($currentFieldMapping[$propName] ?? '', 'post_excerpt'); ?>>
                                        <?php esc_html_e('Post Excerpt', 'schema-markup-generator'); ?>
                                    </option>
                                    <option value="post_content" <?php selected($currentFieldMapping[$propName] ?? '', 'post_content'); ?>>
                                        <?php esc_html_e('Post Content', 'schema-markup-generator'); ?>
                                    </option>
                                    <option value="featured_image" <?php selected($currentFieldMapping[$propName] ?? '', 'featured_image'); ?>>
                                        <?php esc_html_e('Featured Image', 'schema-markup-generator'); ?>
                                    </option>
                                    <option value="author" <?php selected($currentFieldMapping[$propName] ?? '', 'author'); ?>>
                                        <?php esc_html_e('Author', 'schema-markup-generator'); ?>
                                    </option>
                                </optgroup>
                                <?php if (!empty($postTypeFields)): ?>
                                    <optgroup label="<?php esc_attr_e('Custom Fields', 'schema-markup-generator'); ?>">
                                        <?php foreach ($postTypeFields as $field): ?>
                                            <option value="<?php echo esc_attr($field['key']); ?>"
                                                    <?php selected($currentFieldMapping[$propName] ?? '', $field['key']); ?>>
                                                <?php echo esc_html($field['label']); ?>
                                                <?php if ($field['source'] === 'acf'): ?>
                                                    <span>(ACF)</span>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                        </td>
                        <td>
                            <?php if (!empty($propDef['auto'])): ?>
                                <?php 
                                $autoTitle = $propDef['auto_description'] ?? __('Auto-populated from WordPress', 'schema-markup-generator');
                                $autoLabel = $propDef['auto'];
                                if ($propDef['auto'] === 'post_content' && !empty($propDef['auto_description'])) {
                                    $autoLabel = __('Auto', 'schema-markup-generator');
                                }
                                ?>
                                <span class="smg-auto-badge" title="<?php echo esc_attr($autoTitle); ?>">
                                    <span class="dashicons dashicons-yes-alt"></span>
                                    <?php echo esc_html($autoLabel); ?>
                                </span>
                            <?php else: ?>
                                <span class="smg-manual-badge">
                                    <?php esc_html_e('Manual', 'schema-markup-generator'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    /**
     * Render message when no schema is selected
     */
    private function renderNoSchemaMessage(): string
    {
        ob_start();
        ?>
        <p class="smg-notice smg-animate-fade-in">
            <span class="dashicons dashicons-info"></span>
            <?php esc_html_e('Select a schema type above to configure field mappings.', 'schema-markup-generator'); ?>
        </p>
        <?php
        return ob_get_clean();
    }

    /**
     * Render message when schema has no configurable properties
     */
    private function renderNoPropertiesMessage(): string
    {
        ob_start();
        ?>
        <p class="smg-notice smg-animate-fade-in">
            <span class="dashicons dashicons-yes-alt"></span>
            <?php esc_html_e('This schema type is fully automatic and doesn\'t require field mapping.', 'schema-markup-generator'); ?>
        </p>
        <?php
        return ob_get_clean();
    }
}

