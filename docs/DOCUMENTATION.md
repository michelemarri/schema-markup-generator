# Schema Markup Generator - Documentation

Complete documentation for the Schema Markup Generator WordPress plugin.

## Table of Contents

1. [Installation](#installation)
2. [Configuration](#configuration)
3. [Schema Types](#schema-types)
4. [Field Mapping](#field-mapping)
5. [ACF Integration](#acf-integration)
6. [MemberPress Courses Integration](#memberpress-courses-integration)
7. [Caching](#caching)
8. [REST API](#rest-api)
9. [Import/Export](#importexport)
10. [Troubleshooting](#troubleshooting)

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

Organization data is automatically pulled from WordPress settings:
- Site Name (from General Settings)
- Site URL
- Logo (from Customizer → Site Identity)

### Post Type Configuration

In the **Post Types** tab, you can:

1. **Assign Schema Types**: Select a schema type for each post type
2. **Configure Field Mappings**: Map custom fields to schema properties
3. **View Available Fields**: See all discoverable fields (ACF, meta, etc.)

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

For e-commerce and product pages.

**Required fields:**
- `name` - Product name
- `image` - Product image
- `offers` - Price and availability

**Recommended fields:**
- `description`
- `brand`
- `sku`
- `aggregateRating`

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

### WordPress Default Fields

These fields are automatically available:
- `post_title` - Post title
- `post_excerpt` - Post excerpt
- `post_content` - Full content
- `post_date` - Publication date
- `post_modified` - Last modified date
- `featured_image` - Featured image URL
- `author` - Author name

### Custom Meta Fields

Any public post meta can be mapped to schema properties.

### ACF Fields

When ACF is installed, all field groups assigned to a post type are automatically discovered:
- Text fields
- Image fields (returns URL)
- Gallery fields (returns array of URLs)
- Date/time fields
- Repeater fields
- Group fields

### Mapping Syntax

**Simple mapping:**
```
Schema Property → Field Key
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
   - Courses (`mpcs-course`) include curriculum structure with sections and lessons
   - Lesson count is automatically calculated

### Supported Post Types

| Post Type | Schema Type | Features |
|-----------|-------------|----------|
| `mpcs-course` | Course | Curriculum, lesson count, sections |
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

**Course with Curriculum:**
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

### No Configuration Required

The integration works automatically when MemberPress Courses is detected. No additional configuration is needed.

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

