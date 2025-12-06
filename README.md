# Schema Markup Generator

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-purple)](https://php.net)
[![WordPress Version](https://img.shields.io/badge/WordPress-6.0%2B%20(tested%206.9)-blue)](https://wordpress.org)

Automatic schema.org structured data generation for WordPress, optimized for search engines and LLMs.

## Features

- **Auto-Discovery** - Automatically detects all post types, custom fields, and taxonomies
- **16 Schema Types** - Article, Product, Organization, Person, FAQ, HowTo, Event, Recipe, Review, Course, LearningResource and more
- **Smart Schema Suggestions** - Recommends schema types based on post type names
- **Property Documentation** - Click any property for detailed description, examples, and schema.org link
- **ACF Integration** - Full support for Advanced Custom Fields with visual field mapping
- **Rank Math Compatibility** - Prevents duplicate schemas when Rank Math is active
- **WooCommerce Integration** - Currency code and symbol from WooCommerce settings available for mapping
- **MemberPress Courses Integration** - Automatic Course/Lesson hierarchy for LearningResource schemas
- **MemberPress Membership Integration** - Full support for membership fields (price, period, trial, benefits, currency) with Product schema
- **Modern Admin UI** - Clean, tabbed interface for easy configuration
- **Schema Preview** - Real-time preview with validation in the post editor
- **REST API** - Full REST API for programmatic access
- **Caching** - Smart caching with Redis/Memcached support
- **Auto Updates** - Automatic updates from GitHub releases
- **Extensible** - Hooks and filters for custom schema types

## Requirements

- PHP 8.2 or higher
- WordPress 6.0 or higher
- Composer (for development)

## Installation

### From GitHub Release

1. Download the latest release ZIP from [Releases](https://github.com/michelemarri/schema-markup-generator/releases)
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Upload the ZIP file and activate

### From Source

```bash
git clone https://github.com/michelemarri/schema-markup-generator.git
cd schema-markup-generator
composer install
```

## Quick Start

1. Go to **Settings → Schema Markup** in WordPress admin
2. In the **Post Types** tab, assign a schema type to each post type
3. Optionally map custom fields to schema properties
4. Save changes - schema will be automatically generated for all posts

## Supported Schema Types

| Category | Types |
|----------|-------|
| Content | Article, BlogPosting, NewsArticle, WebPage |
| Business | Organization, LocalBusiness, Product |
| People & Reviews | Person, Review |
| Instructional | FAQPage, HowTo, Recipe, Course, LearningResource |
| Media & Events | Event, VideoObject |
| Technical | SoftwareApplication, WebSite, BreadcrumbList |

## Configuration

### Post Type Mapping

Assign a schema type to each post type. The plugin will automatically populate schema properties from post data.

### Field Mapping

Map custom fields to schema properties:

- WordPress native fields (title, excerpt, content, author, featured image)
- Custom fields from any source (ACF, Secure Custom Fields, other plugins)
- All public taxonomies (categories, tags, and custom taxonomies)

### Per-Post Override

Override the schema type or disable schema entirely for individual posts using the Schema Markup meta box.

## Hooks & Filters

### Register Custom Schema Types

```php
add_filter('smg_register_schema_types', function($types) {
    $types['CustomType'] = MyCustomSchema::class;
    return $types;
});
```

### Modify Schema Data

```php
// Type-specific filter (recommended)
add_filter('smg_article_schema_data', function($data, $post, $mapping) {
    $data['customProperty'] = 'value';
    return $data;
}, 10, 3);
```

### Filter Post Schemas

```php
add_filter('smg_post_schemas', function($schemas, $post) {
    // Modify or add schemas
    return $schemas;
}, 10, 2);
```

See [docs/HOOKS.md](docs/HOOKS.md) for complete hook reference.

## REST API

### Get Schema for Post

```
GET /wp-json/smg/v1/schema/{post_id}
```

### Validate Schema

```
POST /wp-json/smg/v1/validate
Content-Type: application/json

{
    "schema": { "@type": "Article", ... }
}
```

### Get Settings (Admin)

```
GET /wp-json/smg/v1/settings
```

## Development

### Project Structure

```
schema-markup-generator/
├── src/
│   ├── Admin/           # Admin UI components
│   │   └── Tabs/        # Settings page tabs
│   ├── Cache/           # Caching layer
│   ├── Discovery/       # Post type/field discovery
│   ├── Integration/     # Third-party integrations
│   ├── Logger/          # Debug logging
│   ├── Mapper/          # Field mapping
│   ├── Rest/            # REST API
│   ├── Schema/          # Schema types and rendering
│   │   └── Types/       # Schema type implementations
│   ├── Tools/           # Import/Export
│   ├── Updater/         # GitHub auto-updates
│   └── Plugin.php       # Main plugin class
├── assets/
│   ├── src/             # Source files
│   │   ├── css/         # Tailwind CSS source
│   │   │   └── components/  # Design system components
│   │   └── js/          # ES6+ JavaScript source
│   ├── css/             # Compiled CSS
│   └── js/              # Compiled JavaScript
├── docs/                # Documentation
├── vendor/              # Composer dependencies
├── package.json         # Node.js dependencies
├── tailwind.config.js   # Tailwind configuration
└── postcss.config.js    # PostCSS configuration
```

### Building

```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies (for CSS/JS build)
npm install

# Build CSS and JS assets
npm run build

# Watch for changes during development
npm run watch

# For production (no dev dependencies)
composer install --no-dev --optimize-autoloader
npm run build
```

### Asset Build System

The plugin uses **Tailwind CSS** with a modern build system:

- `assets/src/css/` - Source CSS files with Tailwind utilities
- `assets/src/js/` - Source JavaScript (ES6+)
- `assets/css/` - Compiled CSS (do not edit directly)
- `assets/js/` - Compiled JavaScript (do not edit directly)

**Design System:**

The plugin includes a micro design system with:
- Custom color tokens (primary, accent, success, warning, error)
- Typography scale
- Spacing system
- Shadow and radius utilities
- Animation library
- Responsive breakpoints

All tokens are defined in `assets/src/css/components/tokens.css` and synced with `tailwind.config.js`.

### Creating a Release

1. Update version in `schema-markup-generator.php`
2. Commit changes
3. Create a tag: `git tag v1.0.0`
4. Push: `git push origin v1.0.0`
5. GitHub Actions will create the release automatically

## Contributing

Contributions are welcome! Please read our contributing guidelines before submitting pull requests.

## Support

- [Documentation](docs/DOCUMENTATION.md)
- [GitHub Issues](https://github.com/michelemarri/schema-markup-generator/issues)
- [Metodo.dev](https://metodo.dev)

## Changelog

### 1.19.0
- **New**: MemberPress virtual fields for schema.org subscription properties
- **New**: `mepr_eligible_duration` - ISO 8601 duration format (P1M, P1Y, P3M) ready for `eligibleDuration`
- **New**: `mepr_billing_duration` - Numeric billing period (1, 3, 12) ready for `billingDuration`
- **New**: `mepr_billing_increment` - Schema.org format (Month, Year, Week, Day) ready for `billingIncrement`
- **Improved**: MemberPress period types (days, weeks, months, years) now automatically converted to schema.org formats

### 1.18.0
- **New**: WooCommerce Integration - exposes `woo_currency_code` and `woo_currency_symbol` as mappable fields
- **New**: MemberPress currency field `mepr_currency_code` now available for schema mapping
- **New**: Currency fields automatically use ISO 4217 codes from plugin settings (WooCommerce/MemberPress)
- **Improved**: Integrations tab shows current currency configuration for WooCommerce and MemberPress
- **Use case**: Map `woo_currency_code` or `mepr_currency_code` to `priceCurrency` in Product, Course, Event schemas

### 1.17.3
- **Updated**: WordPress compatibility tested up to 6.9

### 1.17.2
- **Fixed**: Manual update check now works correctly by using Plugin Update Checker directly instead of WordPress standard `wp_update_plugins()`
- **Improved**: Update check results now show both current and latest version for better clarity

### 1.17.1
- **Fixed**: Integration status badges now show correctly: "Not Detected" (grey), "Detected" (amber), "Active" (green)
- **Fixed**: Integration enable/disable toggles now actually work - disabled integrations don't register hooks
- **Fixed**: Integration settings sections are hidden when integration is disabled
- **Improved**: New `initializeIntegrations()` method for cleaner integration management

### 1.17.0
- **Fixed**: MemberPress Memberships integration now visible in the Integrations tab
- **New**: Integration card for MemberPress Memberships with enable/disable toggle
- **New**: MemberPress Memberships settings section showing detected memberships and available fields
- **New**: Integration toggle `integration_memberpress_memberships_enabled` for granular control

### 1.16.0
- **New**: Customizable Organization Info - Override site name, URL, and logo for schema markup
- **New**: Organization logo uploader - Select a specific logo independent from theme Custom Logo
- **New**: Fallback system - Empty fields automatically use WordPress defaults (Site Name, Home URL, Custom Logo)
- **New**: Helper function `smg_get_organization_data()` returns organization data with fallbacks
- **New**: Filter `smg_organization_data` to customize organization data
- **Improved**: Organization data now used consistently in all schemas (Article publisher, Organization schema, etc.)

### 1.15.0
- **New**: MemberPress Membership integration for `memberpressproduct` post type
- **New**: 20+ membership-specific fields available for mapping (price, period, trial, benefits, etc.)
- **New**: Virtual computed fields: formatted price, billing description, registration URL
- **New**: Automatic Product schema enhancement with Offer data for memberships
- **New**: Filter `smg_product_schema_data` now includes membership offer data automatically
- **New**: Filter `smg_resolve_field_value` supports `memberpress` and `memberpress_virtual` sources

### 1.14.1
- **Improved**: Custom fields now grouped by source plugin in field mapping dropdown
- **New**: Auto-detection of plugin source from field prefixes (Rank Math, MemberPress, WooCommerce, Affiliate WP, etc.)
- **Improved**: ACF fields grouped by their original ACF group name
- **Improved**: Better organized field selection with separate optgroups per plugin

### 1.14.0
- **New**: Product schema now supports subscription products with `Offer` + `PriceSpecification`
- **New**: Added `eligibleDuration` property for subscription duration (ISO 8601: P1M, P1Y)
- **New**: Added `UnitPriceSpecification` with `billingDuration` and `billingIncrement` for recurring billing
- **New**: Smart duration parsing - accepts ISO 8601, numbers (assumed months), or text like "1 month"
- **Improved**: Product schema description updated to mention MemberPress and WooCommerce Subscriptions support
- **Use case**: Ideal for memberships, SaaS products, subscription boxes, and recurring plans

### 1.13.1
- **Improved**: Token Security UI - Security information now displayed as compact grid below the GitHub token input field
- **Improved**: Cleaner Update tab layout - Removed separate "Security Information" section, integrated into GitHub Authentication card

### 1.13.0
- **New**: Automatic HTML sanitization for all mapped field values across all schema types
- **New**: `getMappedValue()` now automatically strips HTML tags from text fields (URLs and emails preserved)
- **New**: Raw WordPress meta dumps are automatically detected and filtered out
- **New**: Filter `smg_sanitize_mapped_value` to customize sanitization behavior
- **Improved**: Cleaner schema output without HTML artifacts from WYSIWYG fields

### 1.12.0
- **New**: Schema Example modal - Click the eye icon next to any post type to see a real schema example generated from a random published post
- **New**: Copy and refresh actions in example modal - Easily copy the JSON-LD or load a different random post
- **New**: Direct links to edit/view the example post from the modal

### 1.11.2
- **Removed**: Lightbulb icon next to post type names - redundant with existing recommendation indicators (★ star and "Recommended" badge)

### 1.11.1
- **New**: Website fields in field mapping - Site Name and Site URL are now available as mappable sources

### 1.11.0
- **New**: Taxonomies available in field mapping - All public taxonomies (including custom) are now mappable to schema properties
- **Fixed**: Duplicate custom fields - Fields discovered via ACF no longer appear twice in the dropdown
- **Improved**: Removed source indicator "(ACF)" from custom fields label - all custom fields are now shown uniformly without specifying their origin

### 1.10.0
- **New**: Auto-save for schema mappings - Schema type and field mappings now save automatically when you make a selection (no Save button needed)
- **Improved**: Visual feedback on save - Green flash and checkmark indicate successful saves
- **Improved**: Better UX in Post Types tab - No more form submissions required
- **Added**: AJAX handlers for `smg_save_schema_mapping` and `smg_save_field_mapping`
- **Added**: CSS animations for save state feedback

### 1.9.0
- **New**: Property information modal - Click on any schema property name to see detailed description, examples, and schema.org documentation link
- **New**: Smart schema suggestions - Plugin now recommends schema types based on post type names (e.g., "recipes" → Recipe schema, "guides" → LearningResource)
- **New**: Auto-selection of recommended schema for unconfigured post types
- **New**: "Recommended" badge displayed next to suggested schema types
- **Improved**: All 16 schema types now include `description_long`, `example`, and `schema_url` for comprehensive documentation
- **New**: Added `SchemaRecommender` class for intelligent post type → schema matching (English patterns)

### 1.8.1
- **Changed**: Repository is now public - automatic updates work without GitHub token
- **Removed**: Admin notice for missing token (no longer required for public repo)

### 1.8.0
- **Improved**: Export/Import system now uses auto-discovery (format 2.0)
- **New**: All settings with `smg_` prefix are automatically included in exports
- **New**: Future settings will be exported/imported automatically without code changes
- **New**: Backward compatible - imports both old and new format exports
- **Security**: Sensitive data (tokens, API keys) are automatically excluded from exports
- **Added**: `Exporter::getExportableOptions()` method to list all exportable options
- **Added**: `Importer::validate()` method for pre-import validation

### 1.7.0
- **Changed**: Migrated from Metodo Design System (mds-*) to Tailwind CSS
- **Changed**: CSS class prefix changed from `mds-*` to `smg-*` for consistency
- **Changed**: Asset handle names changed from `mds-admin` to `smg-admin`
- **Changed**: Log file prefix changed from `mds-` to `smg-`
- **Changed**: Export file prefix changed from `mds-` to `smg-`
- **Improved**: Lighter CSS bundle with only required Tailwind utilities
- **Improved**: Consistent styling approach across Metodo plugins

### 1.6.0
- **Changed**: Namespace updated from `flavor\SchemaMarkupGenerator` to `Metodo\SchemaMarkupGenerator`
- **Changed**: Contact email updated to plugins@metodo.dev
- **Note**: If you have custom code extending this plugin, update your `use` statements accordingly

### 1.4.1
- **Added**: Visual indicator (green border) for post types with schema configured
- **Improved**: Mapped state updates dynamically when changing schema type

### 1.4.0
- **New**: Dynamic field mapping - schema properties now load instantly when changing schema type (no save required)
- **New**: Auto-expand field mapping panel when selecting a schema type
- **Improved**: Better UX with loading animation during field mapping updates
- **Fixed**: HowTo schema step extraction now works with any H2/H3/H4 heading sequence (no numbering required)
- **Fixed**: HowTo supply and tool fields now properly validate data, filtering out invalid values (ACF field names, IDs, HTML content, etc.)
- **Improved**: Step text extraction with better HTML parsing and whitespace normalization
- **Improved**: Gutenberg block support for step extraction

### 1.3.2
- **Fixed**: Plugin icons not showing in WordPress updates screen
- **Improved**: Added 'default' icon key for better WordPress compatibility

### 1.3.1
- **Fixed**: Update checker showing cryptic 404 errors for private repositories without token
- **Improved**: Clear admin notice on plugins page when GitHub token is missing for private repos
- **Added**: Direct link to configure token in the missing token notice

### 1.3.0
- **Major refactoring**: Complete redesign of settings architecture
- **New**: Each tab now has its own WordPress settings group (smg_general, smg_post_types, smg_pages, etc.)
- **New**: Separated options for each settings section (smg_general_settings, smg_advanced_settings, smg_integrations_settings)
- **New**: Automatic migration from legacy smg_settings to new format
- **New**: Helper function `smg_get_settings($section)` for accessing settings
- **Fixed**: Settings from different tabs being reset when saving another tab (now impossible by design)
- **Improved**: Export/Import now supports both legacy and new settings format

### 1.2.1
- **Fixed**: Settings from different tabs being reset when saving another tab
- **Improved**: Each settings tab now saves independently, preserving settings from other tabs

### 1.2.0
- Initial stable release with full feature set
- 16 schema types supported
- ACF, Rank Math, WooCommerce, and MemberPress Courses integrations
- REST API for programmatic access
- Modern admin UI with tabbed interface

## License

This project is licensed under the GPL v3 License - see the [LICENSE](LICENSE) file for details.

## Credits

Developed by **Michele Marri** at [Metodo.dev](https://metodo.dev)

### Dependencies

- [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) by Yahnis Elsts
