<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Admin\Tabs;

/**
 * General Tab
 *
 * General plugin settings.
 *
 * @package Metodo\SchemaMarkupGenerator\Admin\Tabs
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

        return [
            'enabled' => !empty($input['enabled']),
            'enable_website_schema' => !empty($input['enable_website_schema']),
            'enable_breadcrumb_schema' => !empty($input['enable_breadcrumb_schema']),
            'auto_detect_video' => !empty($input['auto_detect_video']),
            'output_format' => sanitize_text_field($input['output_format'] ?? 'json-ld'),
        ];
    }

    public function render(): void
    {
        $settings = \Metodo\SchemaMarkupGenerator\smg_get_settings('general');

        ?>
        <div class="flex flex-col gap-6" id="tab-general">
            <?php $this->renderSection(
                __('General Settings', 'schema-markup-generator'),
                __('Configure the main plugin settings.', 'schema-markup-generator')
            ); ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php
                $this->renderCard(__('Schema Output', 'schema-markup-generator'), function () use ($settings) {
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

                    $this->renderToggle(
                        'smg_general_settings[auto_detect_video]',
                        $settings['auto_detect_video'] ?? false,
                        __('Auto-detect Videos', 'schema-markup-generator'),
                        __('Automatically add VideoObject schema when YouTube or Vimeo videos are detected in the content.', 'schema-markup-generator')
                    );
                }, 'dashicons-editor-code');
                ?>

                <!-- Organization Info Card with Edit button in header -->
                <div class="smg-card">
                    <div class="smg-card-header flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <span class="dashicons dashicons-building"></span>
                            <h3><?php esc_html_e('Organization Info', 'schema-markup-generator'); ?></h3>
                        </div>
                        <a href="<?php echo esc_url(admin_url('options-general.php?page=schema-markup-generator&tab=advanced')); ?>" class="smg-btn smg-btn-ghost smg-btn-sm">
                            <span class="dashicons dashicons-edit"></span>
                            <?php esc_html_e('Edit', 'schema-markup-generator'); ?>
                        </a>
                    </div>
                    <div class="smg-card-body">
                        <?php
                        // Get organization data with fallbacks
                        $orgData = \Metodo\SchemaMarkupGenerator\smg_get_organization_data();
                        ?>
                        <div class="smg-info-grid">
                            <div class="smg-info-item">
                                <span class="smg-info-label"><?php esc_html_e('Name', 'schema-markup-generator'); ?></span>
                                <span class="smg-info-value"><?php echo esc_html($orgData['name']); ?></span>
                            </div>
                            <div class="smg-info-item">
                                <span class="smg-info-label"><?php esc_html_e('URL', 'schema-markup-generator'); ?></span>
                                <span class="smg-info-value"><?php echo esc_html($orgData['url']); ?></span>
                            </div>
                            <div class="smg-info-item">
                                <span class="smg-info-label"><?php esc_html_e('Logo', 'schema-markup-generator'); ?></span>
                                <span class="smg-info-value">
                                    <?php
                                    if ($orgData['logo']) {
                                        echo '<img src="' . esc_url($orgData['logo']['url']) . '" alt="" style="max-height: 40px; border-radius: 4px;">';
                                    } else {
                                        esc_html_e('Not set', 'schema-markup-generator');
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php $this->renderSection(
                __('Quick Start', 'schema-markup-generator'),
                __('Get started with schema markup in a few simple steps.', 'schema-markup-generator')
            ); ?>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="smg-step">
                    <div class="smg-step-number">1</div>
                    <div class="smg-step-content">
                        <h4><?php esc_html_e('Configure Post Types', 'schema-markup-generator'); ?></h4>
                        <p><?php esc_html_e('Assign schema types to your post types in the Post Types tab.', 'schema-markup-generator'); ?></p>
                    </div>
                </div>
                <div class="smg-step">
                    <div class="smg-step-number">2</div>
                    <div class="smg-step-content">
                        <h4><?php esc_html_e('Map Custom Fields', 'schema-markup-generator'); ?></h4>
                        <p><?php esc_html_e('Connect your custom fields to schema properties for richer data.', 'schema-markup-generator'); ?></p>
                    </div>
                </div>
                <div class="smg-step">
                    <div class="smg-step-number">3</div>
                    <div class="smg-step-content">
                        <h4><?php esc_html_e('Test & Validate', 'schema-markup-generator'); ?></h4>
                        <p><?php esc_html_e('Use the preview and validation tools to ensure your schema is correct.', 'schema-markup-generator'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

