<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Schema;

use Metodo\SchemaMarkupGenerator\Cache\CacheInterface;
use Metodo\SchemaMarkupGenerator\Logger\Logger;
use WP_Post;

/**
 * Schema Renderer
 *
 * Renders schema.org JSON-LD to the page head.
 *
 * @package Metodo\SchemaMarkupGenerator\Schema
 * @author  Michele Marri <plugins@metodo.dev>
 */
class SchemaRenderer
{
    private SchemaFactory $schemaFactory;
    private CacheInterface $cache;
    private Logger $logger;

    public function __construct(
        SchemaFactory $schemaFactory,
        CacheInterface $cache,
        Logger $logger
    ) {
        $this->schemaFactory = $schemaFactory;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Render schema to wp_head
     */
    public function render(): void
    {
        // Only render on singular pages
        if (!is_singular()) {
            $this->renderGlobalSchemas();
            return;
        }

        $post = get_post();

        if (!$post instanceof WP_Post) {
            return;
        }

        // Check if schema is disabled for this post
        if (get_post_meta($post->ID, '_smg_disable_schema', true)) {
            $this->logger->debug("Schema disabled for post {$post->ID}");
            return;
        }

        $schemas = $this->getPostSchemas($post);

        if (empty($schemas)) {
            return;
        }

        $this->outputSchemas($schemas);
    }

    /**
     * Get schemas for a post
     *
     * @param WP_Post $post The post object
     * @return array Array of schema data
     */
    public function getPostSchemas(WP_Post $post): array
    {
        $cacheKey = 'schema_' . $post->ID . '_' . $post->post_modified;
        $cached = $this->cache->get($cacheKey);

        if ($cached !== null) {
            $this->logger->debug("Cache hit for post {$post->ID}");
            return $cached;
        }

        $schemas = [];
        $mappings = get_option('smg_post_type_mappings', []);
        $fieldMappings = get_option('smg_field_mappings', []);
        $pageMappings = get_option('smg_page_mappings', []);

        // Get schema type for this post type
        $schemaType = $mappings[$post->post_type] ?? null;

        // Check for page-specific mapping (for pages)
        if ($post->post_type === 'page' && isset($pageMappings[$post->ID])) {
            $schemaType = $pageMappings[$post->ID];
        }

        // Check for per-post override (highest priority)
        $postSchemaType = get_post_meta($post->ID, '_smg_schema_type', true);
        if ($postSchemaType) {
            $schemaType = $postSchemaType;
        }

        if ($schemaType) {
            $schema = $this->schemaFactory->create($schemaType);

            if ($schema) {
                // Get field mapping for this post type (global settings)
                $mapping = $fieldMappings[$post->post_type] ?? [];

                // Get per-post field overrides (new format with type + value)
                $postOverrides = get_post_meta($post->ID, '_smg_field_overrides', true);
                if (is_array($postOverrides)) {
                    $processedOverrides = $this->processFieldOverrides($postOverrides);
                    $mapping = array_merge($mapping, $processedOverrides);
                }

                // Legacy support: check old _smg_field_mapping format
                $legacyMapping = get_post_meta($post->ID, '_smg_field_mapping', true);
                if (is_array($legacyMapping)) {
                    $mapping = array_merge($mapping, $legacyMapping);
                }

                $schemaData = $schema->build($post, $mapping);

                if (!empty($schemaData)) {
                    $schemas[] = $schemaData;
                }
            }
        }

        // Auto-detect video in content (if enabled and no VideoObject schema already)
        $autoDetectedVideo = $this->maybeAutoDetectVideo($post, $schemaType);
        if ($autoDetectedVideo) {
            $schemas[] = $autoDetectedVideo;
        }

        // Add global schemas (WebSite, Breadcrumb)
        $globalSchemas = $this->buildGlobalSchemas($post);
        $schemas = array_merge($schemas, $globalSchemas);

        /**
         * Filter all schemas for a post
         *
         * @param array   $schemas All schema data
         * @param WP_Post $post    The post object
         */
        $schemas = apply_filters('smg_post_schemas', $schemas, $post);

        // Cache the result
        $this->cache->set($cacheKey, $schemas);

        $this->logger->debug("Generated " . count($schemas) . " schemas for post {$post->ID}");

        return $schemas;
    }

    /**
     * Auto-detect video in post content and build VideoObject schema
     *
     * @param WP_Post $post The post object
     * @param string|null $currentSchemaType The currently configured schema type
     * @return array|null VideoObject schema data or null
     */
    private function maybeAutoDetectVideo(WP_Post $post, ?string $currentSchemaType): ?array
    {
        // Skip if already using VideoObject schema
        if ($currentSchemaType === 'VideoObject') {
            return null;
        }

        // Skip if using a schema type that already includes video as a nested property
        // These schemas build their own VideoObject internally
        $schemaTypesWithNestedVideo = [
            'LearningResource',
            'Article',
            'HowTo',
            'Recipe',
            'Course',
            'NewsArticle',
            'BlogPosting',
        ];
        
        if ($currentSchemaType && in_array($currentSchemaType, $schemaTypesWithNestedVideo, true)) {
            return null;
        }

        // Check if auto-detect is enabled
        $settings = \Metodo\SchemaMarkupGenerator\smg_get_settings('general');
        if (empty($settings['auto_detect_video'])) {
            return null;
        }

        // Detect video in content
        $videoData = $this->detectVideoInContent($post->post_content);
        if (!$videoData) {
            return null;
        }

        $this->logger->debug("Auto-detected {$videoData['platform']} video in post {$post->ID}");

        // Build VideoObject schema
        $videoSchema = $this->schemaFactory->create('VideoObject');
        if (!$videoSchema) {
            return null;
        }

        // Create mapping with detected video data
        $mapping = [
            'embedUrl' => $videoData['embed_url'],
        ];

        // Try to get thumbnail from YouTube API if available
        if ($videoData['platform'] === 'youtube' && !empty($videoData['video_id'])) {
            $youtubeData = $this->getYouTubeVideoData($videoData['video_id']);
            if ($youtubeData && !empty($youtubeData['thumbnail'])) {
                $mapping['thumbnailUrl'] = $youtubeData['thumbnail'];
            }
        }

        // Add content URL for self-hosted videos
        if (!empty($videoData['content_url'])) {
            $mapping['contentUrl'] = $videoData['content_url'];
        }

        // Note: duration and transcript are auto-extracted by VideoObjectSchema
        $schemaData = $videoSchema->build($post, $mapping);

        /**
         * Filter auto-detected video schema data
         *
         * @param array   $schemaData The schema data
         * @param WP_Post $post       The post object
         * @param array   $videoData  The detected video data
         */
        $schemaData = apply_filters('smg_auto_detected_video_schema', $schemaData, $post, $videoData);

        return !empty($schemaData) ? $schemaData : null;
    }

    /**
     * Detect video embeds in post content
     *
     * @param string $content The post content
     * @return array|null Video data or null if no video found
     */
    private function detectVideoInContent(string $content): ?array
    {
        // YouTube patterns
        $youtubePatterns = [
            // youtube.com/watch?v=VIDEO_ID
            '/youtube\.com\/watch\?v=([a-zA-Z0-9_-]{11})/',
            // youtu.be/VIDEO_ID
            '/youtu\.be\/([a-zA-Z0-9_-]{11})/',
            // youtube.com/embed/VIDEO_ID
            '/youtube\.com\/embed\/([a-zA-Z0-9_-]{11})/',
            // youtube-nocookie.com/embed/VIDEO_ID
            '/youtube-nocookie\.com\/embed\/([a-zA-Z0-9_-]{11})/',
            // youtube.com/live/VIDEO_ID
            '/youtube\.com\/live\/([a-zA-Z0-9_-]{11})/',
            // WordPress YouTube embed block
            '/<!-- wp:embed {"url":"https?:\/\/(?:www\.)?(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})"/',
        ];

        foreach ($youtubePatterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                return [
                    'platform' => 'youtube',
                    'video_id' => $matches[1],
                    'embed_url' => 'https://www.youtube.com/embed/' . $matches[1],
                ];
            }
        }

        // Vimeo patterns
        $vimeoPatterns = [
            // vimeo.com/VIDEO_ID
            '/vimeo\.com\/(\d+)/',
            // player.vimeo.com/video/VIDEO_ID
            '/player\.vimeo\.com\/video\/(\d+)/',
            // WordPress Vimeo embed block
            '/<!-- wp:embed {"url":"https?:\/\/(?:www\.)?vimeo\.com\/(\d+)"/',
        ];

        foreach ($vimeoPatterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                return [
                    'platform' => 'vimeo',
                    'video_id' => $matches[1],
                    'embed_url' => 'https://player.vimeo.com/video/' . $matches[1],
                ];
            }
        }

