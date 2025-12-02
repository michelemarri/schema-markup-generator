<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Admin;

use Metodo\SchemaMarkupGenerator\Schema\SchemaFactory;
use Metodo\SchemaMarkupGenerator\Schema\SchemaRenderer;
use WP_Post;

/**
 * Meta Box
 *
 * Per-post schema configuration and preview.
 *
 * @package Metodo\SchemaMarkupGenerator\Admin
 * @author  Michele Marri <plugins@metodo.dev>
 */
class MetaBox
{
    private SchemaFactory $schemaFactory;
    private SchemaRenderer $schemaRenderer;

    public function __construct(SchemaFactory $schemaFactory, SchemaRenderer $schemaRenderer)
    {
        $this->schemaFactory = $schemaFactory;
        $this->schemaRenderer = $schemaRenderer;
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
        $customMapping = get_post_meta($post->ID, '_smg_field_mapping', true) ?: [];

        // Get configured schema type for this post type
        $mappings = get_option('smg_post_type_mappings', []);
        $defaultType = $mappings[$post->post_type] ?? '';
        $currentType = $overrideType ?: $defaultType;

        $schemaTypes = $this->schemaFactory->getTypes();

        ?>
        <div class="smg-meta-box">
            <div class="smg-meta-box-section">
                <label class="smg-checkbox-label">
                    <input type="checkbox"
                           name="smg_disable_schema"
                           value="1"
                           <?php checked($disableSchema, '1'); ?>>
                    <?php esc_html_e('Disable schema markup for this post', 'schema-markup-generator'); ?>
                </label>
            </div>

            <div class="smg-meta-box-section">
                <label for="smg_schema_type">
                    <?php esc_html_e('Schema Type', 'schema-markup-generator'); ?>
                </label>
                <select name="smg_schema_type" id="smg_schema_type" class="widefat">
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

            <div class="smg-meta-box-section smg-preview-section">
                <div class="smg-preview-header">
                    <h4><?php esc_html_e('Schema Preview', 'schema-markup-generator'); ?></h4>
                    <div class="smg-preview-actions">
                        <button type="button" class="button smg-refresh-preview">
                            <span class="dashicons dashicons-update"></span>
                            <?php esc_html_e('Refresh', 'schema-markup-generator'); ?>
                        </button>
                        <button type="button" class="button smg-copy-schema">
                            <span class="dashicons dashicons-admin-page"></span>
                            <?php esc_html_e('Copy', 'schema-markup-generator'); ?>
                        </button>
                    </div>
                </div>

                <div class="smg-preview-content">
                    <?php if ($post->post_status === 'publish'): ?>
                        <pre class="smg-schema-preview" id="smg-schema-preview"><?php
                        echo esc_html($this->schemaRenderer->getJsonForPost($post->ID));
                        ?></pre>
                    <?php else: ?>
                        <p class="smg-preview-notice">
                            <span class="dashicons dashicons-info"></span>
                            <?php esc_html_e('Publish this post to see the schema preview.', 'schema-markup-generator'); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="smg-validation-status" id="smg-validation-status">
                    <!-- Populated via AJAX -->
                </div>

                <div class="smg-test-links">
                    <a href="<?php echo esc_url('https://search.google.com/test/rich-results?url=' . urlencode(get_permalink($post))); ?>"
                       target="_blank"
                       rel="noopener"
                       class="button">
                        <span class="dashicons dashicons-external"></span>
                        <?php esc_html_e('Google Rich Results Test', 'schema-markup-generator'); ?>
                    </a>
                    <a href="<?php echo esc_url('https://validator.schema.org/?url=' . urlencode(get_permalink($post))); ?>"
                       target="_blank"
                       rel="noopener"
                       class="button">
                        <span class="dashicons dashicons-external"></span>
                        <?php esc_html_e('Schema.org Validator', 'schema-markup-generator'); ?>
                    </a>
                </div>
            </div>

            <input type="hidden" name="smg_post_id" value="<?php echo esc_attr((string) $post->ID); ?>">
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

        // Clear cache for this post
        $this->schemaRenderer->clearCache($postId);
    }
}

