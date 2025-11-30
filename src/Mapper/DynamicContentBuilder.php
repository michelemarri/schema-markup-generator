<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Mapper;

use Metodo\SchemaMarkupGenerator\Discovery\TaxonomyDiscovery;
use WP_Post;

/**
 * Dynamic Content Builder
 *
 * Builds dynamic content for schema properties from post data.
 *
 * @package Metodo\SchemaMarkupGenerator\Mapper
 * @author  Michele Marri <plugins@metodo.dev>
 */
class DynamicContentBuilder
{
    private TaxonomyDiscovery $taxonomyDiscovery;

    public function __construct(TaxonomyDiscovery $taxonomyDiscovery)
    {
        $this->taxonomyDiscovery = $taxonomyDiscovery;
    }

    /**
     * Build author data for schema
     *
     * @param WP_Post $post The post object
     * @return array Author schema data
     */
    public function buildAuthor(WP_Post $post): array
    {
        $author = get_userdata($post->post_author);

        if (!$author) {
            return [
                '@type' => 'Person',
                'name' => __('Unknown', 'schema-markup-generator'),
            ];
        }

        $authorData = [
            '@type' => 'Person',
            'name' => $author->display_name,
        ];

        // Author URL
        $authorUrl = get_author_posts_url($author->ID);
        if ($authorUrl) {
            $authorData['url'] = $authorUrl;
        }

        // Author image (Gravatar)
        $gravatarUrl = get_avatar_url($author->ID, ['size' => 256]);
        if ($gravatarUrl) {
            $authorData['image'] = $gravatarUrl;
        }

        // Author description
        $description = get_the_author_meta('description', $author->ID);
        if ($description) {
            $authorData['description'] = $description;
        }

        /**
         * Filter author schema data
         *
         * @param array    $authorData The author data
         * @param \WP_User $author     The author user object
         * @param WP_Post  $post       The post object
         */
        return apply_filters('smg_author_data', $authorData, $author, $post);
    }

    /**
     * Build publisher/organization data
     *
     * @return array Publisher schema data
     */
    public function buildPublisher(): array
    {
        $publisher = [
            '@type' => 'Organization',
            'name' => get_bloginfo('name'),
            'url' => home_url('/'),
        ];

        // Logo
        $customLogoId = get_theme_mod('custom_logo');
        if ($customLogoId) {
            $logo = wp_get_attachment_image_src($customLogoId, 'full');
            if ($logo) {
                $publisher['logo'] = [
                    '@type' => 'ImageObject',
                    'url' => $logo[0],
                    'width' => $logo[1],
                    'height' => $logo[2],
                ];
            }
        }

        /**
         * Filter publisher schema data
         *
         * @param array $publisher The publisher data
         */
        return apply_filters('smg_publisher_data', $publisher);
    }

    /**
     * Build image data for schema
     *
     * @param WP_Post $post The post object
     * @return array|null Image schema data or null
     */
    public function buildFeaturedImage(WP_Post $post): ?array
    {
        $thumbnailId = get_post_thumbnail_id($post);

        if (!$thumbnailId) {
            return null;
        }

        $image = wp_get_attachment_image_src($thumbnailId, 'full');

        if (!$image) {
            return null;
        }

        $imageData = [
            '@type' => 'ImageObject',
            'url' => $image[0],
            'width' => $image[1],
            'height' => $image[2],
        ];

        // Add alt text
        $alt = get_post_meta($thumbnailId, '_wp_attachment_image_alt', true);
        if ($alt) {
            $imageData['caption'] = $alt;
        }

        return $imageData;
    }

    /**
     * Build all images from content
     *
     * @param WP_Post $post The post object
     * @return array Array of image data
     */
    public function buildContentImages(WP_Post $post): array
    {
        $images = [];

        // Featured image first
        $featured = $this->buildFeaturedImage($post);
        if ($featured) {
            $images[] = $featured;
        }

        // Extract images from content
        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $post->post_content, $matches);

        foreach ($matches[1] as $imageUrl) {
            // Get attachment ID from URL
            $attachmentId = attachment_url_to_postid($imageUrl);

            if ($attachmentId) {
                $image = wp_get_attachment_image_src($attachmentId, 'full');
                if ($image) {
                    $images[] = [
                        '@type' => 'ImageObject',
                        'url' => $image[0],
                        'width' => $image[1],
                        'height' => $image[2],
                    ];
                }
            } else {
                // External image
                $images[] = [
                    '@type' => 'ImageObject',
                    'url' => $imageUrl,
                ];
            }
        }

