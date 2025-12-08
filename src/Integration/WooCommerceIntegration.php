<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Integration;

use WP_Post;
use WC_Product;

/**
 * WooCommerce Integration
 *
 * Complete integration with WooCommerce for Product schema.
 * Exposes all WooCommerce product fields as mappable virtual fields
 * for automatic schema generation.
 *
 * @package Metodo\SchemaMarkupGenerator\Integration
 * @author  Michele Marri <plugins@metodo.dev>
 */
class WooCommerceIntegration
{
    /**
     * Virtual/computed fields available globally (all post types)
     */
    private const GLOBAL_VIRTUAL_FIELDS = [
        'woo_currency_code' => [
            'label' => 'Currency Code',
            'type' => 'text',
            'description' => 'ISO 4217 currency code from WooCommerce settings (e.g. EUR, USD)',
        ],
        'woo_currency_symbol' => [
            'label' => 'Currency Symbol',
            'type' => 'text',
            'description' => 'Currency symbol from WooCommerce settings (e.g. €, $)',
        ],
    ];

    /**
     * Virtual/computed fields available only for WooCommerce products
     */
    private const PRODUCT_VIRTUAL_FIELDS = [
        // === Pricing ===
        'woo_price' => [
            'label' => 'Price (Active)',
            'type' => 'number',
            'description' => 'Current active price (sale price if on sale, otherwise regular price)',
            'group' => 'Pricing',
        ],
        'woo_regular_price' => [
            'label' => 'Regular Price',
            'type' => 'number',
            'description' => 'Regular/list price before any discounts',
            'group' => 'Pricing',
        ],
        'woo_sale_price' => [
            'label' => 'Sale Price',
            'type' => 'number',
            'description' => 'Discounted sale price (if product is on sale)',
            'group' => 'Pricing',
        ],
        'woo_price_html' => [
            'label' => 'Price (Formatted HTML)',
            'type' => 'text',
            'description' => 'Price formatted with currency symbol and HTML markup',
            'group' => 'Pricing',
        ],

        // === Identifiers ===
        'woo_sku' => [
            'label' => 'SKU',
            'type' => 'text',
            'description' => 'Stock Keeping Unit - unique product identifier',
            'group' => 'Identifiers',
        ],
        'woo_gtin' => [
            'label' => 'GTIN/EAN/UPC',
            'type' => 'text',
            'description' => 'Global Trade Item Number (from product meta or GTIN plugin)',
            'group' => 'Identifiers',
        ],
        'woo_mpn' => [
            'label' => 'MPN',
            'type' => 'text',
            'description' => 'Manufacturer Part Number',
            'group' => 'Identifiers',
        ],

        // === Stock & Availability ===
        'woo_stock_status' => [
            'label' => 'Stock Status (Schema)',
            'type' => 'text',
            'description' => 'Schema.org availability value: InStock, OutOfStock, PreOrder, BackOrder',
            'group' => 'Stock',
        ],
        'woo_stock_status_raw' => [
            'label' => 'Stock Status (Raw)',
            'type' => 'text',
            'description' => 'WooCommerce raw status: instock, outofstock, onbackorder',
            'group' => 'Stock',
        ],
        'woo_stock_quantity' => [
            'label' => 'Stock Quantity',
            'type' => 'number',
            'description' => 'Number of items in stock (if stock management is enabled)',
            'group' => 'Stock',
        ],
        'woo_is_in_stock' => [
            'label' => 'Is In Stock',
            'type' => 'boolean',
            'description' => 'Whether the product is currently in stock',
            'group' => 'Stock',
        ],
        'woo_backorders_allowed' => [
            'label' => 'Backorders Allowed',
            'type' => 'boolean',
            'description' => 'Whether backorders are allowed for this product',
            'group' => 'Stock',
        ],

        // === Rating & Reviews ===
        'woo_average_rating' => [
            'label' => 'Average Rating',
            'type' => 'number',
            'description' => 'Average customer rating (1-5 stars)',
            'group' => 'Reviews',
        ],
        'woo_review_count' => [
            'label' => 'Review Count',
            'type' => 'number',
            'description' => 'Total number of customer reviews',
            'group' => 'Reviews',
        ],
        'woo_rating_count' => [
            'label' => 'Rating Count',
            'type' => 'number',
            'description' => 'Total number of ratings (may differ from reviews)',
            'group' => 'Reviews',
        ],

        // === Sale Dates ===
        'woo_sale_price_dates_from' => [
            'label' => 'Sale Start Date',
            'type' => 'text',
            'description' => 'Date when the sale price starts (ISO 8601 format)',
            'group' => 'Promotions',
        ],
        'woo_sale_price_dates_to' => [
            'label' => 'Sale End Date',
            'type' => 'text',
            'description' => 'Date when the sale price ends - ideal for priceValidUntil (ISO 8601 format)',
            'group' => 'Promotions',
        ],
        'woo_is_on_sale' => [
            'label' => 'Is On Sale',
            'type' => 'boolean',
            'description' => 'Whether the product is currently on sale',
            'group' => 'Promotions',
        ],

        // === Dimensions & Weight ===
        'woo_weight' => [
            'label' => 'Weight',
            'type' => 'text',
            'description' => 'Product weight with unit (e.g. "2.5 kg")',
            'group' => 'Dimensions',
        ],
        'woo_weight_value' => [
            'label' => 'Weight (Value Only)',
            'type' => 'number',
            'description' => 'Product weight numeric value',
            'group' => 'Dimensions',
        ],
        'woo_weight_unit' => [
            'label' => 'Weight Unit',
            'type' => 'text',
            'description' => 'Weight unit from WooCommerce settings (kg, g, lbs, oz)',
            'group' => 'Dimensions',
        ],
        'woo_dimensions' => [
            'label' => 'Dimensions (L×W×H)',
            'type' => 'text',
            'description' => 'Product dimensions formatted as "L × W × H unit"',
            'group' => 'Dimensions',
        ],
        'woo_length' => [
            'label' => 'Length',
            'type' => 'number',
            'description' => 'Product length',
            'group' => 'Dimensions',
        ],
        'woo_width' => [
            'label' => 'Width',
            'type' => 'number',
            'description' => 'Product width',
            'group' => 'Dimensions',
        ],
        'woo_height' => [
            'label' => 'Height',
            'type' => 'number',
            'description' => 'Product height',
            'group' => 'Dimensions',
        ],
        'woo_dimension_unit' => [
            'label' => 'Dimension Unit',
            'type' => 'text',
            'description' => 'Dimension unit from WooCommerce settings (cm, m, in, mm)',
            'group' => 'Dimensions',
        ],

        // === Categories & Taxonomies ===
        'woo_product_category' => [
            'label' => 'Product Category',
            'type' => 'text',
            'description' => 'Primary product category name',
            'group' => 'Taxonomies',
        ],
        'woo_product_categories' => [
            'label' => 'Product Categories (All)',
            'type' => 'text',
            'description' => 'All product categories as comma-separated list',
            'group' => 'Taxonomies',
        ],
        'woo_product_tags' => [
            'label' => 'Product Tags',
            'type' => 'text',
            'description' => 'Product tags as comma-separated list',
            'group' => 'Taxonomies',
        ],
        'woo_product_brand' => [
            'label' => 'Product Brand',
            'type' => 'text',
            'description' => 'Product brand (from brand taxonomy or custom field)',
            'group' => 'Taxonomies',
        ],

        // === Images ===
        'woo_main_image' => [
            'label' => 'Main Product Image',
            'type' => 'url',
            'description' => 'URL of the main product image',
            'group' => 'Images',
        ],
        'woo_gallery_images' => [
            'label' => 'Gallery Images',
            'type' => 'array',
            'description' => 'Array of product gallery image URLs',
            'group' => 'Images',
        ],
        'woo_all_images' => [
            'label' => 'All Images (Main + Gallery)',
            'type' => 'array',
            'description' => 'Array of all product image URLs including main image',
            'group' => 'Images',
        ],

        // === Product Type & Features ===
        'woo_product_type' => [
            'label' => 'Product Type',
            'type' => 'text',
            'description' => 'WooCommerce product type: simple, variable, grouped, external',
            'group' => 'Product Info',
        ],
        'woo_is_virtual' => [
            'label' => 'Is Virtual',
            'type' => 'boolean',
            'description' => 'Whether the product is virtual (no shipping)',
            'group' => 'Product Info',
        ],
        'woo_is_downloadable' => [
            'label' => 'Is Downloadable',
            'type' => 'boolean',
            'description' => 'Whether the product is downloadable',
            'group' => 'Product Info',
        ],
        'woo_is_featured' => [
            'label' => 'Is Featured',
            'type' => 'boolean',
            'description' => 'Whether the product is marked as featured',
            'group' => 'Product Info',
        ],
        'woo_is_sold_individually' => [
            'label' => 'Sold Individually',
            'type' => 'boolean',
            'description' => 'Whether only one item can be purchased at a time',
            'group' => 'Product Info',
        ],

        // === URLs ===
        'woo_product_url' => [
            'label' => 'Product URL',
            'type' => 'url',
            'description' => 'Permalink to the product page',
            'group' => 'URLs',
        ],
        'woo_add_to_cart_url' => [
            'label' => 'Add to Cart URL',
            'type' => 'url',
            'description' => 'Direct add to cart URL',
            'group' => 'URLs',
        ],
        'woo_external_url' => [
            'label' => 'External URL',
            'type' => 'url',
            'description' => 'External/affiliate product URL (for external products)',
            'group' => 'URLs',
        ],

        // === Additional Info ===
        'woo_short_description' => [
            'label' => 'Short Description',
            'type' => 'text',
            'description' => 'Product short description (excerpt)',
            'group' => 'Content',
        ],
        'woo_purchase_note' => [
            'label' => 'Purchase Note',
            'type' => 'text',
            'description' => 'Note displayed after purchase',
            'group' => 'Content',
        ],
        'woo_total_sales' => [
            'label' => 'Total Sales',
            'type' => 'number',
            'description' => 'Total number of times this product has been sold',
            'group' => 'Stats',
        ],

        // === Attributes ===
        'woo_attributes' => [
            'label' => 'Product Attributes',
            'type' => 'array',
            'description' => 'Array of product attributes with name and values',
            'group' => 'Attributes',
        ],
        'woo_attributes_text' => [
            'label' => 'Product Attributes (Text)',
            'type' => 'text',
            'description' => 'Product attributes formatted as text (e.g. "Color: Red, Size: M")',
            'group' => 'Attributes',
        ],
    ];

