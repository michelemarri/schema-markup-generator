<?php

declare(strict_types=1);

/**
 * Plugin Name:       Schema Markup Generator
 * Plugin URI:        https://github.com/michelemarri/schema-markup-generator
 * Description:       Automatic schema markup generation optimized for LLMs. Auto-discovers post types, custom fields, and taxonomies.
 * Version:           1.42.0
 * Requires at least: 6.0
 * Tested up to:      6.9
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
define('SMG_VERSION', '1.42.0');
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
            'organization_name' => '',
            'organization_url' => '',
            'organization_logo' => 0,
            'fallback_image' => 0,
        ],
        'integrations' => [
            'rankmath_disable_all_schemas' => false,
            'rankmath_avoid_duplicates' => true,
            'rankmath_takeover_types' => [],
            'integration_rankmath_enabled' => true,
            'integration_acf_enabled' => true,
            'integration_woocommerce_enabled' => true,
            'integration_memberpress_courses_enabled' => true,
            'integration_memberpress_memberships_enabled' => true,
            'integration_youtube_enabled' => true,
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
 * Get fallback image for schema markup
 *
 * Returns the fallback image configured in settings, or the site favicon as ultimate fallback.
 * This is used when a post has no featured image but the schema type requires an image.
 *
 * @return array|null Image data array with @type, url, width, height or null if no fallback available
 */
function smg_get_fallback_image(): ?array
{
    $settings = smg_get_settings('advanced');

    // First try the custom fallback image from settings
    $fallbackImageId = !empty($settings['fallback_image']) ? (int) $settings['fallback_image'] : 0;

    if ($fallbackImageId) {
        $imageData = wp_get_attachment_image_src($fallbackImageId, 'full');
        if ($imageData) {
            return apply_filters('smg_fallback_image', [
                '@type' => 'ImageObject',
                'url' => $imageData[0],
                'width' => $imageData[1],
                'height' => $imageData[2],
            ]);
        }
    }

    // Ultimate fallback: use site icon (favicon)
    $siteIconId = get_option('site_icon');
    if ($siteIconId) {
        $iconData = wp_get_attachment_image_src((int) $siteIconId, 'full');
        if ($iconData) {
            return apply_filters('smg_fallback_image', [
                '@type' => 'ImageObject',
                'url' => $iconData[0],
                'width' => $iconData[1],
                'height' => $iconData[2],
            ]);
        }
    }

    // No fallback available
    return null;
}

/**
 * Get organization data with fallbacks
 *
 * Returns organization info from plugin settings, falling back to WordPress defaults.
 *
 * @return array{name: string, url: string, logo: array|null} Organization data
 */
function smg_get_organization_data(): array
{
    $settings = smg_get_settings('advanced');

    // Name: custom setting → WordPress site name
    $name = !empty($settings['organization_name'])
        ? $settings['organization_name']
        : get_bloginfo('name');

    // URL: custom setting → home URL
    $url = !empty($settings['organization_url'])
        ? $settings['organization_url']
        : home_url('/');

    // Logo: custom setting → custom_logo theme mod → null
    $logoId = !empty($settings['organization_logo'])
        ? (int) $settings['organization_logo']
        : (int) get_theme_mod('custom_logo');

    $logo = null;
    if ($logoId) {
        $logoData = wp_get_attachment_image_src($logoId, 'full');
        if ($logoData) {
            $logo = [
                '@type' => 'ImageObject',
                'url' => $logoData[0],
                'width' => $logoData[1],
                'height' => $logoData[2],
            ];
        }
    }

    /**
     * Filter organization data
     *
     * @param array $data Organization data with name, url, and logo
     */
    return apply_filters('smg_organization_data', [
        'name' => $name,
        'url' => $url,
        'logo' => $logo,
    ]);
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

