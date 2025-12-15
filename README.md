# Schema Markup Generator

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-purple)](https://php.net)
[![WordPress Version](https://img.shields.io/badge/WordPress-6.0%2B%20(tested%206.9)-blue)](https://wordpress.org)

Automatic schema.org structured data generation for WordPress, optimized for search engines and LLMs.

## Features

- **Auto-Discovery** - Automatically detects all post types, custom fields, and taxonomies
- **Auto-detect Videos** - Automatically adds VideoObject schema when YouTube/Vimeo videos are detected in content
- **18 Schema Types** - Article, Product, Organization, Person, FAQ, HowTo, Event, Recipe, Review, Course, LearningResource, WebApplication, FinancialProduct and more
- **additionalType Support** - Add more specific Schema.org types to any schema (e.g., add "MobileApplication" to SoftwareApplication)
- **Smart Schema Suggestions** - Recommends schema types based on post type names
- **Property Documentation** - Click any property for detailed description, examples, and schema.org link
- **Custom Fields Integration** - Full support for ACF and Secure Custom Fields (SCF) with visual field mapping
- **Rank Math Compatibility** - Prevents duplicate schemas when Rank Math is active
- **WooCommerce Integration** - Complete WooCommerce product fields available for mapping (40+ fields)
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
| Technical | SoftwareApplication, WebApplication, WebSite, BreadcrumbList |
| Financial | FinancialProduct |

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

### 1.39.5
- **New**: Automatic cache flush on plugin update
- **Improved**: Schema cache is automatically cleared when plugin version changes
- **Improved**: Works with any update method (WordPress Updater, FTP, Git)
- **Improved**: Ensures fresh schema generation after plugin updates

### 1.39.4
- **Fixed**: HTML entities in description now decoded (`&nbsp;` → space, `&amp;` → &, etc.)
- **Fixed**: URLs removed from description text (they belong in `embedUrl`/`contentUrl`, not in description)
- **Fixed**: Organization now included in `@graph` when WebSite schema is rendered
- **Impact**: Resolves "Missing Organization referenced by WebSite" validation error
- **Impact**: Cleaner description text without HTML artifacts or embedded URLs

### 1.39.3
- **Fixed**: LearningResource `isPartOf` Course now includes all Google-required properties
- **Fixed**: Parent Course now includes `description`, `provider`, and `hasCourseInstance` with `offers`
- **Impact**: Resolves Google Rich Results Test errors: "A value for the description/offers/provider/hasCourseInstance field is required"
- **Note**: Google recognizes nested Course as Course entity regardless of minimal reference, so all required fields are now included

### 1.39.2
- **Fixed**: LearningResource `isPartOf` Course reference now uses minimal reference pattern (only `@type`, `@id`, `name`, `url`)
- **Fixed**: Removed `description` and `provider` from nested Course to prevent Google from confusing LearningResource with standalone Course
- **Impact**: Resolves Google Search Console warning "Course structured data detected on lesson pages"

### 1.39.1
- **Improved**: Video chapters now extracted from `<p class="video-chapters">` elements
- **Improved**: Support for timestamps wrapped in HTML tags (`<strong>00:00</strong> – Title`)
- **Improved**: Better pattern matching for various HTML chapter formats

### 1.39.0
- **New**: Video Chapters support - Auto-extracts `hasPart` with `Clip` elements for VideoObject schema
- **New**: Virtual field `mpcs_video_chapters` for MemberPress Courses lessons
- **New**: Chapters extracted from meta fields: `video_chapters`, `lesson_video_chapters` (standard meta or ACF)
- **New**: Chapters extracted from content timestamp patterns (e.g., "0:00 Introduction", "1:30 Main Topic")
- **New**: Chapters extracted from "Video Chapters" / "Timestamps" / "Capitoli" content sections
- **New**: Filter `smg_video_chapters` to provide chapters from integrations
- **Improved**: Chapter URLs use `#t=offset` format for embedded video player navigation
- **Improved**: Pattern matching now supports `<br>`, `<li>` elements in addition to newlines
- **SEO Impact**: Google shows "Key Moments" in video search results for videos with chapters

### 1.38.7
- **New**: Transcript now extracted from `lesson_transcription` meta field (MemberPress Courses)
- **New**: HowToSchema now auto-extracts video from content with transcript
- **New**: RecipeSchema now auto-extracts video from content with transcript
- **New**: CourseSchema now auto-extracts video from content with transcript
- **New**: MemberPress Courses integration now exposes `mpcs_lesson_transcription` virtual field
- **Fixed**: Transcript extraction regex lookahead was matching `[MM:SS]` instead of full `[HH:MM:SS]` format
- **Fixed**: Transcript now correctly extracted from content with timestamps like `[00:00:01.20]`
- **New**: Support for `<details class="lesson-transcription">` accordion elements
- **Improved**: Transcript extraction priority: 1) meta field, 2) ACF field, 3) content patterns
- **Improved**: All schemas with nested video now get transcript extraction automatically
- **Improved**: Pattern 3 now matches `lesson-transcription`, `transcript`, `transcription`, `video-transcript` classes
- **Improved**: Pattern 3 now supports `<details>` element in addition to `<div>` and `<section>`

