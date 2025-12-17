<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Admin\Tabs\Settings;

use Metodo\SchemaMarkupGenerator\Admin\Tabs\AbstractTab;

/**
 * General Settings Tab
 *
 * Main plugin settings for schema output configuration.
 *
 * @package Metodo\SchemaMarkupGenerator\Admin\Tabs\Settings
 * @author  Michele Marri <plugins@metodo.dev>
 */
class GeneralTab extends AbstractTab
{
    public function getTitle(): string
    {
        return __('General', 'schema-markup-generator');
    }

    public function getIcon(): string
    {
        return 'dashicons-admin-settings';
    }

    public function getSettingsGroup(): string
    {
        return 'smg_general';
    }

    public function isAutoSaveEnabled(): bool
    {
        return true;
    }

    public function getAutoSaveOptionName(): string
    {
        return 'smg_general_settings';
    }

    public function getRegisteredOptions(): array
    {
        return [
            'smg_general_settings' => [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeSettings'],
                'default' => [
                    'enabled' => true,
                    'enable_website_schema' => true,
                    'enable_breadcrumb_schema' => true,
                    'auto_detect_video' => false,
                    'output_format' => 'json-ld',
                ],
            ],
        ];
    }

    /**
     * Sanitize general settings
     */
    public function sanitizeSettings(?array $input): array
    {
        $input = $input ?? [];

        // Get existing settings to preserve values
        $existing = get_option('smg_general_settings', []);
        if (!is_array($existing)) {
            $existing = [];
        }

        return [
            'enabled' => isset($input['enabled']) ? !empty($input['enabled']) : ($existing['enabled'] ?? true),
            'enable_website_schema' => isset($input['enable_website_schema']) ? !empty($input['enable_website_schema']) : ($existing['enable_website_schema'] ?? true),
            'enable_breadcrumb_schema' => isset($input['enable_breadcrumb_schema']) ? !empty($input['enable_breadcrumb_schema']) : ($existing['enable_breadcrumb_schema'] ?? true),
            'auto_detect_video' => isset($input['auto_detect_video']) ? !empty($input['auto_detect_video']) : ($existing['auto_detect_video'] ?? false),
            'output_format' => sanitize_text_field($input['output_format'] ?? $existing['output_format'] ?? 'json-ld'),
        ];
    }

    public function render(): void
    {
        $settings = \Metodo\SchemaMarkupGenerator\smg_get_settings('general');

        ?>
        <div class="smg-tab-panel flex flex-col gap-6" id="tab-settings-general">
            <?php $this->renderSection(
                __('Schema Output', 'schema-markup-generator'),
                __('Configure how schema markup is generated and output on the frontend.', 'schema-markup-generator')
            ); ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php
                $this->renderCard(__('Output Settings', 'schema-markup-generator'), function () use ($settings) {
                    $this->renderToggle(
                        'smg_general_settings[enabled]',
                        $settings['enabled'] ?? true,
                        __('Enable Schema Markup', 'schema-markup-generator'),
                        __('Enable or disable schema output on the frontend.', 'schema-markup-generator')
                    );

                    $this->renderToggle(
                        'smg_general_settings[enable_website_schema]',
                        $settings['enable_website_schema'] ?? true,
                        __('WebSite Schema', 'schema-markup-generator'),
                        __('Add WebSite schema with SearchAction for sitelinks search box.', 'schema-markup-generator')
                    );

                    $this->renderToggle(
                        'smg_general_settings[enable_breadcrumb_schema]',
                        $settings['enable_breadcrumb_schema'] ?? true,
                        __('Breadcrumb Schema', 'schema-markup-generator'),
                        __('Add BreadcrumbList schema for navigation trails.', 'schema-markup-generator')
                    );
                }, 'dashicons-editor-code');
                ?>

                <?php
                $this->renderCard(__('Auto-detection', 'schema-markup-generator'), function () use ($settings) {
                    $this->renderToggle(
                        'smg_general_settings[auto_detect_video]',
                        $settings['auto_detect_video'] ?? false,
                        __('Auto-detect Videos', 'schema-markup-generator'),
                        __('Automatically add VideoObject schema when YouTube or Vimeo videos are detected in the content.', 'schema-markup-generator')
                    );
                }, 'dashicons-video-alt3');
                ?>
            </div>

            <?php $this->renderSection(
                __('How it works', 'schema-markup-generator'),
                __('Understanding how schema output is controlled.', 'schema-markup-generator')
            ); ?>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php
                $this->renderCard(__('Global Toggle', 'schema-markup-generator'), function () {
                    ?>
                    <div class="smg-info-list text-sm text-gray-600 space-y-2">
                        <p><?php esc_html_e('The "Enable Schema Markup" toggle controls whether any schema is output on the frontend.', 'schema-markup-generator'); ?></p>
                        <p><?php esc_html_e('When disabled, no structured data will be added to your pages.', 'schema-markup-generator'); ?></p>
                    </div>
                    <?php
                }, 'dashicons-admin-site');
                ?>

                <?php
                $this->renderCard(__('Site-wide Schemas', 'schema-markup-generator'), function () {
                    ?>
                    <div class="smg-info-list text-sm text-gray-600 space-y-2">
                        <p><?php esc_html_e('WebSite and Breadcrumb schemas are automatically added to all pages when enabled.', 'schema-markup-generator'); ?></p>
                        <p><?php esc_html_e('These help search engines understand your site structure.', 'schema-markup-generator'); ?></p>
                    </div>
                    <?php
                }, 'dashicons-admin-links');
                ?>

                <?php
                $this->renderCard(__('Video Detection', 'schema-markup-generator'), function () {
                    ?>
                    <div class="smg-info-list text-sm text-gray-600 space-y-2">
                        <p><?php esc_html_e('When enabled, the plugin scans content for YouTube and Vimeo embeds.', 'schema-markup-generator'); ?></p>
                        <p><?php esc_html_e('Detected videos automatically get VideoObject schema added.', 'schema-markup-generator'); ?></p>
                    </div>
                    <?php
                }, 'dashicons-video-alt2');
                ?>
            </div>
        </div>
        <?php
    }
}

