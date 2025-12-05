<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Schema\Types;

use Metodo\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * Breadcrumb Schema
 *
 * For navigation breadcrumbs showing page hierarchy.
 *
 * @package Metodo\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <plugins@metodo.dev>
 */
class BreadcrumbSchema extends AbstractSchema
{
    public function getType(): string
    {
        return 'BreadcrumbList';
    }

    public function getLabel(): string
    {
        return __('Breadcrumb', 'schema-markup-generator');
    }

    public function getDescription(): string
    {
        return __('For navigation breadcrumbs. Helps search engines understand site structure and displays breadcrumb trails in search results.', 'schema-markup-generator');
    }

    public function build(WP_Post $post, array $mapping = []): array
    {
        $data = $this->buildBase($post);

        // Build breadcrumb items
        $data['itemListElement'] = $this->buildBreadcrumbTrail($post);

        /**
         * Filter breadcrumb schema data
         */
        $data = apply_filters('smg_breadcrumb_schema_data', $data, $post, $mapping);

        return $this->cleanData($data);
    }

    /**
     * Build breadcrumb trail for a post
     */
    private function buildBreadcrumbTrail(WP_Post $post): array
    {
        $items = [];
        $position = 1;

        // Home
        $items[] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'name' => __('Home', 'schema-markup-generator'),
            'item' => home_url('/'),
        ];

        // Post type archive (for CPT)
        $postType = get_post_type_object($post->post_type);
        if ($postType && $postType->has_archive && $post->post_type !== 'post') {
            $archiveLink = get_post_type_archive_link($post->post_type);
            if ($archiveLink) {
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $postType->labels->name,
                    'item' => $archiveLink,
                ];
            }
        }

        // Categories (for posts)
        if ($post->post_type === 'post') {
            $categories = get_the_category($post->ID);
            if (!empty($categories)) {
                // Get primary category
                $primaryCategory = $this->getPrimaryCategory($post->ID, $categories);

                // Build category hierarchy
                $categoryChain = $this->getCategoryHierarchy($primaryCategory);

                foreach ($categoryChain as $category) {
                    $items[] = [
                        '@type' => 'ListItem',
                        'position' => $position++,
                        'name' => $category->name,
                        'item' => get_category_link($category->term_id),
                    ];
                }
            }
        }

        // Page hierarchy (for pages)
        if ($post->post_type === 'page' && $post->post_parent) {
            $ancestors = get_post_ancestors($post->ID);
            $ancestors = array_reverse($ancestors);

            foreach ($ancestors as $ancestorId) {
                $ancestor = get_post($ancestorId);
                if ($ancestor) {
                    $items[] = [
                        '@type' => 'ListItem',
                        'position' => $position++,
                        'name' => get_the_title($ancestor),
                        'item' => get_permalink($ancestor),
                    ];
                }
            }
        }

        // Taxonomy terms (for CPT)
        if (!in_array($post->post_type, ['post', 'page'], true)) {
            $taxonomies = get_object_taxonomies($post->post_type, 'objects');
            foreach ($taxonomies as $taxonomy) {
                if ($taxonomy->hierarchical && $taxonomy->public) {
                    $terms = get_the_terms($post->ID, $taxonomy->name);
                    if ($terms && !is_wp_error($terms)) {
                        $primaryTerm = $this->getPrimaryTerm($post->ID, $taxonomy->name, $terms);
                        if ($primaryTerm) {
                            $termChain = $this->getTermHierarchy($primaryTerm, $taxonomy->name);
                            foreach ($termChain as $term) {
                                $items[] = [
                                    '@type' => 'ListItem',
                                    'position' => $position++,
                                    'name' => $term->name,
                                    'item' => get_term_link($term),
                                ];
                            }
                        }
                        break; // Only use first hierarchical taxonomy
                    }
                }
            }
        }

        // Current page (without item URL per Google guidelines)
        $items[] = [
            '@type' => 'ListItem',
            'position' => $position,
            'name' => html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8'),
        ];

        return $items;
    }

    /**
     * Get primary category (supports Rank Math and Yoast)
     */
    private function getPrimaryCategory(int $postId, array $categories): \WP_Term
    {
        // Check Rank Math primary category
        $rankMathPrimary = get_post_meta($postId, 'rank_math_primary_category', true);
        if ($rankMathPrimary) {
            foreach ($categories as $category) {
                if ($category->term_id == $rankMathPrimary) {
                    return $category;
                }
            }
        }

        // Check Yoast primary category
        $yoastPrimary = get_post_meta($postId, '_yoast_wpseo_primary_category', true);
        if ($yoastPrimary) {
            foreach ($categories as $category) {
                if ($category->term_id == $yoastPrimary) {
                    return $category;
                }
            }
        }

        // Default to first category
        return $categories[0];
    }

    /**
     * Get primary term for a taxonomy
     */
    private function getPrimaryTerm(int $postId, string $taxonomy, array $terms): ?\WP_Term
    {
        $rankMathPrimary = get_post_meta($postId, 'rank_math_primary_' . $taxonomy, true);
        if ($rankMathPrimary) {
            foreach ($terms as $term) {
                if ($term->term_id == $rankMathPrimary) {
                    return $term;
                }
            }
        }

        $yoastPrimary = get_post_meta($postId, '_yoast_wpseo_primary_' . $taxonomy, true);
        if ($yoastPrimary) {
            foreach ($terms as $term) {
                if ($term->term_id == $yoastPrimary) {
                    return $term;
                }
            }
        }

        return $terms[0];
    }

    /**
     * Get category hierarchy (from root to current)
     */
    private function getCategoryHierarchy(\WP_Term $category): array
    {
        $hierarchy = [$category];

        while ($category->parent) {
            $parent = get_category($category->parent);
            if ($parent && !is_wp_error($parent)) {
                array_unshift($hierarchy, $parent);
                $category = $parent;
            } else {
                break;
            }
        }

        return $hierarchy;
    }

    /**
     * Get term hierarchy for any taxonomy
     */
    private function getTermHierarchy(\WP_Term $term, string $taxonomy): array
    {
        $hierarchy = [$term];

        while ($term->parent) {
            $parent = get_term($term->parent, $taxonomy);
            if ($parent && !is_wp_error($parent)) {
                array_unshift($hierarchy, $parent);
                $term = $parent;
            } else {
                break;
            }
        }

        return $hierarchy;
    }

    public function getRequiredProperties(): array
    {
        return ['itemListElement'];
    }

    public function getRecommendedProperties(): array
    {
        return [];
    }

    public function getPropertyDefinitions(): array
    {
        return [
            'itemListElement' => [
                'type' => 'auto',
                'description' => __('Auto-generated navigation path. Shows clickable breadcrumb trail in Google search results.', 'schema-markup-generator'),
                'description_long' => __('The breadcrumb trail is automatically generated based on the page hierarchy: homepage → post type archive → categories/parent pages → current page. This displays as clickable links in Google search results, helping users understand where the page sits in your site structure.', 'schema-markup-generator'),
                'example' => __('Home > Blog > Technology > How to Build a Website', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/BreadcrumbList',
                'auto' => 'hierarchy',
            ],
        ];
    }
}