### 1.38.5
- **New**: Auto-fetch thumbnail from YouTube/Vimeo when no featured image is set (fixes Google Search Console "No thumbnail URL provided" error)
- **New**: Uses YouTube API for high-quality thumbnails (if API key configured), otherwise uses standard YouTube thumbnail URLs
- **New**: Vimeo thumbnail support via oEmbed API (no authentication required)
- **Improved**: Thumbnail fallback chain: mapped field → featured image → YouTube/Vimeo thumbnail

### 1.38.4
- **Improved**: Description length now optimized per schema type (Google recommended limits)
- **Improved**: VideoObject description increased to 2048 chars (was 160)
- **Improved**: Article/Course/LearningResource description increased to 500 chars
- **Improved**: Product description increased to 5000 chars
- **Improved**: Default description increased to 320 chars
- **Improved**: Smart word-boundary truncation for cleaner output

### 1.38.3
- **Fixed**: VideoObject standalone no longer added when schema contains nested video (LearningResource, Article, HowTo, Recipe, Course)
- **New**: LearningResource video now includes auto-extracted transcript from page content
- **Improved**: Schemas with nested video property now get transcript extraction automatically

### 1.38.2
- **Fixed**: Duration and transcript now auto-extracted for ALL VideoObject schemas (configured or auto-detected)
- **Fixed**: VideoObject standalone not duplicated when schema is already configured as VideoObject
- **Improved**: Moved duration/transcript extraction logic to VideoObjectSchema for consistent behavior
- **Improved**: YouTube API duration fetch now works for any VideoObject with YouTube embed URL

### 1.38.1
- **New**: Auto-extract video transcript from page content for VideoObject schema
- **New**: Detects transcript sections by heading ("Video Transcription", "Transcript", "Trascrizione")
- **New**: Detects transcript by timestamp patterns (`[00:00:01]` format)
- **New**: Detects transcript by CSS class (`.transcript`, `.video-transcript`)
- **Improved**: Cleans transcript by removing timestamps and speaker labels for clean schema output
- **Note**: Transcript limited to 5000 characters to keep schema size reasonable

### 1.38.0
- **New**: Auto-detect video schema - Automatically adds VideoObject schema when YouTube or Vimeo videos are detected in content
- **New**: Toggle option in General Settings to enable/disable video auto-detection
- **New**: YouTube API integration for auto-detected videos (fetches duration and thumbnail if API key is configured)
- **New**: Support for WordPress video blocks and self-hosted videos detection
- **New**: Filter `smg_auto_detected_video_schema` to customize auto-detected video schema data

### 1.37.3
- **Fixed**: Nested Offer `category` field now always included (fixes Semrush "A value for the category field is required for a nested Offer" error)
- **Fixed**: Event schema `offers.category` defaults to "primary" (tickets from original seller)
- **Fixed**: Course schema `offers.category` defaults to "Fees" (standard category for course pricing)
- **New**: `offerCategory` property added to Event and Course schemas for customization

### 1.37.2
- **Improved**: Nested Course in `isPartOf` now includes `provider` for complete context

