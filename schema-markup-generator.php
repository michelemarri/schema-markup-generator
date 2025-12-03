<?php

declare(strict_types=1);

/**
 * Plugin Name:       Schema Markup Generator
 * Plugin URI:        https://github.com/michelemarri/schema-markup-generator
 * Description:       Automatic schema markup generation optimized for LLMs. Auto-discovers post types, custom fields, and taxonomies.
 * Version:           1.8.0
 * Requires at least: 6.0
 * Tested up to:      6.8
 * Requires PHP:      8.2
 * Author:            Michele Marri
 * Author URI:        https://metodo.dev
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       schema-markup-generator
 * Domain Path:       /languages
 *
 * @package Metodo\SchemaMarkupGenerator
 */

namespace Metodo\SchemaMarkupGenerator;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('SMG_VERSION', '1.8.0');
define('SMG_PLUGIN_FILE', __FILE__);
define('SMG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SMG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SMG_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Autoloader
 */
if (file_exists(SMG_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once SMG_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Get settings for a specific section
 *
 * @param string $section Section name: 'general', 'advanced', 'integrations', 'update'
 * @return array Settings array with defaults
 */
function smg_get_settings(string $section): array
{
    $optionMap = [
        'general' => 'smg_general_settings',
        'advanced' => 'smg_advanced_settings',
        'integrations' => 'smg_integrations_settings',
        'update' => 'smg_update_settings',
    ];

    $defaults = [
        'general' => [
            'enabled' => true,
            'enable_website_schema' => true,
            'enable_breadcrumb_schema' => true,
            'output_format' => 'json-ld',
        ],
        'advanced' => [
            'cache_enabled' => true,
            'cache_ttl' => 3600,
            'debug_mode' => false,
        ],
        'integrations' => [
            'rankmath_avoid_duplicates' => true,
            'rankmath_takeover_types' => [],
            'integration_rankmath_enabled' => true,
            'integration_acf_enabled' => true,
            'integration_woocommerce_enabled' => true,
            'integration_memberpress_courses_enabled' => true,
            'acf_auto_discover' => true,
            'acf_include_nested' => true,
            'mpcs_auto_parent_course' => true,
            'mpcs_include_curriculum' => false,
            'woo_auto_product' => true,
            'woo_include_reviews' => true,
            'woo_include_offers' => true,
        ],
        'update' => [
            'auto_update' => false,
        ],
    ];

    if (!isset($optionMap[$section])) {
        return [];
    }

    $settings = get_option($optionMap[$section], []);
    if (!is_array($settings)) {
        $settings = [];
    }

    return array_merge($defaults[$section] ?? [], $settings);
}

/**
 * Initialize the plugin
 *
 * @return Plugin
 */
function smg_init(): Plugin
{
    static $plugin = null;

    if ($plugin === null) {
        $plugin = new Plugin();
        $plugin->init();
    }

    return $plugin;
}

// Initialize on plugins_loaded
add_action('plugins_loaded', __NAMESPACE__ . '\\smg_init');

/**
 * Activation hook
 */
register_activation_hook(__FILE__, function (): void {
    // Set default options for new installations
    if (!get_option('smg_general_settings')) {
        update_option('smg_general_settings', [
            'enabled' => true,
            'enable_website_schema' => true,
            'enable_breadcrumb_schema' => true,
            'output_format' => 'json-ld',
        ]);
    }

    if (!get_option('smg_advanced_settings')) {
        update_option('smg_advanced_settings', [
            'cache_enabled' => true,
            'cache_ttl' => 3600,
            'debug_mode' => false,
        ]);
    }

    if (!get_option('smg_post_type_mappings')) {
        update_option('smg_post_type_mappings', [
            'post' => 'Article',
            'page' => 'WebPage',
        ]);
    }

    // Create logs directory
    $log_dir = SMG_PLUGIN_DIR . 'logs';
    if (!is_dir($log_dir)) {
        wp_mkdir_p($log_dir);
        file_put_contents($log_dir . '/.htaccess', 'Deny from all');
        file_put_contents($log_dir . '/index.php', '<?php // Silence is golden');
    }

    // Flush rewrite rules for REST API
    flush_rewrite_rules();
});

/**
 * Deactivation hook
 */
register_deactivation_hook(__FILE__, function (): void {
    // Clear scheduled events if any
    wp_clear_scheduled_hook('smg_cleanup_logs');

    // Flush rewrite rules
    flush_rewrite_rules();
});

