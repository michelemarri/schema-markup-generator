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

        if (!empty($schemas)) {
            $this->outputSchemas($schemas);
        }
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

