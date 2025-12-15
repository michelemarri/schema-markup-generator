<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Admin;

use Metodo\SchemaMarkupGenerator\Schema\SchemaFactory;
use Metodo\SchemaMarkupGenerator\Discovery\CustomFieldDiscovery;
use Metodo\SchemaMarkupGenerator\Discovery\TaxonomyDiscovery;

/**
 * MetaBox Properties Handler
 *
 * Handles AJAX requests for loading schema properties in the post metabox.
 * Provides field override UI with selector and custom input options.
 *
 * @package Metodo\SchemaMarkupGenerator\Admin
 * @author  Michele Marri <plugins@metodo.dev>
 */
class MetaBoxPropertiesHandler
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
     * Handle AJAX request for metabox schema properties
     */
    public function handle(): void
    {
        check_ajax_referer('smg_admin_nonce', 'nonce');

        $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $schemaType = isset($_POST['schema_type']) ? sanitize_text_field($_POST['schema_type']) : '';

        if (!$postId || !current_user_can('edit_post', $postId)) {
            wp_send_json_error(['message' => __('Permission denied', 'schema-markup-generator')]);
        }

        $post = get_post($postId);
        if (!$post) {
            wp_send_json_error(['message' => __('Post not found', 'schema-markup-generator')]);
        }

        // If no schema type, return empty message
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

        // Get available fields for this post type
        $fieldGroups = $this->customFieldDiscovery->getFieldsGroupedBySource($post->post_type);

        // Get global field mappings for this post type
        $globalMappings = get_option('smg_field_mappings', []);
        $globalFieldMapping = $globalMappings[$post->post_type] ?? [];

        // Get current per-post overrides
        $currentOverrides = get_post_meta($postId, '_smg_field_overrides', true) ?: [];

        // Render the field overrides UI
        $html = $this->renderFieldOverrides(
            $post,
            $schemaProps,
            $fieldGroups,
            $globalFieldMapping,
            $currentOverrides
        );

        wp_send_json_success([
            'html' => $html,
            'properties' => array_keys($schemaProps),
            'schema_type' => $schemaType,
        ]);
    }

    /**
     * Render the field overrides UI
     */
    private function renderFieldOverrides(
        \WP_Post $post,
        array $schemaProps,
        array $fieldGroups,
        array $globalFieldMapping,
        array $currentOverrides
    ): string {
        ob_start();
        ?>
        <div class="smg-field-overrides-list">
            <?php foreach ($schemaProps as $propName => $propDef): ?>
                <?php
                $override = $currentOverrides[$propName] ?? null;
                $overrideType = $override['type'] ?? 'auto';
                $overrideValue = $override['value'] ?? '';
                $globalMapping = $globalFieldMapping[$propName] ?? '';
                $hasAuto = !empty($propDef['auto']);
                ?>
                <div class="smg-field-override-row" data-property="<?php echo esc_attr($propName); ?>">
                    <div class="smg-field-override-header">
                        <div class="smg-field-override-info">
                            <span class="smg-field-override-name">
                                <?php echo esc_html($propName); ?>
                                <?php if ($hasAuto): ?>
                                    <?php 
                                    $autoSource = !empty($propDef['auto_integration']) ? $propDef['auto_integration'] : $propDef['auto'];
                                    $autoDescription = $propDef['auto_description'] ?? '';
                                    ?>
                                    <span class="smg-auto-badge-wrapper">
                                        <span class="smg-auto-badge-integration">
                                            <span class="dashicons dashicons-yes-alt"></span>
                                            <span class="smg-auto-label"><?php esc_html_e('Auto:', 'schema-markup-generator'); ?></span>
                                            <span class="smg-auto-source"><?php echo esc_html($autoSource); ?></span>
                                        </span>
                                        <?php if ($autoDescription): ?>
                                        <span class="smg-auto-tooltip">
                                            <?php echo esc_html($autoDescription); ?>
                                        </span>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </span>
                            <?php if (!empty($propDef['description'])): ?>
                            <span class="smg-field-override-desc"><?php echo esc_html($propDef['description']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="smg-field-override-controls">
                        <div class="smg-field-override-type">
                            <label class="smg-radio-label">
                                <input type="radio" 
                                       name="smg_override_type_<?php echo esc_attr($propName); ?>" 
                                       value="auto" 
                                       class="smg-override-type-radio"
                                       <?php checked($overrideType, 'auto'); ?>>
                                <span><?php echo $hasAuto || $globalMapping ? esc_html__('Use default', 'schema-markup-generator') : esc_html__('None', 'schema-markup-generator'); ?></span>
                                <?php if ($globalMapping): ?>
                                <span class="smg-global-mapping-hint" title="<?php echo esc_attr(sprintf(__('Global mapping: %s', 'schema-markup-generator'), $globalMapping)); ?>">
                                    (<?php echo esc_html($globalMapping); ?>)
                                </span>
                                <?php endif; ?>
                            </label>
                            <label class="smg-radio-label">
                                <input type="radio" 
                                       name="smg_override_type_<?php echo esc_attr($propName); ?>" 
                                       value="field" 
                                       class="smg-override-type-radio"
                                       <?php checked($overrideType, 'field'); ?>>
                                <span><?php esc_html_e('Select field', 'schema-markup-generator'); ?></span>
                            </label>
                            <label class="smg-radio-label">
                                <input type="radio" 
                                       name="smg_override_type_<?php echo esc_attr($propName); ?>" 
                                       value="custom" 
                                       class="smg-override-type-radio"
                                       <?php checked($overrideType, 'custom'); ?>>
                                <span><?php esc_html_e('Custom value', 'schema-markup-generator'); ?></span>
                            </label>
                        </div>
                        
                        <div class="smg-field-override-value">
                            <!-- Field selector (shown when type=field) -->
                            <div class="smg-override-field-select" <?php echo $overrideType !== 'field' ? 'style="display:none;"' : ''; ?>>
                                <select class="smg-select smg-override-select" data-property="<?php echo esc_attr($propName); ?>">
                                    <option value=""><?php esc_html_e('— Select Field —', 'schema-markup-generator'); ?></option>
                                    <optgroup label="<?php esc_attr_e('Post Fields', 'schema-markup-generator'); ?>">
                                        <option value="post_title" <?php selected($overrideType === 'field' ? $overrideValue : '', 'post_title'); ?>>
                                            <?php esc_html_e('Post Title', 'schema-markup-generator'); ?>
                                        </option>
                                        <option value="post_excerpt" <?php selected($overrideType === 'field' ? $overrideValue : '', 'post_excerpt'); ?>>
                                            <?php esc_html_e('Post Excerpt', 'schema-markup-generator'); ?>
                                        </option>
                                        <option value="post_content" <?php selected($overrideType === 'field' ? $overrideValue : '', 'post_content'); ?>>
                                            <?php esc_html_e('Post Content', 'schema-markup-generator'); ?>
                                        </option>
                                        <option value="featured_image" <?php selected($overrideType === 'field' ? $overrideValue : '', 'featured_image'); ?>>
                                            <?php esc_html_e('Featured Image', 'schema-markup-generator'); ?>
                                        </option>
                                        <option value="author" <?php selected($overrideType === 'field' ? $overrideValue : '', 'author'); ?>>
                                            <?php esc_html_e('Author', 'schema-markup-generator'); ?>
                                        </option>
                                    </optgroup>
                                    <optgroup label="<?php esc_attr_e('Website', 'schema-markup-generator'); ?>">
                                        <option value="site_name" <?php selected($overrideType === 'field' ? $overrideValue : '', 'site_name'); ?>>
                                            <?php esc_html_e('Site Name', 'schema-markup-generator'); ?>
                                        </option>
                                        <option value="site_url" <?php selected($overrideType === 'field' ? $overrideValue : '', 'site_url'); ?>>
                                            <?php esc_html_e('Site URL', 'schema-markup-generator'); ?>
                                        </option>
                                        <option value="site_wordpress_version" <?php selected($overrideType === 'field' ? $overrideValue : '', 'site_wordpress_version'); ?>>
                                            <?php esc_html_e('WordPress Version', 'schema-markup-generator'); ?>
                                        </option>
                                        <option value="site_theme_version" <?php selected($overrideType === 'field' ? $overrideValue : '', 'site_theme_version'); ?>>
                                            <?php esc_html_e('Theme Version', 'schema-markup-generator'); ?>
                                        </option>
                                    </optgroup>
                                    <?php 
                                    foreach ($fieldGroups as $groupKey => $group): 
                                        if (empty($group['fields'])) continue;
                                    ?>
                                        <optgroup label="<?php echo esc_attr($group['label']); ?>">
                                            <?php foreach ($group['fields'] as $field): ?>
                                                <option value="<?php echo esc_attr($field['key']); ?>"
                                                        <?php selected($overrideType === 'field' ? $overrideValue : '', $field['key']); ?>>
                                                    <?php echo esc_html($field['label']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                    <?php
                                    $taxonomies = $this->taxonomyDiscovery->getTaxonomiesForPostType($post->post_type);
                                    if (!empty($taxonomies)):
                                    ?>
                                        <optgroup label="<?php esc_attr_e('Taxonomies', 'schema-markup-generator'); ?>">
                                            <?php foreach ($taxonomies as $taxSlug => $taxonomy): ?>
                                                <option value="taxonomy:<?php echo esc_attr($taxSlug); ?>"
                                                        <?php selected($overrideType === 'field' ? $overrideValue : '', 'taxonomy:' . $taxSlug); ?>>
                                                    <?php echo esc_html($taxonomy->labels->singular_name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endif; ?>
                                    <?php
                                    // Custom value options for metabox override
                                    $isFieldCustom = $overrideType === 'field' && str_starts_with($overrideValue, 'custom:');
                                    $fieldCustomType = '';
                                    $fieldCustomValue = '';
                                    if ($isFieldCustom) {
                                        $parts = explode(':', $overrideValue, 3);
                                        $fieldCustomType = $parts[1] ?? '';
                                        $fieldCustomValue = $parts[2] ?? '';
                                    }
                                    ?>
                                    <optgroup label="<?php esc_attr_e('Custom Value', 'schema-markup-generator'); ?>">
                                        <option value="custom:text:" 
                                                data-custom-type="text"
                                                <?php selected($isFieldCustom && $fieldCustomType === 'text', true); ?>
                                                <?php echo $isFieldCustom && $fieldCustomType === 'text' ? 'data-custom-value="' . esc_attr($fieldCustomValue) . '"' : ''; ?>>
                                            <?php esc_html_e('✏️ Custom Text', 'schema-markup-generator'); ?>
                                        </option>
                                        <option value="custom:number:" 
                                                data-custom-type="number"
                                                <?php selected($isFieldCustom && $fieldCustomType === 'number', true); ?>
                                                <?php echo $isFieldCustom && $fieldCustomType === 'number' ? 'data-custom-value="' . esc_attr($fieldCustomValue) . '"' : ''; ?>>
                                            <?php esc_html_e('🔢 Custom Number', 'schema-markup-generator'); ?>
                                        </option>
                                        <option value="custom:date:" 
                                                data-custom-type="date"
                                                <?php selected($isFieldCustom && $fieldCustomType === 'date', true); ?>
                                                <?php echo $isFieldCustom && $fieldCustomType === 'date' ? 'data-custom-value="' . esc_attr($fieldCustomValue) . '"' : ''; ?>>
                                            <?php esc_html_e('📅 Custom Date', 'schema-markup-generator'); ?>
                                        </option>
                                        <option value="custom:url:" 
                                                data-custom-type="url"
                                                <?php selected($isFieldCustom && $fieldCustomType === 'url', true); ?>
                                                <?php echo $isFieldCustom && $fieldCustomType === 'url' ? 'data-custom-value="' . esc_attr($fieldCustomValue) . '"' : ''; ?>>
                                            <?php esc_html_e('🔗 Custom URL', 'schema-markup-generator'); ?>
                                        </option>
                                        <option value="custom:boolean:true" 
                                                data-custom-type="boolean"
                                                <?php selected($isFieldCustom && $fieldCustomType === 'boolean' && $fieldCustomValue === 'true', true); ?>>
                                            <?php esc_html_e('✓ Boolean: True', 'schema-markup-generator'); ?>
                                        </option>
                                        <option value="custom:boolean:false" 
                                                data-custom-type="boolean"
                                                <?php selected($isFieldCustom && $fieldCustomType === 'boolean' && $fieldCustomValue === 'false', true); ?>>
                                            <?php esc_html_e('✗ Boolean: False', 'schema-markup-generator'); ?>
                                        </option>
                                    </optgroup>
                                </select>
                                <?php if ($isFieldCustom && in_array($fieldCustomType, ['text', 'number', 'date', 'url'])): ?>
                                <div class="smg-custom-value-wrapper" style="margin-top: 8px;">
                                    <input type="<?php echo $fieldCustomType === 'number' ? 'number' : ($fieldCustomType === 'date' ? 'date' : ($fieldCustomType === 'url' ? 'url' : 'text')); ?>"
                                           class="smg-custom-value-input smg-input"
                                           data-property="<?php echo esc_attr($propName); ?>"
                                           value="<?php echo esc_attr($fieldCustomValue); ?>"
                                           placeholder="<?php echo esc_attr($this->getCustomPlaceholder($fieldCustomType)); ?>"
                                           <?php echo $fieldCustomType === 'number' ? 'step="any"' : ''; ?>>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Custom value input (shown when type=custom) -->
                            <div class="smg-override-custom-input" <?php echo $overrideType !== 'custom' ? 'style="display:none;"' : ''; ?>>
                                <?php if (in_array($propDef['type'] ?? 'text', ['text', 'url', 'email', 'number', 'datetime'])): ?>
                                <input type="<?php echo $propDef['type'] === 'url' ? 'url' : ($propDef['type'] === 'email' ? 'email' : ($propDef['type'] === 'number' ? 'number' : 'text')); ?>"
                                       class="smg-input smg-override-input"
                                       data-property="<?php echo esc_attr($propName); ?>"
                                       value="<?php echo esc_attr($overrideType === 'custom' ? $overrideValue : ''); ?>"
                                       placeholder="<?php echo esc_attr($propDef['example'] ?? ''); ?>">
                                <?php else: ?>
                                <textarea class="smg-textarea smg-override-textarea"
                                          data-property="<?php echo esc_attr($propName); ?>"
                                          rows="2"
                                          placeholder="<?php echo esc_attr($propDef['example'] ?? ''); ?>"><?php echo esc_textarea($overrideType === 'custom' ? $overrideValue : ''); ?></textarea>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
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
        <p class="smg-notice">
            <span class="dashicons dashicons-info"></span>
            <?php esc_html_e('Select a schema type to configure field overrides.', 'schema-markup-generator'); ?>
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
        <p class="smg-notice">
            <span class="dashicons dashicons-yes-alt"></span>
            <?php esc_html_e('This schema type is fully automatic and doesn\'t require field configuration.', 'schema-markup-generator'); ?>
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

