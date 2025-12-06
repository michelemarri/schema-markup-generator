<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Integration;

use WP_Post;

/**
 * MemberPress Membership Integration
 *
 * Integration with MemberPress for membership/product fields.
 * Provides membership-specific fields like price, period, trial info
 * that can be mapped to schema properties.
 *
 * @package Metodo\SchemaMarkupGenerator\Integration
 * @author  Michele Marri <plugins@metodo.dev>
 */
class MemberPressMembershipIntegration
{
    /**
     * Post type for MemberPress memberships/products
     */
    public const MEMBERSHIP_POST_TYPE = 'memberpressproduct';

    /**
     * Membership meta fields with their labels and types
     */
    private const MEMBERSHIP_FIELDS = [
        '_mepr_product_price' => [
            'label' => 'Price',
            'type' => 'number',
            'description' => 'Membership price',
        ],
        '_mepr_product_period' => [
            'label' => 'Period',
            'type' => 'number',
            'description' => 'Billing period number (e.g. 1, 3, 12)',
        ],
        '_mepr_product_period_type' => [
            'label' => 'Period Type',
            'type' => 'text',
            'description' => 'Period type (days, weeks, months, years, lifetime)',
        ],
        '_mepr_product_trial' => [
            'label' => 'Has Trial',
            'type' => 'boolean',
            'description' => 'Whether membership has a trial period',
        ],
        '_mepr_product_trial_days' => [
            'label' => 'Trial Days',
            'type' => 'number',
            'description' => 'Number of trial days',
        ],
        '_mepr_product_trial_amount' => [
            'label' => 'Trial Amount',
            'type' => 'number',
            'description' => 'Price during trial period',
        ],
        '_mepr_product_limit_cycles' => [
            'label' => 'Limit Cycles',
            'type' => 'boolean',
            'description' => 'Whether billing cycles are limited',
        ],
        '_mepr_product_limit_cycles_num' => [
            'label' => 'Cycles Number',
            'type' => 'number',
            'description' => 'Number of billing cycles',
        ],
        '_mepr_product_limit_cycles_action' => [
            'label' => 'After Cycles Action',
            'type' => 'text',
            'description' => 'Action after cycles complete',
        ],
        '_mepr_product_who_can_purchase' => [
            'label' => 'Who Can Purchase',
            'type' => 'text',
            'description' => 'Purchase restrictions',
        ],
        '_mepr_product_is_highlighted' => [
            'label' => 'Is Highlighted',
            'type' => 'boolean',
            'description' => 'Whether membership is featured/highlighted',
        ],
        '_mepr_product_pricing_title' => [
            'label' => 'Pricing Title',
            'type' => 'text',
            'description' => 'Custom pricing display title',
        ],
        '_mepr_product_pricing_show_price' => [
            'label' => 'Show Price',
            'type' => 'boolean',
            'description' => 'Whether to show price',
        ],
        '_mepr_product_pricing_display' => [
            'label' => 'Pricing Display',
            'type' => 'text',
            'description' => 'Price display format',
        ],
        '_mepr_product_pricing_heading_txt' => [
            'label' => 'Pricing Heading',
            'type' => 'text',
            'description' => 'Pricing table heading text',
        ],
        '_mepr_product_pricing_footer_txt' => [
            'label' => 'Pricing Footer',
            'type' => 'text',
            'description' => 'Pricing table footer text',
        ],
        '_mepr_product_pricing_button_txt' => [
            'label' => 'Button Text',
            'type' => 'text',
            'description' => 'Signup button text',
        ],
        '_mepr_product_pricing_benefits' => [
            'label' => 'Benefits',
            'type' => 'array',
            'description' => 'List of membership benefits',
        ],
        '_mepr_product_access_url' => [
            'label' => 'Access URL',
            'type' => 'url',
            'description' => 'URL after successful registration',
        ],
        '_mepr_product_expire_type' => [
            'label' => 'Expire Type',
            'type' => 'text',
            'description' => 'How membership expires',
        ],
        '_mepr_product_expire_after' => [
            'label' => 'Expire After',
            'type' => 'number',
            'description' => 'Expiration period value',
        ],
        '_mepr_product_expire_unit' => [
            'label' => 'Expire Unit',
            'type' => 'text',
            'description' => 'Expiration period unit',
        ],
    ];