### 1.37.1
- **Improved**: LearningResource now includes `@id` and `mainEntityOfPage` for proper entity identification
- **Improved**: Nested Course in `isPartOf` now uses `@id` reference pattern to avoid SEO tools validating it as incomplete
- **Note**: Google deprecated Course rich results in 2024 - LearningResource is now the recommended schema for lessons

### 1.37.0
- **Breaking**: Course curriculum now uses semantically correct `hasPart` with `LearningResource` instead of `hasCourseInstance`
- **Improved**: Course sections are now `LearningResource` with `learningResourceType: "module"` and `position`
- **Improved**: Course lessons include `learningResourceType: "Lesson"` and `position` within their section
- **Improved**: `hasCourseInstance` is now reserved for the main course offering (with pricing, instructor, schedule)
- **Note**: Google deprecated Course rich results in June 2024, but schema remains useful for LLMs and other search engines

### 1.36.2
- **Fixed**: CourseInstance now includes required `courseWorkload` property
- **New**: `courseWorkload` auto-generated from course duration (e.g., "Approximately 10 hours of self-paced learning")

### 1.36.1
- **Fixed**: Removed invalid `numberOfLessons` property from Course schema (not recognized by Schema.org vocabulary)

### 1.36.0
- **New**: LearningResource `learningResourceType` now auto-detected from content analysis
- **New**: Auto-detection rules: Video (embedded videos), Quiz (forms/assessments), Tutorial (step-by-step structure), Exercise (code blocks + interactive), Reading (text-heavy), Lecture (video+text), Lesson (fallback)
- **New**: LearningResource `interactivityType` now auto-detected: active (quizzes/forms), expositive (video/reading), mixed (both)
- **New**: Virtual fields `mpcs_learning_resource_type` and `mpcs_interactivity_type` for MemberPress Courses lessons
- **Improved**: Content analysis detects quiz plugins (Quiz Master, LearnDash, WPForms, Gravity Forms), tutorial structure, code blocks, interactive elements

### 1.35.1
- **Improved**: Auto-populated properties now show the integration name (e.g., "Auto: MemberPress Courses")
- **Improved**: Custom CSS-only tooltip appears instantly on hover with detailed description
- **Improved**: Clearer indication of which integration provides each auto-populated value
- **Improved**: UI consistency across MetaBox, PostTypesTab, and SchemaPropertiesHandler

### 1.35.0
- **New**: LearningResource `timeRequired` now auto-calculated when not mapped
- **New**: Auto-calculation combines text reading time (~200 words/minute) + embedded video duration
- **New**: Filter `smg_learning_resource_auto_time_required` to customize auto-calculated time
- **Improved**: `timeRequired` property shows "AUTO" badge indicating automatic calculation
- **Improved**: More accurate learning time estimates that account for both reading and watching

### 1.34.0
- **New**: LearningResource auto-populates `isPartOf` from MemberPress Courses lesson hierarchy (lesson → section → course)
- **New**: LearningResource auto-populates `position` with global curriculum position (counts all lessons across all sections)
- **New**: Virtual fields for MemberPress Courses lessons: `mpcs_lesson_position`, `mpcs_parent_course_name`, `mpcs_parent_course_url`
- **Improved**: Schema properties now show "AUTO" badge when auto-populated from integrations
- **Improved**: UI clearly indicates which properties are automatically handled by MemberPress Courses integration

### 1.33.3
- **Improved**: LearningResource schema now auto-fetches video duration via YouTube Data API when video is detected in content
- **Improved**: YouTube API takes precedence over oEmbed for more accurate video duration (oEmbed as fallback for Vimeo/others)

### 1.33.1
- **Fixed**: WordPress 6.7+ translation loading warning - Lazy load translations to avoid "loaded too early" notice

### 1.33.0
- **New**: Auto-save for integration settings - Changes in integration modals are saved automatically
- **Improved**: No need to close modal and click "Save Changes" - toggle any setting and it's saved instantly
- **Improved**: Visual feedback with "Saved" toast notification when settings change
- **Improved**: Modal footers now show "Changes are saved automatically" message

