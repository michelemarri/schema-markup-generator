<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Admin;

use Metodo\SchemaMarkupGenerator\Schema\SchemaFactory;
use Metodo\SchemaMarkupGenerator\Discovery\CustomFieldDiscovery;
use Metodo\SchemaMarkupGenerator\Discovery\TaxonomyDiscovery;

/**
 * Schema Properties Handler
 *
 * Handles AJAX requests for loading schema properties dynamically.
 *
 * @package Metodo\SchemaMarkupGenerator\Admin
 * @author  Michele Marri <plugins@metodo.dev>
 */
class SchemaPropertiesHandler
{
    private SchemaFactory $schemaFactory;
    private CustomFieldDiscovery $customFieldDiscovery;
    private TaxonomyDiscovery $taxonomyDiscovery;

    public function __construct(
        SchemaFactory $schemaFactory,
        CustomFieldDiscovery $customFieldDiscovery,
        TaxonomyDiscovery $taxonomyDiscovery
    ) {
        $this->schemaFactory = $schemaFactory;
        $this->customFieldDiscovery = $customFieldDiscovery;
        $this->taxonomyDiscovery = $taxonomyDiscovery;
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

        // Force fresh read (clear cache)
        wp_cache_delete('alloptions', 'options');
        
        // Get current field mappings for this post type
        $fieldMappings = get_option('smg_field_mappings', []);
        $currentFieldMapping = $fieldMappings[$postType] ?? [];

        // Get available fields for this post type, grouped by source
        $fieldGroups = $this->customFieldDiscovery->getFieldsGroupedBySource($postType);

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
        $html = $this->renderMappingTable($postType, $schemaProps, $fieldGroups, $currentFieldMapping);

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
        array $fieldGroups,
        array $currentFieldMapping
    ): string {
        ob_start();
        ?>
        <p class="smg-fields-description">
            <?php esc_html_e('Map your custom fields to schema properties for richer structured data.', 'schema-markup-generator'); ?>
            <span class="smg-fields-hint"><?php esc_html_e('Click on a property name for more details.', 'schema-markup-generator'); ?></span>
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
                            <a href="#" 
                               class="smg-property-name" 
                               data-property="<?php echo esc_attr($propName); ?>"
                               data-description="<?php echo esc_attr($propDef['description_long'] ?? $propDef['description'] ?? ''); ?>"
                               data-example="<?php echo esc_attr($propDef['example'] ?? ''); ?>"
                               data-schema-url="<?php echo esc_attr($propDef['schema_url'] ?? ''); ?>">
                                <?php echo esc_html($propName); ?>
                            </a>
                            <br>
                            <small><?php echo esc_html($propDef['description'] ?? ''); ?></small>
                        </td>
                        <td>
                            <select name="smg_field_mappings[<?php echo esc_attr($postType); ?>][<?php echo esc_attr($propName); ?>]"
                                    class="smg-field-select"
                                    autocomplete="off">
                                <option value="" selected><?php esc_html_e('— Select Field —', 'schema-markup-generator'); ?></option>
                                <optgroup label="<?php esc_attr_e('Post Fields', 'schema-markup-generator'); ?>">
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
                                <optgroup label="<?php esc_attr_e('Website', 'schema-markup-generator'); ?>">
                                    <option value="site_name" <?php selected($currentFieldMapping[$propName] ?? '', 'site_name'); ?>>
                                        <?php esc_html_e('Site Name', 'schema-markup-generator'); ?>
                                    </option>
                                    <option value="site_url" <?php selected($currentFieldMapping[$propName] ?? '', 'site_url'); ?>>
                                        <?php esc_html_e('Site URL', 'schema-markup-generator'); ?>
                                    </option>
                                    <option value="site_language" <?php selected($currentFieldMapping[$propName] ?? '', 'site_language'); ?>>
                                        <?php esc_html_e('Site Language (e.g. it-IT)', 'schema-markup-generator'); ?>
                                    </option>
                                    <option value="site_language_code" <?php selected($currentFieldMapping[$propName] ?? '', 'site_language_code'); ?>>
                                        <?php esc_html_e('Language Code (e.g. it)', 'schema-markup-generator'); ?>
                                    </option>
                                    <option value="site_currency" <?php selected($currentFieldMapping[$propName] ?? '', 'site_currency'); ?>>
                                        <?php esc_html_e('Site Currency (EUR, USD...)', 'schema-markup-generator'); ?>
                                    </option>
                                    <option value="site_wordpress_version" <?php selected($currentFieldMapping[$propName] ?? '', 'site_wordpress_version'); ?>>
                                        <?php esc_html_e('WordPress Version', 'schema-markup-generator'); ?>
                                    </option>
                                    <option value="site_theme_version" <?php selected($currentFieldMapping[$propName] ?? '', 'site_theme_version'); ?>>
                                        <?php esc_html_e('Theme Version', 'schema-markup-generator'); ?>
                                    </option>
                                </optgroup>
                                <?php 
                                // Render field groups by source/plugin
                                foreach ($fieldGroups as $groupKey => $group): 
                                    if (empty($group['fields'])) continue;
                                ?>
                                    <optgroup label="<?php echo esc_attr($group['label']); ?>">
                                        <?php foreach ($group['fields'] as $field): ?>
                                            <option value="<?php echo esc_attr($field['key']); ?>"
                                                    <?php selected($currentFieldMapping[$propName] ?? '', $field['key']); ?>>
                                                <?php echo esc_html($field['label']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                                <?php
                                $taxonomies = $this->taxonomyDiscovery->getTaxonomiesForPostType($postType);
                                if (!empty($taxonomies)):
                                ?>
                                    <optgroup label="<?php esc_attr_e('Taxonomies', 'schema-markup-generator'); ?>">
                                        <?php foreach ($taxonomies as $taxSlug => $taxonomy): ?>
                                            <option value="taxonomy:<?php echo esc_attr($taxSlug); ?>"
                                                    <?php selected($currentFieldMapping[$propName] ?? '', 'taxonomy:' . $taxSlug); ?>>
                                                <?php echo esc_html($taxonomy->labels->singular_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                <?php
                                // Custom value options
                                $currentValue = $currentFieldMapping[$propName] ?? '';
                                $isCustom = str_starts_with($currentValue, 'custom:');
                                $customType = '';
                                $customValue = '';
                                if ($isCustom) {
                                    $parts = explode(':', $currentValue, 3);
                                    $customType = $parts[1] ?? '';
                                    $customValue = $parts[2] ?? '';
                                }
                                ?>
                                <optgroup label="<?php esc_attr_e('Custom Value', 'schema-markup-generator'); ?>">
                                    <option value="custom:text:" 
                                            data-custom-type="text"
                                            <?php selected($isCustom && $customType === 'text', true); ?>
                                            <?php echo $isCustom && $customType === 'text' ? 'data-custom-value="' . esc_attr($customValue) . '"' : ''; ?>>
                                        <?php esc_html_e('✏️ Custom Text', 'schema-markup-generator'); ?>
                                    </option>
                                    <option value="custom:number:" 
                                            data-custom-type="number"
                                            <?php selected($isCustom && $customType === 'number', true); ?>
                                            <?php echo $isCustom && $customType === 'number' ? 'data-custom-value="' . esc_attr($customValue) . '"' : ''; ?>>
                                        <?php esc_html_e('🔢 Custom Number', 'schema-markup-generator'); ?>
                                    </option>
                                    <option value="custom:date:" 
                                            data-custom-type="date"
                                            <?php selected($isCustom && $customType === 'date', true); ?>
                                            <?php echo $isCustom && $customType === 'date' ? 'data-custom-value="' . esc_attr($customValue) . '"' : ''; ?>>
                                        <?php esc_html_e('📅 Custom Date', 'schema-markup-generator'); ?>
                                    </option>
                                    <option value="custom:url:" 
                                            data-custom-type="url"
                                            <?php selected($isCustom && $customType === 'url', true); ?>
                                            <?php echo $isCustom && $customType === 'url' ? 'data-custom-value="' . esc_attr($customValue) . '"' : ''; ?>>
                                        <?php esc_html_e('🔗 Custom URL', 'schema-markup-generator'); ?>
                                    </option>
                                    <option value="custom:boolean:true" 
                                            data-custom-type="boolean"
                                            <?php selected($isCustom && $customType === 'boolean' && $customValue === 'true', true); ?>>
                                        <?php esc_html_e('✓ Boolean: True', 'schema-markup-generator'); ?>
                                    </option>
                                    <option value="custom:boolean:false" 
                                            data-custom-type="boolean"
                                            <?php selected($isCustom && $customType === 'boolean' && $customValue === 'false', true); ?>>
                                        <?php esc_html_e('✗ Boolean: False', 'schema-markup-generator'); ?>
                                    </option>
                                </optgroup>
                            </select>
                            <?php if ($isCustom && in_array($customType, ['text', 'number', 'date', 'url'])): ?>
                            <div class="smg-custom-value-wrapper" style="margin-top: 8px;">
                                <input type="<?php echo $customType === 'number' ? 'number' : ($customType === 'date' ? 'date' : ($customType === 'url' ? 'url' : 'text')); ?>"
                                       class="smg-custom-value-input smg-input"
                                       data-property="<?php echo esc_attr($propName); ?>"
                                       value="<?php echo esc_attr($customValue); ?>"
                                       placeholder="<?php echo esc_attr($this->getCustomPlaceholder($customType)); ?>"
                                       <?php echo $customType === 'number' ? 'step="any"' : ''; ?>>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($propDef['auto'])): ?>
                                <?php 
                                $autoSource = !empty($propDef['auto_integration']) ? $propDef['auto_integration'] : $propDef['auto'];
                                // For content-based auto fields, show a more descriptive label
                                if ($propDef['auto'] === 'post_content' && !empty($propDef['auto_description'])) {
                                    $autoSource = __('content', 'schema-markup-generator');
                                }
                                $autoDescription = $propDef['auto_description'] ?? '';
                                ?>
                                <span class="smg-auto-badge-wrapper">
                                    <span class="smg-auto-badge-integration">
                                        <span class="dashicons dashicons-yes-alt"></span>
                                        <span class="smg-auto-label"><?php esc_html_e('Auto:', 'schema-markup-generator'); ?></span>
                                        <span class="smg-auto-source"><?php echo esc_html($autoSource); ?></span>
                                    </span>
                                    <?php if ($autoDescription): ?>
                                    <span class="smg-auto-tooltip smg-auto-tooltip-right">
                                        <?php echo esc_html($autoDescription); ?>
                                    </span>
                                    <?php endif; ?>
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

        <!-- Property Details Modal -->
        <div id="smg-property-modal" class="smg-modal" style="display: none;">
            <div class="smg-modal-overlay"></div>
            <div class="smg-modal-content">
                <button type="button" class="smg-modal-close">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
                <h3 class="smg-modal-title"></h3>
                <div class="smg-modal-body">
                    <div class="smg-modal-description"></div>
                    <div class="smg-modal-examples">
                        <strong><?php esc_html_e('Examples:', 'schema-markup-generator'); ?></strong>
                        <ul class="smg-examples-list"></ul>
                    </div>
                </div>
                <div class="smg-modal-footer">
                    <a class="smg-modal-link button button-secondary" href="#" target="_blank" rel="noopener">
                        <span class="dashicons dashicons-external"></span>
                        <?php esc_html_e('View on schema.org', 'schema-markup-generator'); ?>
                    </a>
                </div>
            </div>
        </div>
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

    /**
     * Get placeholder text for custom value input
     */
    private function getCustomPlaceholder(string $type): string
    {
        return match ($type) {
            'text' => __('Enter text value...', 'schema-markup-generator'),
            'number' => __('e.g. 5, 4.5, 100', 'schema-markup-generator'),
            'date' => __('YYYY-MM-DD', 'schema-markup-generator'),
            'url' => __('https://...', 'schema-markup-generator'),
            default => '',
        };
    }
}

