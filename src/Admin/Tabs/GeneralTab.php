<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Admin\Tabs;

/**
 * General Tab
 *
 * General plugin settings.
 *
 * @package flavor\SchemaMarkupGenerator\Admin\Tabs
 * @author  Michele Marri <info@metodo.dev>
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

    public function render(): void
    {
        $settings = get_option('smg_settings', []);

        ?>
        <div class="smg-tab-panel" id="tab-general">
            <?php $this->renderSection(
                __('General Settings', 'schema-markup-generator'),
                __('Configure the main plugin settings.', 'schema-markup-generator')
            ); ?>

            <div class="smg-cards-grid">
                <?php
                $this->renderCard(__('Schema Output', 'schema-markup-generator'), function () use ($settings) {
                    $this->renderToggle(
                        'smg_settings[enabled]',
                        $settings['enabled'] ?? true,
                        __('Enable Schema Markup', 'schema-markup-generator'),
                        __('Enable or disable schema output on the frontend.', 'schema-markup-generator')
                    );

                    $this->renderToggle(
                        'smg_settings[enable_website_schema]',
                        $settings['enable_website_schema'] ?? true,
                        __('WebSite Schema', 'schema-markup-generator'),
                        __('Add WebSite schema with SearchAction for sitelinks search box.', 'schema-markup-generator')
                    );

                    $this->renderToggle(
                        'smg_settings[enable_breadcrumb_schema]',
                        $settings['enable_breadcrumb_schema'] ?? true,
                        __('Breadcrumb Schema', 'schema-markup-generator'),
                        __('Add BreadcrumbList schema for navigation trails.', 'schema-markup-generator')
                    );
                }, 'dashicons-editor-code');
                ?>

                <?php
                $this->renderCard(__('Organization Info', 'schema-markup-generator'), function () use ($settings) {
                    ?>
                    <p class="smg-info">
                        <?php esc_html_e('Organization info is automatically pulled from WordPress settings.', 'schema-markup-generator'); ?>
                    </p>

                    <div class="smg-info-grid">
                        <div class="smg-info-item">
                            <span class="smg-info-label"><?php esc_html_e('Site Name', 'schema-markup-generator'); ?></span>
                            <span class="smg-info-value"><?php echo esc_html(get_bloginfo('name')); ?></span>
                        </div>
                        <div class="smg-info-item">
                            <span class="smg-info-label"><?php esc_html_e('Site URL', 'schema-markup-generator'); ?></span>
                            <span class="smg-info-value"><?php echo esc_html(home_url('/')); ?></span>
                        </div>
                        <div class="smg-info-item">
                            <span class="smg-info-label"><?php esc_html_e('Logo', 'schema-markup-generator'); ?></span>
                            <span class="smg-info-value">
                                <?php
                                $logoId = get_theme_mod('custom_logo');
                                if ($logoId) {
                                    echo '<img src="' . esc_url(wp_get_attachment_image_url($logoId, 'thumbnail')) . '" alt="" style="max-height: 40px;">';
                                } else {
                                    esc_html_e('Not set', 'schema-markup-generator');
                                }
                                ?>
                            </span>
                        </div>
                    </div>

                    <p class="smg-note">
                        <span class="dashicons dashicons-info"></span>
                        <?php
                        printf(
                            /* translators: %s: Customizer link */
                            esc_html__('To change these settings, go to %s.', 'schema-markup-generator'),
                            '<a href="' . esc_url(admin_url('customize.php')) . '">' . esc_html__('Customizer', 'schema-markup-generator') . '</a>'
                        );
                        ?>
                    </p>
                    <?php
                }, 'dashicons-building');
                ?>
            </div>

            <?php $this->renderSection(
                __('Quick Start', 'schema-markup-generator'),
                __('Get started with schema markup in a few simple steps.', 'schema-markup-generator')
            ); ?>

            <div class="smg-quick-start">
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

