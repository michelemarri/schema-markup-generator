<?php

declare(strict_types=1);

/**
 * Schema Markup Generator Uninstall
 *
 * Fired when the plugin is uninstalled.
 *
 * @package flavor\SchemaMarkupGenerator
 * @author  Michele Marri <info@metodo.dev>
 * @license GPL-3.0-or-later
 */

// If uninstall not called from WordPress, exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('smg_settings');
delete_option('smg_post_type_mappings');
delete_option('smg_field_mappings');
delete_option('smg_cache_settings');

// Delete transients
global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like('_transient_smg_') . '%'
    )
);
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like('_transient_timeout_smg_') . '%'
    )
);

// Delete post meta
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
        $wpdb->esc_like('_smg_') . '%'
    )
);

// Remove log files
$log_dir = __DIR__ . '/logs';
if (is_dir($log_dir)) {
    $files = glob($log_dir . '/*.log');
    if ($files) {
        foreach ($files as $file) {
            @unlink($file);
        }
    }
    @rmdir($log_dir);
}