        // WordPress video block with self-hosted video
        if (preg_match('/<!-- wp:video.*?"src":"([^"]+)"/', $content, $matches)) {
            return [
                'platform' => 'self-hosted',
                'video_id' => null,
                'embed_url' => null,
                'content_url' => $matches[1],
            ];
        }

        // HTML5 video tag
        if (preg_match('/<video[^>]+src=["\']([^"\']+)["\']/', $content, $matches)) {
            return [
                'platform' => 'self-hosted',
                'video_id' => null,
                'embed_url' => null,
                'content_url' => $matches[1],
            ];
        }

        return null;
    }

    /**
     * Get YouTube video data via API (if available)
     *
     * @param string $videoId YouTube video ID
     * @return array|null Video data or null
     */
    private function getYouTubeVideoData(string $videoId): ?array
    {
        // Check if YouTube integration is available
        if (!class_exists(\Metodo\SchemaMarkupGenerator\Integration\YouTubeIntegration::class)) {
            return null;
        }

        $youtube = new \Metodo\SchemaMarkupGenerator\Integration\YouTubeIntegration();
        
        if (!$youtube->isAvailable()) {
            return null;
        }

        $details = $youtube->getVideoDetails($videoId);
        
        if (!$details) {
            return null;
        }

        return [
            'duration' => $details['duration_seconds'],
            'thumbnail' => $details['thumbnail'],
            'title' => $details['title'],
            'description' => $details['description'],
        ];
    }

    /**
     * Build global schemas (WebSite, Breadcrumb)
     */
    private function buildGlobalSchemas(WP_Post $post): array
    {
        $schemas = [];
        $settings = \Metodo\SchemaMarkupGenerator\smg_get_settings('general');

        // WebSite schema (only on home or if enabled globally)
        if (is_front_page() || ($settings['enable_website_schema'] ?? true)) {
            $websiteSchema = $this->schemaFactory->create('WebSite');
            if ($websiteSchema) {
                $schemas[] = $websiteSchema->build($post);
            }
        }

        // Breadcrumb schema
        if ($settings['enable_breadcrumb_schema'] ?? true) {
            $breadcrumbSchema = $this->schemaFactory->create('BreadcrumbList');
            if ($breadcrumbSchema) {
                $schemas[] = $breadcrumbSchema->build($post);
            }
        }

        return $schemas;
    }

    /**
     * Render global schemas (for non-singular pages)
     */
    private function renderGlobalSchemas(): void
    {
        $settings = \Metodo\SchemaMarkupGenerator\smg_get_settings('general');
        $schemas = [];

        // WebSite schema on home page
        if (is_front_page() || is_home()) {
            $websiteSchema = $this->schemaFactory->create('WebSite');
            if ($websiteSchema) {
                // Create a dummy post for the home page
                $dummyPost = new WP_Post((object) [
                    'ID' => 0,
                    'post_type' => 'page',
                    'post_title' => get_bloginfo('name'),
                    'post_content' => get_bloginfo('description'),
                ]);
                $schemas[] = $websiteSchema->build($dummyPost);
            }
        }

        // Taxonomy archive pages (category, tag, custom taxonomy)
        if (is_tax() || is_category() || is_tag()) {
            $taxonomySchema = $this->buildTaxonomySchema();
            if ($taxonomySchema) {
                $schemas[] = $taxonomySchema;
            }
        }

        if (!empty($schemas)) {
            $this->outputSchemas($schemas);
        }
    }

    /**
     * Build schema for taxonomy archive pages
     *
     * @return array|null Schema data or null if not configured
     */
    private function buildTaxonomySchema(): ?array
    {
        $term = get_queried_object();

        if (!$term instanceof \WP_Term) {
            return null;
        }

        $taxonomy = $term->taxonomy;
        $mappings = get_option('smg_taxonomy_mappings', []);

        // Check if this taxonomy has a schema mapping
        if (!isset($mappings[$taxonomy]) || empty($mappings[$taxonomy])) {
            $this->logger->debug("No schema mapping for taxonomy: {$taxonomy}");
            return null;
        }

        $schemaType = $mappings[$taxonomy];
        $termUrl = get_term_link($term);
        $termUrl = is_wp_error($termUrl) ? home_url() : $termUrl;

        // Build schema based on type
        $schema = $this->buildTaxonomySchemaByType($schemaType, $term, $termUrl);

        if ($schema) {
            /**
             * Filter taxonomy schema data
             *
             * @param array    $schema     The schema data
             * @param \WP_Term $term       The term object
             * @param string   $schemaType The schema type
             */
            $schema = apply_filters('smg_taxonomy_schema', $schema, $term, $schemaType);

            $this->logger->debug("Generated {$schemaType} schema for term {$term->term_id} ({$taxonomy})");
        }

        return $schema;
    }

    /**
     * Build taxonomy schema by type
     *
     * @param string   $schemaType The schema type
     * @param \WP_Term $term       The term object
     * @param string   $termUrl    The term archive URL
     * @return array Schema data
     */
    private function buildTaxonomySchemaByType(string $schemaType, \WP_Term $term, string $termUrl): array
    {
        $baseSchema = [
            '@context' => 'https://schema.org',
            '@type' => $schemaType,
            'name' => html_entity_decode($term->name, ENT_QUOTES, 'UTF-8'),
            'url' => $termUrl,
        ];

        // Add description if available
        if (!empty($term->description)) {
            $baseSchema['description'] = wp_strip_all_tags($term->description);
        }

        // Type-specific enhancements
        switch ($schemaType) {
            case 'DefinedTerm':
                $baseSchema = $this->buildDefinedTermSchema($baseSchema, $term);
                break;

            case 'ItemList':
                $baseSchema = $this->buildItemListSchema($baseSchema, $term);
                break;

            case 'CollectionPage':
                $baseSchema = $this->buildCollectionPageSchema($baseSchema, $term, $termUrl);
                break;

            case 'Place':
            case 'City':
            case 'Country':
            case 'State':
                $baseSchema = $this->buildPlaceSchema($baseSchema, $term, $schemaType);
                break;

            case 'Person':
                $baseSchema = $this->buildPersonSchema($baseSchema, $term);
                break;

            case 'Organization':
            case 'Brand':
                $baseSchema = $this->buildOrganizationSchema($baseSchema, $term, $schemaType);
                break;

            case 'BreadcrumbList':
                $baseSchema = $this->buildTaxonomyBreadcrumbSchema($term, $termUrl);
                break;
        }

        return $this->cleanSchemaData($baseSchema);
    }

    /**
     * Build DefinedTerm schema for taxonomy
     */
    private function buildDefinedTermSchema(array $schema, \WP_Term $term): array
    {
        // Get the taxonomy object for the term set name
        $taxonomyObj = get_taxonomy($term->taxonomy);
        
        if ($taxonomyObj) {
            $schema['inDefinedTermSet'] = [
                '@type' => 'DefinedTermSet',
                'name' => $taxonomyObj->labels->singular_name ?? $taxonomyObj->label,
            ];
        }

        // Add term ID as identifier
        $schema['identifier'] = $term->slug;

        return $schema;
    }

    /**
     * Build ItemList schema for taxonomy (list of posts in this term)
     */
    private function buildItemListSchema(array $schema, \WP_Term $term): array
    {
        $schema['numberOfItems'] = (int) $term->count;

        // Get recent items in this term
        $posts = get_posts([
            'post_type' => 'any',
            'posts_per_page' => 10,
            'tax_query' => [
                [
                    'taxonomy' => $term->taxonomy,
                    'field' => 'term_id',
                    'terms' => $term->term_id,
                ],
            ],
        ]);

        if (!empty($posts)) {
            $listItems = [];
            foreach ($posts as $index => $post) {
                $listItems[] = [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8'),
                    'url' => get_permalink($post),
                ];
            }
            $schema['itemListElement'] = $listItems;
        }

        return $schema;
    }

    /**
     * Build CollectionPage schema for taxonomy
     */
    private function buildCollectionPageSchema(array $schema, \WP_Term $term, string $termUrl): array
    {
        $schema['mainEntity'] = [
            '@type' => 'ItemList',
            'numberOfItems' => (int) $term->count,
        ];

        // Add breadcrumb
        $schema['breadcrumb'] = $this->buildTaxonomyBreadcrumbSchema($term, $termUrl);

        return $schema;
    }

    /**
     * Build Place schema for taxonomy (locations)
     */
    private function buildPlaceSchema(array $schema, \WP_Term $term, string $schemaType): array
    {
        // Keep the specific type (City, Country, State, or Place)
        $schema['@type'] = $schemaType;

        // Try to get additional location data from term meta
        $address = get_term_meta($term->term_id, 'address', true);
        if ($address) {
            $schema['address'] = $address;
        }

        $geo = get_term_meta($term->term_id, 'geo', true);
        if (is_array($geo) && isset($geo['lat']) && isset($geo['lng'])) {
            $schema['geo'] = [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $geo['lat'],
                'longitude' => (float) $geo['lng'],
            ];
        }

        return $schema;
    }

    /**
     * Build Person schema for taxonomy (authors, speakers, etc.)
     */
    private function buildPersonSchema(array $schema, \WP_Term $term): array
    {
        // Try to get additional person data from term meta
        $jobTitle = get_term_meta($term->term_id, 'job_title', true);
        if ($jobTitle) {
            $schema['jobTitle'] = $jobTitle;
        }

        $email = get_term_meta($term->term_id, 'email', true);
        if ($email && is_email($email)) {
            $schema['email'] = $email;
        }

        $image = get_term_meta($term->term_id, 'image', true);
        if ($image) {
            $schema['image'] = $image;
        }

        // Check if ACF has an image field
        if (function_exists('get_field')) {
            $acfImage = get_field('image', $term);
            if ($acfImage) {
                if (is_array($acfImage) && isset($acfImage['url'])) {
                    $schema['image'] = $acfImage['url'];
                } elseif (is_string($acfImage)) {
                    $schema['image'] = $acfImage;
                }
            }
        }

        return $schema;
    }

    /**
     * Build Organization/Brand schema for taxonomy
     */
    private function buildOrganizationSchema(array $schema, \WP_Term $term, string $schemaType): array
    {
        $schema['@type'] = $schemaType;

        // Try to get logo from term meta or ACF
        $logo = get_term_meta($term->term_id, 'logo', true);
        
        if (!$logo && function_exists('get_field')) {
            $logo = get_field('logo', $term);
            if (is_array($logo) && isset($logo['url'])) {
                $logo = $logo['url'];
            }
        }

        if ($logo) {
            $schema['logo'] = $logo;
        }

        return $schema;
    }

    /**
     * Build breadcrumb schema for taxonomy archive
     */
    private function buildTaxonomyBreadcrumbSchema(\WP_Term $term, string $termUrl): array
    {
        $items = [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => get_bloginfo('name'),
                'item' => home_url('/'),
            ],
        ];

        // Add parent terms for hierarchical taxonomies
        $position = 2;
        $ancestors = get_ancestors($term->term_id, $term->taxonomy, 'taxonomy');
        $ancestors = array_reverse($ancestors);

        foreach ($ancestors as $ancestorId) {
            $ancestor = get_term($ancestorId, $term->taxonomy);
            if ($ancestor && !is_wp_error($ancestor)) {
                $ancestorUrl = get_term_link($ancestor);
                if (!is_wp_error($ancestorUrl)) {
                    $items[] = [
                        '@type' => 'ListItem',
                        'position' => $position++,
                        'name' => html_entity_decode($ancestor->name, ENT_QUOTES, 'UTF-8'),
                        'item' => $ancestorUrl,
                    ];
                }
            }
        }

        // Add current term
        $items[] = [
            '@type' => 'ListItem',
            'position' => $position,
            'name' => html_entity_decode($term->name, ENT_QUOTES, 'UTF-8'),
            'item' => $termUrl,
        ];

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * Clean schema data by removing empty values
     */
    private function cleanSchemaData(array $data): array
    {
        return array_filter($data, function ($value) {
            if (is_array($value)) {
                return !empty($value);
            }
            return $value !== null && $value !== '';
        });
    }

    /**
     * Output schemas as JSON-LD
     */
    private function outputSchemas(array $schemas): void
    {
        if (empty($schemas)) {
            return;
        }

        // Use @graph if multiple schemas
        if (count($schemas) > 1) {
            $output = [
                '@context' => 'https://schema.org',
                '@graph' => array_map(function ($schema) {
                    unset($schema['@context']);
                    return $schema;
                }, $schemas),
            ];
        } else {
            $output = $schemas[0];
        }

        $json = wp_json_encode($output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if ($json === false) {
            $this->logger->error('Failed to encode schema to JSON');
            return;
        }

        echo "\n<!-- Schema Markup Generator by Metodo.dev -->\n";
        echo '<script type="application/ld+json">' . "\n";
        echo $json . "\n";
        echo '</script>' . "\n";
        echo "<!-- /Schema Markup Generator -->\n\n";
    }

    /**
     * Get schema for a specific post (for API/preview)
     *
     * @param int $postId The post ID
     * @return array|null Schema data or null
     */
    public function getSchemaForPost(int $postId): ?array
    {
        $post = get_post($postId);

        if (!$post instanceof WP_Post) {
            return null;
        }

        return $this->getPostSchemas($post);
    }

    /**
     * Get raw JSON for a post (for preview)
     *
     * @param int $postId The post ID
     * @return string JSON string
     */
    public function getJsonForPost(int $postId): string
    {
        $schemas = $this->getSchemaForPost($postId);

        if (empty($schemas)) {
            return '{}';
        }

        if (count($schemas) > 1) {
            $output = [
                '@context' => 'https://schema.org',
                '@graph' => array_map(function ($schema) {
                    unset($schema['@context']);
                    return $schema;
                }, $schemas),
            ];
        } else {
            $output = $schemas[0];
        }

        return wp_json_encode($output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}';
    }

    /**
     * Clear cache for a post
     */
    public function clearCache(int $postId): void
    {
        // Delete all possible cache keys for this post
        $post = get_post($postId);
        if ($post) {
            $this->cache->delete('schema_' . $postId . '_' . $post->post_modified);
        }

        // Also try without modification date (legacy)
        $this->cache->delete('schema_' . $postId);
    }

    /**
     * Process field overrides from the new format
     *
     * Converts the new format:
     * ['property' => ['type' => 'field|custom|schema', 'value' => '...']]
     * 
     * To the standard mapping format:
     * ['property' => 'field_key_or_custom_value']
     * 
     * Custom values are prefixed with 'custom:' and schema types with 'schema:'
     * so they can be handled differently in the schema build process.
     *
     * @param array $overrides Field overrides from post meta
     * @return array Processed mapping array
     */
    private function processFieldOverrides(array $overrides): array
    {
        $mapping = [];

        foreach ($overrides as $property => $override) {
            if (!is_array($override)) {
                continue;
            }

            $type = $override['type'] ?? 'auto';
            $value = $override['value'] ?? '';

            // Skip 'auto' type - it means use global mapping
            if ($type === 'auto' || empty($value)) {
                continue;
            }

            if ($type === 'custom') {
                // Prefix custom values so they're treated as literal values
                $mapping[$property] = 'custom:' . $value;
            } elseif ($type === 'schema') {
                // Prefix schema types for additionalType handling
                $mapping[$property] = 'schema:' . $value;
            } else {
                // 'field' type - use the field key directly
                $mapping[$property] = $value;
            }
        }

        return $mapping;
    }
}

