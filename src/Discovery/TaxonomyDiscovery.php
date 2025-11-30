<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Discovery;

/**
 * Taxonomy Discovery Service
 *
 * Discovers all registered taxonomies and their associations with post types.
 *
 * @package Metodo\SchemaMarkupGenerator\Discovery
 * @author  Michele Marri <plugins@metodo.dev>
 */
class TaxonomyDiscovery
{
    /**
     * Cached taxonomies
     */
    private ?array $taxonomies = null;

    /**
     * Cached taxonomies per post type
     */
    private array $postTypeTaxonomies = [];

    /**
     * Get all public taxonomies
     *
     * @param bool $includeBuiltIn Include WordPress built-in taxonomies
     * @return array<string, \WP_Taxonomy>
     */
    public function getTaxonomies(bool $includeBuiltIn = true): array
    {
        if ($this->taxonomies !== null) {
            return $this->taxonomies;
        }

        $args = [
            'public' => true,
        ];

        if (!$includeBuiltIn) {
            $args['_builtin'] = false;
        }

        $taxonomies = get_taxonomies($args, 'objects');

        /**
         * Filter discovered taxonomies
         *
         * @param array $taxonomies Array of WP_Taxonomy objects
         */
        $this->taxonomies = apply_filters('smg_discovered_taxonomies', $taxonomies);

        return $this->taxonomies;
    }

    /**
     * Get taxonomies for a specific post type
     *
     * @param string $postType The post type slug
     * @return array<string, \WP_Taxonomy>
     */
    public function getTaxonomiesForPostType(string $postType): array
    {
        if (isset($this->postTypeTaxonomies[$postType])) {
            return $this->postTypeTaxonomies[$postType];
        }

        $taxonomyNames = get_object_taxonomies($postType, 'objects');

        // Filter to only public taxonomies
        $taxonomies = array_filter($taxonomyNames, fn($tax) => $tax->public);

        /**
         * Filter taxonomies for a post type
         *
         * @param array  $taxonomies Array of WP_Taxonomy objects
         * @param string $postType   The post type
         */
        $this->postTypeTaxonomies[$postType] = apply_filters(
            'smg_post_type_taxonomies',
            $taxonomies,
            $postType
        );

        return $this->postTypeTaxonomies[$postType];
    }

    /**
     * Get taxonomy labels for display
     *
     * @param string|null $postType Optionally filter by post type
     * @return array<string, string>
     */
    public function getTaxonomyLabels(?string $postType = null): array
    {
        $taxonomies = $postType
            ? $this->getTaxonomiesForPostType($postType)
            : $this->getTaxonomies();

        $labels = [];

        foreach ($taxonomies as $slug => $taxonomy) {
            $labels[$slug] = $taxonomy->labels->singular_name ?? $taxonomy->label;
        }

        return $labels;
    }

    /**
     * Get terms for a post
     *
     * @param int    $postId   The post ID
     * @param string $taxonomy The taxonomy slug
     * @return array Array of WP_Term objects
     */
    public function getPostTerms(int $postId, string $taxonomy): array
    {
        $terms = get_the_terms($postId, $taxonomy);

        if (is_wp_error($terms) || !$terms) {
            return [];
        }

        return $terms;
    }

    /**
     * Get all terms for a post across all its taxonomies
     *
     * @param int         $postId   The post ID
     * @param string|null $postType Optional post type (auto-detected if null)
     * @return array<string, array> Associative array of taxonomy => terms
     */
    public function getAllPostTerms(int $postId, ?string $postType = null): array
    {
        if ($postType === null) {
            $postType = get_post_type($postId);
        }

        $taxonomies = $this->getTaxonomiesForPostType($postType);
        $allTerms = [];

        foreach (array_keys($taxonomies) as $taxonomy) {
            $terms = $this->getPostTerms($postId, $taxonomy);
            if (!empty($terms)) {
                $allTerms[$taxonomy] = $terms;
            }
        }

        return $allTerms;
    }

    /**
     * Get primary term for a post in a taxonomy
     *
     * Supports Rank Math and Yoast SEO primary terms if available.
     *
     * @param int    $postId   The post ID
     * @param string $taxonomy The taxonomy slug
     * @return \WP_Term|null
     */
    public function getPrimaryTerm(int $postId, string $taxonomy): ?\WP_Term
    {
        // Check Rank Math primary term
        $rankMathPrimary = get_post_meta($postId, 'rank_math_primary_' . $taxonomy, true);
        if ($rankMathPrimary) {
            $term = get_term((int) $rankMathPrimary, $taxonomy);
            if ($term && !is_wp_error($term)) {
                return $term;
            }
        }

        // Check Yoast primary term
        $yoastPrimary = get_post_meta($postId, '_yoast_wpseo_primary_' . $taxonomy, true);
        if ($yoastPrimary) {
            $term = get_term((int) $yoastPrimary, $taxonomy);
            if ($term && !is_wp_error($term)) {
                return $term;
            }
        }

        // Fallback to first term
        $terms = $this->getPostTerms($postId, $taxonomy);
        return $terms[0] ?? null;
    }

    /**
     * Check if a taxonomy exists and is public
     */
    public function isValidTaxonomy(string $taxonomy): bool
    {
        $taxonomies = $this->getTaxonomies();
        return isset($taxonomies[$taxonomy]);
    }

    /**
     * Get taxonomy object by slug
     */
    public function getTaxonomy(string $slug): ?\WP_Taxonomy
    {
        $taxonomies = $this->getTaxonomies();
        return $taxonomies[$slug] ?? null;
    }

    /**
     * Get custom taxonomies only (exclude built-in)
     *
     * @return array<string, \WP_Taxonomy>
     */
    public function getCustomTaxonomies(): array
    {
        return $this->getTaxonomies(false);
    }

    /**
     * Get hierarchical taxonomies for a post type
     */
    public function getHierarchicalTaxonomies(string $postType): array
    {
        $taxonomies = $this->getTaxonomiesForPostType($postType);
        return array_filter($taxonomies, fn($tax) => $tax->hierarchical);
    }

    /**
     * Get flat (non-hierarchical) taxonomies for a post type
     */
    public function getFlatTaxonomies(string $postType): array
    {
        $taxonomies = $this->getTaxonomiesForPostType($postType);
        return array_filter($taxonomies, fn($tax) => !$tax->hierarchical);
    }

    /**
     * Reset cached data
     */
    public function reset(): void
    {
        $this->taxonomies = null;
        $this->postTypeTaxonomies = [];
    }
}

