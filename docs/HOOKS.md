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
#### `smg_learning_resource_schema_data`
#### `smg_software_schema_data`
#### `smg_breadcrumb_schema_data`
#### `smg_website_schema_data`
#### `smg_webpage_schema_data`

---

### MemberPress Filters

#### `smg_learning_resource_parent_course`

Filter the parent course data for LearningResource schemas (MemberPress Courses).

```php
add_filter('smg_learning_resource_parent_course', function(?array $parentCourse, WP_Post $post, array $mapping): ?array {
    // Return custom parent course data
    return [
        '@type' => 'Course',
        'name' => 'My Custom Course',
        'url' => 'https://example.com/course/',
    ];
}, 10, 3);
```

**Parameters:**
- `$parentCourse` (array|null) - Current parent course data
- `$post` (WP_Post) - The lesson post
- `$mapping` (array) - Field mapping configuration

---

#### `smg_product_schema_data` (MemberPress Membership)

The Product schema filter is automatically enhanced for `memberpressproduct` posts.

```php
add_filter('smg_product_schema_data', function(array $data, WP_Post $post, array $mapping): array {
    // Customize membership product schema
    if ($post->post_type === 'memberpressproduct') {
        // Add custom membership data
        $data['additionalProperty'] = [
            '@type' => 'PropertyValue',
            'name' => 'membership_level',
            'value' => get_post_meta($post->ID, '_level', true),
        ];
    }
    return $data;
}, 10, 3);
```

**Auto-enhanced properties for memberships:**
- `offers` - Automatically populated with price, currency, availability
- `category` - Set to "Membership"

---

#### MemberPress Membership Field Sources

When using `smg_resolve_field_value`, membership fields use these sources:

- `memberpress` - Standard meta fields (e.g., `_mepr_product_price`)
- `memberpress_virtual` - Computed fields (e.g., `mepr_formatted_price`)

```php
add_filter('smg_resolve_field_value', function($value, int $postId, string $fieldKey, string $source) {
    if ($source === 'memberpress_virtual' && $fieldKey === 'mepr_formatted_price') {
        // Customize formatted price
        return '$' . number_format((float)get_post_meta($postId, '_mepr_product_price', true), 0);
    }
    return $value;
}, 10, 4);
```

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

#### `smg_sanitize_mapped_value`

Customize sanitization of mapped field values. By default, HTML is stripped from all text values.

```php
add_filter('smg_sanitize_mapped_value', function($value, string $property, string $fieldKey, WP_Post $post) {
    // Preserve HTML for a specific property
    if ($property === 'articleBody') {
        return get_post_meta($post->ID, $fieldKey, true); // Return raw value
    }
    return $value;
}, 10, 4);
```

**Parameters:**
- `$value` (mixed) - The sanitized value
- `$property` (string) - The schema property name (e.g., 'description', 'teaches')
- `$fieldKey` (string) - The source field key
- `$post` (WP_Post) - The post object

**Default behavior:**
- Strings: HTML tags stripped, entities decoded, whitespace normalized
- URLs: Preserved as-is
- Emails: Preserved as-is
- Arrays: Recursively sanitized
- Raw meta dumps: Filtered out (returns null)

---

#### `smg_mapped_fields`

Filter mapped field data.

```php
add_filter('smg_mapped_fields', function(array $data, WP_Post $post, array $mapping): array {
    // Transform mapped values
    return $data;
}, 10, 3);
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

### Organization Data Filters

#### `smg_organization_data`

Modify the base organization data used throughout the plugin. This filter is called by `smg_get_organization_data()` and affects all places where organization info is used (publisher in Article schemas, Organization schema, etc.).

```php
add_filter('smg_organization_data', function(array $data): array {
    // Override or extend organization data
    $data['name'] = 'My Custom Organization Name';
    $data['url'] = 'https://custom-url.com/';
    
    // Add custom logo
    $data['logo'] = [
        '@type' => 'ImageObject',
        'url' => 'https://example.com/logo.png',
        'width' => 600,
        'height' => 60,
    ];
    
    return $data;
});
```

**Parameters:**
- `$data` (array) - Organization data with keys: `name`, `url`, `logo`

**Returns:** Array with organization data

---

### Publisher/Author Filters

#### `smg_publisher_data`

Modify publisher/organization data. This filter is applied after the base organization data is fetched from settings.

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

### Update Filters

#### `smg_github_token`

Provide GitHub token dynamically for private repository updates.

```php
add_filter('smg_github_token', function(): ?string {
    // Return token from environment variable
    return getenv('GITHUB_TOKEN') ?: null;
});
```

**Parameters:**
- Return: `string|null` - The GitHub Personal Access Token

**Use cases:**
- Retrieve token from environment variables
- Use a secrets management service
- Site-specific token configuration in mu-plugins

**Priority:**
1. `SMG_GITHUB_TOKEN` constant in wp-config.php (highest)
2. `smg_github_token` filter
3. Encrypted token from plugin settings (lowest)

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

### AJAX Actions

#### `wp_ajax_smg_save_schema_mapping`

Auto-saves schema type assignment for a post type.

**POST parameters:**
- `nonce` - Security nonce (`smg_admin_nonce`)
- `post_type` - The post type slug
- `schema_type` - The schema type to assign (or empty to remove)

**Response:**
```json
{
    "success": true,
    "data": {
        "message": "Schema mapping saved",
        "post_type": "product",
        "schema_type": "Product"
    }
}
```

---

#### `wp_ajax_smg_save_field_mapping`

Auto-saves field mapping for a schema property.

**POST parameters:**
- `nonce` - Security nonce (`smg_admin_nonce`)
- `post_type` - The post type slug
- `property` - The schema property name
- `field_key` - The field key to map (or empty to remove mapping)

**Response:**
```json
{
    "success": true,
    "data": {
        "message": "Field mapping saved",
        "post_type": "product",
        "property": "price",
        "field_key": "product_price"
    }
}
```

---

## Creating Custom Schema Types

### Step 1: Create Schema Class

```php
<?php

namespace MyPlugin\Schema;

use Metodo\SchemaMarkupGenerator\Schema\AbstractSchema;
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

