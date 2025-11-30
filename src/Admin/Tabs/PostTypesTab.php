<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Admin\Tabs;

use flavor\SchemaMarkupGenerator\Discovery\PostTypeDiscovery;
use flavor\SchemaMarkupGenerator\Discovery\CustomFieldDiscovery;
use flavor\SchemaMarkupGenerator\Discovery\TaxonomyDiscovery;
use flavor\SchemaMarkupGenerator\Integration\ACFIntegration;
use flavor\SchemaMarkupGenerator\Schema\SchemaFactory;

/**
 * Post Types Tab
 *
 * Configure schema mappings for post types.
 *
 * @package flavor\SchemaMarkupGenerator\Admin\Tabs
 * @author  Michele Marri <info@metodo.dev>
 */
class PostTypesTab extends AbstractTab
{
    private PostTypeDiscovery $postTypeDiscovery;
    private CustomFieldDiscovery $customFieldDiscovery;
    private TaxonomyDiscovery $taxonomyDiscovery;
    private ACFIntegration $acfIntegration;
    private SchemaFactory $schemaFactory;

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
    }

    public function getTitle(): string
    {
        return __('Post Types', 'schema-markup-generator');
    }

    public function getIcon(): string
    {
        return 'dashicons-admin-post';
    }

    public function getSettingsGroup(): string
    {
        return 'smg_post_types';
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
        $mappings = get_option('smg_post_type_mappings', []);
        $fieldMappings = get_option('smg_field_mappings', []);
        $schemaTypes = $this->schemaFactory->getTypesGrouped();

        ?>
        <div class="smg-tab-panel" id="tab-post-types">
            <?php $this->renderSection(
                __('Post Type Schema Mapping', 'schema-markup-generator'),
                __('Assign a schema type to each post type. The schema will be automatically generated based on your content.', 'schema-markup-generator')
            ); ?>

            <div class="smg-post-types-list">
                <?php foreach ($postTypes as $postType => $postTypeObj): ?>
                    <?php
                    $currentSchema = $mappings[$postType] ?? '';
                    $postTypeFields = $this->customFieldDiscovery->getFieldsForPostType($postType);
                    $currentFieldMapping = $fieldMappings[$postType] ?? [];
                    ?>
                    <div class="smg-post-type-card<?php echo $currentSchema ? ' smg-mapped' : ''; ?>" data-post-type="<?php echo esc_attr($postType); ?>">
                        <div class="smg-post-type-header">
                            <div class="smg-post-type-info">
                                <span class="dashicons <?php echo esc_attr($postTypeObj->menu_icon ?? 'dashicons-admin-post'); ?>"></span>
                                <h3><?php echo esc_html($postTypeObj->labels->singular_name); ?></h3>
                                <code><?php echo esc_html($postType); ?></code>
                            </div>
                            <div class="smg-post-type-schema">
                                <select name="smg_post_type_mappings[<?php echo esc_attr($postType); ?>]"
                                        class="smg-schema-select"
                                        data-post-type="<?php echo esc_attr($postType); ?>">
                                    <option value=""><?php esc_html_e('— No Schema —', 'schema-markup-generator'); ?></option>
                                    <?php foreach ($schemaTypes as $group => $types): ?>
                                        <optgroup label="<?php echo esc_attr($group); ?>">
                                            <?php foreach ($types as $type => $label): ?>
                                                <option value="<?php echo esc_attr($type); ?>"
                                                        <?php selected($currentSchema, $type); ?>>
                                                    <?php echo esc_html($label); ?>
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
                                                <tr class="smg-mapping-row">
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
                                    <p class="smg-notice">
                                        <span class="dashicons dashicons-info"></span>
                                        <?php esc_html_e('Select a schema type above to configure field mappings.', 'schema-markup-generator'); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($this->acfIntegration->isAvailable()): ?>
                <div class="smg-acf-notice">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e('Advanced Custom Fields detected. ACF fields are available for mapping.', 'schema-markup-generator'); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