### 1.32.0
- **New**: YouTube Data API v3 Integration - Accurate video duration extraction for Course schemas
- **New**: Secure API key storage with AES-256-CBC encryption (using WordPress AUTH_KEY)
- **New**: YouTube API configuration modal in Integrations tab
- **New**: API key test, save, and remove functionality via AJAX
- **New**: Video details retrieval (duration, title, thumbnail, channel) via YouTube Data API
- **New**: Results cached for 1 week to minimize API usage (10,000 free units/day)
- **Improved**: Course duration calculation now uses YouTube API (when configured) instead of oEmbed fallback
- **Improved**: Clear instructions for obtaining a free Google Cloud API key
- **Security**: API key is never stored in plaintext - always encrypted at rest

### 1.31.0
- **New**: Auto-calculated course duration - MemberPress Courses now automatically calculates total course duration from lesson videos
- **New**: "Calculate All Course Durations" button in MemberPress Courses integration modal
- **New**: Virtual field `mpcs_total_duration` - Returns total course duration in minutes (maps to `duration`)
- **New**: Virtual field `mpcs_total_duration_hours` - Returns total course duration in hours (maps to `duration`)
- **New**: Automatic video duration extraction from YouTube and Vimeo embeds via oEmbed API
- **New**: Duration is calculated and saved when lessons are saved - zero performance impact on frontend
- **New**: Background calculation via WP-Cron for courses not yet calculated
- **New**: Progress bar and results display when calculating durations
- **Improved**: Course schema automatically includes `timeRequired` from calculated duration when not explicitly mapped
- **Improved**: Supports multiple duration input formats: seconds, minutes, HH:MM:SS, MM:SS, ISO 8601 (PT1H30M)
- **Performance**: oEmbed calls happen only in admin when saving lessons, not during page render

### 1.30.0
- **Added**: Taxonomy Schema Mapping - Assign schema types (DefinedTerm, Place, Person, Brand, etc.) to taxonomies
- **Added**: Taxonomy Archive Schema Rendering - Schemas now render on category, tag, and custom taxonomy archive pages
- **Added**: Sub-tabs navigation system - Grouped Post Types, Taxonomies, and Pages under unified "Schemas" tab
- **Added**: Modern sub-tabs UI with smooth transitions and active state indicators
- **Added**: Smart taxonomy schema suggestions based on taxonomy name/slug (locations → Place, authors → Person, brands → Brand)
- **Added**: Auto-save for taxonomy schema mappings via AJAX
- **Added**: `smg_save_taxonomy_mapping` AJAX handler
- **Added**: `smg_taxonomy_schema` filter for customizing taxonomy schema data
- **Added**: New option `smg_taxonomy_mappings` for storing taxonomy-to-schema assignments
- **Added**: Support for multiple taxonomy schema types: DefinedTerm, ItemList, CollectionPage, Place, Person, Organization, Brand
- **Added**: Hierarchical breadcrumb generation for taxonomy archives
- **Added**: ACF integration for taxonomy term meta (image, logo fields)
- **Improved**: Settings page organization with cleaner tab hierarchy

### 1.29.1
- **Fixed**: Currency now auto-detected from MemberPress/WooCommerce settings in all schema types
- **Fixed**: Product, Event, SoftwareApp, WebApplication, HowTo, FinancialProduct schemas now use site currency instead of hardcoded EUR fallback
- **Fixed**: Currency detection now respects integration enabled/disabled settings - only reads from active integrations
- **Changed**: Default fallback currency changed from EUR to USD when no ecommerce plugin is detected

### 1.29.0
- **New**: FinancialProduct schema type - for loans, mortgages, bank accounts, credit cards, insurance policies, and investment products
- **New**: Supports key financial properties: interestRate, annualPercentageRate (TAEG/APR), feesAndCommissionsSpecification, loanTerm, amount
- **New**: 20+ predefined categories for financial products (Loan, MortgageLoan, CreditCard, SavingsAccount, Insurance, etc.)

### 1.28.0
- **New**: `additionalType` dropdown moved next to the main schema type selector for better UX
- **New**: Select any schema type (Article, Product, WebApplication, etc.) as an additional type directly from the header row
- **Improved**: `additionalType` no longer appears in the field mapping table - cleaner interface
- **Improved**: Auto-saves when you change the additional type, just like the main schema type
- **Note**: Schema types are automatically converted to full Schema.org URLs (e.g., `WebApplication` → `https://schema.org/WebApplication`)

