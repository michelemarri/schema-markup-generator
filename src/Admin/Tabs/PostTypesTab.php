<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Admin\Tabs;

use Metodo\SchemaMarkupGenerator\Discovery\PostTypeDiscovery;
use Metodo\SchemaMarkupGenerator\Discovery\CustomFieldDiscovery;
use Metodo\SchemaMarkupGenerator\Discovery\TaxonomyDiscovery;
use Metodo\SchemaMarkupGenerator\Discovery\SchemaRecommender;
use Metodo\SchemaMarkupGenerator\Integration\ACFIntegration;
use Metodo\SchemaMarkupGenerator\Schema\SchemaFactory;

/**
 * Post Types Tab
 *
 * Configure schema mappings for post types.
 *
 * @package Metodo\SchemaMarkupGenerator\Admin\Tabs
 * @author  Michele Marri <plugins@metodo.dev>
 */
class PostTypesTab extends AbstractTab
{
    private PostTypeDiscovery $postTypeDiscovery;
    private CustomFieldDiscovery $customFieldDiscovery;
    private TaxonomyDiscovery $taxonomyDiscovery;
    private ACFIntegration $acfIntegration;
    private SchemaFactory $schemaFactory;
    private SchemaRecommender $schemaRecommender;

    public function __construct(
        PostTypeDiscovery $postTypeDiscovery,
        CustomFieldDiscovery $customFieldDiscovery,
        TaxonomyDiscovery $taxonomyDiscovery,
        ACFIntegration $acfIntegration
    ) {
        $this->postTypeDiscovery = $postTypeDiscovery;
        $this->customFieldDiscovery = $customFieldDiscovery;
        $this->taxonomyDiscovery = $taxonomyDiscovery;
        $this->acfIntegration = $acfIntegration;
        $this->schemaFactory = new SchemaFactory();
        $this->schemaRecommender = new SchemaRecommender();
    }

    public function getTitle(): string
    {
        return __('Post Types', 'schema-markup-generator');
    }

    public function getIcon(): string
    {
        return 'dashicons-admin-post';
    }

    /**
     * Return empty string to disable the form wrapper and Save button
     * Mappings are auto-saved via AJAX
     */
    public function getSettingsGroup(): string
    {
        return '';
    }

    public function getRegisteredOptions(): array
    {
        return [
            'smg_post_type_mappings' => [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizePostTypeMappings'],
                'default' => [
                    'post' => 'Article',
                    'page' => 'WebPage',
                ],
            ],
            'smg_field_mappings' => [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeFieldMappings'],
                'default' => [],
            ],
        ];
    }

    /**
     * Sanitize post type mappings
     */
    public function sanitizePostTypeMappings(?array $input): array
    {
        if ($input === null) {
            return [];
        }

        $sanitized = [];
        foreach ($input as $postType => $schemaType) {
            $sanitized[sanitize_key($postType)] = sanitize_text_field($schemaType);
        }

        return $sanitized;
    }

    /**
     * Sanitize field mappings
     */
    public function sanitizeFieldMappings(?array $input): array
    {
        if ($input === null) {
            return [];
        }

        $sanitized = [];
        foreach ($input as $postType => $mappings) {
            if (!is_array($mappings)) {
                continue;
            }

            $sanitized[sanitize_key($postType)] = [];
            foreach ($mappings as $schemaProperty => $fieldKey) {
                $sanitized[sanitize_key($postType)][sanitize_key($schemaProperty)] = sanitize_text_field($fieldKey);
            }
        }

        return $sanitized;
    }

