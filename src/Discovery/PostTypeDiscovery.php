<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Discovery;

/**
 * Post Type Discovery Service
 *
 * Discovers all registered public post types in WordPress.
 *
 * @package Metodo\SchemaMarkupGenerator\Discovery
 * @author  Michele Marri <plugins@metodo.dev>
 */
class PostTypeDiscovery
{
    /**
     * Cached post types (keyed by includeBuiltIn flag)
     */
    private array $postTypesCache = [];

    /**
     * Get all public post types
     *
     * @param bool $includeBuiltIn Include WordPress built-in post types
     * @return array<string, \WP_Post_Type>
     */
    public function getPostTypes(bool $includeBuiltIn = true): array
    {
        // Don't cache if 'init' hook hasn't run yet (CPTs may not be registered)
        $canCache = did_action('init') > 0;
        
        $cacheKey = $includeBuiltIn ? 'all' : 'custom';
        
        if ($canCache && isset($this->postTypesCache[$cacheKey])) {
            return $this->postTypesCache[$cacheKey];
        }

        $args = [
            'public' => true,
        ];

        if (!$includeBuiltIn) {
            $args['_builtin'] = false;
        }

        $postTypes = get_post_types($args, 'objects');

        // Filter out unwanted types
        unset($postTypes['attachment']);

        /**
         * Filter discovered post types
         *
         * @param array $postTypes Array of WP_Post_Type objects
         */
        $postTypes = apply_filters('smg_discovered_post_types', $postTypes);

        // Only cache after 'init' has run
        if ($canCache) {
            $this->postTypesCache[$cacheKey] = $postTypes;
        }

        return $postTypes;
    }

    /**
     * Get post type labels for display
     *
     * @return array<string, string>
     */
    public function getPostTypeLabels(): array
    {
        $labels = [];
        $postTypes = $this->getPostTypes();

        foreach ($postTypes as $slug => $postType) {
            $labels[$slug] = $postType->labels->singular_name ?? $postType->label;
        }

        return $labels;
    }

    /**
     * Check if a post type exists and is public
     */
    public function isValidPostType(string $postType): bool
    {
        $postTypes = $this->getPostTypes();
        return isset($postTypes[$postType]);
    }

    /**
     * Get post type object by slug
     */
    public function getPostType(string $slug): ?\WP_Post_Type
    {
        $postTypes = $this->getPostTypes();
        return $postTypes[$slug] ?? null;
    }

    /**
     * Get custom post types only (exclude built-in)
     *
     * @return array<string, \WP_Post_Type>
     */
    public function getCustomPostTypes(): array
    {
        return $this->getPostTypes(false);
    }

    /**
     * Check if post type supports a specific feature
     */
    public function postTypeSupports(string $postType, string $feature): bool
    {
        return post_type_supports($postType, $feature);
    }

    /**
     * Get post type capabilities
     */
    public function getPostTypeCapabilities(string $postType): ?object
    {
        $postTypeObj = $this->getPostType($postType);
        return $postTypeObj?->cap;
    }

    /**
     * Reset cached post types
     */
    public function reset(): void
    {
        $this->postTypesCache = [];
    }
}