    /**
     * Initialize integration
     */
    public function init(): void
    {
        // Add WooCommerce fields to discovery for all post types
        add_filter('smg_discovered_fields', [$this, 'addWooCommerceFields'], 10, 2);

        // Resolve WooCommerce field values
        add_filter('smg_resolve_field_value', [$this, 'resolveFieldValue'], 10, 4);

        // Enhance Product schema for WooCommerce products
        add_filter('smg_product_schema_data', [$this, 'enhanceProductSchema'], 10, 3);
    }

    /**
     * Check if WooCommerce is active
     */
    public function isAvailable(): bool
    {
        return class_exists('WooCommerce') || function_exists('get_woocommerce_currency');
    }

    /**
     * Add WooCommerce fields to discovered fields
     *
     * Global fields are available for all post types.
     * Product fields are only available for WooCommerce product post type.
     *
     * @param array  $fields   Current discovered fields
     * @param string $postType The post type being queried
     * @return array Modified fields array
     */
    public function addWooCommerceFields(array $fields, string $postType): array
    {
        // Check availability
        if (!$this->isAvailable()) {
            return $fields;
        }

        // Add global virtual fields (available for all post types)
        foreach (self::GLOBAL_VIRTUAL_FIELDS as $key => $config) {
            $fields[] = [
                'key' => $key,
                'name' => $key,
                'label' => $config['label'],
                'type' => $config['type'],
                'source' => 'woocommerce_virtual',
                'plugin' => 'woocommerce',
                'plugin_label' => 'WooCommerce (Global)',
                'plugin_priority' => 10,
                'description' => $config['description'] ?? '',
                'virtual' => true,
            ];
        }

        // Add product-specific fields only for WooCommerce products
        if ($postType === 'product') {
            // Group fields by their category for better organization
            $currentGroup = '';
            
            foreach (self::PRODUCT_VIRTUAL_FIELDS as $key => $config) {
                $group = $config['group'] ?? 'General';
                $groupLabel = "WooCommerce ({$group})";
                
                $fields[] = [
                    'key' => $key,
                    'name' => $key,
                    'label' => $config['label'],
                    'type' => $config['type'],
                    'source' => 'woocommerce_product',
                    'plugin' => 'woocommerce',
                    'plugin_label' => $groupLabel,
                    'plugin_priority' => 5, // Higher priority for product-specific fields
                    'description' => $config['description'] ?? '',
                    'virtual' => true,
                    'group' => $group,
                ];
            }
        }

        return $fields;
    }