### 1.27.1
- **Fixed**: WebApplication schema now includes `additionalType` property in field mapping
- **Fixed**: WebApplication schema correctly passes mapping data to `buildBase()` for `additionalType` support

### 1.27.0
- **New**: WebApplication schema type - for web-based applications, SaaS platforms, and online tools
- **New**: `additionalType` property available on ALL schema types - allows specifying more specific Schema.org types
- **Note**: `additionalType` is automatically normalized to full Schema.org URL (e.g., "MobileApplication" becomes "https://schema.org/MobileApplication")
- **Note**: Google, Bing, and LLMs only recognize `additionalType` when it's a full URL pointing to Schema.org

### 1.26.0
- **New**: Integration settings now accessible via modal dialogs
- **New**: "Settings" button on each integration card opens a modal with all configuration options
- **Improved**: Cleaner integrations page - settings are no longer displayed inline below cards
- **Improved**: Better UX with modal dialogs for Rank Math, ACF/SCF, WooCommerce, and MemberPress Courses settings

### 1.25.0
- **New**: Option to completely disable all Rank Math schema output
- **New**: "Disable All Rank Math Schemas" toggle in Integrations → Rank Math Settings
- **Improved**: When all Rank Math schemas are disabled, SMG handles all structured data exclusively
- **Improved**: Duplicate prevention and takeover options are disabled when "Disable All" is active

### 1.23.0
- **Fixed**: Course schema now compliant with schema.org specification
- **Changed**: `instructor`, `courseMode`, and `offers` now correctly placed in `CourseInstance` (not directly on `Course`)
- **New**: Course schema automatically generates `hasCourseInstance` with instructor, courseMode, and offers
- **New**: `courseWorkload` property added for CourseInstance (e.g., "2 hours lectures + 3 hours study per week")
- **Improved**: Explicit course instances (via `hasCourseInstance` mapping) inherit instructor/courseMode/offers from defaults
- **Improved**: Property definitions updated with clearer schema.org placement information
- **Note**: This fixes Google Rich Results Test and schema.org validator warnings about `instructor` and `courseMode` on Course type

### 1.22.2
- **Fixed**: ACF/SCF custom fields appearing duplicated in field mapping dropdown
- **Refactored**: Centralized ACF field discovery in ACFIntegration class

### 1.22.1
- **Improved**: Generic support for ACF and SCF (Secure Custom Fields) - both plugins are now recognized
- **Improved**: Integration labels adapt to the detected plugin (ACF or SCF)
- **Removed**: ACF detection notice from Post Types tab (not needed)
- **Improved**: All UI labels now use generic "Custom Fields" terminology

### 1.22.0
- **New**: Complete WooCommerce Integration - 40+ product fields now available for schema mapping
- **New**: WooCommerce product fields organized by category:
  - **Pricing**: `woo_price`, `woo_regular_price`, `woo_sale_price`, `woo_price_html`
  - **Identifiers**: `woo_sku`, `woo_gtin`, `woo_mpn` (auto-detects from common GTIN plugins)
  - **Stock**: `woo_stock_status`, `woo_stock_quantity`, `woo_is_in_stock`, `woo_backorders_allowed`
  - **Reviews**: `woo_average_rating`, `woo_review_count`, `woo_rating_count`
  - **Promotions**: `woo_sale_price_dates_from`, `woo_sale_price_dates_to`, `woo_is_on_sale`
  - **Dimensions**: `woo_weight`, `woo_dimensions`, `woo_length`, `woo_width`, `woo_height`
  - **Taxonomies**: `woo_product_category`, `woo_product_categories`, `woo_product_tags`, `woo_product_brand`
  - **Images**: `woo_main_image`, `woo_gallery_images`, `woo_all_images`
  - **Product Info**: `woo_product_type`, `woo_is_virtual`, `woo_is_downloadable`, `woo_is_featured`
  - **URLs**: `woo_product_url`, `woo_add_to_cart_url`, `woo_external_url`
  - **Content**: `woo_short_description`, `woo_purchase_note`
  - **Attributes**: `woo_attributes`, `woo_attributes_text`