    /**
     * Virtual/computed fields
     */
    private const VIRTUAL_FIELDS = [
        'mepr_formatted_price' => [
            'label' => 'Formatted Price',
            'type' => 'text',
            'description' => 'Price with currency symbol (e.g. $99.00)',
        ],
        'mepr_billing_description' => [
            'label' => 'Billing Description',
            'type' => 'text',
            'description' => 'Human-readable billing description (e.g. "$99/month")',
        ],
        'mepr_registration_url' => [
            'label' => 'Registration URL',
            'type' => 'url',
            'description' => 'Direct membership registration URL',
        ],
    ];

    /**
     * Initialize integration
     */
    public function init(): void
    {
        // Add membership fields to discovery
        add_filter('smg_discovered_fields', [$this, 'addMembershipFields'], 10, 2);

        // Resolve membership field values
        add_filter('smg_resolve_field_value', [$this, 'resolveFieldValue'], 10, 4);

        // Enhance Product schema with membership data
        add_filter('smg_product_schema_data', [$this, 'enhanceProductSchema'], 10, 3);
    }

    /**
     * Check if MemberPress is active
     */
    public function isAvailable(): bool
    {
        return post_type_exists(self::MEMBERSHIP_POST_TYPE)
            || class_exists('MeprProduct')
            || defined('MEPR_VERSION');
    }

    /**
     * Add membership fields to discovered fields for the membership post type
     *
     * @param array  $fields   Current discovered fields
     * @param string $postType The post type being queried
     * @return array Modified fields array
     */
    public function addMembershipFields(array $fields, string $postType): array
    {
        // Only add fields for membership post type
        if ($postType !== self::MEMBERSHIP_POST_TYPE) {
            return $fields;
        }

        // Check availability
        if (!$this->isAvailable()) {
            return $fields;
        }

        // Add standard membership meta fields
        foreach (self::MEMBERSHIP_FIELDS as $key => $config) {
            $fields[] = [
                'key' => $key,
                'name' => $key,
                'label' => $config['label'],
                'type' => $config['type'],
                'source' => 'memberpress',
                'plugin' => 'memberpress',
                'plugin_label' => 'MemberPress',
                'plugin_priority' => 10,
                'description' => $config['description'] ?? '',
            ];
        }

        // Add virtual/computed fields
        foreach (self::VIRTUAL_FIELDS as $key => $config) {
            $fields[] = [
                'key' => $key,
                'name' => $key,
                'label' => $config['label'],
                'type' => $config['type'],
                'source' => 'memberpress_virtual',
                'plugin' => 'memberpress',
                'plugin_label' => 'MemberPress (Computed)',
                'plugin_priority' => 15,
                'description' => $config['description'] ?? '',
                'virtual' => true,
            ];
        }

        return $fields;
    }

    /**
     * Resolve membership field values
     *
     * @param mixed  $value    Current resolved value
     * @param int    $postId   The post ID
     * @param string $fieldKey The field key
     * @param string $source   The field source
     * @return mixed Resolved value
     */
    public function resolveFieldValue(mixed $value, int $postId, string $fieldKey, string $source): mixed
    {
        // Only handle memberpress sources
        if (!in_array($source, ['memberpress', 'memberpress_virtual'], true)) {
            return $value;
        }

        // Check if this is a membership post
        $post = get_post($postId);
        if (!$post || $post->post_type !== self::MEMBERSHIP_POST_TYPE) {
            return $value;
        }

        // Handle virtual fields
        if ($source === 'memberpress_virtual') {
            return $this->resolveVirtualField($fieldKey, $postId);
        }

        // Handle standard meta fields
        $metaValue = get_post_meta($postId, $fieldKey, true);

        // Type conversion based on field definition
        if (isset(self::MEMBERSHIP_FIELDS[$fieldKey])) {
            $type = self::MEMBERSHIP_FIELDS[$fieldKey]['type'];

            switch ($type) {
                case 'number':
                    return is_numeric($metaValue) ? (float) $metaValue : $metaValue;
                case 'boolean':
                    return filter_var($metaValue, FILTER_VALIDATE_BOOLEAN);
                case 'array':
                    return is_array($metaValue) ? $metaValue : (is_string($metaValue) ? maybe_unserialize($metaValue) : []);
            }
        }

        return $metaValue;
    }

