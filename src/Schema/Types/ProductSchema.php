<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Schema\Types;

use flavor\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * Product Schema
 *
 * For products and e-commerce items.
 *
 * @package flavor\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <info@metodo.dev>
 */
class ProductSchema extends AbstractSchema
{
    public function getType(): string
    {
        return 'Product';
    }

    public function getLabel(): string
    {
        return __('Product', 'schema-markup-generator');
    }

    public function getDescription(): string
    {
        return __('For products and e-commerce items. Enables rich results with pricing, availability, and reviews.', 'schema-markup-generator');
    }

    public function build(WP_Post $post, array $mapping = []): array
    {
        $data = $this->buildBase($post);

        // Core properties
        $data['name'] = html_entity_decode(get_the_title($post), ENT_QUOTES, 'UTF-8');
        $data['description'] = $this->getPostDescription($post);
        $data['url'] = $this->getPostUrl($post);

        // Image
        $image = $this->getFeaturedImage($post);
        if ($image) {
            $data['image'] = $image['url'];
        }

        // SKU from mapping or meta
        $sku = $this->getMappedValue($post, $mapping, 'sku');
        if ($sku) {
            $data['sku'] = $sku;
        }

        // Brand
        $brand = $this->getMappedValue($post, $mapping, 'brand');
        if ($brand) {
            $data['brand'] = [
                '@type' => 'Brand',
                'name' => is_array($brand) ? ($brand['name'] ?? '') : $brand,
            ];
        }

        // Price and Offers
        $price = $this->getMappedValue($post, $mapping, 'price');
        if ($price) {
            $data['offers'] = $this->buildOffers($post, $mapping, $price);
        }

        // Aggregate Rating
        $rating = $this->getMappedValue($post, $mapping, 'ratingValue');
        $ratingCount = $this->getMappedValue($post, $mapping, 'ratingCount');
        if ($rating) {
            $data['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (float) $rating,
                'ratingCount' => (int) ($ratingCount ?: 1),
            ];
        }

        // Reviews
        $reviews = $this->getMappedValue($post, $mapping, 'reviews');
        if (is_array($reviews) && !empty($reviews)) {
            $data['review'] = $this->buildReviews($reviews);
        }

        // GTIN/MPN
        $gtin = $this->getMappedValue($post, $mapping, 'gtin');
        if ($gtin) {
            $data['gtin'] = $gtin;
        }

        $mpn = $this->getMappedValue($post, $mapping, 'mpn');
        if ($mpn) {
            $data['mpn'] = $mpn;
        }

        /**
         * Filter product schema data
         */
        $data = apply_filters('smg_product_schema_data', $data, $post, $mapping);

        return $this->cleanData($data);
    }

    /**
     * Build offers data
     */
    private function buildOffers(WP_Post $post, array $mapping, mixed $price): array
    {
        $offers = [
            '@type' => 'Offer',
            'price' => (float) $price,
            'priceCurrency' => $this->getMappedValue($post, $mapping, 'priceCurrency') ?: 'EUR',
            'url' => $this->getPostUrl($post),
        ];

        // Availability
        $availability = $this->getMappedValue($post, $mapping, 'availability');
        if ($availability) {
            $offers['availability'] = 'https://schema.org/' . $availability;
        } else {
            $offers['availability'] = 'https://schema.org/InStock';
        }

        // Price valid until
        $priceValidUntil = $this->getMappedValue($post, $mapping, 'priceValidUntil');
        if ($priceValidUntil) {
            $offers['priceValidUntil'] = $priceValidUntil;
        }

        return $offers;
    }

    /**
     * Build reviews array
     */
    private function buildReviews(array $reviews): array
    {
        $schemaReviews = [];

        foreach ($reviews as $review) {
            $schemaReviews[] = [
                '@type' => 'Review',
                'reviewRating' => [
                    '@type' => 'Rating',
                    'ratingValue' => (float) ($review['rating'] ?? 5),
                ],
                'author' => [
                    '@type' => 'Person',
                    'name' => $review['author'] ?? __('Anonymous', 'schema-markup-generator'),
                ],
                'reviewBody' => $review['body'] ?? '',
            ];
        }

        return $schemaReviews;
    }

    public function getRequiredProperties(): array
    {
        return ['name', 'image', 'offers'];
    }

    public function getRecommendedProperties(): array
    {
        return ['description', 'brand', 'sku', 'aggregateRating', 'review'];
    }

    public function getPropertyDefinitions(): array
    {
        return [
            'name' => [
                'type' => 'text',
                'description' => __('Product name', 'schema-markup-generator'),
                'auto' => 'post_title',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('Product description', 'schema-markup-generator'),
                'auto' => 'post_excerpt',
            ],
            'sku' => [
                'type' => 'text',
                'description' => __('Stock Keeping Unit', 'schema-markup-generator'),
            ],
            'brand' => [
                'type' => 'text',
                'description' => __('Brand name', 'schema-markup-generator'),
            ],
            'price' => [
                'type' => 'number',
                'description' => __('Product price', 'schema-markup-generator'),
            ],
            'priceCurrency' => [
                'type' => 'text',
                'description' => __('Currency code (EUR, USD, etc.)', 'schema-markup-generator'),
            ],
            'availability' => [
                'type' => 'select',
                'description' => __('Product availability', 'schema-markup-generator'),
                'options' => ['InStock', 'OutOfStock', 'PreOrder', 'Discontinued'],
            ],
            'gtin' => [
                'type' => 'text',
                'description' => __('GTIN/EAN/UPC code', 'schema-markup-generator'),
            ],
            'mpn' => [
                'type' => 'text',
                'description' => __('Manufacturer Part Number', 'schema-markup-generator'),
            ],
        ];
    }
}