- **New**: Auto-enhancement for WooCommerce Product schema:
  - SKU, GTIN, MPN auto-populated if not explicitly mapped
  - Brand auto-detected from common brand taxonomies (WooCommerce Brands, Perfect Brands, YITH)
  - Offers auto-populated with price, currency, availability, and priceValidUntil
  - AggregateRating auto-populated from WooCommerce reviews
  - Product images include gallery images automatically
  - Weight with proper UN/CEFACT unit codes
- **New**: Stock status automatically converted to Schema.org values (InStock, OutOfStock, BackOrder, PreOrder)
- **New**: GTIN auto-detection from multiple plugin formats (WooCommerce GTIN, Yoast, EAN for WooCommerce, etc.)
- **New**: Brand auto-detection from multiple brand taxonomies and custom fields
- **Improved**: Global WooCommerce fields (`woo_currency_code`, `woo_currency_symbol`) still available for all post types

### 1.21.0
- **New**: Course schema now has sensible defaults with "Auto" badges in UI:
  - `price` defaults to 0 (Free) if not mapped
  - `availability` defaults to InStock (always available)
  - `courseMode` defaults to "online"
  - `priceCurrency` auto-detected from WooCommerce/MemberPress or defaults to EUR
  - `inLanguage` auto-detected from WordPress site language
  - `isAccessibleForFree` automatically set to `true` for free courses (Google recommended)
- **New**: MemberPress Courses virtual fields for explicit mapping:
  - `mpcs_enrollment_count` - Total students enrolled (maps to `totalHistoricalEnrollment`)
  - `mpcs_course_mode` - Default "online" (maps to `courseMode`)
  - `mpcs_availability` - Default "InStock" (maps to `availability`)
  - `mpcs_price_free` - Default 0 (maps to `price`)
  - `mpcs_is_free` - Default true (maps to `isAccessibleForFree`)
- **New**: Global WordPress fields available for all mappings:
  - `site_language` - Full locale (e.g. "it-IT")
  - `site_language_code` - ISO 639-1 code (e.g. "it")
  - `site_currency` - Currency code from WooCommerce/MemberPress or EUR
- **Improved**: Course schema properties now show "Auto" badge in UI when auto-populated
- **Improved**: Course schema `offers` now uses only standard schema.org properties (removed non-standard `category`)

### 1.20.0
- **New**: MemberPress Courses virtual fields for Course schema:
  - `mpcs_curriculum` - Auto-generated course curriculum text, ideal for mapping to `syllabus`
  - `mpcs_curriculum_html` - Course curriculum formatted as HTML list
  - `mpcs_lesson_count` - Total number of lessons in the course
  - `mpcs_section_count` - Total number of sections in the course
- **Fixed**: "Include Curriculum in Course Schema" setting now properly controls whether `hasCourseInstance` is added to Course schema
- **Improved**: Course curriculum fields are now discoverable and mappable like other integration fields

### 1.19.0
- **New**: MemberPress virtual fields for schema.org subscription properties
- **New**: `mepr_eligible_duration` - ISO 8601 duration format (P1M, P1Y, P3M) ready for `eligibleDuration`
- **New**: `mepr_billing_duration` - Numeric billing period (1, 3, 12) ready for `billingDuration`
- **New**: `mepr_billing_increment` - Schema.org format (Month, Year, Week, Day) ready for `billingIncrement`
- **New**: `referencePrice` property - Map original/list price for discount display (strikethrough price)
- **New**: `priceValidUntil` property - Set promotion expiration date
- **New**: Smart price extraction - `referencePrice` accepts text like "$129 / original price" and extracts the numeric value
- **New**: Price fallback cascade for MemberPress: mapped price → referencePrice → `_mepr_product_price`
- **Improved**: MemberPress period types (days, weeks, months, years) now automatically converted to schema.org formats
- **Improved**: Product schema for MemberPress memberships now automatically includes `eligibleDuration` and `priceSpecification` with `billingDuration`/`billingIncrement`
- **Improved**: Subscription data is now merged into existing offers (no manual mapping required)

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