    /**
     * Resolve virtual/computed fields
     *
     * @param string $fieldKey The field key
     * @param int    $postId   The post ID
     * @return mixed Computed value
     */
    private function resolveVirtualField(string $fieldKey, int $postId): mixed
    {
        switch ($fieldKey) {
            case 'mepr_formatted_price':
                return $this->getFormattedPrice($postId);

            case 'mepr_billing_description':
                return $this->getBillingDescription($postId);

            case 'mepr_registration_url':
                return $this->getRegistrationUrl($postId);

            default:
                return null;
        }
    }

    /**
     * Get formatted price with currency
     *
     * @param int $postId The membership post ID
     * @return string Formatted price
     */
    public function getFormattedPrice(int $postId): string
    {
        $price = get_post_meta($postId, '_mepr_product_price', true);

        if (empty($price) || !is_numeric($price)) {
            return '';
        }

        // Try to use MemberPress currency settings if available
        if (class_exists('MeprUtils')) {
            return \MeprUtils::format_currency((float) $price);
        }

        // Fallback to WordPress locale
        $currencySymbol = $this->getCurrencySymbol();
        return $currencySymbol . number_format((float) $price, 2);
    }

    /**
     * Get human-readable billing description
     *
     * @param int $postId The membership post ID
     * @return string Billing description
     */
    public function getBillingDescription(int $postId): string
    {
        $price = get_post_meta($postId, '_mepr_product_price', true);
        $period = get_post_meta($postId, '_mepr_product_period', true);
        $periodType = get_post_meta($postId, '_mepr_product_period_type', true);

        if (empty($price) || !is_numeric($price)) {
            return '';
        }

        $formattedPrice = $this->getFormattedPrice($postId);

        // Lifetime membership
        if ($periodType === 'lifetime') {
            return sprintf(__('%s (one-time)', 'schema-markup-generator'), $formattedPrice);
        }

        // Recurring membership
        $periodLabels = [
            'days' => _n('day', 'days', (int) $period, 'schema-markup-generator'),
            'weeks' => _n('week', 'weeks', (int) $period, 'schema-markup-generator'),
            'months' => _n('month', 'months', (int) $period, 'schema-markup-generator'),
            'years' => _n('year', 'years', (int) $period, 'schema-markup-generator'),
        ];

        $periodLabel = $periodLabels[$periodType] ?? $periodType;

        if ((int) $period === 1) {
            // Simplified format: "$99/month"
            $shortLabels = [
                'days' => __('day', 'schema-markup-generator'),
                'weeks' => __('week', 'schema-markup-generator'),
                'months' => __('month', 'schema-markup-generator'),
                'years' => __('year', 'schema-markup-generator'),
            ];
            return sprintf('%s/%s', $formattedPrice, $shortLabels[$periodType] ?? $periodType);
        }

        // Full format: "$99 every 3 months"
        return sprintf(
            __('%s every %d %s', 'schema-markup-generator'),
            $formattedPrice,
            (int) $period,
            $periodLabel
        );
    }

    /**
     * Get registration URL for the membership
     *
     * @param int $postId The membership post ID
     * @return string Registration URL
     */
    public function getRegistrationUrl(int $postId): string
    {
        // Try MemberPress function if available
        if (class_exists('MeprProduct')) {
            try {
                $product = new \MeprProduct($postId);
                if (method_exists($product, 'url')) {
                    return $product->url();
                }
            } catch (\Exception $e) {
                // Fallback below
            }
        }

        // Fallback: construct URL
        $permalink = get_permalink($postId);
        if ($permalink) {
            return add_query_arg('action', 'signup', $permalink);
        }

        return '';
    }

