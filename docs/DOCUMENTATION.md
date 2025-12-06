# Schema Markup Generator - Documentation

Complete documentation for the Schema Markup Generator WordPress plugin.

## Table of Contents

1. [Installation](#installation)
2. [Configuration](#configuration)
3. [Schema Types](#schema-types)
4. [Field Mapping](#field-mapping)
5. [ACF Integration](#acf-integration)
6. [MemberPress Courses Integration](#memberpress-courses-integration)
7. [MemberPress Membership Integration](#memberpress-membership-integration)
8. [Advanced Settings](#advanced-settings)
9. [Caching](#caching)
10. [REST API](#rest-api)
11. [Import/Export](#importexport)
12. [Troubleshooting](#troubleshooting)

---

## Installation

### Requirements

- PHP 8.2 or higher
- WordPress 6.0 or higher
- Composer (for development only)

### Installation Steps

1. Download the plugin ZIP from [GitHub Releases](https://github.com/michelemarri/schema-markup-generator/releases)
2. Navigate to **Plugins → Add New → Upload Plugin**
3. Upload the ZIP file
4. Click **Install Now** and then **Activate**

### Automatic Updates

The plugin includes automatic update functionality from GitHub. When a new release is published, you'll see an update notification in your WordPress admin.

**No configuration required!** The repository is public, so updates work automatically without any authentication.

#### Update Settings

Navigate to **Settings → Schema Markup → Update** to configure:

- **Current Version**: See installed version and last update check
- **Auto-Update**: Enable automatic updates when new versions are available

---

## Configuration

### General Settings

Navigate to **Settings → Schema Markup** to access the plugin settings.

#### Schema Output

- **Enable Schema Markup**: Toggle schema output on/off globally
- **WebSite Schema**: Adds WebSite schema with SearchAction for sitelinks
- **Breadcrumb Schema**: Adds BreadcrumbList schema for navigation

#### Organization Info

The General tab shows a read-only summary of your organization info. Click "Edit Organization Info" to customize these settings in the Advanced tab.

Organization data is used in:
- Organization schema type
- Publisher info in Article schemas
- WebSite schema

See [Advanced Settings → Organization Info](#organization-info-advanced) for customization options.

### Post Type Configuration

In the **Post Types** tab, you can:

1. **Assign Schema Types**: Select a schema type for each post type
2. **Configure Field Mappings**: Map custom fields to schema properties
3. **View Available Fields**: See all discoverable fields (ACF, meta, etc.)

#### Auto-Save Feature

All changes in the Post Types tab are **saved automatically** when you:
- Select a different schema type for a post type
- Change any field mapping

No "Save Changes" button is required - you'll see a brief visual confirmation (green flash) when your changes are saved. This makes configuration faster and more intuitive.

#### Smart Schema Suggestions

The plugin automatically recommends schema types based on your post type names. For example:
- Post types containing "recipe" or "recipes" → Recipe schema
- Post types containing "guide" or "guides" → LearningResource schema
- Post types containing "product" or "shop" → Product schema
- Post types containing "event" → Event schema

When a recommendation is available:
- The suggested schema is **automatically selected** for unconfigured post types
- A **"Recommended" badge** appears next to the suggested option
- A **★ star** marks the recommended option in the dropdown

You can always override the suggestion and select a different schema type.

#### Property Information Modal

When configuring field mappings, **click on any property name** to open a detailed information modal that includes:
- **Detailed description**: In-depth explanation of what the property is for
- **Examples**: Real-world examples of valid values
- **Schema.org link**: Direct link to the official schema.org documentation

This helps you understand exactly what data to provide for each schema property.

#### Schema Example Preview

Want to see what the generated schema looks like for a specific post type? **Click the eye icon (👁)** next to any post type name to open the Schema Example modal.

The modal shows:
- **Real JSON-LD output**: Generated schema from a random published post of that type
- **Post information**: Title of the example post with links to edit or view it
- **Copy button**: One-click copy of the schema JSON to clipboard
- **Refresh button**: Load a different random post to see various examples

This feature helps you:
- Verify your field mappings are working correctly
- See real schema output before publishing
- Test different posts to ensure consistent schema generation
- Debug issues by examining actual generated data

### Advanced Settings

In the **Advanced** tab:

- **Cache Settings**: Enable/disable caching and set TTL
- **Debug Mode**: Enable logging for troubleshooting
- **System Info**: View technical details about your installation

---

## Schema Types

### Content Schemas

#### Article / BlogPosting / NewsArticle

For blog posts and editorial content.

**Auto-populated properties:**
- `headline` → Post title
- `description` → Post excerpt or auto-generated
- `author` → Post author
- `datePublished` → Publication date
- `dateModified` → Last modified date
- `image` → Featured image
- `articleSection` → Primary category
- `keywords` → Post tags
- `wordCount` → Auto-calculated

#### WebPage

For static pages with automatic type detection:
- `ContactPage` - Contact pages
- `AboutPage` - About pages
- `FAQPage` - FAQ pages
- `ProfilePage` - Profile pages

### Business Schemas

#### Product

For e-commerce and product pages, including subscription products.

**Required fields:**
- `name` - Product name
- `image` - Product image
- `offers` - Price and availability

**Recommended fields:**
- `description`
- `brand`
- `sku`
- `aggregateRating`
- `eligibleDuration` (for subscriptions)
- `billingDuration` + `billingIncrement` (for subscriptions)

##### Subscription Products

The Product schema supports recurring billing for memberships and subscriptions (MemberPress, WooCommerce Subscriptions, etc.).

**Subscription fields:**
- `eligibleDuration` - ISO 8601 duration (P1M = monthly, P1Y = yearly)
- `billingDuration` - Number of billing periods (e.g., 1 for monthly)
- `billingIncrement` - Time unit: Month, Year, Week, Day
- `referenceQuantity` - Number of billing periods (optional)

**MemberPress virtual fields:**
For MemberPress memberships, use these pre-formatted virtual fields for easy mapping:
- `mepr_eligible_duration` → Maps directly to `eligibleDuration` (e.g., P1M, P1Y)
- `mepr_billing_duration` → Maps directly to `billingDuration` (e.g., 1, 3, 12)
- `mepr_billing_increment` → Maps directly to `billingIncrement` (Month, Year, Week, Day)

**Discount/Promotion fields:**
- `referencePrice` - Original/list price before discount (creates strikethrough price display)
- `priceValidUntil` - When the promotional price expires (YYYY-MM-DD format)

**Example output with discount:**
```json
{
  "@type": "Product",
  "name": "Premium Monthly",
  "offers": {
    "@type": "Offer",
    "price": 59,
    "priceCurrency": "EUR",
    "priceValidUntil": "2025-01-31",
    "priceSpecification": {
      "@type": "UnitPriceSpecification",
      "price": 59,
      "priceCurrency": "EUR",
      "billingDuration": 1,
      "billingIncrement": "Month",
      "referencePrice": {
        "@type": "PriceSpecification",
        "price": 129,
        "priceCurrency": "EUR"
      }
    }
  }
}
```

The `referencePrice` field accepts text values like "$129 / original price" - the plugin automatically extracts the numeric value.

**Price fallback cascade (MemberPress):**

When the mapped `price` field is empty (e.g., promo disabled), the plugin automatically falls back:

1. **Mapped price** (e.g., ACF "Promo Price") - if available and > 0
2. **referencePrice** (e.g., ACF "Original Price") - if price is empty
3. **`_mepr_product_price`** (MemberPress standard price) - final fallback

This ensures the schema always has a valid price, even when promotions are disabled.

**Example output for monthly subscription:**
```json
{
  "@type": "Product",
  "name": "MenthorQ Pro Membership",
  "description": "Access to premium features and analytics.",
  "offers": {
    "@type": "Offer",
    "price": 39.00,
    "priceCurrency": "EUR",
    "availability": "https://schema.org/InStock",
    "eligibleDuration": "P1M",
    "priceSpecification": {
      "@type": "UnitPriceSpecification",
      "price": 39.00,
      "priceCurrency": "EUR",
      "billingDuration": 1,
      "billingIncrement": "Month"
    }
  }
}
```

**Duration format:**
The plugin accepts multiple formats for `eligibleDuration`:
- ISO 8601: `P1M`, `P1Y`, `P3M`, `P1W`
- Numeric (assumed months): `1`, `12`
- Text: `1 month`, `1 year`, `3 months`

#### Organization / LocalBusiness

For business and organization pages.

**Key properties:**
- `name` - Business name
- `logo` - Business logo
- `address` - Physical address
- `telephone` - Contact phone
- `openingHours` - Business hours (LocalBusiness)
- `geo` - Location coordinates (LocalBusiness)

### Instructional Schemas

#### FAQPage

For FAQ pages with question/answer pairs.

**Auto-extraction:**
The plugin can automatically extract FAQs from content using H2/H3 headings followed by paragraphs.

**Manual configuration:**
Map a repeater field with `question` and `answer` sub-fields.

#### HowTo

For step-by-step guides and tutorials.

**Properties:**
- `name` - Guide title
- `step` - Steps (auto-extracted from content - see below)
- `totalTime` - Duration (auto-extracted or mapped)
- `supply` - Materials needed (optional, requires explicit mapping)
- `tool` - Tools required (optional, requires explicit mapping)

**Auto-extraction of Steps:**

The plugin intelligently extracts steps from content in this priority:

1. **Ordered lists** (`<ol>`) - Each list item becomes a step
2. **Numbered headings** - Headings like "Step 1:", "Passo 1:", "#1" etc.
3. **Any H2/H3/H4 sequence** - Each heading becomes a step with following content as description

The heading-based extraction treats the natural sequence of headings as steps, regardless of numbering. This works well for educational content where sections represent logical steps.

**Supply & Tool Validation:**

Supply and tool fields require explicit field mapping and include validation to filter out:
- Numeric IDs and timestamps
- ACF field names (field_*)
- HTML content
- Default placeholder values
- Values that are too short or too long

#### Recipe

For cooking recipes.

**Required:**
- `name`
- `image`
- `recipeIngredient`
- `recipeInstructions`

**Recommended:**
- `prepTime` / `cookTime` / `totalTime`
- `recipeYield`
- `nutrition`

### Education Schemas

#### Course

For online courses and educational content.

**Properties:**
- `name` - Course title
- `description` - Course description
- `provider` - Organization offering the course
- `hasCourseInstance` - Course sections/modules
- `numberOfLessons` - Total lesson count

#### LearningResource

For individual lessons or educational materials.

**Properties:**
- `name` - Resource title
- `description` - Resource description
- `learningResourceType` - Type (lesson, tutorial, etc.)
- `isPartOf` - Parent course (auto-detected with MemberPress Courses)

---

## Field Mapping

### Post Fields

These fields are automatically available for every post:
- `post_title` - Post title
- `post_excerpt` - Post excerpt
- `post_content` - Full content
- `post_date` - Publication date
- `post_modified` - Last modified date
- `featured_image` - Featured image URL
- `author` - Author name

### Website Fields

Global site information available for mapping:
- `site_name` - The site name from WordPress General Settings
- `site_url` - The home URL of the website

### Custom Fields

Any public custom fields (post meta) can be mapped to schema properties. The plugin discovers custom fields from multiple sources:
- **ACF (Advanced Custom Fields)** - All field groups assigned to a post type
- **WordPress Secure Custom Fields** - Native WordPress custom fields
- **Other field plugins** - Any plugin that registers meta keys

All custom fields are displayed uniformly in the "Custom Fields" group without specifying their source, making it easier to work with mixed field providers.

**Supported field types:**
- Text fields
- Image fields (returns URL)
- Gallery fields (returns array of URLs)
- Date/time fields
- Repeater fields
- Group fields

### Taxonomies

All public taxonomies (both built-in and custom) are available for mapping. This is useful for:
- Mapping categories to `articleSection`
- Mapping tags to `keywords`
- Mapping custom taxonomies like "difficulty" or "cuisine" to appropriate schema properties

**Usage:**
Taxonomies appear in the "Taxonomies" group in the field mapping dropdown. When mapped, they return a comma-separated list of term names.

**Examples:**
- `category` → Maps to the post's categories
- `post_tag` → Maps to the post's tags
- Custom taxonomy like `recipe_cuisine` → Maps to your custom taxonomy terms

### Mapping Syntax

**Simple mapping:**
```
Schema Property → Field Key
```

**Taxonomy mapping:**
```
Schema Property → taxonomy:taxonomy_slug
```

**Nested properties (ACF Repeater):**
```
offers.price → product_price
brand.name → product_brand
```

---

## ACF Integration

### Supported Field Types

| ACF Type | Schema Type |
|----------|-------------|
| text, textarea, wysiwyg | text |
| number, range | number |
| email | email |
| url, link | url |
| image, file | file/url |
| gallery | array of urls |
| date_picker | date |
| date_time_picker | datetime |
| true_false | boolean |
| repeater | array |
| google_map | geo coordinates |

### Repeater Fields

Repeater fields are ideal for:
- FAQ items (`question` + `answer`)
- Recipe ingredients
- How-to steps
- Product reviews

Configure the repeater sub-fields to match schema property names.

### Image Fields

ACF image fields are automatically resolved:
- Returns URL if return format is URL
- Extracts URL from array if return format is Array

---

## MemberPress Courses Integration

When MemberPress Courses is active, the plugin automatically enhances schema generation for courses and lessons.

### Automatic Features

1. **Parent Course Detection**
   - Lessons (`mpcs-lesson`) automatically include their parent course in the `isPartOf` property
   - The course hierarchy (Lesson → Section → Course) is traversed automatically

2. **Course Enhancement**
   - Courses (`mpcs-course`) can include curriculum structure with sections and lessons (configurable)
   - Lesson count is automatically calculated

### Configuration

In **Settings → Schema Markup → Integrations**, you can configure:

| Setting | Description |
|---------|-------------|
| **Auto-detect Parent Course** | Automatically link lessons to their parent course in the schema (`isPartOf`) |
| **Include Curriculum in Course Schema** | Add sections and lessons list to Course schema as `hasCourseInstance` (may increase page size) |

### Available Virtual Fields

The following computed fields are available for mapping to Course schema properties:

| Field | Type | Description | Suggested Mapping |
|-------|------|-------------|-------------------|
| `mpcs_curriculum` | text | Auto-generated course curriculum (sections and lessons as text) | `syllabus` |
| `mpcs_curriculum_html` | text | Course curriculum formatted as HTML nested list | - |
| `mpcs_lesson_count` | number | Total number of lessons in the course | - |
| `mpcs_section_count` | number | Total number of sections in the course | - |

**Pro Tip:** Map `mpcs_curriculum` to the `syllabus` property for optimal LLM understanding of your course structure.

### Supported Post Types

| Post Type | Schema Type | Features |
|-----------|-------------|----------|
| `mpcs-course` | Course | Virtual fields, curriculum, lesson count, sections |
| `mpcs-lesson` | LearningResource | Parent course auto-detection |

### Example Output

**Lesson (LearningResource):**
```json
{
  "@type": "LearningResource",
  "name": "Introduction to SEO",
  "learningResourceType": "Lesson",
  "isPartOf": {
    "@type": "Course",
    "name": "Digital Marketing Fundamentals",
    "url": "https://example.com/courses/digital-marketing/"
  }
}
```

**Course with Curriculum (when setting is enabled):**
```json
{
  "@type": "Course",
  "name": "Digital Marketing Fundamentals",
  "numberOfLessons": 12,
  "hasCourseInstance": [
    {
      "@type": "CourseInstance",
      "name": "Module 1: SEO Basics",
      "hasPart": [
        { "@type": "LearningResource", "name": "What is SEO?" },
        { "@type": "LearningResource", "name": "Keywords Research" }
      ]
    }
  ]
}
```

**Course with Syllabus (mapped from `mpcs_curriculum`):**
```json
{
  "@type": "Course",
  "name": "Digital Marketing Fundamentals",
  "numberOfLessons": 12,
  "syllabus": "Section 1: SEO Basics. 1.1 What is SEO?. 1.2 Keywords Research. Section 2: Content Marketing. 2.1 Content Strategy. 2.2 Writing for the Web."
}
```

---

## MemberPress Membership Integration

When MemberPress is active, the plugin provides comprehensive field mapping for membership products (`memberpressproduct` post type).

### Available Fields

The integration exposes 20+ membership-specific fields:

#### Pricing Fields

| Field | Type | Description |
|-------|------|-------------|
| `_mepr_product_price` | number | Membership price |
| `_mepr_product_period` | number | Billing period (e.g., 1, 3, 12) |
| `_mepr_product_period_type` | text | Period type: days, weeks, months, years, lifetime |

#### Trial Fields

| Field | Type | Description |
|-------|------|-------------|
| `_mepr_product_trial` | boolean | Has trial period |
| `_mepr_product_trial_days` | number | Trial duration in days |
| `_mepr_product_trial_amount` | number | Price during trial |

#### Billing Cycle Fields

| Field | Type | Description |
|-------|------|-------------|
| `_mepr_product_limit_cycles` | boolean | Whether billing cycles are limited |
| `_mepr_product_limit_cycles_num` | number | Number of billing cycles |
| `_mepr_product_limit_cycles_action` | text | Action after cycles complete |

#### Display Fields

| Field | Type | Description |
|-------|------|-------------|
| `_mepr_product_is_highlighted` | boolean | Featured/highlighted membership |
| `_mepr_product_pricing_title` | text | Custom pricing display title |
| `_mepr_product_pricing_display` | text | Price display format |
| `_mepr_product_pricing_heading_txt` | text | Pricing table heading |
| `_mepr_product_pricing_footer_txt` | text | Pricing table footer |
| `_mepr_product_pricing_button_txt` | text | Signup button text |
| `_mepr_product_pricing_benefits` | array | List of membership benefits |

#### Access Fields

| Field | Type | Description |
|-------|------|-------------|
| `_mepr_product_access_url` | url | URL after successful registration |
| `_mepr_product_who_can_purchase` | text | Purchase restrictions |
| `_mepr_product_expire_type` | text | How membership expires |
| `_mepr_product_expire_after` | number | Expiration period value |
| `_mepr_product_expire_unit` | text | Expiration period unit |

### Virtual/Computed Fields

In addition to raw meta fields, the integration provides computed fields:

| Field | Type | Description |
|-------|------|-------------|
| `mepr_formatted_price` | text | Price with currency symbol (e.g., "$99.00") |
| `mepr_billing_description` | text | Human-readable billing (e.g., "$99/month") |
| `mepr_registration_url` | url | Direct membership registration URL |

These fields are automatically calculated from the membership data.

### Product Schema Enhancement

When a `memberpressproduct` post type is assigned the Product schema, the integration automatically:

1. **Adds Offer data** with price, currency, and availability
2. **Sets category** to "Membership"
3. **Calculates price validity** based on billing period

### Example Output

**Monthly Membership:**
```json
{
  "@type": "Product",
  "name": "Pro Membership",
  "description": "Access to all premium features",
  "category": "Membership",
  "offers": {
    "@type": "Offer",
    "price": 29.00,
    "priceCurrency": "USD",
    "availability": "https://schema.org/InStock",
    "url": "https://example.com/membership/pro/?action=signup",
    "priceValidUntil": "2025-02-15"
  }
}
```

**Lifetime Membership:**
```json
{
  "@type": "Product",
  "name": "Lifetime Access",
  "offers": {
    "@type": "Offer",
    "price": 299.00,
    "priceCurrency": "USD",
    "availability": "https://schema.org/InStock"
  }
}
```

### Usage Tips

1. **Assign Product schema** to the `memberpressproduct` post type in Settings → Schema Markup → Post Types
2. **Map additional fields** like `description`, `image`, `brand` as needed
3. **Use virtual fields** for human-readable price displays in your templates

### Filters

The integration uses these filters:

- `smg_discovered_fields` - Adds membership fields to discovery
- `smg_resolve_field_value` - Resolves membership field values (sources: `memberpress`, `memberpress_virtual`)
- `smg_product_schema_data` - Enhances Product schema with offer data

---

## Advanced Settings

### Organization Info (Advanced) {#organization-info-advanced}

Navigate to **Settings → Schema Markup → Advanced** to customize your organization data.

| Field | Custom Setting | Fallback (if empty) |
|-------|----------------|---------------------|
| **Organization Name** | Text input | WordPress Site Title |
| **Organization URL** | URL input | WordPress Home URL |
| **Organization Logo** | Media Library | Theme Custom Logo (Customizer) |

**Logo Selection:**
1. Click "Select Logo" to open the WordPress Media Library
2. Choose an existing image or upload a new one
3. Click "Use this logo" to confirm
4. Save settings to apply

**Best Practices for Logo:**
- Use a square image, at least 112×112 pixels
- PNG or JPG format recommended
- Transparent or white background works best for Knowledge Panels

**Helper Function:**

Use `smg_get_organization_data()` to retrieve organization data programmatically:

```php
$org = \Metodo\SchemaMarkupGenerator\smg_get_organization_data();
// Returns: ['name' => string, 'url' => string, 'logo' => array|null]
```

**Filter:**

Customize organization data with the `smg_organization_data` filter:

```php
add_filter('smg_organization_data', function($data) {
    $data['name'] = 'Custom Name';
    return $data;
});
```

---

## Caching

### How Caching Works

1. Schema data is generated once and cached
2. Cache is keyed by post ID and modification date
3. Cache is automatically invalidated when post is updated

### Cache Types

**Object Cache (Preferred)**
When Redis or Memcached is available, the plugin uses WordPress object cache for optimal performance.

**Transients (Fallback)**
When object cache is not available, WordPress transients are used.

### Cache Settings

- **Enable Caching**: Toggle on/off
- **Cache TTL**: Time-to-live in seconds (default: 3600)
- **Clear Cache**: Manually clear all cached schema data

### Cache Invalidation

Cache is automatically cleared when:
- Post is saved or updated
- Post is deleted
- Plugin settings are changed

---

## REST API

### Endpoints

#### Get Schema for Post

```http
GET /wp-json/smg/v1/schema/{post_id}
```

**Response:**
```json
{
    "post_id": 123,
    "post_type": "post",
    "schemas": [...],
    "json_ld": "..."
}
```

#### Validate Schema

```http
POST /wp-json/smg/v1/validate
Content-Type: application/json

{
    "schema": {
        "@context": "https://schema.org",
        "@type": "Article",
        "headline": "Test Article"
    }
}
```

**Response:**
```json
{
    "valid": false,
    "errors": ["Missing required property: image"],
    "warnings": ["Missing recommended property: author"]
}
```

#### Get Settings (Admin Only)

```http
GET /wp-json/smg/v1/settings
```

#### Update Settings (Admin Only)

```http
POST /wp-json/smg/v1/settings
Content-Type: application/json

{
    "settings": {
        "enabled": true,
        "cache_enabled": true
    }
}
```

#### Clear Cache (Admin Only)

```http
POST /wp-json/smg/v1/cache/clear
```

Optional: Clear cache for specific post:
```json
{
    "post_id": 123
}
```

---

## Import/Export

The Import/Export feature allows you to backup settings or transfer them between sites.

### Export Settings

Navigate to **Settings → Schema Markup → Tools** to export:

1. Select options:
   - **Include post type mappings**: Include schema-to-post-type assignments
   - **Include field mappings**: Include custom field mappings
2. Click **Export Settings**
3. A JSON file will be downloaded

#### Export Format (v2.0)

The export uses auto-discovery, meaning all options with `smg_` prefix are automatically included:

```json
{
    "export_format": "2.0",
    "plugin_version": "1.8.0",
    "exported_at": "2024-01-15T12:00:00+00:00",
    "site_url": "https://example.com/",
    "options": {
        "smg_general_settings": { ... },
        "smg_advanced_settings": { ... },
        "smg_post_type_mappings": { ... },
        "smg_field_mappings": { ... },
        ...
    }
}
```

#### What's Included

- General settings (enabled, WebSite schema, breadcrumbs)
- Advanced settings
- Integration settings
- Post type mappings (which schema type for each post type)
- Page mappings (specific page settings)
- Field mappings (custom field to schema property)

#### What's Excluded (Security)

For security reasons, the following are **never** exported:

- GitHub tokens (encrypted or plain)
- API keys and secrets
- Any sensitive authentication data

### Import Settings

1. Navigate to **Settings → Schema Markup → Tools**
2. Click **Choose File** and select a previously exported JSON file
3. Select options:
   - **Create backup before importing**: Saves current settings to `smg_settings_backup`
   - **Merge with existing settings**: Combines imported settings with current ones
4. Click **Import Settings**

#### Backward Compatibility

The importer supports both:

- **v2.0 format**: New auto-discovery format
- **v1.0 format**: Legacy format from older exports

Old exports will be imported correctly and converted to the new format when re-exported.

#### Programmatic Export/Import

```php
use Metodo\SchemaMarkupGenerator\Tools\Exporter;
use Metodo\SchemaMarkupGenerator\Tools\Importer;

// Export
$exporter = new Exporter();
$data = $exporter->export(
    includeMappings: true,
    includeFieldMappings: true
);

// Get list of exportable options
$options = $exporter->getExportableOptions();

// Validate before import
$importer = new Importer();
$validation = $importer->validate($data);

if ($validation['valid']) {
    $importer->import($data, mergeExisting: false);
}
```

---

## Troubleshooting

### Schema Not Appearing

1. **Check if enabled**: Settings → Schema Markup → Enable Schema Markup
2. **Check post type mapping**: Ensure a schema type is assigned
3. **Check per-post setting**: The post may have schema disabled
4. **Clear cache**: Try clearing the schema cache
5. **Enable debug mode**: Check logs for errors

### Duplicate Schemas

If using Rank Math:
1. The plugin automatically detects Rank Math
2. Duplicate schema types are filtered out
3. Check Advanced tab for integration status

### Invalid Schema Errors

Use the built-in validation:
1. Open the post editor
2. Check the Schema Markup meta box
3. Click "Refresh" to see validation errors
4. Use "Google Rich Results Test" link to verify

### Debug Logging

1. Go to Settings → Schema Markup → Advanced
2. Enable Debug Mode
3. Check logs in `/wp-content/plugins/schema-markup-generator/logs/`

### Common Errors

**"Missing required property: image"**
- Ensure the post has a featured image
- Or map an image field to the `image` property

**"datePublished is not in valid ISO 8601 format"**
- Check the date field format
- Use date picker fields that return proper date strings

---

## Support

- **Documentation**: [docs/DOCUMENTATION.md](DOCUMENTATION.md)
- **Hooks Reference**: [docs/HOOKS.md](HOOKS.md)
- **Extending Guide**: [docs/EXTENDING.md](EXTENDING.md)
- **GitHub Issues**: [Report a bug](https://github.com/michelemarri/schema-markup-generator/issues)
- **Website**: [metodo.dev](https://metodo.dev)

