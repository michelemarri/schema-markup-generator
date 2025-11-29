# Schema Markup Generator - Hooks Reference

Complete reference for all available filters and actions.

---

## Filters

### Schema Types

#### `smg_register_schema_types`

Register custom schema types.

```php
add_filter('smg_register_schema_types', function(array $types): array {
    $types['CustomType'] = \MyNamespace\CustomSchema::class;
    return $types;
});
```

**Parameters:**
- `$types` (array) - Associative array of type => class

---

### Schema Data

#### `smg_schema_data`

Modify schema data before rendering.

```php
add_filter('smg_schema_data', function(array $data, WP_Post $post, string $type): array {
    $data['customProperty'] = get_post_meta($post->ID, 'custom_value', true);
    return $data;
}, 10, 3);
```

**Parameters:**
- `$data` (array) - The schema data
- `$post` (WP_Post) - The post object
- `$type` (string) - The schema type

---

#### `smg_post_schemas`

Filter all schemas for a post.

```php
add_filter('smg_post_schemas', function(array $schemas, WP_Post $post): array {
    // Add a custom schema
    $schemas[] = [
        '@context' => 'https://schema.org',
        '@type' => 'CustomType',
        'name' => 'Custom Data',
    ];
    return $schemas;
}, 10, 2);
```

**Parameters:**
- `$schemas` (array) - Array of schema data
- `$post` (WP_Post) - The post object

---

### Schema Type Filters

Each schema type has its own filter:

#### `smg_article_schema_data`

```php
add_filter('smg_article_schema_data', function(array $data, WP_Post $post, array $mapping): array {
    // Customize article schema
    return $data;
}, 10, 3);
```

#### `smg_product_schema_data`
#### `smg_organization_schema_data`
#### `smg_person_schema_data`
#### `smg_faq_schema_data`
#### `smg_howto_schema_data`
#### `smg_event_schema_data`
#### `smg_recipe_schema_data`
#### `smg_review_schema_data`
#### `smg_video_schema_data`
#### `smg_course_schema_data`
#### `smg_software_schema_data`
#### `smg_breadcrumb_schema_data`
#### `smg_website_schema_data`
#### `smg_webpage_schema_data`

---

### Discovery Filters

#### `smg_discovered_post_types`

Filter discovered post types.

```php
add_filter('smg_discovered_post_types', function(array $postTypes): array {
    // Remove a post type from schema
    unset($postTypes['unwanted_cpt']);
    return $postTypes;
});
```

---

#### `smg_discovered_fields`

Filter discovered custom fields for a post type.

```php
add_filter('smg_discovered_fields', function(array $fields, string $postType): array {
    // Add a custom field definition
    $fields[] = [
        'key' => 'custom_field',
        'name' => 'custom_field',
        'label' => 'Custom Field',
        'type' => 'text',
        'source' => 'custom',
    ];
    return $fields;
}, 10, 2);
```

---

#### `smg_discovered_taxonomies`

Filter discovered taxonomies.

```php
add_filter('smg_discovered_taxonomies', function(array $taxonomies): array {
    return $taxonomies;
});
```

---

#### `smg_post_type_taxonomies`

Filter taxonomies for a specific post type.

```php
add_filter('smg_post_type_taxonomies', function(array $taxonomies, string $postType): array {
    return $taxonomies;
}, 10, 2);
```

---

### Field Mapping Filters

#### `smg_mapped_fields`

Filter mapped field data.

```php
add_filter('smg_mapped_fields', function(array $data, WP_Post $post, array $mapping): array {
    // Transform mapped values
    return $data;
}, 10, 3);
```

---

#### `smg_acf_field_mapping`

Add custom ACF field mappings.

```php
add_filter('smg_acf_field_mapping', function(array $mapping, string $postType): array {
    if ($postType === 'product') {
        $mapping['price'] = 'product_price';
        $mapping['sku'] = 'product_sku';
    }
    return $mapping;
}, 10, 2);
```

---

#### `smg_resolve_field_value`

Custom field value resolver.

