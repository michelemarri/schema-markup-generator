# Schema Markup Generator - Documentation

Complete documentation for the Schema Markup Generator WordPress plugin.

## Table of Contents

1. [Installation](#installation)
2. [Configuration](#configuration)
3. [Schema Types](#schema-types)
4. [Field Mapping](#field-mapping)
5. [Custom Fields Integration](#custom-fields-integration)
6. [MemberPress Courses Integration](#memberpress-courses-integration)
7. [MemberPress Membership Integration](#memberpress-membership-integration)
8. [WooCommerce Integration](#woocommerce-integration)
9. [Settings](#settings)
   - [Organization](#organization-settings)
   - [Performance](#performance-settings)
   - [Debug](#debug-settings)
   - [Update](#update-settings)
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

Navigate to **Settings → Schema Markup → Settings → Update** to configure:

- **Current Version**: See installed version and last update check
- **Auto-Update**: Enable automatic updates when new versions are available

---

## Configuration

### Home Tab

Navigate to **Settings → Schema Markup** to access the plugin settings. The **Home** tab provides a statistics dashboard:

- **Summary Stats**: Quick overview showing:
  - Posts with schema
  - Posts without schema (not configured)
  - Posts with individual overrides
  - Posts with schema disabled
- **Coverage by Post Type**: Table showing ALL public post types:
  - Post type name and assigned schema (or "Not configured" warning)
  - Total published posts
  - Posts with schema enabled
  - Coverage percentage with visual progress bar
- **Content by Schema Type**: Breakdown of content count per schema type
- **Overall Coverage**: Percentage of all content that has schema

### Schema Output Settings

Schema output settings are located in **Settings → General** sub-tab:

- **Enable Schema Markup**: Toggle schema output on/off globally
- **WebSite Schema**: Adds WebSite schema with SearchAction for sitelinks
- **Breadcrumb Schema**: Adds BreadcrumbList schema for navigation
- **Auto-detect Videos**: Automatically add VideoObject schema when YouTube/Vimeo videos are detected

All changes in this tab are saved automatically (auto-save enabled).

### Organization Info

Organization settings are in **Settings → Organization** sub-tab.

Organization data is used in:
- Organization schema type
- Publisher info in Article schemas
- WebSite schema

See [Organization Settings](#organization-settings) for customization options.

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

### Settings Tab

The **Settings** tab is organized into sub-tabs for better organization:

- **Organization**: Organization name, URL, logo, and fallback image
- **Performance**: Cache settings and cache management
- **Debug**: Debug mode, logs, and system information
- **Update**: Plugin updates and GitHub authentication

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

For online courses and educational content. Compliant with schema.org specification where `instructor`, `courseMode`, and `offers` belong to `CourseInstance`.

**Course-Level Properties:**
- `name` - Course title
- `description` - Course description
- `provider` - Organization offering the course
- `inLanguage` - Course language (auto-detected from WordPress)
- `educationalLevel` - Difficulty level (Beginner, Intermediate, Advanced)
- `timeRequired` - Total course duration
- `isAccessibleForFree` - Automatically `true` for free courses
- `teaches` - Skills/concepts taught (important for AI/LLM matching)
- `about` - Main topics covered
- `syllabus` - Course curriculum description
- `aggregateRating` - Student ratings

**CourseInstance Properties (auto-generated in `hasCourseInstance`):**
- `instructor` - Person teaching the course (defaults to post author)
- `courseMode` - Delivery format: online, onsite, blended (defaults to "online")
- `offers` - Pricing and availability
- `courseWorkload` - Expected weekly workload

**Note:** Per schema.org specification, `instructor`, `courseMode`, and `offers` are properties of `CourseInstance`, not `Course`. The plugin automatically creates a `CourseInstance` with these properties.

**Example Output:**
```json
{
  "@type": "Course",
  "name": "Python Fundamentals",
  "description": "Learn Python programming...",
  "provider": {
    "@type": "Organization",
    "name": "MenthorQ"
  },
  "inLanguage": "en-US",
  "isAccessibleForFree": true,
  "hasCourseInstance": {
    "@type": "CourseInstance",
    "courseMode": "online",
    "instructor": {
      "@type": "Person",
      "name": "John Smith"
    },
    "offers": {
      "@type": "Offer",
      "price": 0,
      "priceCurrency": "EUR",
      "availability": "https://schema.org/InStock"
    }
  }
}
```

#### LearningResource

For individual lessons or educational materials.

**Properties:**
- `name` - Resource title
- `description` - Resource description
- `learningResourceType` - Type (auto-detected from content, see below)
- `interactivityType` - Engagement style (auto-detected from content)
- `isPartOf` - Parent course (auto-detected with MemberPress Courses)
- `timeRequired` - Completion time (auto-calculated, see below)

**Learning Resource Type Auto-Detection:**

The `learningResourceType` property is automatically detected from content analysis when not explicitly mapped. The detection analyzes:

| Detected Type | Criteria |
|---------------|----------|
| **Quiz** | Contains quiz shortcodes (Quiz Master, LearnDash, QSM) or form plugins (Gravity Forms, WPForms) |
| **Video** | Video is dominant (>80% of estimated time), minimal text |
| **Exercise** | Has interactive elements + multiple code blocks |
| **Tutorial** | Step-by-step structure (numbered headings, "Step 1/2/3", ordered lists, code examples) |
| **Lecture** | Has video + substantial text (>300 words) |
| **Reading** | Text-heavy (>500 words) with structured headings, no video |
| **Lesson** | Default fallback for general educational content |

**Interactivity Type Auto-Detection:**

The `interactivityType` property is also automatically detected:

| Type | Meaning | Detection Criteria |
|------|---------|-------------------|
| **active** | Learner actively participates | Quiz elements, forms, interactive components, coding exercises |
| **expositive** | One-way content delivery | Video viewing, text reading |
| **mixed** | Combination of both | Active elements + video/text content |

Both properties:
- Show the "AUTO: Content Analysis" indicator in the UI
- Work without any configuration
- Can be overridden by mapping a custom field

**Time Required Auto-Calculation:**

The `timeRequired` property is automatically calculated when not explicitly mapped. The calculation combines:

1. **Reading time**: Content word count ÷ 200 words/minute (average web reading speed)
2. **Video duration**: Duration of embedded YouTube/Vimeo videos (fetched via API)

For example, a lesson with 1,000 words and a 15-minute video would have `timeRequired` set to `PT20M` (5 min reading + 15 min video).

This auto-calculation:
- Shows the "AUTO" badge in the schema property mapping UI
- Works without any configuration
- Can be overridden by mapping a custom field to `timeRequired`
- Can be customized via the `smg_learning_resource_auto_time_required` filter

**Video Auto-Detection:**

The LearningResource schema automatically detects embedded videos in post content:

- **YouTube** - Detected from standard URLs, short URLs, embed URLs, iframes, and WordPress blocks
- **Vimeo** - Detected from standard URLs, player URLs, and embed blocks
- **Other video platforms** - Detected from WordPress embed blocks

When a video is detected, the schema automatically includes a `VideoObject` with:
- `name` - Post title
- `description` - Post description
- `embedUrl` / `contentUrl` - Video URLs
- `thumbnailUrl` - Video thumbnail (or featured image as fallback)
- `uploadDate` - Post publication date
- `duration` - Video duration (auto-fetched, see below)
- `hasPart` - Video chapters (auto-extracted from content if present)

**Video Duration Auto-Fetch:**

Video duration is automatically fetched based on the platform:

| Platform | Method | Configuration Required |
|----------|--------|----------------------|
| YouTube | YouTube Data API v3 | Yes - configure API key in Settings → Integrations |
| Vimeo | oEmbed API | No - works automatically |
| Other | oEmbed API | No - if provider supports duration |

**Note:** The YouTube Data API provides more accurate duration data than oEmbed. Configure your API key in the Integrations tab for automatic YouTube video duration extraction. Results are cached for 1 week.

**Video Chapters Auto-Extraction (hasPart with Clip):**

Video chapters are automatically extracted from multiple sources and added to the VideoObject as `hasPart` with `Clip` elements. This enables Google's "Key Moments" feature in video search results.

**Sources (in priority order):**

1. **Meta fields**: `video_chapters`, `lesson_video_chapters`, `_video_chapters`, `_lesson_video_chapters`
2. **ACF fields**: `video_chapters`, `lesson_video_chapters`, `chapters`
3. **Content sections**: Headings containing "Video Chapters", "Chapters", "Timestamps", "Capitoli", "Key Moments"
4. **Content timestamp patterns**: Lines matching `0:00 Introduction`, `1:30 - Main Topic`, `00:05:30 Advanced`

**Supported chapter formats:**

| Format | Example |
|--------|---------|
| MM:SS Title | `0:00 Introduction` |
| MM:SS - Title | `1:30 - Getting Started` |
| HH:MM:SS Title | `00:05:30 Advanced Topics` |
| Seconds Title | `80 Crypto becoming investable` |
| HTML with tags | `<strong>00:00</strong> – Title` |
| HTML class | `<p class="video-chapters">...</p>` |
| Array of objects | `[{name: "Intro", startOffset: 0}, ...]` |
| JSON string | `'[{"name": "Intro", "time": "0:00"}]'` |

**HTML format example:**

```html
<p class="video-chapters">
  <strong>00:00</strong> – Why crypto and why now<br>
  <strong>00:26</strong> – Introducing crypto intelligence<br>
  <strong>01:20</strong> – Crypto becoming investable<br>
  <strong>02:07</strong> – Cutting through noise<br>
  <strong>02:44</strong> – Growth of the derivative market
</p>
```

**Automatic endOffset Calculation:**

The plugin automatically calculates `endOffset` for each chapter to comply with Google's requirements:

- **For each chapter (except last):** `endOffset` = `startOffset` of the next chapter
- **For the last chapter:** `endOffset` = total video duration (if available)

This eliminates the "Missing field endOffset" warning in Google Search Console.

**Generated schema structure:**

```json
{
  "@type": "VideoObject",
  "name": "Lesson Title",
  "duration": "PT5M30S",
  "hasPart": [
    {
      "@type": "Clip",
      "name": "Why crypto and why now",
      "startOffset": 0,
      "endOffset": 80,
      "position": 1,
      "url": "https://example.com/lessons/my-lesson/#t=0"
    },
    {
      "@type": "Clip",
      "name": "Crypto becoming investable",
      "startOffset": 80,
      "endOffset": 330,
      "position": 2,
      "url": "https://example.com/lessons/my-lesson/#t=80"
    }
  ]
}
```

**URL format:**
- For learning resources (lessons), URLs use the page URL with `#t=offset` for embedded player navigation
- For standalone YouTube videos, URLs use `youtube.com/watch?v=ID&t=offset`

**SEO Impact:**
- Google shows "Key Moments" in video rich results
- Users can click to jump directly to specific sections
- Improves video CTR by +10-20% (estimated)
- AI platforms can reference specific timestamps when citing content

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

| Field | Description | Example |
|-------|-------------|---------|
| `site_name` | Site name from WordPress General Settings | "My Website" |
| `site_url` | Home URL of the website | "https://example.com" |
| `site_language` | Full locale from WordPress | "it-IT", "en-US" |
| `site_language_code` | ISO 639-1 language code | "it", "en" |
| `site_currency` | Currency code (auto-detected from WooCommerce/MemberPress, or EUR) | "EUR", "USD" |
| `site_wordpress_version` | Current WordPress version | "6.7.1" |
| `site_theme_version` | Active theme version | "2.5.0" |

**Pro Tip:** Use `site_language_code` for the `inLanguage` property, `site_currency` for `priceCurrency`, and `site_wordpress_version`/`site_theme_version` for `softwareVersion` on SoftwareApplication schemas.

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

### Custom Values

For properties that require a fixed/static value, you can use Custom Values instead of mapping to a field. This is especially useful when all posts of a type share the same value.

**Available types:**

| Type | Description | Example Use |
|------|-------------|-------------|
| ✏️ Custom Text | Free-form text value | `inLanguage: "en"`, `applicationCategory: "EducationalApplication"` |
| 🔢 Custom Number | Numeric value (integer or decimal) | `ratingValue: 5`, `reviewCount: 100` |
| 📅 Custom Date | Date in YYYY-MM-DD format | `datePublished: "2024-01-01"` |
| 🔗 Custom URL | Full URL | `sameAs: "https://twitter.com/yourhandle"` |
| ✓ Boolean: True | Boolean true value | `isAccessibleForFree: true` |
| ✗ Boolean: False | Boolean false value | `requiresSubscription: false` |

**When to use Custom Values:**
- Properties with the same value for all posts (e.g., `inLanguage`, `isAccessibleForFree`)
- Default values that rarely change
- Overriding auto-detected values with a specific static value

**How to use:**
1. In the field mapping dropdown, scroll to the "Custom Value" group
2. Select the appropriate type
3. Enter the value in the input field that appears (for Text, Number, Date, URL)
4. The value is auto-saved when you click outside the field

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

## Custom Fields Integration

The plugin supports both **Advanced Custom Fields (ACF)** and **Secure Custom Fields (SCF)**. Both plugins share the same API and are fully supported.

### Detection

The plugin automatically detects which custom fields plugin is installed:
- **ACF** (Advanced Custom Fields) - Free and Pro versions
- **SCF** (Secure Custom Fields) - Community fork of ACF

All features work identically with either plugin.

### Supported Field Types

| Field Type | Schema Type |
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

Image fields are automatically resolved:
- Returns URL if return format is URL
- Extracts URL from array if return format is Array

---

## MemberPress Courses Integration

When MemberPress Courses is active, the plugin automatically enhances schema generation for courses and lessons.

### Automatic Features

1. **Parent Course Detection**
   - Lessons (`mpcs-lesson`) automatically include their parent course in the `isPartOf` property
   - The course hierarchy (Lesson → Section → Course) is traversed automatically
   - Displayed with "AUTO" badge in the schema property mapping UI

2. **Lesson Position Detection**
   - Lessons automatically include their global position within the entire course curriculum
   - Counts all lessons across all sections to provide course-wide numbering
   - Position is 1-based (first lesson = 1, second = 2, etc., continuing across sections)
   - Displayed with "AUTO" badge in the schema property mapping UI

3. **Course Enhancement**
   - Courses (`mpcs-course`) can include curriculum structure with sections and lessons (configurable)
   - Lesson count is automatically calculated

### Configuration

In **Settings → Schema Markup → Integrations**, you can configure:

| Setting | Description |
|---------|-------------|
| **Auto-detect Parent Course** | Automatically link lessons to their parent course in the schema (`isPartOf`) |
| **Include Curriculum in Course Schema** | Add sections and lessons list to Course schema as `hasCourseInstance` (may increase page size) |

### Available Virtual Fields

**Course Fields (`mpcs-course`):**

| Field | Type | Description | Suggested Mapping |
|-------|------|-------------|-------------------|
| `mpcs_curriculum` | text | Auto-generated course curriculum (sections and lessons as text) | `syllabus` |
| `mpcs_curriculum_html` | text | Course curriculum formatted as HTML nested list | - |
| `mpcs_lesson_count` | number | Total number of lessons in the course | - |
| `mpcs_section_count` | number | Total number of sections in the course | - |
| `mpcs_enrollment_count` | number | Total students enrolled (from user progress table) | `totalHistoricalEnrollment` |
| `mpcs_course_mode` | text | Default: "online" | `courseMode` |
| `mpcs_availability` | text | Default: "InStock" (always available) | `availability` |
| `mpcs_price_free` | number | Default: 0 (free course) | `price` |
| `mpcs_is_free` | boolean | Default: true | `isAccessibleForFree` |

**Lesson Fields (`mpcs-lesson`):**

| Field | Type | Description | Suggested Mapping |
|-------|------|-------------|-------------------|
| `mpcs_lesson_position` | number | Global position of the lesson within the entire course curriculum | `position` |
| `mpcs_parent_course_name` | text | Name of the parent course | - |
| `mpcs_parent_course_url` | url | URL of the parent course | - |

**Note:** The `isPartOf` and `position` properties are automatically populated for lessons without requiring manual mapping. They show an "AUTO" badge in the UI.

**Pro Tip:** The Course schema automatically uses sensible defaults (free, online, always available). Use these virtual fields only if you need to explicitly map them or override the defaults.

### Supported Post Types

| Post Type | Schema Type | Features |
|-----------|-------------|----------|
| `mpcs-course` | Course | Virtual fields, curriculum, lesson count, sections |
| `mpcs-lesson` | LearningResource | Parent course auto-detection |

### Example Output

**Lesson (LearningResource):**

The `isPartOf` Course includes all properties required by Google Rich Results:

```json
{
  "@type": "LearningResource",
  "name": "Introduction to SEO",
  "learningResourceType": "Lesson",
  "position": 1,
  "isPartOf": {
    "@type": "Course",
    "@id": "https://example.com/courses/digital-marketing/#course",
    "name": "Digital Marketing Fundamentals",
    "url": "https://example.com/courses/digital-marketing/",
    "description": "Learn the fundamentals of digital marketing...",
    "provider": {
      "@type": "Organization",
      "name": "Your Site Name",
      "sameAs": "https://example.com/"
    },
    "hasCourseInstance": {
      "@type": "CourseInstance",
      "courseMode": "online",
      "offers": {
        "@type": "Offer",
        "price": 0,
        "priceCurrency": "EUR",
        "availability": "https://schema.org/InStock"
      }
    }
  }
}
```

**Note:** The `description`, `provider`, `hasCourseInstance`, and `offers` properties are automatically added to satisfy Google Rich Results requirements. The currency is auto-detected from MemberPress/WooCommerce settings.

**Course with Curriculum (when setting is enabled):**
```json
{
  "@type": "Course",
  "name": "Digital Marketing Fundamentals",
  "hasCourseInstance": {
    "@type": "CourseInstance",
    "courseMode": "online",
    "courseWorkload": "Approximately 10 hours of self-paced learning",
    "offers": {
      "@type": "Offer",
      "price": 0,
      "priceCurrency": "EUR",
      "availability": "https://schema.org/InStock"
    }
  },
  "hasPart": [
    {
      "@type": "LearningResource",
      "name": "Module 1: SEO Basics",
      "learningResourceType": "module",
      "position": 1,
      "hasPart": [
        { "@type": "LearningResource", "name": "What is SEO?", "learningResourceType": "Lesson", "position": 1 },
        { "@type": "LearningResource", "name": "Keywords Research", "learningResourceType": "Lesson", "position": 2 }
      ]
    }
  ]
}
```

> **Note:** Course modules/sections use `hasPart` with `LearningResource` (semantically correct for course content), while `hasCourseInstance` is reserved for the course offering with pricing, schedule, and instructor information.

**Course with Syllabus (mapped from `mpcs_curriculum`):**
```json
{
  "@type": "Course",
  "name": "Digital Marketing Fundamentals",
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

## WooCommerce Integration

When WooCommerce is active, the plugin provides comprehensive field mapping for WooCommerce products with 40+ virtual fields and automatic Product schema enhancement.

### Global Fields (All Post Types)

These fields are available for all post types:

| Field | Type | Description | Use Case |
|-------|------|-------------|----------|
| `woo_currency_code` | text | ISO 4217 currency code (EUR, USD) | Map to `priceCurrency` |
| `woo_currency_symbol` | text | Currency symbol (€, $) | Display purposes |

### Product-Specific Fields

The following fields are available only for the `product` post type:

#### Pricing Fields

| Field | Type | Description | Schema Mapping |
|-------|------|-------------|----------------|
| `woo_price` | number | Current active price (sale or regular) | `price` |
| `woo_regular_price` | number | Regular/list price | `referencePrice` |
| `woo_sale_price` | number | Discounted price (if on sale) | `price` |
| `woo_price_html` | text | Formatted price with currency | Display only |

#### Identifier Fields

| Field | Type | Description | Schema Mapping |
|-------|------|-------------|----------------|
| `woo_sku` | text | Stock Keeping Unit | `sku` |
| `woo_gtin` | text | GTIN/EAN/UPC (auto-detected) | `gtin` |
| `woo_mpn` | text | Manufacturer Part Number | `mpn` |

**GTIN Auto-Detection:** The plugin automatically searches for GTIN in these meta fields:
- `_wc_gtin`, `_gtin`, `_ean`, `_upc` (generic)
- `wpseo_global_identifier_gtin` (Yoast SEO)
- `_alg_wc_ean` (EAN for WooCommerce)
- `hwp_product_gtin` (other plugins)

#### Stock & Availability Fields

| Field | Type | Description | Schema Mapping |
|-------|------|-------------|----------------|
| `woo_stock_status` | text | Schema.org value: InStock, OutOfStock, PreOrder, BackOrder | `availability` |
| `woo_stock_status_raw` | text | WooCommerce value: instock, outofstock, onbackorder | - |
| `woo_stock_quantity` | number | Items in stock | - |
| `woo_is_in_stock` | boolean | In stock status | - |
| `woo_backorders_allowed` | boolean | Backorders enabled | - |

#### Rating & Review Fields

| Field | Type | Description | Schema Mapping |
|-------|------|-------------|----------------|
| `woo_average_rating` | number | Average rating (1-5) | `ratingValue` |
| `woo_review_count` | number | Number of reviews | `reviewCount` |
| `woo_rating_count` | number | Number of ratings | `ratingCount` |

#### Promotion/Sale Date Fields

| Field | Type | Description | Schema Mapping |
|-------|------|-------------|----------------|
| `woo_sale_price_dates_from` | text | Sale start date (YYYY-MM-DD) | - |
| `woo_sale_price_dates_to` | text | Sale end date (YYYY-MM-DD) | `priceValidUntil` |
| `woo_is_on_sale` | boolean | Currently on sale | - |

#### Dimension & Weight Fields

| Field | Type | Description | Schema Mapping |
|-------|------|-------------|----------------|
| `woo_weight` | text | Weight with unit (e.g., "2.5 kg") | - |
| `woo_weight_value` | number | Weight numeric value | `weight.value` |
| `woo_weight_unit` | text | Weight unit (kg, g, lbs, oz) | `weight.unitCode` |
| `woo_dimensions` | text | Formatted "L × W × H unit" | - |
| `woo_length` | number | Product length | `depth` |
| `woo_width` | number | Product width | `width` |
| `woo_height` | number | Product height | `height` |
| `woo_dimension_unit` | text | Dimension unit (cm, m, in) | - |

#### Taxonomy Fields

| Field | Type | Description | Schema Mapping |
|-------|------|-------------|----------------|
| `woo_product_category` | text | Primary category name | `category` |
| `woo_product_categories` | text | All categories (comma-separated) | - |
| `woo_product_tags` | text | Tags (comma-separated) | `keywords` |
| `woo_product_brand` | text | Brand name (auto-detected) | `brand` |

**Brand Auto-Detection:** The plugin searches for brand in these taxonomies:
- `product_brand` (WooCommerce Brands)
- `pwb-brand` (Perfect WooCommerce Brands)
- `yith_product_brand` (YITH WooCommerce Brands)
- `brand` (generic)

And these meta fields: `_brand`, `brand`, `_product_brand`

#### Image Fields

| Field | Type | Description | Schema Mapping |
|-------|------|-------------|----------------|
| `woo_main_image` | url | Main product image URL | `image` |
| `woo_gallery_images` | array | Gallery image URLs | `image` (array) |
| `woo_all_images` | array | Main + gallery images | `image` (array) |

#### Product Info Fields

| Field | Type | Description |
|-------|------|-------------|
| `woo_product_type` | text | simple, variable, grouped, external |
| `woo_is_virtual` | boolean | Virtual product (no shipping) |
| `woo_is_downloadable` | boolean | Downloadable product |
| `woo_is_featured` | boolean | Featured product |
| `woo_is_sold_individually` | boolean | Only one per order |

#### URL Fields

| Field | Type | Description | Schema Mapping |
|-------|------|-------------|----------------|
| `woo_product_url` | url | Product permalink | `url` |
| `woo_add_to_cart_url` | url | Add to cart URL | - |
| `woo_external_url` | url | Affiliate/external URL | `url` (for external products) |

#### Content & Stats Fields

| Field | Type | Description |
|-------|------|-------------|
| `woo_short_description` | text | Product excerpt |
| `woo_purchase_note` | text | Post-purchase note |
| `woo_total_sales` | number | Total sales count |

#### Attribute Fields

| Field | Type | Description |
|-------|------|-------------|
| `woo_attributes` | array | Array of {name, value} objects |
| `woo_attributes_text` | text | Formatted "Color: Red, Size: M" |

### Automatic Product Schema Enhancement

When a WooCommerce product is assigned the Product schema, the plugin automatically populates missing fields:

1. **SKU, GTIN, MPN** - Auto-populated from product data
2. **Brand** - Auto-detected from brand taxonomies
3. **Offers** - Auto-populated with:
   - `price` from current price
   - `priceCurrency` from WooCommerce settings
   - `availability` from stock status
   - `url` from product permalink
   - `priceValidUntil` from sale end date (if on sale)
4. **AggregateRating** - Auto-populated if product has reviews
5. **Images** - Includes gallery images automatically
6. **Weight** - With proper UN/CEFACT unit codes (KGM, GRM, LBR, ONZ)

### Example Output

**WooCommerce Product with Auto-Enhancement:**
```json
{
  "@type": "Product",
  "name": "Premium Wireless Headphones",
  "description": "High-quality noise-cancelling headphones",
  "sku": "WH-PRO-100",
  "gtin": "5901234123457",
  "mpn": "WH-PRO-100-BLK",
  "brand": {
    "@type": "Brand",
    "name": "SoundMax"
  },
  "image": [
    "https://example.com/product-main.jpg",
    "https://example.com/product-side.jpg",
    "https://example.com/product-case.jpg"
  ],
  "offers": {
    "@type": "Offer",
    "price": 199.99,
    "priceCurrency": "EUR",
    "availability": "https://schema.org/InStock",
    "url": "https://example.com/product/headphones/",
    "priceValidUntil": "2025-01-31"
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": 4.7,
    "ratingCount": 156,
    "bestRating": 5,
    "worstRating": 1
  },
  "weight": {
    "@type": "QuantitativeValue",
    "value": 0.25,
    "unitCode": "KGM"
  }
}
```

### Usage Tips

1. **Assign Product schema** to the `product` post type in Settings → Schema Markup → Post Types
2. **Auto-enhancement is automatic** - No mapping required for basic WooCommerce fields
3. **Override when needed** - Map custom fields to override auto-detected values
4. **Use sale dates** - `woo_sale_price_dates_to` is perfect for `priceValidUntil`
5. **Brand detection** - Install a brand taxonomy plugin for automatic brand population

### Filters

The integration uses these filters:

- `smg_discovered_fields` - Adds WooCommerce fields to discovery (sources: `woocommerce_virtual`, `woocommerce_product`)
- `smg_resolve_field_value` - Resolves WooCommerce field values
- `smg_product_schema_data` - Enhances Product schema with WooCommerce data

---

## Settings

The Settings tab is organized into sub-tabs for better organization of plugin configuration.

### Organization Settings {#organization-settings}

Navigate to **Settings → Schema Markup → Settings → Organization** to customize your organization data.

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

#### Fallback Image

Navigate to **Settings → Schema Markup → Settings → Organization** to configure a fallback image for schemas.

Many schema types (Product, Article, Course, Event, etc.) require an `image` property. When a post doesn't have a featured image, the plugin uses a fallback chain:

1. **Featured Image** (post thumbnail)
2. **Custom Fallback Image** (configured in Organization settings)
3. **Site Favicon** (WordPress site icon)

**Setting the Fallback Image:**
1. Go to **Settings → Organization** sub-tab
2. Find the **Fallback Image** section
3. Click "Select Image" to open the Media Library
4. Choose an image (recommended: at least 1200×630 pixels for social sharing)
5. Save settings

**Schema Types Using Fallback:**
- Product, Article, BlogPosting, NewsArticle
- Course, LearningResource
- Event, Recipe, HowTo
- Person, Organization
- WebPage, SoftwareApplication, WebApplication
- FinancialProduct, VideoObject (thumbnailUrl)

**Helper Function:**

Use `smg_get_fallback_image()` to retrieve the fallback image programmatically:

```php
$image = \Metodo\SchemaMarkupGenerator\smg_get_fallback_image();
// Returns: ['@type' => 'ImageObject', 'url' => string, 'width' => int, 'height' => int] or null
```

**Filter:**

Customize the fallback image with the `smg_fallback_image` filter:

```php
add_filter('smg_fallback_image', function($image) {
    // Use a different image
    return [
        '@type' => 'ImageObject',
        'url' => 'https://example.com/default-image.jpg',
        'width' => 1200,
        'height' => 630,
    ];
});
```

### Performance Settings {#performance-settings}

Navigate to **Settings → Schema Markup → Settings → Performance** to configure caching.

#### Cache Configuration

| Setting | Description | Default |
|---------|-------------|---------|
| **Enable Caching** | Toggle schema caching on/off | Enabled |
| **Cache TTL** | Time-to-live in seconds | 3600 (1 hour) |

#### Cache Management

The Performance sub-tab includes cache management tools:

- **Clear Schema Cache**: Removes all cached schema data
- **Cache Status**: Shows current cache type (Object Cache or Transients)

#### How Caching Works

1. Schema data is generated once and cached
2. Cache is keyed by post ID and modification date
3. Cache is automatically invalidated when post is updated

#### Cache Types

**Object Cache (Preferred)**
When Redis or Memcached is available, the plugin uses WordPress object cache for optimal performance.

**Transients (Fallback)**
When object cache is not available, WordPress transients are used.

#### Cache Invalidation

Cache is automatically cleared when:
- Post is saved or updated
- Post is deleted
- Plugin settings are changed

### Debug Settings {#debug-settings}

Navigate to **Settings → Schema Markup → Settings → Debug** for troubleshooting tools.

#### Debug Mode

Enable debug mode to log schema generation details:

1. Toggle **Enable Debug Mode** on
2. Check logs in `/wp-content/plugins/schema-markup-generator/logs/`
3. Recent logs are displayed directly in the tab

#### System Information

The Debug sub-tab displays:

**Environment:**
- Plugin Version
- WordPress Version
- PHP Version

**Server Limits:**
- Max Execution Time
- Memory Limit
- Upload Max Size

**Active Integrations:**
- Status of ACF, WooCommerce, Rank Math, MemberPress

### Update Settings {#update-settings}

Navigate to **Settings → Schema Markup → Settings → Update** to configure plugin updates.

#### Current Version

Shows:
- Installed version with badge
- GitHub repository link
- Last update check time

#### GitHub Authentication

For private repositories, configure a GitHub Personal Access Token:

1. Generate a token at [GitHub Settings](https://github.com/settings/tokens?type=beta)
2. Enter the token in the **GitHub Personal Access Token** field
3. Token is encrypted using AES-256-CBC before storage

**Alternative:** Define the token in `wp-config.php`:
```php
define('SMG_GITHUB_TOKEN', 'your-token');
```

#### Auto-Update

Enable **Auto-Updates** to automatically update the plugin when new versions are available.

#### Manual Check

Click **Check for Updates** to force a check for new versions.

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
- Settings (organization, performance, debug)
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
3. Check Integrations tab for integration status

**Complete Rank Math Schema Disable:**

If you want SMG to handle ALL structured data and completely disable Rank Math's schema output:

1. Go to **Settings → Schema Markup → Integrations**
2. Click the **"Settings"** button on the Rank Math integration card
3. In the modal, enable **"Disable All Rank Math Schemas"**
4. Save settings

This option removes all schema markup generated by Rank Math, letting SMG handle everything.

**Note:** When "Disable All Rank Math Schemas" is enabled, the "Avoid Duplicate Schemas" and "Schema Takeover" options are ignored (since there are no Rank Math schemas to conflict with).

**Tip:** Each integration card has a "Settings" button that opens a modal with all available configuration options for that integration.

### Invalid Schema Errors

Use the built-in validation:
1. Open the post editor
2. Check the Schema Markup meta box
3. Click "Refresh" to see validation errors
4. Use "Google Rich Results Test" link to verify

### Debug Logging

1. Go to Settings → Schema Markup → Settings → Debug
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

