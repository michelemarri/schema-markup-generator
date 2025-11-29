<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Admin\Tabs;

/**
 * Integrations Tab
 *
 * Third-party plugin integrations and settings.
 *
 * @package flavor\SchemaMarkupGenerator\Admin\Tabs
 * @author  Michele Marri <info@metodo.dev>
 */
class IntegrationsTab extends AbstractTab
{
    public function getTitle(): string
    {
        return __('Integrations', 'schema-markup-generator');
    }

    public function getIcon(): string
    {
        return 'dashicons-admin-plugins';
    }

    public function render(): void
    {
        $settings = get_option('smg_settings', []);

        ?>
        <div class="smg-tab-panel" id="tab-integrations">
            <?php $this->renderSection(
                __('Plugin Integrations', 'schema-markup-generator'),
                __('Configure how Schema Markup Generator works with other plugins.', 'schema-markup-generator')
            ); ?>

            <div class="smg-integrations-grid">
                <?php
                $this->renderIntegrationCard(
                    'Rank Math SEO',
                    class_exists('RankMath'),
                    'rankmath',
                    __('Prevent duplicate schemas and sync with Rank Math SEO settings.', 'schema-markup-generator'),
                    $settings
                );

                $this->renderIntegrationCard(
                    'Advanced Custom Fields',
                    class_exists('ACF') || function_exists('get_field'),
                    'acf',
                    __('Map ACF fields to schema properties for dynamic content generation.', 'schema-markup-generator'),
                    $settings
                );

                $this->renderIntegrationCard(
                    'WooCommerce',
                    class_exists('WooCommerce'),
                    'woocommerce',
                    __('Automatically generate Product schemas from WooCommerce products.', 'schema-markup-generator'),
                    $settings
                );
                ?>
            </div>

            <?php if (class_exists('RankMath')): ?>
                <?php $this->renderSection(
                    __('Rank Math Settings', 'schema-markup-generator'),
                    __('Configure how Schema Markup Generator interacts with Rank Math.', 'schema-markup-generator')
                ); ?>

                <div class="smg-cards-grid">
                    <?php
                    $this->renderCard(__('Duplicate Prevention', 'schema-markup-generator'), function () use ($settings) {
                        $this->renderToggle(
                            'smg_settings[rankmath_avoid_duplicates]',
                            $settings['rankmath_avoid_duplicates'] ?? true,
                            __('Avoid Duplicate Schemas', 'schema-markup-generator'),
                            __('Automatically skip schema types that Rank Math already generates.', 'schema-markup-generator')
                        );
                    }, 'dashicons-admin-generic');

                    $this->renderCard(__('Schema Takeover', 'schema-markup-generator'), function () use ($settings) {
                        $takeoverTypes = $settings['rankmath_takeover_types'] ?? [];
                        $schemaTypes = [
                            'Article' => __('Article', 'schema-markup-generator'),
                            'BlogPosting' => __('Blog Posting', 'schema-markup-generator'),
                            'Product' => __('Product', 'schema-markup-generator'),
                            'FAQPage' => __('FAQ', 'schema-markup-generator'),
                            'HowTo' => __('How To', 'schema-markup-generator'),
                            'Recipe' => __('Recipe', 'schema-markup-generator'),
                            'Event' => __('Event', 'schema-markup-generator'),
                            'Course' => __('Course', 'schema-markup-generator'),
                            'VideoObject' => __('Video', 'schema-markup-generator'),
                        ];
                        ?>
                        <p class="smg-field-description" style="margin-bottom: 15px;">
                            <?php esc_html_e('Select schema types that SMG should handle instead of Rank Math.', 'schema-markup-generator'); ?>
                        </p>
                        <div class="smg-checkboxes-grid">
                            <?php foreach ($schemaTypes as $type => $label): ?>
                                <label class="smg-checkbox-label">
                                    <input type="checkbox"
                                           name="smg_settings[rankmath_takeover_types][]"
                                           value="<?php echo esc_attr($type); ?>"
                                           <?php checked(in_array($type, $takeoverTypes, true)); ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <?php
                    }, 'dashicons-superhero');
                    ?>
                </div>
            <?php endif; ?>

            <?php if (class_exists('ACF') || function_exists('get_field')): ?>
                <?php $this->renderSection(
                    __('ACF Settings', 'schema-markup-generator'),
                    __('Configure Advanced Custom Fields integration.', 'schema-markup-generator')
                ); ?>

                <div class="smg-cards-grid">
                    <?php
                    $this->renderCard(__('Field Discovery', 'schema-markup-generator'), function () use ($settings) {
                        $this->renderToggle(
                            'smg_settings[acf_auto_discover]',
                            $settings['acf_auto_discover'] ?? true,
                            __('Auto-discover ACF Fields', 'schema-markup-generator'),
                            __('Automatically include ACF fields in the field mapping dropdown.', 'schema-markup-generator')
                        );

                        $this->renderToggle(
                            'smg_settings[acf_include_nested]',
                            $settings['acf_include_nested'] ?? true,
                            __('Include Nested Fields', 'schema-markup-generator'),
                            __('Include fields from repeaters, groups, and flexible content.', 'schema-markup-generator')
                        );
                    }, 'dashicons-list-view');

                    $isAcfPro = class_exists('ACF') && defined('ACF_PRO');
                    $this->renderCard(__('ACF Version', 'schema-markup-generator'), function () use ($isAcfPro) {
                        ?>
                        <div class="smg-acf-version-info">
                            <?php if ($isAcfPro): ?>
                                <div class="smg-version-badge pro">
                                    <span class="dashicons dashicons-star-filled"></span>
                                    <?php esc_html_e('ACF Pro', 'schema-markup-generator'); ?>
                                </div>
                                <p><?php esc_html_e('Full support for all field types including repeaters, flexible content, and galleries.', 'schema-markup-generator'); ?></p>
                            <?php else: ?>
                                <div class="smg-version-badge free">
                                    <span class="dashicons dashicons-yes"></span>
                                    <?php esc_html_e('ACF Free', 'schema-markup-generator'); ?>
                                </div>
                                <p><?php esc_html_e('Basic field types are supported. Upgrade to ACF Pro for advanced field support.', 'schema-markup-generator'); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php
                    }, 'dashicons-info');
                    ?>
                </div>
            <?php endif; ?>

            <?php if (class_exists('WooCommerce')): ?>
                <?php $this->renderSection(
                    __('WooCommerce Settings', 'schema-markup-generator'),
                    __('Configure WooCommerce product schema generation.', 'schema-markup-generator')
                ); ?>

                <div class="smg-cards-grid">
                    <?php
                    $this->renderCard(__('Product Schema', 'schema-markup-generator'), function () use ($settings) {
                        $this->renderToggle(
                            'smg_settings[woo_auto_product]',
                            $settings['woo_auto_product'] ?? true,
                            __('Auto-generate Product Schema', 'schema-markup-generator'),
                            __('Automatically generate Product schema for WooCommerce products.', 'schema-markup-generator')
                        );

                        $this->renderToggle(
                            'smg_settings[woo_include_reviews]',
                            $settings['woo_include_reviews'] ?? true,
                            __('Include Reviews', 'schema-markup-generator'),
                            __('Include product reviews in the schema aggregate rating.', 'schema-markup-generator')
                        );

                        $this->renderToggle(
                            'smg_settings[woo_include_offers]',
                            $settings['woo_include_offers'] ?? true,
                            __('Include Offers', 'schema-markup-generator'),
                            __('Include pricing and availability as Offer schema.', 'schema-markup-generator')
                        );
                    }, 'dashicons-cart');
                    ?>
                </div>
            <?php endif; ?>

            <?php $this->renderSection(
                __('Available Integrations', 'schema-markup-generator'),
                __('Install these plugins to unlock additional schema features.', 'schema-markup-generator')
            ); ?>

            <div class="smg-suggested-integrations">
                <?php
                $this->renderSuggestedIntegration(
                    'Yoast SEO',
                    !class_exists('WPSEO_Options'),
                    __('Read primary categories and canonical URLs from Yoast SEO.', 'schema-markup-generator')
                );

                $this->renderSuggestedIntegration(
                    'The Events Calendar',
                    !class_exists('Tribe__Events__Main'),
                    __('Automatically generate Event schema from calendar events.', 'schema-markup-generator')
                );

                $this->renderSuggestedIntegration(
                    'LearnDash',
                    !class_exists('SFWD_LMS'),
                    __('Generate Course schema from LearnDash courses and lessons.', 'schema-markup-generator')
                );
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render integration card
     */
    private function renderIntegrationCard(string $name, bool $active, string $slug, string $description, array $settings): void
    {
        ?>
        <div class="smg-integration-card <?php echo $active ? 'active' : 'inactive'; ?>">
            <div class="smg-integration-header">
                <div class="smg-integration-status">
                    <?php if ($active): ?>
                        <span class="smg-status-badge active">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e('Active', 'schema-markup-generator'); ?>
                        </span>
                    <?php else: ?>
                        <span class="smg-status-badge inactive">
                            <span class="dashicons dashicons-marker"></span>
                            <?php esc_html_e('Not Detected', 'schema-markup-generator'); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <h3 class="smg-integration-title"><?php echo esc_html($name); ?></h3>
            </div>
            <div class="smg-integration-body">
                <p><?php echo esc_html($description); ?></p>
            </div>
            <?php if ($active): ?>
                <div class="smg-integration-footer">
                    <label class="smg-toggle-inline">
                        <input type="checkbox"
                               name="smg_settings[integration_<?php echo esc_attr($slug); ?>_enabled]"
                               value="1"
                               <?php checked($settings['integration_' . $slug . '_enabled'] ?? true); ?>>
                        <span class="smg-toggle-slider-small"></span>
                        <?php esc_html_e('Enabled', 'schema-markup-generator'); ?>
                    </label>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render suggested integration
     */
    private function renderSuggestedIntegration(string $name, bool $notInstalled, string $description): void
    {
        if (!$notInstalled) {
            return; // Already installed
        }
        ?>
        <div class="smg-suggested-integration">
            <div class="smg-suggested-icon">
                <span class="dashicons dashicons-plus-alt2"></span>
            </div>
            <div class="smg-suggested-content">
                <h4><?php echo esc_html($name); ?></h4>
                <p><?php echo esc_html($description); ?></p>
            </div>
        </div>
        <?php
    }
}