```php
add_filter('smg_resolve_field_value', function($value, int $postId, string $fieldKey, string $source) {
    if ($fieldKey === 'special_field') {
        return get_special_value($postId);
    }
    return $value;
}, 10, 4);
```

---

### Publisher/Author Filters

#### `smg_publisher_data`

Modify publisher/organization data.

```php
add_filter('smg_publisher_data', function(array $publisher): array {
    $publisher['sameAs'] = [
        'https://twitter.com/company',
        'https://facebook.com/company',
    ];
    return $publisher;
});
```

---

#### `smg_author_data`

Modify author data.

```php
add_filter('smg_author_data', function(array $author, WP_User $user, WP_Post $post): array {
    $author['sameAs'] = get_user_meta($user->ID, 'social_profiles', true);
    return $author;
}, 10, 3);
```

---

### Admin Filters

#### `smg_settings_tabs`

Register custom settings tabs.

```php
add_filter('smg_settings_tabs', function(array $tabs): array {
    $tabs['custom'] = new CustomTab();
    return $tabs;
});
```

---

### Import/Export Filters

#### `smg_export_data`

Modify export data.

```php
add_filter('smg_export_data', function(array $data): array {
    $data['custom_settings'] = get_option('my_custom_settings');
    return $data;
});
```

---

#### `smg_import_data`

Modify import data before processing.

```php
add_filter('smg_import_data', function(array $data): array {
    // Validate or transform import data
    return $data;
});
```

---

## Actions

### Lifecycle Actions

#### `smg_update_checker_init`

Fired when update checker is initialized.

```php
add_action('smg_update_checker_init', function($updateChecker) {
    // Configure update checker
    $updateChecker->setBranch('main');
});
```

---

#### `smg_after_import`

Fired after settings are imported.

```php
add_action('smg_after_import', function(array $data) {
    // Clear caches or trigger other actions
    wp_cache_flush();
});
```

---

### Rendering Actions

#### `smg_before_render`

Fired before schema is rendered.

```php
add_action('smg_before_render', function(WP_Post $post) {
    // Pre-render logic
});
```

---

#### `smg_after_render`

Fired after schema is rendered.

```php
add_action('smg_after_render', function(WP_Post $post, array $schemas) {
    // Post-render logic
}, 10, 2);
```

---

## Creating Custom Schema Types

### Step 1: Create Schema Class

```php
<?php

namespace MyPlugin\Schema;

use flavor\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

class CustomSchema extends AbstractSchema
{
    public function getType(): string
    {
        return 'CustomType';
    }

    public function getLabel(): string
    {
        return __('Custom Type', 'my-plugin');
    }

    public function getDescription(): string
    {
        return __('Description of custom schema type.', 'my-plugin');
    }

    public function build(WP_Post $post, array $mapping = []): array
    {
        $data = $this->buildBase($post);
        
        $data['name'] = get_the_title($post);
        $data['description'] = $this->getDescription($post);
        
        // Add custom properties
        $data['customProperty'] = $this->getMappedValue($post, $mapping, 'customProperty');
        
        return $this->cleanData($data);
    }

    public function getRequiredProperties(): array
    {
        return ['name', 'customProperty'];
    }

    public function getRecommendedProperties(): array
    {
        return ['description'];
    }

    public function getPropertyDefinitions(): array
    {
        return [
            'name' => [
                'type' => 'text',
                'description' => __('Name', 'my-plugin'),
                'auto' => 'post_title',
            ],
            'customProperty' => [
                'type' => 'text',
                'description' => __('Custom property', 'my-plugin'),
            ],
        ];
    }
}
```

### Step 2: Register Schema Type

```php
add_filter('smg_register_schema_types', function($types) {
    $types['CustomType'] = \MyPlugin\Schema\CustomSchema::class;
    return $types;
});
```

---

## Best Practices

1. **Use specific hooks** - Use schema-type-specific filters when possible
2. **Preserve data** - Always return the original data if not modifying
3. **Validate carefully** - Ensure schema data is valid after modifications
4. **Cache aware** - Consider cache implications when modifying data
5. **Test thoroughly** - Use Google Rich Results Test to verify changes

