<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Admin;

use Metodo\SchemaMarkupGenerator\Schema\SchemaFactory;
use Metodo\SchemaMarkupGenerator\Schema\SchemaRenderer;
use Metodo\SchemaMarkupGenerator\Discovery\CustomFieldDiscovery;
use Metodo\SchemaMarkupGenerator\Discovery\TaxonomyDiscovery;
use WP_Post;

/**
 * Meta Box
 *
 * Per-post schema configuration with field overrides and preview modal.
 *
 * @package Metodo\SchemaMarkupGenerator\Admin
 * @author  Michele Marri <plugins@metodo.dev>
 */
class MetaBox
{
    private SchemaFactory $schemaFactory;
    private SchemaRenderer $schemaRenderer;
    private ?CustomFieldDiscovery $customFieldDiscovery = null;
    private ?TaxonomyDiscovery $taxonomyDiscovery = null;

    public function __construct(SchemaFactory $schemaFactory, SchemaRenderer $schemaRenderer)
    {
        $this->schemaFactory = $schemaFactory;
        $this->schemaRenderer = $schemaRenderer;
    }

    /**
     * Set discovery services (injected from Plugin)
     */
    public function setDiscoveryServices(
        CustomFieldDiscovery $customFieldDiscovery,
        TaxonomyDiscovery $taxonomyDiscovery
    ): void {
        $this->customFieldDiscovery = $customFieldDiscovery;
        $this->taxonomyDiscovery = $taxonomyDiscovery;
    }

    /**
     * Register meta box for all public post types
     */
    public function register(): void
    {
        $postTypes = get_post_types(['public' => true], 'names');
        unset($postTypes['attachment']);

        foreach ($postTypes as $postType) {
            add_meta_box(
                'smg_schema_meta_box',
                __('Schema Markup', 'schema-markup-generator'),
                [$this, 'render'],
                $postType,
                'normal',
                'default'
            );
        }
    }