    /**
     * Resolve WooCommerce field values
     *
     * @param mixed  $value    Current resolved value
     * @param int    $postId   The post ID
     * @param string $fieldKey The field key
     * @param string $source   The field source
     * @return mixed Resolved value
     */
    public function resolveFieldValue(mixed $value, int $postId, string $fieldKey, string $source): mixed
    {
        // Handle global virtual fields
        if ($source === 'woocommerce_virtual') {
            if (!$this->isAvailable()) {
                return $value;
            }
            return $this->resolveGlobalField($fieldKey);
        }

        // Handle product-specific fields
        if ($source === 'woocommerce_product') {
            if (!$this->isAvailable()) {
                return $value;
            }
            return $this->resolveProductField($postId, $fieldKey);
        }

        return $value;
    }

    /**
     * Resolve global WooCommerce fields
     *
     * @param string $fieldKey The field key
     * @return mixed Computed value
     */
    private function resolveGlobalField(string $fieldKey): mixed
    {
        switch ($fieldKey) {
            case 'woo_currency_code':
                return $this->getCurrencyCode();

            case 'woo_currency_symbol':
                return $this->getCurrencySymbol();

            default:
                return null;
        }
    }

    /**
     * Resolve product-specific WooCommerce fields
     *
     * @param int    $postId   The post ID
     * @param string $fieldKey The field key
     * @return mixed Computed value
     */
    private function resolveProductField(int $postId, string $fieldKey): mixed
    {
        $product = $this->getProduct($postId);
        if (!$product) {
            return null;
        }

        switch ($fieldKey) {
            // === Pricing ===
            case 'woo_price':
                return $product->get_price();

            case 'woo_regular_price':
                return $product->get_regular_price();

            case 'woo_sale_price':
                return $product->get_sale_price();

            case 'woo_price_html':
                return $product->get_price_html();

            // === Identifiers ===
            case 'woo_sku':
                return $product->get_sku();

            case 'woo_gtin':
                return $this->getGTIN($product);

            case 'woo_mpn':
                return $this->getMPN($product);

            // === Stock & Availability ===
            case 'woo_stock_status':
                return $this->getSchemaStockStatus($product);

            case 'woo_stock_status_raw':
                return $product->get_stock_status();

            case 'woo_stock_quantity':
                return $product->get_stock_quantity();

            case 'woo_is_in_stock':
                return $product->is_in_stock();

            case 'woo_backorders_allowed':
                return $product->backorders_allowed();

            // === Rating & Reviews ===
            case 'woo_average_rating':
                return (float) $product->get_average_rating();

            case 'woo_review_count':
                return (int) $product->get_review_count();

            case 'woo_rating_count':
                return (int) $product->get_rating_count();

            // === Sale Dates ===
            case 'woo_sale_price_dates_from':
                $date = $product->get_date_on_sale_from();
                return $date ? $date->date('Y-m-d') : null;

            case 'woo_sale_price_dates_to':
                $date = $product->get_date_on_sale_to();
                return $date ? $date->date('Y-m-d') : null;

            case 'woo_is_on_sale':
                return $product->is_on_sale();

            // === Dimensions & Weight ===
            case 'woo_weight':
                $weight = $product->get_weight();
                if ($weight) {
                    return $weight . ' ' . get_option('woocommerce_weight_unit', 'kg');
                }
                return null;

            case 'woo_weight_value':
                return $product->get_weight() ? (float) $product->get_weight() : null;

            case 'woo_weight_unit':
                return get_option('woocommerce_weight_unit', 'kg');

            case 'woo_dimensions':
                if ($product->has_dimensions()) {
                    return wc_format_dimensions($product->get_dimensions(false));
                }
                return null;

            case 'woo_length':
                return $product->get_length() ? (float) $product->get_length() : null;

            case 'woo_width':
                return $product->get_width() ? (float) $product->get_width() : null;

            case 'woo_height':
                return $product->get_height() ? (float) $product->get_height() : null;

            case 'woo_dimension_unit':
                return get_option('woocommerce_dimension_unit', 'cm');

            // === Categories & Taxonomies ===
            case 'woo_product_category':
                return $this->getPrimaryCategory($postId);

            case 'woo_product_categories':
                return $this->getAllCategories($postId);

            case 'woo_product_tags':
                return $this->getProductTags($postId);

            case 'woo_product_brand':
                return $this->getProductBrand($postId, $product);

            // === Images ===
            case 'woo_main_image':
                $imageId = $product->get_image_id();
                return $imageId ? wp_get_attachment_url($imageId) : null;

            case 'woo_gallery_images':
                return $this->getGalleryImages($product);

            case 'woo_all_images':
                return $this->getAllImages($product);

            // === Product Type & Features ===
            case 'woo_product_type':
                return $product->get_type();

            case 'woo_is_virtual':
                return $product->is_virtual();

            case 'woo_is_downloadable':
                return $product->is_downloadable();

            case 'woo_is_featured':
                return $product->is_featured();

            case 'woo_is_sold_individually':
                return $product->is_sold_individually();

            // === URLs ===
            case 'woo_product_url':
                return $product->get_permalink();

            case 'woo_add_to_cart_url':
                return $product->add_to_cart_url();

            case 'woo_external_url':
                return $product->is_type('external') ? $product->get_product_url() : null;

            // === Additional Info ===
            case 'woo_short_description':
                return $product->get_short_description();

            case 'woo_purchase_note':
                return $product->get_purchase_note();

            case 'woo_total_sales':
                return (int) $product->get_total_sales();

            // === Attributes ===
            case 'woo_attributes':
                return $this->getProductAttributes($product);

            case 'woo_attributes_text':
                return $this->getProductAttributesText($product);

            default:
                return null;
        }
    }