        return $images;
    }

    /**
     * Build breadcrumb data for a post
     *
     * @param WP_Post $post The post object
     * @return array Breadcrumb list data
     */
    public function buildBreadcrumb(WP_Post $post): array
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

        // Category (for posts)
        if ($post->post_type === 'post') {
            $categories = get_the_category($post->ID);
            if (!empty($categories)) {
                $category = $categories[0];
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => $category->name,
                    'item' => get_category_link($category->term_id),
                ];
            }
        }

        // Parent pages (for pages)
        if ($post->post_type === 'page' && $post->post_parent) {
            $ancestors = array_reverse(get_post_ancestors($post->ID));
            foreach ($ancestors as $ancestorId) {
                $items[] = [
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'name' => get_the_title($ancestorId),
                    'item' => get_permalink($ancestorId),
                ];
            }
        }

        // Current page (no item URL)
        $items[] = [
            '@type' => 'ListItem',
            'position' => $position,
            'name' => html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8'),
        ];

        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    /**
     * Build aggregate rating data
     *
     * @param float $rating      The rating value
     * @param int   $ratingCount The number of ratings
     * @param float $bestRating  The best possible rating
     * @param float $worstRating The worst possible rating
     * @return array Rating schema data
     */
    public function buildAggregateRating(
        float $rating,
        int $ratingCount,
        float $bestRating = 5.0,
        float $worstRating = 1.0
    ): array {
        return [
            '@type' => 'AggregateRating',
            'ratingValue' => $rating,
            'ratingCount' => $ratingCount,
            'bestRating' => $bestRating,
            'worstRating' => $worstRating,
        ];
    }

    /**
     * Build offer data
     *
     * @param float       $price        The price
     * @param string      $currency     Currency code
     * @param string      $availability Availability status
     * @param string|null $url          Offer URL
     * @return array Offer schema data
     */
    public function buildOffer(
        float $price,
        string $currency = 'EUR',
        string $availability = 'InStock',
        ?string $url = null
    ): array {
        $offer = [
            '@type' => 'Offer',
            'price' => $price,
            'priceCurrency' => $currency,
            'availability' => 'https://schema.org/' . $availability,
        ];

        if ($url) {
            $offer['url'] = $url;
        }

        return $offer;
    }

    /**
     * Build address data
     *
     * @param array $addressData Address components
     * @return array Address schema data
     */
    public function buildAddress(array $addressData): array
    {
        $address = ['@type' => 'PostalAddress'];

        $mapping = [
            'street' => 'streetAddress',
            'streetAddress' => 'streetAddress',
            'city' => 'addressLocality',
            'addressLocality' => 'addressLocality',
            'state' => 'addressRegion',
            'region' => 'addressRegion',
            'addressRegion' => 'addressRegion',
            'zip' => 'postalCode',
            'postalCode' => 'postalCode',
            'postal_code' => 'postalCode',
            'country' => 'addressCountry',
            'addressCountry' => 'addressCountry',
        ];

        foreach ($mapping as $input => $output) {
            if (!empty($addressData[$input])) {
                $address[$output] = $addressData[$input];
            }
        }

        return $address;
    }

    /**
     * Build geo coordinates
     *
     * @param float $latitude  Latitude
     * @param float $longitude Longitude
     * @return array Geo schema data
     */
    public function buildGeoCoordinates(float $latitude, float $longitude): array
    {
        return [
            '@type' => 'GeoCoordinates',
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    /**
     * Build main entity of page
     *
     * @param WP_Post $post The post object
     * @return array Main entity reference
     */
    public function buildMainEntityOfPage(WP_Post $post): array
    {
        return [
            '@type' => 'WebPage',
            '@id' => get_permalink($post),
        ];
    }

    /**
     * Extract keywords from post
     *
     * @param WP_Post $post The post object
     * @return string Comma-separated keywords
     */
    public function extractKeywords(WP_Post $post): string
    {
        $keywords = [];

        // Tags
        $tags = get_the_tags($post->ID);
        if ($tags && !is_wp_error($tags)) {
            foreach ($tags as $tag) {
                $keywords[] = $tag->name;
            }
        }

        // Categories
        $categories = get_the_category($post->ID);
        foreach ($categories as $category) {
            if ($category->slug !== 'uncategorized') {
                $keywords[] = $category->name;
            }
        }

        return implode(', ', array_unique($keywords));
    }

    /**
     * Calculate word count
     *
     * @param WP_Post $post The post object
     * @return int Word count
     */
    public function calculateWordCount(WP_Post $post): int
    {
        $content = wp_strip_all_tags($post->post_content);
        return str_word_count($content);
    }

    /**
     * Estimate reading time in minutes
     *
     * @param WP_Post $post           The post object
     * @param int     $wordsPerMinute Average reading speed
     * @return int Reading time in minutes
     */
    public function estimateReadingTime(WP_Post $post, int $wordsPerMinute = 200): int
    {
        $wordCount = $this->calculateWordCount($post);
        return max(1, (int) ceil($wordCount / $wordsPerMinute));
    }
}

