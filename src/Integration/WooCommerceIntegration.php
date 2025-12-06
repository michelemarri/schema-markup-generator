<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Integration;

use WP_Post;

/**
 * WooCommerce Integration
 *
 * Integration with WooCommerce for currency and product fields.
 * Provides WooCommerce-specific fields like currency code
 * that can be mapped to schema properties.
 *
 * @package Metodo\SchemaMarkupGenerator\Integration
 * @author  Michele Marri <plugins@metodo.dev>
 */
class WooCommerceIntegration
{
    /**
     * Virtual/computed fields available globally
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
     * Initialize integration
     */
    public function init(): void
    {
        // Add WooCommerce fields to discovery for all post types
        add_filter('smg_discovered_fields', [$this, 'addWooCommerceFields'], 10, 2);

        // Resolve WooCommerce field values
        add_filter('smg_resolve_field_value', [$this, 'resolveFieldValue'], 10, 4);
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
     * These fields are available for all post types since currency
     * is a global WooCommerce setting.
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

        // Add virtual/computed fields (available for all post types)
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
        // Only handle woocommerce sources
        if ($source !== 'woocommerce_virtual') {
            return $value;
        }

        // Check availability
        if (!$this->isAvailable()) {
            return $value;
        }

        return $this->resolveVirtualField($fieldKey);
    }

    /**
     * Resolve virtual/computed fields
     *
     * @param string $fieldKey The field key
     * @return mixed Computed value
     */
    private function resolveVirtualField(string $fieldKey): mixed
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
}