    /**
     * Get currency code from WooCommerce settings
     *
     * @return string ISO currency code
     */
    public function getCurrencyCode(): string
    {
        if (function_exists('get_woocommerce_currency')) {
            return get_woocommerce_currency();
        }

        // Fallback to WordPress option
        $currency = get_option('woocommerce_currency', 'EUR');
        
        return is_string($currency) ? $currency : 'EUR';
    }

    /**
     * Get currency symbol from WooCommerce settings
     *
     * @return string Currency symbol
     */
    public function getCurrencySymbol(): string
    {
        if (function_exists('get_woocommerce_currency_symbol')) {
            return get_woocommerce_currency_symbol();
        }

        // Fallback
        return '€';
    }

    /**
     * Get WC_Product object
     */
    private function getProduct(int $postId): ?WC_Product
    {
        if (!function_exists('wc_get_product')) {
            return null;
        }

        $product = wc_get_product($postId);
        return $product instanceof WC_Product ? $product : null;
    }

    /**
     * Get GTIN from product meta (supports various GTIN plugins)
     */
    private function getGTIN(WC_Product $product): ?string
    {
        $postId = $product->get_id();

        // Check common GTIN meta keys
        $gtinKeys = [
            '_wc_gtin',              // WooCommerce GTIN
            '_gtin',                 // Generic
            '_ean',                  // European Article Number
            '_upc',                  // Universal Product Code
            'wpseo_global_identifier_gtin', // Yoast
            '_alg_wc_ean',           // EAN for WooCommerce
            'hwp_product_gtin',      // Other plugins
        ];

        foreach ($gtinKeys as $key) {
            $value = get_post_meta($postId, $key, true);
            if (!empty($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * Get MPN from product meta
     */
    private function getMPN(WC_Product $product): ?string
    {
        $postId = $product->get_id();

        // Check common MPN meta keys
        $mpnKeys = [
            '_wc_mpn',
            '_mpn',
            'wpseo_global_identifier_mpn',
            'hwp_product_mpn',
        ];

        foreach ($mpnKeys as $key) {
            $value = get_post_meta($postId, $key, true);
            if (!empty($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * Convert WooCommerce stock status to Schema.org availability
     */
    private function getSchemaStockStatus(WC_Product $product): string
    {
        $status = $product->get_stock_status();

        switch ($status) {
            case 'instock':
                return 'InStock';

            case 'outofstock':
                return 'OutOfStock';

            case 'onbackorder':
                return $product->backorders_allowed() ? 'BackOrder' : 'PreOrder';

            default:
                return 'InStock';
        }
    }

    /**
     * Get primary product category
     */
    private function getPrimaryCategory(int $postId): ?string
    {
        // Try Yoast primary category first
        $primaryCatId = get_post_meta($postId, '_yoast_wpseo_primary_product_cat', true);
        if ($primaryCatId) {
            $term = get_term($primaryCatId, 'product_cat');
            if ($term && !is_wp_error($term)) {
                return $term->name;
            }
        }

        // Fallback to first category
        $terms = get_the_terms($postId, 'product_cat');
        if ($terms && !is_wp_error($terms)) {
            // Skip "Uncategorized"
            foreach ($terms as $term) {
                if ($term->slug !== 'uncategorized') {
                    return $term->name;
                }
            }
            // Return first if all are uncategorized
            return $terms[0]->name;
        }

        return null;
    }

    /**
     * Get all product categories as comma-separated string
     */
    private function getAllCategories(int $postId): ?string
    {
        $terms = get_the_terms($postId, 'product_cat');
        if ($terms && !is_wp_error($terms)) {
            $names = array_map(fn($term) => $term->name, $terms);
            return implode(', ', $names);
        }

        return null;
    }

    /**
     * Get product tags as comma-separated string
     */
    private function getProductTags(int $postId): ?string
    {
        $terms = get_the_terms($postId, 'product_tag');
        if ($terms && !is_wp_error($terms)) {
            $names = array_map(fn($term) => $term->name, $terms);
            return implode(', ', $names);
        }

        return null;
    }

    /**
     * Get product brand from various sources
     */
    private function getProductBrand(int $postId, WC_Product $product): ?string
    {
        // Check common brand taxonomies
        $brandTaxonomies = [
            'product_brand',      // WooCommerce Brands
            'pwb-brand',          // Perfect WooCommerce Brands
            'yith_product_brand', // YITH WooCommerce Brands
            'brand',              // Generic
        ];

        foreach ($brandTaxonomies as $taxonomy) {
            if (taxonomy_exists($taxonomy)) {
                $terms = get_the_terms($postId, $taxonomy);
                if ($terms && !is_wp_error($terms)) {
                    return $terms[0]->name;
                }
            }
        }

        // Check meta fields
        $brandKeys = [
            '_brand',
            'brand',
            '_product_brand',
        ];

        foreach ($brandKeys as $key) {
            $value = get_post_meta($postId, $key, true);
            if (!empty($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * Get gallery image URLs
     */
    private function getGalleryImages(WC_Product $product): array
    {
        $galleryIds = $product->get_gallery_image_ids();
        $urls = [];

        foreach ($galleryIds as $imageId) {
            $url = wp_get_attachment_url($imageId);
            if ($url) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    /**
     * Get all images (main + gallery)
     */
    private function getAllImages(WC_Product $product): array
    {
        $urls = [];

        // Add main image first
        $mainImageId = $product->get_image_id();
        if ($mainImageId) {
            $mainUrl = wp_get_attachment_url($mainImageId);
            if ($mainUrl) {
                $urls[] = $mainUrl;
            }
        }

        // Add gallery images
        $urls = array_merge($urls, $this->getGalleryImages($product));

        return $urls;
    }

    /**
     * Get product attributes as structured array
     */
    private function getProductAttributes(WC_Product $product): array
    {
        $attributes = $product->get_attributes();
        $result = [];

        foreach ($attributes as $attribute) {
            if ($attribute->is_taxonomy()) {
                $taxonomy = $attribute->get_taxonomy_object();
                $terms = wc_get_product_terms($product->get_id(), $attribute->get_name(), ['fields' => 'names']);
                
                $result[] = [
                    'name' => $taxonomy ? $taxonomy->attribute_label : $attribute->get_name(),
                    'value' => implode(', ', $terms),
                ];
            } else {
                $result[] = [
                    'name' => $attribute->get_name(),
                    'value' => implode(', ', $attribute->get_options()),
                ];
            }
        }

        return $result;
    }

    /**
     * Get product attributes as formatted text
     */
    private function getProductAttributesText(WC_Product $product): ?string
    {
        $attributes = $this->getProductAttributes($product);
        
        if (empty($attributes)) {
            return null;
        }

        $parts = [];
        foreach ($attributes as $attr) {
            $parts[] = "{$attr['name']}: {$attr['value']}";
        }

        return implode(', ', $parts);
    }

    /**
     * Enhance Product schema for WooCommerce products
     *
     * Automatically populates schema properties from WooCommerce data
     * when explicit mappings are not provided.
     *
     * @param array   $data    Current schema data
     * @param WP_Post $post    The post object
     * @param array   $mapping Field mapping configuration
     * @return array Enhanced schema data
     */
    public function enhanceProductSchema(array $data, WP_Post $post, array $mapping): array
    {
        // Only enhance WooCommerce products
        if ($post->post_type !== 'product') {
            return $data;
        }

        $product = $this->getProduct($post->ID);
        if (!$product) {
            return $data;
        }

        // === Auto-populate SKU if not mapped ===
        if (empty($data['sku'])) {
            $sku = $product->get_sku();
            if ($sku) {
                $data['sku'] = $sku;
            }
        }

        // === Auto-populate GTIN if not mapped ===
        if (empty($data['gtin'])) {
            $gtin = $this->getGTIN($product);
            if ($gtin) {
                $data['gtin'] = $gtin;
            }
        }

        // === Auto-populate MPN if not mapped ===
        if (empty($data['mpn'])) {
            $mpn = $this->getMPN($product);
            if ($mpn) {
                $data['mpn'] = $mpn;
            }
        }

        // === Auto-populate Brand if not mapped ===
        if (empty($data['brand'])) {
            $brand = $this->getProductBrand($post->ID, $product);
            if ($brand) {
                $data['brand'] = [
                    '@type' => 'Brand',
                    'name' => $brand,
                ];
            }
        }

        // === Auto-populate/enhance Offers if price exists ===
        if (!isset($data['offers']) || empty($data['offers']['price'])) {
            $price = $product->get_price();
            if ($price) {
                $offers = $data['offers'] ?? [];
                $offers['@type'] = 'Offer';
                $offers['price'] = (float) $price;
                $offers['priceCurrency'] = $this->getCurrencyCode();
                $offers['availability'] = 'https://schema.org/' . $this->getSchemaStockStatus($product);
                $offers['url'] = $product->get_permalink();

                // Add priceValidUntil if on sale
                $saleEndDate = $product->get_date_on_sale_to();
                if ($saleEndDate) {
                    $offers['priceValidUntil'] = $saleEndDate->date('Y-m-d');
                }

                $data['offers'] = $offers;
            }
        } else {
            // Enhance existing offers with missing fields
            if (empty($data['offers']['priceCurrency'])) {
                $data['offers']['priceCurrency'] = $this->getCurrencyCode();
            }
            if (empty($data['offers']['availability'])) {
                $data['offers']['availability'] = 'https://schema.org/' . $this->getSchemaStockStatus($product);
            }
            if (empty($data['offers']['url'])) {
                $data['offers']['url'] = $product->get_permalink();
            }
        }

        // === Auto-populate Aggregate Rating if reviews exist ===
        if (empty($data['aggregateRating'])) {
            $rating = (float) $product->get_average_rating();
            $ratingCount = (int) $product->get_rating_count();
            
            if ($rating > 0 && $ratingCount > 0) {
                $data['aggregateRating'] = [
                    '@type' => 'AggregateRating',
                    'ratingValue' => $rating,
                    'ratingCount' => $ratingCount,
                    'bestRating' => 5,
                    'worstRating' => 1,
                ];
            }
        }

        // === Add additional images from gallery ===
        if (empty($data['image']) || !is_array($data['image'])) {
            $allImages = $this->getAllImages($product);
            if (!empty($allImages)) {
                // If single image, keep as string; if multiple, use array
                $data['image'] = count($allImages) === 1 ? $allImages[0] : $allImages;
            }
        }

        // === Auto-populate weight if available ===
        if (empty($data['weight']) && $product->has_weight()) {
            $data['weight'] = [
                '@type' => 'QuantitativeValue',
                'value' => (float) $product->get_weight(),
                'unitCode' => $this->getWeightUnitCode(),
            ];
        }

        return $data;
    }

    /**
     * Get UN/CEFACT unit code for weight
     */
    private function getWeightUnitCode(): string
    {
        $unit = get_option('woocommerce_weight_unit', 'kg');
        
        return match ($unit) {
            'kg' => 'KGM',
            'g' => 'GRM',
            'lbs' => 'LBR',
            'oz' => 'ONZ',
            default => 'KGM',
        };
    }

    /**
     * Get list of available WooCommerce fields for documentation
     *
     * @return array Array of field definitions
     */
    public static function getAvailableFields(): array
    {
        return array_merge(
            self::GLOBAL_VIRTUAL_FIELDS,
            self::PRODUCT_VIRTUAL_FIELDS
        );
    }
}