    /**
     * Render meta box content
     */
    public function render(WP_Post $post): void
    {
        wp_nonce_field('smg_meta_box', 'smg_meta_box_nonce');

        $disableSchema = get_post_meta($post->ID, '_smg_disable_schema', true);
        $overrideType = get_post_meta($post->ID, '_smg_schema_type', true);
        $customOverrides = get_post_meta($post->ID, '_smg_field_overrides', true) ?: [];

        // Get configured schema type for this post type
        $mappings = get_option('smg_post_type_mappings', []);
        $defaultType = $mappings[$post->post_type] ?? '';
        $currentType = $overrideType ?: $defaultType;

        $schemaTypes = $this->schemaFactory->getTypes();

        ?>
        <div class="smg-meta-box" data-post-id="<?php echo esc_attr((string) $post->ID); ?>" data-post-type="<?php echo esc_attr($post->post_type); ?>">
            <!-- Disable Schema -->
            <div class="smg-meta-box-section">
                <label class="smg-checkbox-label">
                    <input type="checkbox"
                           name="smg_disable_schema"
                           value="1"
                           <?php checked($disableSchema, '1'); ?>>
                    <?php esc_html_e('Disable schema markup for this post', 'schema-markup-generator'); ?>
                </label>
            </div>

            <!-- Schema Type Override -->
            <div class="smg-meta-box-section">
                <label for="smg_schema_type" class="smg-field-label">
                    <?php esc_html_e('Schema Type', 'schema-markup-generator'); ?>
                </label>
                <select name="smg_schema_type" id="smg_schema_type" class="smg-select smg-schema-type-select">
                    <option value="">
                        <?php
                        if ($defaultType) {
                            printf(
                                /* translators: %s: schema type */
                                esc_html__('Use default (%s)', 'schema-markup-generator'),
                                esc_html($defaultType)
                            );
                        } else {
                            esc_html_e('— No Schema —', 'schema-markup-generator');
                        }
                        ?>
                    </option>
                    <?php foreach ($schemaTypes as $type => $label): ?>
                        <option value="<?php echo esc_attr($type); ?>" <?php selected($overrideType, $type); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description">
                    <?php esc_html_e('Override the default schema type for this post.', 'schema-markup-generator'); ?>
                </p>
            </div>

            <!-- Field Overrides Section -->
            <div class="smg-meta-box-section smg-field-overrides-section" <?php echo empty($currentType) ? 'style="display:none;"' : ''; ?>>
                <div class="smg-meta-box-header">
                    <div class="smg-meta-box-title">
                        <span class="dashicons dashicons-edit"></span>
                        <?php esc_html_e('Field Overrides', 'schema-markup-generator'); ?>
                    </div>
                    <button type="button" class="smg-toggle-overrides smg-btn smg-btn-sm smg-btn-ghost" aria-expanded="false">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                        <span class="smg-toggle-text"><?php esc_html_e('Show', 'schema-markup-generator'); ?></span>
                        </button>
                </div>
                <p class="smg-field-description">
                    <?php esc_html_e('Override individual field values for this post. Leave empty to use global mappings.', 'schema-markup-generator'); ?>
                </p>
                
                <div class="smg-field-overrides-container" style="display: none;">
                    <div class="smg-field-overrides-loading">
                        <span class="dashicons dashicons-update smg-spin"></span>
                        <?php esc_html_e('Loading fields...', 'schema-markup-generator'); ?>
                    </div>
                    <div class="smg-field-overrides-content"></div>
                </div>
            </div>

            <!-- Preview Section -->
            <div class="smg-meta-box-section">
                <div class="smg-meta-box-header">
                    <div class="smg-meta-box-title">
                        <span class="dashicons dashicons-visibility"></span>
                        <?php esc_html_e('Schema Preview', 'schema-markup-generator'); ?>
                    </div>
                    <div class="smg-meta-box-actions">
                        <?php if ($post->post_status === 'publish'): ?>
                        <button type="button" class="smg-btn smg-btn-sm smg-btn-secondary smg-open-preview-modal">
                            <span class="dashicons dashicons-fullscreen-alt"></span>
                            <?php esc_html_e('Preview', 'schema-markup-generator'); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                    <?php if ($post->post_status === 'publish'): ?>
                    <div class="smg-preview-mini">
                        <pre class="smg-schema-preview-mini" id="smg-schema-preview-mini"><?php
                        echo esc_html($this->schemaRenderer->getJsonForPost($post->ID));
                        ?></pre>
                    </div>
                    
                    <div class="smg-test-links">
                        <a href="<?php echo esc_url('https://search.google.com/test/rich-results?url=' . urlencode(get_permalink($post))); ?>"
                           target="_blank"
                           rel="noopener"
                           class="smg-btn smg-btn-sm smg-btn-ghost">
                            <span class="dashicons dashicons-external"></span>
                            <?php esc_html_e('Rich Results Test', 'schema-markup-generator'); ?>
                        </a>
                        <a href="<?php echo esc_url('https://validator.schema.org/?url=' . urlencode(get_permalink($post))); ?>"
                           target="_blank"
                           rel="noopener"
                           class="smg-btn smg-btn-sm smg-btn-ghost">
                            <span class="dashicons dashicons-external"></span>
                            <?php esc_html_e('Schema.org Validator', 'schema-markup-generator'); ?>
                        </a>
                    </div>
                    <?php else: ?>
                        <p class="smg-preview-notice">
                            <span class="dashicons dashicons-info"></span>
                            <?php esc_html_e('Publish this post to see the schema preview.', 'schema-markup-generator'); ?>
                        </p>
                    <?php endif; ?>
                </div>

            <input type="hidden" name="smg_post_id" value="<?php echo esc_attr((string) $post->ID); ?>">
            <input type="hidden" name="smg_current_schema_type" id="smg_current_schema_type" value="<?php echo esc_attr($currentType); ?>">
            <input type="hidden" name="smg_field_overrides_json" id="smg_field_overrides_json" value="<?php echo esc_attr(wp_json_encode($customOverrides)); ?>">
        </div>

        <!-- Preview Modal -->
        <div id="smg-preview-modal" class="smg-modal" style="display: none;">
            <div class="smg-modal-overlay"></div>
            <div class="smg-modal-content smg-modal-lg">
                <button type="button" class="smg-modal-close">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
                <h3 class="smg-modal-title">
                    <span class="dashicons dashicons-editor-code"></span>
                    <?php esc_html_e('Schema Preview', 'schema-markup-generator'); ?>
                </h3>
                <div class="smg-modal-body">
                    <div class="smg-preview-modal-actions">
                        <button type="button" class="smg-btn smg-btn-sm smg-btn-secondary smg-refresh-preview">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Refresh', 'schema-markup-generator'); ?>
                        </button>
                        <button type="button" class="smg-btn smg-btn-sm smg-btn-secondary smg-copy-schema">
                            <span class="dashicons dashicons-admin-page"></span>
                            <?php esc_html_e('Copy', 'schema-markup-generator'); ?>
                        </button>
                    </div>
                    <div class="smg-code-preview">
                        <pre class="smg-schema-preview smg-schema-preview-modal"></pre>
                    </div>
                    <div class="smg-validation-status" id="smg-validation-status"></div>
                </div>
                <div class="smg-modal-footer">
                    <a href="<?php echo esc_url('https://search.google.com/test/rich-results?url=' . urlencode(get_permalink($post))); ?>"
                       target="_blank"
                       rel="noopener"
                       class="smg-btn smg-btn-sm smg-btn-secondary">
                        <span class="dashicons dashicons-external"></span>
                        <?php esc_html_e('Google Rich Results Test', 'schema-markup-generator'); ?>
                    </a>
                    <a href="<?php echo esc_url('https://validator.schema.org/?url=' . urlencode(get_permalink($post))); ?>"
                       target="_blank"
                       rel="noopener"
                       class="smg-btn smg-btn-sm smg-btn-secondary">
                        <span class="dashicons dashicons-external"></span>
                        <?php esc_html_e('Schema.org Validator', 'schema-markup-generator'); ?>
                    </a>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Save meta box data
     */
    public function save(int $postId, WP_Post $post): void
    {
        // Security checks
        if (!isset($_POST['smg_meta_box_nonce']) ||
            !wp_verify_nonce($_POST['smg_meta_box_nonce'], 'smg_meta_box')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        // Save disable schema
        if (isset($_POST['smg_disable_schema'])) {
            update_post_meta($postId, '_smg_disable_schema', '1');
        } else {
            delete_post_meta($postId, '_smg_disable_schema');
        }

        // Save schema type override
        if (!empty($_POST['smg_schema_type'])) {
            update_post_meta($postId, '_smg_schema_type', sanitize_text_field($_POST['smg_schema_type']));
        } else {
            delete_post_meta($postId, '_smg_schema_type');
        }

        // Save field overrides
        if (!empty($_POST['smg_field_overrides_json'])) {
            $overridesJson = wp_unslash($_POST['smg_field_overrides_json']);
            $overrides = json_decode($overridesJson, true);
            
            if (is_array($overrides) && !empty($overrides)) {
                // Sanitize and filter empty values
                $sanitizedOverrides = [];
                foreach ($overrides as $property => $override) {
                    if (!is_array($override)) {
                        continue;
                    }
                    
                    $type = sanitize_text_field($override['type'] ?? 'auto');
                    $value = '';
                    
                    if ($type === 'custom' && isset($override['value'])) {
                        $value = sanitize_textarea_field($override['value']);
                    } elseif ($type === 'field' && isset($override['value'])) {
                        $value = sanitize_text_field($override['value']);
                    }
                    
                    // Only save non-auto overrides with values
                    if ($type !== 'auto' && !empty($value)) {
                        $sanitizedOverrides[sanitize_key($property)] = [
                            'type' => $type,
                            'value' => $value,
                        ];
                    }
                }
                
                if (!empty($sanitizedOverrides)) {
                    update_post_meta($postId, '_smg_field_overrides', $sanitizedOverrides);
                } else {
                    delete_post_meta($postId, '_smg_field_overrides');
                }
            } else {
                delete_post_meta($postId, '_smg_field_overrides');
            }
        } else {
            delete_post_meta($postId, '_smg_field_overrides');
        }

        // Clear cache for this post
        $this->schemaRenderer->clearCache($postId);
    }
}
