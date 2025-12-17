<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Admin\Tabs\Settings;

use Metodo\SchemaMarkupGenerator\Admin\Tabs\AbstractTab;

/**
 * Debug Tab
 *
 * Debug mode, logging, and system information.
 *
 * @package Metodo\SchemaMarkupGenerator\Admin\Tabs\Settings
 * @author  Michele Marri <plugins@metodo.dev>
 */
class DebugTab extends AbstractTab
{
    public function getTitle(): string
    {
        return __('Debug', 'schema-markup-generator');
    }

    public function getIcon(): string
    {
        return 'dashicons-visibility';
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
        $settings = \Metodo\SchemaMarkupGenerator\smg_get_settings('advanced');

        ?>
        <div class="smg-tab-panel flex flex-col gap-6" id="tab-settings-debug">
            <?php $this->renderSection(
                __('Debug & Logging', 'schema-markup-generator'),
                __('Enable debug mode and logging for troubleshooting.', 'schema-markup-generator')
            ); ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php
                $this->renderCard(__('Debug Mode', 'schema-markup-generator'), function () use ($settings) {
                    $this->renderToggle(
                        'smg_advanced_settings[debug_mode]',
                        $settings['debug_mode'] ?? false,
                        __('Enable Debug Mode', 'schema-markup-generator'),
                        __('Log schema generation details for troubleshooting.', 'schema-markup-generator')
                    );

                    $logDir = SMG_PLUGIN_DIR . 'logs';
                    ?>
                    <div class="smg-info-item mt-4">
                        <span class="smg-info-label"><?php esc_html_e('Log Location', 'schema-markup-generator'); ?></span>
                        <code class="smg-info-value text-xs"><?php echo esc_html($logDir); ?></code>
                    </div>
                    <?php
                }, 'dashicons-visibility');
                ?>

                <?php
                $logFile = SMG_PLUGIN_DIR . 'logs/smg-' . date('Y-m-d') . '.log';
                if (file_exists($logFile)):
                    $this->renderCard(__('Recent Logs', 'schema-markup-generator'), function () use ($logFile) {
                        $lines = file($logFile);
                        $lastLines = array_slice($lines, -8);
                        ?>
                        <pre class="smg-code-block text-xs max-h-48 overflow-auto"><?php echo esc_html(implode('', $lastLines)); ?></pre>
                        <?php
                    }, 'dashicons-text-page');
                else:
                    $this->renderCard(__('Logs', 'schema-markup-generator'), function () {
                        ?>
                        <p class="text-gray-500"><?php esc_html_e('No logs available for today. Enable debug mode to start logging.', 'schema-markup-generator'); ?></p>
                        <?php
                    }, 'dashicons-text-page');
                endif;
                ?>
            </div>

            <?php $this->renderSection(
                __('System Information', 'schema-markup-generator'),
                __('Technical details about your installation.', 'schema-markup-generator')
            ); ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php
                $this->renderCard(__('Environment', 'schema-markup-generator'), function () {
                    ?>
                    <div class="smg-info-grid">
                        <div class="smg-info-item">
                            <span class="smg-info-label"><?php esc_html_e('Plugin Version', 'schema-markup-generator'); ?></span>
                            <span class="smg-info-value"><?php echo esc_html(SMG_VERSION); ?></span>
                        </div>
                        <div class="smg-info-item">
                            <span class="smg-info-label"><?php esc_html_e('WordPress', 'schema-markup-generator'); ?></span>
                            <span class="smg-info-value"><?php echo esc_html(get_bloginfo('version')); ?></span>
                        </div>
                        <div class="smg-info-item">
                            <span class="smg-info-label"><?php esc_html_e('PHP', 'schema-markup-generator'); ?></span>
                            <span class="smg-info-value"><?php echo esc_html(PHP_VERSION); ?></span>
                        </div>
                    </div>
                    <?php
                }, 'dashicons-wordpress');
                ?>

                <?php
                $this->renderCard(__('Server Limits', 'schema-markup-generator'), function () {
                    ?>
                    <div class="smg-info-grid">
                        <div class="smg-info-item">
                            <span class="smg-info-label"><?php esc_html_e('Max Execution Time', 'schema-markup-generator'); ?></span>
                            <span class="smg-info-value"><?php echo esc_html(ini_get('max_execution_time')); ?>s</span>
                        </div>
                        <div class="smg-info-item">
                            <span class="smg-info-label"><?php esc_html_e('Memory Limit', 'schema-markup-generator'); ?></span>
                            <span class="smg-info-value"><?php echo esc_html(ini_get('memory_limit')); ?></span>
                        </div>
                        <div class="smg-info-item">
                            <span class="smg-info-label"><?php esc_html_e('Upload Max', 'schema-markup-generator'); ?></span>
                            <span class="smg-info-value"><?php echo esc_html(ini_get('upload_max_filesize')); ?></span>
                        </div>
                    </div>
                    <?php
                }, 'dashicons-cloud');
                ?>
            </div>

            <?php $this->renderSection(
                __('Active Integrations', 'schema-markup-generator'),
                __('Detected plugins and integrations.', 'schema-markup-generator')
            ); ?>

            <div class="grid grid-cols-1 gap-6">
                <?php
                $this->renderCard(__('Integrations Status', 'schema-markup-generator'), function () {
                    $integrations = [
                        'ACF' => class_exists('ACF'),
                        'WooCommerce' => class_exists('WooCommerce'),
                        'Rank Math' => class_exists('RankMath'),
                        'MemberPress' => class_exists('MeprCtrlFactory'),
                    ];
                    ?>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <?php foreach ($integrations as $name => $active): ?>
                            <div class="flex items-center gap-2">
                                <?php if ($active): ?>
                                    <span class="dashicons dashicons-yes-alt text-green-500"></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-minus text-gray-400"></span>
                                <?php endif; ?>
                                <span class="<?php echo $active ? 'text-gray-700' : 'text-gray-400'; ?>"><?php echo esc_html($name); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php
                }, 'dashicons-admin-plugins');
                ?>
            </div>
        </div>
        <?php
    }
}

