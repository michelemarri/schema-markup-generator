<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Admin\Tabs\Settings;

use Metodo\SchemaMarkupGenerator\Admin\Tabs\AbstractTab;

/**
 * Performance Tab
 *
 * Cache configuration and performance settings.
 *
 * @package Metodo\SchemaMarkupGenerator\Admin\Tabs\Settings
 * @author  Michele Marri <plugins@metodo.dev>
 */
class PerformanceTab extends AbstractTab
{
    public function getTitle(): string
    {
        return __('Performance', 'schema-markup-generator');
    }

    public function getIcon(): string
    {
        return 'dashicons-performance';
    }

    public function getSettingsGroup(): string
    {
        return 'smg_advanced';
    }

    public function isAutoSaveEnabled(): bool
    {
        return true;
    }

    public function getAutoSaveOptionName(): string
    {
        return 'smg_advanced_settings';
    }

    /**
     * Get registered options
     * 
     * Note: This tab does NOT register smg_advanced_settings to avoid
     * sanitize callback conflicts. OrganizationTab handles registration
     * with a unified sanitize callback for all Settings sub-tabs.
     */
    public function getRegisteredOptions(): array
    {
        return [];
    }

    public function render(): void
    {
        $this->handleClearCache();

        $settings = \Metodo\SchemaMarkupGenerator\smg_get_settings('advanced');

        ?>
        <div class="smg-tab-panel flex flex-col gap-6" id="tab-settings-performance">
            <?php $this->renderSection(
                __('Cache Configuration', 'schema-markup-generator'),
                __('Configure caching and performance settings.', 'schema-markup-generator')
            ); ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php
                $this->renderCard(__('Cache Settings', 'schema-markup-generator'), function () use ($settings) {
                    $this->renderToggle(
                        'smg_advanced_settings[cache_enabled]',
                        $settings['cache_enabled'] ?? true,
                        __('Enable Caching', 'schema-markup-generator'),
                        __('Cache generated schema data for faster page loads.', 'schema-markup-generator')
                    );

                    $this->renderNumberField(
                        'smg_advanced_settings[cache_ttl]',
                        (int) ($settings['cache_ttl'] ?? 3600),
                        __('Cache TTL (seconds)', 'schema-markup-generator'),
                        __('How long to keep cached data. Default: 3600 (1 hour).', 'schema-markup-generator'),
                        60,
                        86400
                    );
                }, 'dashicons-performance');
                ?>

                <?php
                $this->renderCard(__('Cache Status', 'schema-markup-generator'), function () {
                    $cacheType = wp_using_ext_object_cache()
                        ? __('Object Cache (Redis/Memcached)', 'schema-markup-generator')
                        : __('WordPress Transients', 'schema-markup-generator');
                    ?>
                    <div class="smg-info-grid">
                        <div class="smg-info-item">
                            <span class="smg-info-label"><?php esc_html_e('Cache Type', 'schema-markup-generator'); ?></span>
                            <span class="smg-info-value"><?php echo esc_html($cacheType); ?></span>
                        </div>
                        <div class="smg-info-item">
                            <span class="smg-info-label"><?php esc_html_e('Object Cache', 'schema-markup-generator'); ?></span>
                            <span class="smg-info-value">
                                <?php
                                if (wp_using_ext_object_cache()) {
                                    echo '<span class="smg-badge smg-badge-success">' . esc_html__('Enabled', 'schema-markup-generator') . '</span>';
                                } else {
                                    echo '<span class="smg-badge smg-badge-warning">' . esc_html__('Not Available', 'schema-markup-generator') . '</span>';
                                }
                                ?>
                            </span>
                        </div>
                    </div>
                    <?php
                }, 'dashicons-chart-bar');
                ?>
            </div>

            <?php $this->renderSection(
                __('Cache Management', 'schema-markup-generator'),
                __('Clear cached schema data.', 'schema-markup-generator')
            ); ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php
                $this->renderCard(__('Clear Cache', 'schema-markup-generator'), function () {
                    ?>
                    <p class="mb-4"><?php esc_html_e('Clear all cached schema data. The cache will be rebuilt automatically when pages are visited.', 'schema-markup-generator'); ?></p>
                    <form method="post">
                        <?php wp_nonce_field('smg_clear_cache', 'smg_clear_cache_nonce'); ?>
                        <button type="submit" name="smg_clear_cache" class="smg-btn smg-btn-secondary">
                            <span class="dashicons dashicons-trash"></span>
                            <?php esc_html_e('Clear Schema Cache', 'schema-markup-generator'); ?>
                        </button>
                    </form>
                    <?php
                }, 'dashicons-trash');
                ?>

                <?php
                $this->renderCard(__('Performance Tips', 'schema-markup-generator'), function () {
                    ?>
                    <div class="smg-info-list text-sm text-gray-600 space-y-2">
                        <p><span class="dashicons dashicons-yes text-green-500"></span> <?php esc_html_e('Enable caching for production sites', 'schema-markup-generator'); ?></p>
                        <p><span class="dashicons dashicons-yes text-green-500"></span> <?php esc_html_e('Use Redis or Memcached for best performance', 'schema-markup-generator'); ?></p>
                        <p><span class="dashicons dashicons-yes text-green-500"></span> <?php esc_html_e('Increase TTL for content that rarely changes', 'schema-markup-generator'); ?></p>
                    </div>
                    <?php
                }, 'dashicons-lightbulb');
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Handle cache clear request
     */
    private function handleClearCache(): void
    {
        if (!isset($_POST['smg_clear_cache']) || !check_admin_referer('smg_clear_cache', 'smg_clear_cache_nonce')) {
            return;
        }

        // Clear transients
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('_transient_smg_') . '%'
            )
        );

        // Clear object cache if available
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('smg_schema');
        }

        add_settings_error('smg_cache', 'cleared', __('Schema cache cleared successfully.', 'schema-markup-generator'), 'success');
    }
}

