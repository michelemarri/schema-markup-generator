# Schema Markup Generator - Extending Guide

Learn how to extend the Schema Markup Generator plugin with custom functionality.

---

## Table of Contents

1. [Creating Custom Schema Types](#creating-custom-schema-types)
2. [Custom Field Sources](#custom-field-sources)
3. [Custom Admin Tabs](#custom-admin-tabs)
4. [Integration with Other Plugins](#integration-with-other-plugins)

---

## Creating Custom Schema Types

### Basic Implementation

Create a class that extends `AbstractSchema`:

```php
<?php

namespace MyPlugin\Schema;

use Metodo\SchemaMarkupGenerator\Schema\AbstractSchema;
use Metodo\SchemaMarkupGenerator\Schema\SchemaInterface;
use WP_Post;

class PodcastEpisodeSchema extends AbstractSchema implements SchemaInterface
{
    public function getType(): string
    {
        return 'PodcastEpisode';
    }

    public function getLabel(): string
    {
        return __('Podcast Episode', 'my-plugin');
    }

    public function getDescription(): string
    {
        return __('For podcast episodes with audio content.', 'my-plugin');
    }

    public function build(WP_Post $post, array $mapping = []): array
    {
        $data = $this->buildBase($post);

        // Core properties
        $data['name'] = html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8');
        $data['description'] = $this->getDescription($post);
        $data['url'] = $this->getPostUrl($post);
        $data['datePublished'] = $this->formatDate($post->post_date_gmt);

        // Podcast-specific properties
        $data['associatedMedia'] = [
            '@type' => 'MediaObject',
            'contentUrl' => $this->getMappedValue($post, $mapping, 'audioUrl'),
        ];

        $duration = $this->getMappedValue($post, $mapping, 'duration');
        if ($duration) {
            $data['timeRequired'] = $this->formatDuration($duration);
        }

        // Part of podcast series
        $podcastName = $this->getMappedValue($post, $mapping, 'podcastName');
        if ($podcastName) {
            $data['partOfSeries'] = [
                '@type' => 'PodcastSeries',
                'name' => $podcastName,
            ];
        }

        return $this->cleanData($data);
    }

    private function formatDuration(int $minutes): string
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return $hours > 0 ? "PT{$hours}H{$mins}M" : "PT{$mins}M";
    }

    public function getRequiredProperties(): array
    {
        return ['name', 'associatedMedia'];
    }

    public function getRecommendedProperties(): array
    {
        return ['description', 'datePublished', 'timeRequired', 'partOfSeries'];
    }

    public function getPropertyDefinitions(): array
    {
        return [
            'name' => [
                'type' => 'text',
                'description' => __('Episode title', 'my-plugin'),
                'auto' => 'post_title',
            ],
            'audioUrl' => [
                'type' => 'url',
                'description' => __('Audio file URL', 'my-plugin'),
            ],
            'duration' => [
                'type' => 'number',
                'description' => __('Duration in minutes', 'my-plugin'),
            ],
            'podcastName' => [
                'type' => 'text',
                'description' => __('Podcast series name', 'my-plugin'),
            ],
        ];
    }
}
```

### Register the Schema Type

```php
add_filter('smg_register_schema_types', function(array $types): array {
    $types['PodcastEpisode'] = \MyPlugin\Schema\PodcastEpisodeSchema::class;
    return $types;
});
```

### Add to Schema Groups (Optional)

To show your schema in a specific category in the admin:

```php
add_filter('smg_schema_types_grouped', function(array $groups): array {
    $groups['Media & Events']['PodcastEpisode'] = __('Podcast Episode', 'my-plugin');
    return $groups;
});
```

---

## Custom Field Sources

### Register Custom Field Discovery

```php
add_filter('smg_discovered_fields', function(array $fields, string $postType): array {
    // Add fields from a custom source
    if ($postType === 'podcast') {
        $fields[] = [
            'key' => 'episode_audio',
            'name' => 'episode_audio',
            'label' => 'Episode Audio URL',
            'type' => 'url',
            'source' => 'custom_plugin',
        ];
        
        $fields[] = [
            'key' => 'episode_duration',
            'name' => 'episode_duration',
            'label' => 'Episode Duration',
            'type' => 'number',
            'source' => 'custom_plugin',
        ];
    }
    
    return $fields;
}, 10, 2);
```

### Custom Field Value Resolver

```php
add_filter('smg_resolve_field_value', function($value, int $postId, string $fieldKey, string $source) {
    if ($source !== 'custom_plugin') {
        return $value;
    }
    
    switch ($fieldKey) {
        case 'episode_audio':
            return get_post_meta($postId, '_podcast_audio_url', true);
            
        case 'episode_duration':
            return (int) get_post_meta($postId, '_podcast_duration', true);
            
        default:
            return $value;
    }
}, 10, 4);
```

---

## Custom Admin Tabs

### Create Tab Class

```php
<?php

namespace MyPlugin\Admin;

use Metodo\SchemaMarkupGenerator\Admin\Tabs\AbstractTab;

class PodcastTab extends AbstractTab
{
    public function getTitle(): string
    {
        return __('Podcast', 'my-plugin');
    }

    public function getIcon(): string
    {
        return 'dashicons-microphone';
    }

    public function render(): void
    {
        $settings = get_option('smg_podcast_settings', []);
        ?>
        <div class="smg-tab-panel" id="tab-podcast">
            <?php $this->renderSection(
                __('Podcast Settings', 'my-plugin'),
                __('Configure podcast-specific schema options.', 'my-plugin')
            ); ?>
            
            <div class="smg-cards-grid">
                <?php
                $this->renderCard(__('Default Podcast', 'my-plugin'), function() use ($settings) {
                    $this->renderTextField(
                        'smg_podcast_settings[podcast_name]',
                        $settings['podcast_name'] ?? '',
                        __('Podcast Name', 'my-plugin'),
                        __('Default podcast series name for all episodes.', 'my-plugin')
                    );
                    
                    $this->renderTextField(
                        'smg_podcast_settings[podcast_url]',
                        $settings['podcast_url'] ?? '',
                        __('Podcast URL', 'my-plugin'),
                        __('Main podcast page URL.', 'my-plugin')
                    );
                }, 'dashicons-microphone');
                ?>
            </div>
        </div>
        <?php
    }

    public function registerSettings(): void
    {
        register_setting('smg_settings', 'smg_podcast_settings', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize'],
        ]);
    }

    public function sanitize(array $input): array
    {
        return [
            'podcast_name' => sanitize_text_field($input['podcast_name'] ?? ''),
            'podcast_url' => esc_url_raw($input['podcast_url'] ?? ''),
        ];
    }
}
```

### Register Tab

```php
add_filter('smg_settings_tabs', function(array $tabs): array {
    $tabs['podcast'] = new \MyPlugin\Admin\PodcastTab();
    return $tabs;
});
```

---

## Integration with Other Plugins

### WooCommerce Integration Example

```php
<?php

namespace MyPlugin\Integration;

class WooCommerceIntegration
{
    public function init(): void
    {
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Override Product schema for WooCommerce products
        add_filter('smg_product_schema_data', [$this, 'enhanceProductSchema'], 10, 3);
        
        // Add WooCommerce fields to discovery
        add_filter('smg_discovered_fields', [$this, 'addWooFields'], 10, 2);
    }

    public function enhanceProductSchema(array $data, \WP_Post $post, array $mapping): array
    {
        if ($post->post_type !== 'product') {
            return $data;
        }
        
        $product = wc_get_product($post->ID);
        if (!$product) {
            return $data;
        }
        
        // Enhanced offers
        $data['offers'] = [
            '@type' => 'Offer',
            'price' => $product->get_price(),
            'priceCurrency' => get_woocommerce_currency(),
            'availability' => $product->is_in_stock() 
                ? 'https://schema.org/InStock' 
                : 'https://schema.org/OutOfStock',
            'url' => $product->get_permalink(),
        ];
        
        // SKU
        if ($product->get_sku()) {
            $data['sku'] = $product->get_sku();
        }
        
        // Brand from attribute
        $brand = $product->get_attribute('brand');
        if ($brand) {
            $data['brand'] = [
                '@type' => 'Brand',
                'name' => $brand,
            ];
        }
        
        // Aggregate rating
        if ($product->get_review_count() > 0) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $product->get_average_rating(),
                'reviewCount' => $product->get_review_count(),
            ];
        }
        
        return $data;
    }

    public function addWooFields(array $fields, string $postType): array
    {
        if ($postType !== 'product') {
            return $fields;
        }
        
        $wooFields = [
            ['key' => '_regular_price', 'label' => 'Regular Price', 'type' => 'number'],
            ['key' => '_sale_price', 'label' => 'Sale Price', 'type' => 'number'],
            ['key' => '_sku', 'label' => 'SKU', 'type' => 'text'],
            ['key' => '_stock_status', 'label' => 'Stock Status', 'type' => 'select'],
        ];
        
        foreach ($wooFields as $field) {
            $fields[] = [
                'key' => $field['key'],
                'name' => $field['key'],
                'label' => $field['label'],
                'type' => $field['type'],
                'source' => 'woocommerce',
            ];
        }
        
        return $fields;
    }
}

// Initialize
add_action('plugins_loaded', function() {
    (new \MyPlugin\Integration\WooCommerceIntegration())->init();
});
```

### MemberPress Courses (Built-in Example)

The plugin includes built-in integration with MemberPress Courses. Here's how it works:

```php
// The integration hooks into smg_learning_resource_parent_course
add_filter('smg_learning_resource_parent_course', [$this, 'getParentCourse'], 10, 3);

// And enhances Course schema data
add_filter('smg_course_schema_data', [$this, 'enhanceCourseSchema'], 10, 3);
```

Key features of the MemberPress Courses integration:
- Automatic parent course detection for lessons
- Course curriculum with sections and lessons
- Lesson count calculation
- No configuration required

See `src/Integration/MemberPressCoursesIntegration.php` for the full implementation.

---

## Best Practices

### 1. Namespace Everything

Always use namespaces to avoid conflicts:

```php
namespace MyPlugin\SchemaExtensions;
```

### 2. Check Dependencies

Verify required plugins/classes exist:

```php
if (!class_exists('Metodo\SchemaMarkupGenerator\Plugin')) {
    return;
}
```

### 3. Use Proper Hook Priorities

```php
// Run early to modify before other filters
add_filter('smg_schema_data', $callback, 5, 3);

// Run late to have final say
add_filter('smg_schema_data', $callback, 99, 3);
```

### 4. Validate Schema Output

Test your custom schemas with:
- [Google Rich Results Test](https://search.google.com/test/rich-results)
- [Schema.org Validator](https://validator.schema.org/)

### 5. Document Your Extensions

Provide clear documentation for:
- Required fields
- Expected data formats
- Integration points

---

## Support

For questions about extending the plugin:

- [GitHub Issues](https://github.com/michelemarri/schema-markup-generator/issues)
- [Metodo.dev](https://metodo.dev)

