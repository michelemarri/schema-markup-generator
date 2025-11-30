# Schema Markup Generator

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-purple)](https://php.net)
[![WordPress Version](https://img.shields.io/badge/WordPress-6.0%2B-blue)](https://wordpress.org)

Automatic schema.org structured data generation for WordPress, optimized for search engines and LLMs.

## Features

- **Auto-Discovery** - Automatically detects all post types, custom fields, and taxonomies
- **16 Schema Types** - Article, Product, Organization, Person, FAQ, HowTo, Event, Recipe, Review, Course, LearningResource and more
- **ACF Integration** - Full support for Advanced Custom Fields with visual field mapping
- **Rank Math Compatibility** - Prevents duplicate schemas when Rank Math is active
- **MemberPress Courses Integration** - Automatic Course/Lesson hierarchy for LearningResource schemas
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

Map custom fields (including ACF fields) to schema properties:

- WordPress native fields (title, excerpt, content, author)
- ACF text, image, date, and repeater fields
- Custom post meta
- Taxonomy terms

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

## License

This project is licensed under the GPL v3 License - see the [LICENSE](LICENSE) file for details.

## Credits

Developed by **Michele Marri** at [Metodo.dev](https://metodo.dev)

### Dependencies

- [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) by Yahnis Elsts
