<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Admin\Tabs;

use Metodo\SchemaMarkupGenerator\Discovery\TaxonomyDiscovery;
use Metodo\SchemaMarkupGenerator\Schema\SchemaFactory;

/**
 * Taxonomies Tab
 *
 * Configure schema mappings for taxonomies.
 *
 * @package Metodo\SchemaMarkupGenerator\Admin\Tabs
 * @author  Michele Marri <plugins@metodo.dev>
 */
class TaxonomiesTab extends AbstractTab
{
    private TaxonomyDiscovery $taxonomyDiscovery;
    private SchemaFactory $schemaFactory;

    public function __construct(TaxonomyDiscovery $taxonomyDiscovery)
    {
        $this->taxonomyDiscovery = $taxonomyDiscovery;
        $this->schemaFactory = new SchemaFactory();
    }

    public function getTitle(): string
    {
        return __('Taxonomies', 'schema-markup-generator');
    }

    public function getIcon(): string
    {
        return 'dashicons-category';
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
            'smg_taxonomy_mappings' => [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeTaxonomyMappings'],
                'default' => [],
            ],
            'smg_taxonomy_field_mappings' => [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeFieldMappings'],
                'default' => [],
            ],
        ];
    }

    /**
     * Sanitize taxonomy mappings
     */
    public function sanitizeTaxonomyMappings(?array $input): array
    {
        if ($input === null) {
            return [];
        }

        $sanitized = [];
        foreach ($input as $taxonomy => $schemaType) {
            $sanitized[sanitize_key($taxonomy)] = sanitize_text_field($schemaType);
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
        foreach ($input as $taxonomy => $mappings) {
            if (!is_array($mappings)) {
                continue;
            }

            $sanitized[sanitize_key($taxonomy)] = [];
            foreach ($mappings as $schemaProperty => $fieldKey) {
                $sanitized[sanitize_key($taxonomy)][sanitize_key($schemaProperty)] = sanitize_text_field($fieldKey);
            }
        }

        return $sanitized;
    }

    /**
     * Get suggested schema for a taxonomy based on its slug/name
     */
    private function getSuggestedSchema(string $taxonomy, \WP_Taxonomy $taxonomyObj): string
    {
        $suggestions = [
            'category' => 'DefinedTerm',
            'post_tag' => 'DefinedTerm',
            'product_cat' => 'ItemList',
            'product_tag' => 'DefinedTerm',
            'location' => 'Place',
            'locations' => 'Place',
            'place' => 'Place',
            'places' => 'Place',
            'city' => 'Place',
            'cities' => 'Place',
            'country' => 'Place',
            'countries' => 'Place',
            'region' => 'Place',
            'regions' => 'Place',
            'author' => 'Person',
            'authors' => 'Person',
            'speaker' => 'Person',
            'speakers' => 'Person',
            'instructor' => 'Person',
            'instructors' => 'Person',
            'team' => 'Person',
            'team_member' => 'Person',
            'brand' => 'Brand',
            'brands' => 'Brand',
            'genre' => 'DefinedTerm',
            'genres' => 'DefinedTerm',
            'topic' => 'DefinedTerm',
            'topics' => 'DefinedTerm',
            'skill' => 'DefinedTerm',
            'skills' => 'DefinedTerm',
            'course_category' => 'ItemList',
            'course_tag' => 'DefinedTerm',
        ];

        if (isset($suggestions[$taxonomy])) {
            return $suggestions[$taxonomy];
        }

        // Check if taxonomy name contains certain keywords
        $taxonomyLower = strtolower($taxonomy);
        
        if (str_contains($taxonomyLower, 'location') || str_contains($taxonomyLower, 'place') || str_contains($taxonomyLower, 'city')) {
            return 'Place';
        }
        
        if (str_contains($taxonomyLower, 'author') || str_contains($taxonomyLower, 'person') || str_contains($taxonomyLower, 'speaker')) {
            return 'Person';
        }
        
        if (str_contains($taxonomyLower, 'brand')) {
            return 'Brand';
        }

        // Default for hierarchical taxonomies (categories)
        if ($taxonomyObj->hierarchical) {
            return 'DefinedTerm';
        }

        // Default for non-hierarchical (tags)
        return 'DefinedTerm';
    }

    /**
     * Get schema types suitable for taxonomies
     */
    private function getTaxonomySchemaTypes(): array
    {
        return [
            __('General', 'schema-markup-generator') => [
                'DefinedTerm' => __('Defined Term (Category/Tag)', 'schema-markup-generator'),
                'Thing' => __('Thing (Generic)', 'schema-markup-generator'),
            ],
            __('Lists', 'schema-markup-generator') => [
                'ItemList' => __('Item List (Collection)', 'schema-markup-generator'),
                'BreadcrumbList' => __('Breadcrumb List', 'schema-markup-generator'),
            ],
            __('Places', 'schema-markup-generator') => [
                'Place' => __('Place', 'schema-markup-generator'),
                'City' => __('City', 'schema-markup-generator'),
                'Country' => __('Country', 'schema-markup-generator'),
                'State' => __('State/Province', 'schema-markup-generator'),
                'LocalBusiness' => __('Local Business', 'schema-markup-generator'),
            ],
            __('People & Organizations', 'schema-markup-generator') => [
                'Person' => __('Person', 'schema-markup-generator'),
                'Organization' => __('Organization', 'schema-markup-generator'),
                'Brand' => __('Brand', 'schema-markup-generator'),
            ],
            __('Content', 'schema-markup-generator') => [
                'CreativeWork' => __('Creative Work', 'schema-markup-generator'),
                'Article' => __('Article', 'schema-markup-generator'),
                'CollectionPage' => __('Collection Page', 'schema-markup-generator'),
            ],
        ];
    }

    public function render(): void
    {
        $taxonomies = $this->taxonomyDiscovery->getTaxonomies();
        
        // Force fresh read (clear alloptions cache)
        wp_cache_delete('alloptions', 'options');
        
        $mappings = get_option('smg_taxonomy_mappings', []);
        $fieldMappings = get_option('smg_taxonomy_field_mappings', []);
        $schemaTypes = $this->getTaxonomySchemaTypes();

        ?>
        <div class="flex flex-col gap-6" id="tab-taxonomies">
            <?php $this->renderSection(
                __('Taxonomy Schema Mapping', 'schema-markup-generator'),
                __('Assign a schema type to each taxonomy. The schema will be automatically generated for taxonomy archive pages and can be referenced from posts.', 'schema-markup-generator')
            ); ?>

            <div class="smg-alert smg-alert-info">
                <span class="dashicons dashicons-info"></span>
                <div>
                    <strong><?php esc_html_e('How taxonomy schemas work', 'schema-markup-generator'); ?></strong>
                    <p><?php esc_html_e('Taxonomy schemas are rendered on taxonomy archive pages (e.g., category pages, tag pages). They help search engines understand the structure and hierarchy of your content.', 'schema-markup-generator'); ?></p>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <?php foreach ($taxonomies as $taxonomy => $taxonomyObj): ?>
                    <?php
                    $suggestedSchema = $this->getSuggestedSchema($taxonomy, $taxonomyObj);
                    $currentSchema = $mappings[$taxonomy] ?? '';
                    // Auto-select suggested schema if no mapping exists
                    if (empty($currentSchema) && $suggestedSchema) {
                        $currentSchema = $suggestedSchema;
                    }
                    $currentFieldMapping = $fieldMappings[$taxonomy] ?? [];
                    $postTypes = $taxonomyObj->object_type;
                    $termCount = wp_count_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
                    ?>
                    <div class="smg-taxonomy-card<?php echo $currentSchema ? ' smg-mapped' : ''; ?>" data-taxonomy="<?php echo esc_attr($taxonomy); ?>">
                        <div class="smg-taxonomy-header">
                            <div class="smg-taxonomy-info">
                                <span class="dashicons <?php echo $taxonomyObj->hierarchical ? 'dashicons-category' : 'dashicons-tag'; ?>"></span>
                                <div class="smg-taxonomy-details">
                                    <h3><?php echo esc_html($taxonomyObj->labels->singular_name); ?></h3>
                                    <div class="smg-taxonomy-meta">
                                        <code><?php echo esc_html($taxonomy); ?></code>
                                        <span class="smg-taxonomy-type">
                                            <?php echo $taxonomyObj->hierarchical 
                                                ? esc_html__('Hierarchical', 'schema-markup-generator') 
                                                : esc_html__('Flat', 'schema-markup-generator'); ?>
                                        </span>
                                        <span class="smg-taxonomy-count">
                                            <?php printf(
                                                /* translators: %d: number of terms */
                                                _n('%d term', '%d terms', (int) $termCount, 'schema-markup-generator'),
                                                (int) $termCount
                                            ); ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($postTypes)): ?>
                                        <div class="smg-taxonomy-post-types">
                                            <span class="smg-label"><?php esc_html_e('Used by:', 'schema-markup-generator'); ?></span>
                                            <?php foreach ($postTypes as $pt): 
                                                $ptObj = get_post_type_object($pt);
                                                if ($ptObj):
                                            ?>
                                                <span class="smg-post-type-tag"><?php echo esc_html($ptObj->labels->singular_name); ?></span>
                                            <?php endif; endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="smg-taxonomy-schema">
                                <select name="smg_taxonomy_mappings[<?php echo esc_attr($taxonomy); ?>]"
                                        class="smg-select smg-taxonomy-schema-select"
                                        data-taxonomy="<?php echo esc_attr($taxonomy); ?>"
                                        data-suggested="<?php echo esc_attr($suggestedSchema); ?>">
                                    <option value=""><?php esc_html_e('— No Schema —', 'schema-markup-generator'); ?></option>
                                    <?php foreach ($schemaTypes as $group => $types): ?>
                                        <optgroup label="<?php echo esc_attr($group); ?>">
                                            <?php foreach ($types as $type => $label): ?>
                                                <?php $isSuggested = ($type === $suggestedSchema); ?>
                                                <option value="<?php echo esc_attr($type); ?>"
                                                        <?php selected($currentSchema, $type); ?>
                                                        <?php echo $isSuggested ? 'data-suggested="true"' : ''; ?>>
                                                    <?php echo esc_html($label); ?><?php if ($isSuggested): ?> — <?php esc_html_e('Suggested', 'schema-markup-generator'); ?><?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="button" 
                                    class="smg-view-term-example-btn smg-btn smg-btn-secondary smg-btn-sm" 
                                    data-taxonomy="<?php echo esc_attr($taxonomy); ?>"
                                    title="<?php esc_attr_e('View schema example from a random term', 'schema-markup-generator'); ?>">
                                <span class="dashicons dashicons-visibility"></span>
                                <?php esc_html_e('Preview', 'schema-markup-generator'); ?>
                            </button>
                        </div>

                        <?php if ($currentSchema): ?>
                        <div class="smg-taxonomy-schema-info">
                            <span class="smg-schema-badge">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php echo esc_html($currentSchema); ?>
                            </span>
                            <span class="smg-schema-hint">
                                <?php esc_html_e('Schema will be rendered on taxonomy archive pages', 'schema-markup-generator'); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Help Section -->
            <div class="smg-help-section">
                <h4><?php esc_html_e('Schema Suggestions Guide', 'schema-markup-generator'); ?></h4>
                <div class="smg-help-grid">
                    <div class="smg-help-item">
                        <strong>DefinedTerm</strong>
                        <span><?php esc_html_e('Best for categories, tags, topics, genres', 'schema-markup-generator'); ?></span>
                    </div>
                    <div class="smg-help-item">
                        <strong>ItemList</strong>
                        <span><?php esc_html_e('Product categories, collections, listings', 'schema-markup-generator'); ?></span>
                    </div>
                    <div class="smg-help-item">
                        <strong>Place</strong>
                        <span><?php esc_html_e('Locations, cities, regions, countries', 'schema-markup-generator'); ?></span>
                    </div>
                    <div class="smg-help-item">
                        <strong>Person</strong>
                        <span><?php esc_html_e('Authors, speakers, team members', 'schema-markup-generator'); ?></span>
                    </div>
                    <div class="smg-help-item">
                        <strong>Brand</strong>
                        <span><?php esc_html_e('Product brands, manufacturers', 'schema-markup-generator'); ?></span>
                    </div>
                    <div class="smg-help-item">
                        <strong>Organization</strong>
                        <span><?php esc_html_e('Companies, partners, sponsors', 'schema-markup-generator'); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