    /**
     * Enhance Product schema with MemberPress membership data
     *
     * @param array   $data    Current schema data
     * @param WP_Post $post    The post
     * @param array   $mapping Field mapping configuration
     * @return array Enhanced schema data
     */
    public function enhanceProductSchema(array $data, WP_Post $post, array $mapping): array
    {
        // Only enhance membership post types
        if ($post->post_type !== self::MEMBERSHIP_POST_TYPE) {
            return $data;
        }

        // Check availability
        if (!$this->isAvailable()) {
            return $data;
        }

        // Add offer data if not already set
        if (empty($data['offers'])) {
            $offer = $this->buildOfferData($post->ID);
            if (!empty($offer)) {
                $data['offers'] = $offer;
            }
        }

        // Add additional product type info for memberships
        if (empty($data['@type']) || $data['@type'] === 'Product') {
            $data['@type'] = 'Product';
            $data['category'] = __('Membership', 'schema-markup-generator');
        }

        return $data;
    }

    /**
     * Build offer data from membership
     *
     * @param int $postId The membership post ID
     * @return array Offer schema data
     */
    private function buildOfferData(int $postId): array
    {
        $price = get_post_meta($postId, '_mepr_product_price', true);

        if (empty($price) || !is_numeric($price)) {
            return [];
        }

        $periodType = get_post_meta($postId, '_mepr_product_period_type', true);

        $offer = [
            '@type' => 'Offer',
            'price' => (float) $price,
            'priceCurrency' => $this->getCurrencyCode(),
            'availability' => 'https://schema.org/InStock',
            'url' => $this->getRegistrationUrl($postId),
        ];

        // Add price validity for subscriptions
        if ($periodType && $periodType !== 'lifetime') {
            $period = get_post_meta($postId, '_mepr_product_period', true);
            $offer['priceValidUntil'] = $this->calculatePriceValidUntil($period, $periodType);
        }

        return $offer;
    }

    /**
     * Calculate price valid until date based on billing period
     *
     * @param mixed  $period     Period number
     * @param string $periodType Period type
     * @return string ISO 8601 date
     */
    private function calculatePriceValidUntil(mixed $period, string $periodType): string
    {
        $period = (int) $period ?: 1;

        $intervalMap = [
            'days' => 'days',
            'weeks' => 'weeks',
            'months' => 'months',
            'years' => 'years',
        ];

        $interval = $intervalMap[$periodType] ?? 'months';

        $date = new \DateTime();
        $date->modify("+{$period} {$interval}");

        return $date->format('Y-m-d');
    }

    /**
     * Get currency symbol
     *
     * @return string Currency symbol
     */
    private function getCurrencySymbol(): string
    {
        // Try MemberPress settings
        if (class_exists('MeprOptions')) {
            $options = \MeprOptions::fetch();
            if (isset($options->currency_symbol)) {
                return $options->currency_symbol;
            }
        }

        // Default
        return '$';
    }

    /**
     * Get currency code
     *
     * @return string ISO currency code
     */
    private function getCurrencyCode(): string
    {
        // Try MemberPress settings
        if (class_exists('MeprOptions')) {
            $options = \MeprOptions::fetch();
            if (isset($options->currency_code)) {
                return $options->currency_code;
            }
        }

        // Default
        return 'USD';
    }

    /**
     * Get all memberships
     *
     * @return array Array of membership posts
     */
    public function getAllMemberships(): array
    {
        if (!$this->isAvailable()) {
            return [];
        }

        return get_posts([
            'post_type' => self::MEMBERSHIP_POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ]);
    }

    /**
     * Get membership by ID
     *
     * @param int $postId The membership post ID
     * @return WP_Post|null Membership post or null
     */
    public function getMembership(int $postId): ?WP_Post
    {
        $post = get_post($postId);

        if (!$post || $post->post_type !== self::MEMBERSHIP_POST_TYPE) {
            return null;
        }

        return $post;
    }
}

