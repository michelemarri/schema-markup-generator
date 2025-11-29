<?php

declare(strict_types=1);

/**
 * Plugin Name:       Schema Markup Generator
 * Plugin URI:        https://github.com/michelemarri/schema-markup-generator
 * Description:       Automatic schema markup generation optimized for LLMs. Auto-discovers post types, custom fields, and taxonomies.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            Michele Marri
 * Author URI:        https://metodo.dev
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       schema-markup-generator
 * Domain Path:       /languages
 *
 * @package flavor\SchemaMarkupGenerator
 */

namespace flavor\SchemaMarkupGenerator;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('SMG_VERSION', '1.0.0');
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
    // Set default options
    if (!get_option('smg_settings')) {
        update_option('smg_settings', [
            'enabled' => true,
            'output_format' => 'json-ld',
            'debug_mode' => false,
            'cache_enabled' => true,
            'cache_ttl' => 3600,
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

