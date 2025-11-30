<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Admin\Tabs;

/**
 * Advanced Tab
 *
 * Advanced settings including cache, logging, and debug options.
 *
 * @package Metodo\SchemaMarkupGenerator\Admin\Tabs
 * @author  Michele Marri <plugins@metodo.dev>
 */
class AdvancedTab extends AbstractTab
{
    public function getTitle(): string
    {
        return __('Advanced', 'schema-markup-generator');
    }

    public function getIcon(): string
    {
        return 'dashicons-admin-generic';
    }

    public function getSettingsGroup(): string
    {
        return 'smg_advanced';
    }

    public function getRegisteredOptions(): array
    {
        return [
            'smg_advanced_settings' => [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeSettings'],
                'default' => [
                    'cache_enabled' => true,
                    'cache_ttl' => 3600,
                    'debug_mode' => false,
                ],
            ],
        ];
    }

    /**
     * Sanitize advanced settings
     */
    public function sanitizeSettings(?array $input): array
    {
        $input = $input ?? [];

        return [
            'cache_enabled' => !empty($input['cache_enabled']),
            'cache_ttl' => absint($input['cache_ttl'] ?? 3600),
            'debug_mode' => !empty($input['debug_mode']),
        ];
    }

    public function render(): void
    {
        $settings = \Metodo\SchemaMarkupGenerator\smg_get_settings('advanced');

        ?>
        <div class="mds-tab-panel" id="tab-advanced">
            <?php $this->renderSection(
                __('Cache Settings', 'schema-markup-generator'),
                __('Configure how schema data is cached for better performance.', 'schema-markup-generator')
            ); ?>

            <div class="mds-cards-grid">
                <?php
                $this->renderCard(__('Cache Configuration', 'schema-markup-generator'), function () use ($settings) {
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

                    // Show cache type
                    $cacheType = wp_using_ext_object_cache()
                        ? __('Object Cache (Redis/Memcached)', 'schema-markup-generator')
                        : __('WordPress Transients', 'schema-markup-generator');
                    ?>
                    <div class="mds-cache-info">
                        <span class="mds-info-label"><?php esc_html_e('Cache Type:', 'schema-markup-generator'); ?></span>
                        <span class="mds-info-value"><?php echo esc_html($cacheType); ?></span>
                    </div>
                    <?php
                }, 'dashicons-performance');
                ?>
            </div>

            <?php $this->renderSection(
                __('Debug & Logging', 'schema-markup-generator'),
                __('Enable debug mode and logging for troubleshooting.', 'schema-markup-generator')
            ); ?>

            <div class="mds-cards-grid">
                <?php
                $this->renderCard(__('Debug Mode', 'schema-markup-generator'), function () use ($settings) {
                    $this->renderToggle(
                        'smg_advanced_settings[debug_mode]',
                        $settings['debug_mode'] ?? false,
                        __('Enable Debug Mode', 'schema-markup-generator'),
                        __('Log schema generation details for troubleshooting. Logs are stored in the plugin\'s logs folder.', 'schema-markup-generator')
                    );

                    // Show log file location
                    $logDir = SMG_PLUGIN_DIR . 'logs';
                    $logFile = $logDir . '/mds-' . date('Y-m-d') . '.log';
                    ?>
                    <div class="mds-log-info">
                        <span class="mds-info-label"><?php esc_html_e('Log Location:', 'schema-markup-generator'); ?></span>
                        <code><?php echo esc_html($logDir); ?></code>
                    </div>

                    <?php if (file_exists($logFile)): ?>
                        <div class="mds-log-preview">
                            <h4><?php esc_html_e('Recent Log Entries', 'schema-markup-generator'); ?></h4>
                            <pre><?php
                            $lines = file($logFile);
                            $lastLines = array_slice($lines, -10);
                            echo esc_html(implode('', $lastLines));
                            ?></pre>
                        </div>
                    <?php endif; ?>
                    <?php
                }, 'dashicons-visibility');
                ?>
            </div>

            <?php $this->renderSection(
                __('System Information', 'schema-markup-generator'),
                __('Technical details about your installation.', 'schema-markup-generator')
            ); ?>

            <div class="mds-system-info">
                <table class="mds-info-table">
                    <tr>
                        <th><?php esc_html_e('Plugin Version', 'schema-markup-generator'); ?></th>
                        <td><?php echo esc_html(SMG_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('WordPress Version', 'schema-markup-generator'); ?></th>
                        <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('PHP Version', 'schema-markup-generator'); ?></th>
                        <td><?php echo esc_html(PHP_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Object Cache', 'schema-markup-generator'); ?></th>
                        <td>
                            <?php
                            if (wp_using_ext_object_cache()) {
                                echo '<span class="mds-status-ok">';
                                esc_html_e('Enabled', 'schema-markup-generator');
                                echo '</span>';
                            } else {
                                echo '<span class="mds-status-warning">';
                                esc_html_e('Not Available', 'schema-markup-generator');
                                echo '</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Max Execution Time', 'schema-markup-generator'); ?></th>
                        <td><?php echo esc_html(ini_get('max_execution_time')); ?>s</td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Memory Limit', 'schema-markup-generator'); ?></th>
                        <td><?php echo esc_html(ini_get('memory_limit')); ?></td>
                    </tr>
                </table>
            </div>
        </div>
        <?php
    }
}

