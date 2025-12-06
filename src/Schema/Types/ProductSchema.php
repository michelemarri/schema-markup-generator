<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Schema\Types;

use Metodo\SchemaMarkupGenerator\Schema\AbstractSchema;
use WP_Post;

/**
 * Product Schema
 *
 * For products and e-commerce items, including subscription products.
 * Supports Offer with PriceSpecification for recurring billing (memberships, subscriptions).
 *
 * @package Metodo\SchemaMarkupGenerator\Schema\Types
 * @author  Michele Marri <plugins@metodo.dev>
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
        return __('For products, e-commerce items, and subscription products. Supports recurring billing with PriceSpecification for memberships (MemberPress, WooCommerce Subscriptions).', 'schema-markup-generator');
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
     * 
     * Supports both one-time purchases and recurring subscriptions with PriceSpecification.
     */
    private function buildOffers(WP_Post $post, array $mapping, mixed $price): array
    {
        $priceCurrency = $this->getMappedValue($post, $mapping, 'priceCurrency') ?: 'EUR';

        $offers = [
            '@type' => 'Offer',
            'price' => (float) $price,
            'priceCurrency' => $priceCurrency,
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

        // Eligible duration (for subscriptions) - ISO 8601 duration (P1M, P1Y, etc.)
        $eligibleDuration = $this->getMappedValue($post, $mapping, 'eligibleDuration');
        if ($eligibleDuration) {
            $offers['eligibleDuration'] = $this->formatDuration($eligibleDuration);
        }

        // Price Specification for recurring billing (subscriptions/memberships)
        $priceSpecification = $this->buildPriceSpecification($post, $mapping, $price, $priceCurrency);
        if (!empty($priceSpecification)) {
            $offers['priceSpecification'] = $priceSpecification;
        }

        return $offers;
    }

    /**
     * Build PriceSpecification for recurring billing
     * 
     * Creates a UnitPriceSpecification with billing details for subscriptions.
     */
    private function buildPriceSpecification(WP_Post $post, array $mapping, mixed $price, string $priceCurrency): ?array
    {
        $billingDuration = $this->getMappedValue($post, $mapping, 'billingDuration');
        $billingIncrement = $this->getMappedValue($post, $mapping, 'billingIncrement');

        // Only build if we have billing information
        if (!$billingDuration && !$billingIncrement) {
            return null;
        }

        $priceSpec = [
            '@type' => 'UnitPriceSpecification',
            'price' => (float) $price,
            'priceCurrency' => $priceCurrency,
        ];

        // Add billing duration (number of units)
        if ($billingDuration) {
            $priceSpec['billingDuration'] = (int) $billingDuration;
        }

        // Add billing increment (time unit: Month, Year, Week, Day)
        if ($billingIncrement) {
            // Normalize billing increment
            $validIncrements = ['Month', 'Year', 'Week', 'Day'];
            $normalizedIncrement = ucfirst(strtolower($billingIncrement));
            if (in_array($normalizedIncrement, $validIncrements)) {
                $priceSpec['billingIncrement'] = $normalizedIncrement;
            }
        }

        // Reference quantity (how many billing periods)
        $referenceQuantity = $this->getMappedValue($post, $mapping, 'referenceQuantity');
        if ($referenceQuantity) {
            $priceSpec['referenceQuantity'] = [
                '@type' => 'QuantitativeValue',
                'value' => (int) $referenceQuantity,
            ];
        }

        return $priceSpec;
    }

    /**
     * Format duration to ISO 8601 if not already formatted
     */
    private function formatDuration(mixed $duration): string
    {
        if (is_string($duration) && str_starts_with(strtoupper($duration), 'P')) {
            return strtoupper($duration);
        }

        // If numeric, assume months
        if (is_numeric($duration)) {
            return 'P' . (int) $duration . 'M';
        }

        // Try to parse common formats
        $duration = strtolower(trim((string) $duration));
        
        // Match patterns like "1 month", "12 months", "1 year"
        if (preg_match('/^(\d+)\s*(month|year|week|day)s?$/i', $duration, $matches)) {
            $num = (int) $matches[1];
            $unit = strtoupper(substr($matches[2], 0, 1));
            return "P{$num}{$unit}";
        }

        // Return as-is with P prefix if doesn't start with P
        return 'P' . strtoupper($duration);
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
        return ['description', 'brand', 'sku', 'aggregateRating', 'review', 'eligibleDuration', 'billingDuration', 'billingIncrement'];
    }

    public function getPropertyDefinitions(): array
    {
        return [
            'name' => [
                'type' => 'text',
                'description' => __('Product title. Shown in Google Shopping and product rich results.', 'schema-markup-generator'),
                'description_long' => __('The name of the product. This is the primary identifier shown in search results, Google Shopping, and product rich snippets. Use clear, descriptive names that include key product attributes.', 'schema-markup-generator'),
                'example' => __('Apple iPhone 15 Pro Max 256GB - Natural Titanium, Sony WH-1000XM5 Wireless Headphones', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/name',
                'auto' => 'post_title',
            ],
            'description' => [
                'type' => 'text',
                'description' => __('Product summary. Displayed in search results and shopping feeds.', 'schema-markup-generator'),
                'description_long' => __('A detailed description of the product. Include key features, specifications, and benefits. This text may appear in search snippets and helps users understand the product before clicking.', 'schema-markup-generator'),
                'example' => __('Premium wireless headphones with industry-leading noise cancellation, 30-hour battery life, and crystal-clear audio quality.', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/description',
                'auto' => 'post_excerpt',
            ],
            'sku' => [
                'type' => 'text',
                'description' => __('Unique product identifier. Required for Google Merchant Center integration.', 'schema-markup-generator'),
                'description_long' => __('The Stock Keeping Unit (SKU) is a merchant-specific identifier for the product. This helps track inventory and is required for Google Merchant Center feeds.', 'schema-markup-generator'),
                'example' => __('WH1000XM5-BLK, IPHONE15PM-256-NAT', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/sku',
            ],
            'brand' => [
                'type' => 'text',
                'description' => __('Brand/manufacturer name. Improves product discoverability in branded searches.', 'schema-markup-generator'),
                'description_long' => __('The brand or manufacturer of the product. Brand information helps Google match products with branded searches and improves visibility in Google Shopping.', 'schema-markup-generator'),
                'example' => __('Sony, Apple, Nike, Samsung', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/brand',
            ],
            'price' => [
                'type' => 'number',
                'description' => __('Product price. Required for price display in search results.', 'schema-markup-generator'),
                'description_long' => __('The current price of the product. Use the actual selling price, not the recommended retail price. Price is required to show rich results with pricing information.', 'schema-markup-generator'),
                'example' => __('299.99, 1499.00, 49.95', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/price',
            ],
            'priceCurrency' => [
                'type' => 'text',
                'description' => __('ISO 4217 currency code (EUR, USD, GBP). Required with price.', 'schema-markup-generator'),
                'description_long' => __('The currency in which the price is specified. Use the 3-letter ISO 4217 currency code. This is required whenever a price is specified.', 'schema-markup-generator'),
                'example' => __('EUR, USD, GBP, CHF', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/priceCurrency',
            ],
            'availability' => [
                'type' => 'select',
                'description' => __('Stock status. Shown in search results and affects click-through rate.', 'schema-markup-generator'),
                'description_long' => __('The availability status of the product. This information is displayed in search results and can significantly impact click-through rates - users prefer to click on in-stock products.', 'schema-markup-generator'),
                'example' => __('InStock, OutOfStock, PreOrder', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/availability',
                'options' => ['InStock', 'OutOfStock', 'PreOrder', 'Discontinued'],
            ],
            'gtin' => [
                'type' => 'text',
                'description' => __('Global Trade Item Number (EAN/UPC). Helps Google match products across retailers.', 'schema-markup-generator'),
                'description_long' => __('The Global Trade Item Number (GTIN) includes UPC, EAN, ISBN, and JAN codes. Providing GTIN helps Google match your product with the same product from other retailers, improving visibility.', 'schema-markup-generator'),
                'example' => __('0012345678905 (UPC), 5901234123457 (EAN-13)', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/gtin',
            ],
            'mpn' => [
                'type' => 'text',
                'description' => __('Manufacturer Part Number. Alternative identifier when GTIN is unavailable.', 'schema-markup-generator'),
                'description_long' => __('The Manufacturer Part Number (MPN) is assigned by the manufacturer. Use this when GTIN is not available. It helps Google identify the specific product.', 'schema-markup-generator'),
                'example' => __('WH-1000XM5, A2849LL/A, NM-BB-02', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/mpn',
            ],
            'ratingValue' => [
                'type' => 'number',
                'description' => __('Average rating (1-5). Shows star rating in search results - major CTR boost.', 'schema-markup-generator'),
                'description_long' => __('The average rating value based on customer reviews. This displays as star ratings in search results, which significantly improves click-through rates. Must be between 1 and 5 (or your bestRating value).', 'schema-markup-generator'),
                'example' => __('4.5, 4.8, 3.9', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/ratingValue',
            ],
            'ratingCount' => [
                'type' => 'number',
                'description' => __('Total reviews count. Displayed with star rating for social proof.', 'schema-markup-generator'),
                'description_long' => __('The total number of ratings/reviews. Shown alongside the star rating to provide social proof. Higher numbers increase trust and click-through rates.', 'schema-markup-generator'),
                'example' => __('127, 1543, 89', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/ratingCount',
            ],

            // ========================================
            // Subscription / Recurring Billing Properties
            // ========================================
            'eligibleDuration' => [
                'type' => 'text',
                'description' => __('Subscription duration (ISO 8601: P1M=1 month, P1Y=1 year). For memberships and recurring products.', 'schema-markup-generator'),
                'description_long' => __('The duration of the subscription or membership period in ISO 8601 format. This tells Google this is a recurring product. Examples: P1M (1 month), P3M (3 months), P1Y (1 year). Used for MemberPress, WooCommerce Subscriptions, etc.', 'schema-markup-generator'),
                'example' => __('P1M (monthly), P3M (quarterly), P1Y (yearly), P1W (weekly)', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/eligibleDuration',
            ],
            'billingDuration' => [
                'type' => 'number',
                'description' => __('Billing cycle number (e.g., 1 for monthly, 12 for yearly). Used with billingIncrement.', 'schema-markup-generator'),
                'description_long' => __('The numeric duration of each billing cycle. Combined with billingIncrement to define the recurring payment schedule. For monthly billing, use 1 with Month increment.', 'schema-markup-generator'),
                'example' => __('1 (monthly), 3 (quarterly), 12 (yearly)', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/billingDuration',
            ],
            'billingIncrement' => [
                'type' => 'select',
                'description' => __('Billing time unit (Month, Year, Week, Day). Combined with billingDuration.', 'schema-markup-generator'),
                'description_long' => __('The time unit for billing cycles. Combined with billingDuration to define the payment schedule. For example, billingDuration=1 + billingIncrement=Month means monthly billing.', 'schema-markup-generator'),
                'example' => __('Month (monthly), Year (annual), Week (weekly)', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/billingIncrement',
                'options' => ['Month', 'Year', 'Week', 'Day'],
            ],
            'referenceQuantity' => [
                'type' => 'number',
                'description' => __('Number of billing periods (optional). For multi-period subscriptions.', 'schema-markup-generator'),
                'description_long' => __('The reference quantity for the price specification. Use this when pricing covers multiple billing periods or units.', 'schema-markup-generator'),
                'example' => __('1, 6, 12', 'schema-markup-generator'),
                'schema_url' => 'https://schema.org/referenceQuantity',
            ],
        ];
    }
}