    public function render(): void
    {
        $postTypes = $this->postTypeDiscovery->getPostTypes();
        
        // Force fresh read (clear alloptions cache)
        wp_cache_delete('alloptions', 'options');
        
        $mappings = get_option('smg_post_type_mappings', []);
        $fieldMappings = get_option('smg_field_mappings', []);
        $schemaTypes = $this->schemaFactory->getTypesGrouped();

        ?>
        <div class="flex flex-col gap-6" id="tab-post-types">
            <?php $this->renderSection(
                __('Post Type Schema Mapping', 'schema-markup-generator'),
                __('Assign a schema type to each post type. The schema will be automatically generated based on your content.', 'schema-markup-generator')
            ); ?>

            <div class="flex flex-col gap-4">
                <?php foreach ($postTypes as $postType => $postTypeObj): ?>
                    <?php
                    $recommendedSchema = $this->schemaRecommender->getRecommendedSchema($postType);
                    $currentSchema = $mappings[$postType] ?? '';
                    // Auto-select recommended schema if no mapping exists
                    if (empty($currentSchema) && $recommendedSchema) {
                        $currentSchema = $recommendedSchema;
                    }
                    $fieldGroups = $this->customFieldDiscovery->getFieldsGroupedBySource($postType);
                    $currentFieldMapping = $fieldMappings[$postType] ?? [];
                    ?>
                    <div class="smg-post-type-card<?php echo $currentSchema ? ' smg-mapped' : ''; ?>" data-post-type="<?php echo esc_attr($postType); ?>">
                        <div class="smg-post-type-header">
                            <div class="smg-post-type-info">
                                <span class="dashicons <?php echo esc_attr($postTypeObj->menu_icon ?? 'dashicons-admin-post'); ?>"></span>
                                <h3><?php echo esc_html($postTypeObj->labels->singular_name); ?></h3>
                                <code><?php echo esc_html($postType); ?></code>
                                <button type="button" 
                                        class="smg-view-example-btn" 
                                        data-post-type="<?php echo esc_attr($postType); ?>"
                                        title="<?php esc_attr_e('View schema example from a random post', 'schema-markup-generator'); ?>">
                                    <span class="dashicons dashicons-visibility"></span>
                                </button>
                            </div>
                            <div class="smg-post-type-schema">
                                <select name="smg_post_type_mappings[<?php echo esc_attr($postType); ?>]"
                                        class="smg-select smg-schema-select"
                                        data-post-type="<?php echo esc_attr($postType); ?>"
                                        data-recommended="<?php echo esc_attr($recommendedSchema ?? ''); ?>">
                                    <option value=""><?php esc_html_e('— No Schema —', 'schema-markup-generator'); ?></option>
                                    <?php foreach ($schemaTypes as $group => $types): ?>
                                        <optgroup label="<?php echo esc_attr($group); ?>">
                                            <?php foreach ($types as $type => $label): ?>
                                                <?php $isRecommended = ($type === $recommendedSchema); ?>
                                                <option value="<?php echo esc_attr($type); ?>"
                                                        <?php selected($currentSchema, $type); ?>
                                                        <?php echo $isRecommended ? 'data-recommended="true"' : ''; ?>>
                                                    <?php echo esc_html($label); ?><?php if ($isRecommended): ?> — <?php esc_html_e('Recommended', 'schema-markup-generator'); ?><?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" class="smg-toggle-fields button button-secondary"
                                    aria-expanded="false">
                                <?php esc_html_e('Field Mapping', 'schema-markup-generator'); ?>
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </button>
                        </div>

                        <div class="smg-post-type-fields" style="display: none;">
                            <div class="smg-field-mappings">
                                <?php
                                // Get schema properties for current type
                                $schemaProps = [];
                                if ($currentSchema) {
                                    $schema = $this->schemaFactory->create($currentSchema);
                                    if ($schema) {
                                        $schemaProps = $schema->getPropertyDefinitions();
                                    }
                                }
                                ?>

                                <?php if (!empty($schemaProps)): ?>
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
                                                <tr>
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
                                                        <small class="text-gray-500"><?php echo esc_html($propDef['description'] ?? ''); ?></small>
                                                    </td>
                                                    <td>
                                                        <select name="smg_field_mappings[<?php echo esc_attr($postType); ?>][<?php echo esc_attr($propName); ?>]"
                                                                class="smg-select smg-field-select">
                                                            <option value=""><?php esc_html_e('— Select Field —', 'schema-markup-generator'); ?></option>
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
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($propDef['auto'])): ?>
                                                            <?php 
                                                            $autoTitle = $propDef['auto_description'] ?? __('Auto-populated from WordPress', 'schema-markup-generator');
                                                            $autoLabel = $propDef['auto'];
                                                            // For content-based auto fields, show a more descriptive label
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
                                <?php else: ?>
                                    <div class="smg-notice">
                                        <span class="dashicons dashicons-info"></span>
                                        <?php esc_html_e('Select a schema type above to configure field mappings.', 'schema-markup-generator'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

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

            <!-- Schema Example Modal -->
            <div id="smg-example-modal" class="smg-modal smg-modal-lg" style="display: none;">
                <div class="smg-modal-overlay"></div>
                <div class="smg-modal-content">
                    <button type="button" class="smg-modal-close">
                        <span class="dashicons dashicons-no-alt"></span>
                    </button>
                    <h3 class="smg-modal-title">
                        <span class="dashicons dashicons-editor-code"></span>
                        <?php esc_html_e('Schema Example', 'schema-markup-generator'); ?>
                    </h3>
                    <div class="smg-modal-body">
                        <div class="smg-example-info">
                            <span class="smg-example-post-title"></span>
                            <div class="smg-example-actions">
                                <a href="#" class="smg-example-edit-link" target="_blank" rel="noopener">
                                    <span class="dashicons dashicons-edit"></span>
                                    <?php esc_html_e('Edit Post', 'schema-markup-generator'); ?>
                                </a>
                                <a href="#" class="smg-example-view-link" target="_blank" rel="noopener">
                                    <span class="dashicons dashicons-external"></span>
                                    <?php esc_html_e('View Post', 'schema-markup-generator'); ?>
                                </a>
                            </div>
                        </div>
                        <div class="smg-code-preview">
                            <div class="smg-code-header">
                                <span class="smg-code-title">JSON-LD</span>
                                <div class="smg-code-actions">
                                    <button type="button" class="smg-copy-example smg-btn smg-btn-sm smg-btn-ghost">
                                        <span class="dashicons dashicons-admin-page"></span>
                                        <?php esc_html_e('Copy', 'schema-markup-generator'); ?>
                                    </button>
                                    <button type="button" class="smg-refresh-example smg-btn smg-btn-sm smg-btn-ghost">
                                        <span class="dashicons dashicons-update"></span>
                                        <?php esc_html_e('New Random', 'schema-markup-generator'); ?>
                                    </button>
                                </div>
                            </div>
                            <pre class="smg-schema-preview smg-example-schema"></pre>
                        </div>
                    </div>
                    <div class="smg-modal-footer">
                        <span class="smg-example-hint">
                            <span class="dashicons dashicons-info"></span>
                            <?php esc_html_e('This schema is generated from a random published post of this type.', 'schema-markup-generator'); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

