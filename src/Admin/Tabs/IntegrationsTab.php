<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Admin\Tabs;

/**
 * Integrations Tab
 *
 * Third-party plugin integrations and settings.
 *
 * @package Metodo\SchemaMarkupGenerator\Admin\Tabs
 * @author  Michele Marri <plugins@metodo.dev>
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

    public function getSettingsGroup(): string
    {
        return 'smg_integrations';
    }

    public function getRegisteredOptions(): array
    {
        return [
            'smg_integrations_settings' => [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeSettings'],
                'default' => [],
            ],
        ];
    }

    /**
     * Sanitize integrations settings
     */
    public function sanitizeSettings(?array $input): array
    {
        $input = $input ?? [];

        return [
            // Rank Math
            'rankmath_avoid_duplicates' => !empty($input['rankmath_avoid_duplicates']),
            'rankmath_takeover_types' => isset($input['rankmath_takeover_types']) && is_array($input['rankmath_takeover_types'])
                ? array_map('sanitize_text_field', $input['rankmath_takeover_types'])
                : [],
            // Integration toggles
            'integration_rankmath_enabled' => !empty($input['integration_rankmath_enabled']),
            'integration_acf_enabled' => !empty($input['integration_acf_enabled']),
            'integration_woocommerce_enabled' => !empty($input['integration_woocommerce_enabled']),
            'integration_memberpress_courses_enabled' => !empty($input['integration_memberpress_courses_enabled']),
            // ACF
            'acf_auto_discover' => !empty($input['acf_auto_discover']),
            'acf_include_nested' => !empty($input['acf_include_nested']),
            // MemberPress Courses
            'mpcs_auto_parent_course' => !empty($input['mpcs_auto_parent_course']),
            'mpcs_include_curriculum' => !empty($input['mpcs_include_curriculum']),
            // WooCommerce
            'woo_auto_product' => !empty($input['woo_auto_product']),
            'woo_include_reviews' => !empty($input['woo_include_reviews']),
            'woo_include_offers' => !empty($input['woo_include_offers']),
        ];
    }

    public function render(): void
    {
        $settings = \Metodo\SchemaMarkupGenerator\smg_get_settings('integrations');

        ?>
        <div class="mds-tab-panel mds-stack-gap" id="tab-integrations">
            <?php $this->renderSection(
                __('Plugin Integrations', 'schema-markup-generator'),
                __('Configure how Schema Markup Generator works with other plugins.', 'schema-markup-generator')
            ); ?>

            <div class="mds-grid mds-grid-auto-sm">
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

                $this->renderIntegrationCard(
                    'MemberPress Courses',
                    post_type_exists('mpcs-lesson'),
                    'memberpress_courses',
                    __('Link lessons to courses and generate LearningResource schemas with course hierarchy.', 'schema-markup-generator'),
                    $settings
                );
                ?>
            </div>

            <?php if (class_exists('RankMath')): ?>
                <?php $this->renderSection(
                    __('Rank Math Settings', 'schema-markup-generator'),
                    __('Configure how Schema Markup Generator interacts with Rank Math.', 'schema-markup-generator')
                ); ?>

                <div class="mds-grid mds-grid-auto">
                    <?php
                    $this->renderCard(__('Duplicate Prevention', 'schema-markup-generator'), function () use ($settings) {
                        $this->renderToggle(
                            'smg_integrations_settings[rankmath_avoid_duplicates]',
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
                        <p class="mds-field-description">
                            <?php esc_html_e('Select schema types that SMG should handle instead of Rank Math.', 'schema-markup-generator'); ?>
                        </p>
                        <div class="mds-cluster mds-cluster-sm">
                            <?php foreach ($schemaTypes as $type => $label): ?>
                                <label class="mds-checkbox-label">
                                    <input type="checkbox"
                                           name="smg_integrations_settings[rankmath_takeover_types][]"
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

                <div class="mds-grid mds-grid-auto">
                    <?php
                    $this->renderCard(__('Field Discovery', 'schema-markup-generator'), function () use ($settings) {
                        $this->renderToggle(
                            'smg_integrations_settings[acf_auto_discover]',
                            $settings['acf_auto_discover'] ?? true,
                            __('Auto-discover ACF Fields', 'schema-markup-generator'),
                            __('Automatically include ACF fields in the field mapping dropdown.', 'schema-markup-generator')
                        );

                        $this->renderToggle(
                            'smg_integrations_settings[acf_include_nested]',
                            $settings['acf_include_nested'] ?? true,
                            __('Include Nested Fields', 'schema-markup-generator'),
                            __('Include fields from repeaters, groups, and flexible content.', 'schema-markup-generator')
                        );
                    }, 'dashicons-list-view');

                    $isAcfPro = class_exists('ACF') && defined('ACF_PRO');
                    $this->renderCard(__('ACF Version', 'schema-markup-generator'), function () use ($isAcfPro) {
                        ?>
                        <div class="mds-stack-gap mds-stack-gap-sm">
                            <?php if ($isAcfPro): ?>
                                <div class="mds-badge mds-badge-success">
                                    <span class="dashicons dashicons-star-filled"></span>
                                    <?php esc_html_e('ACF Pro', 'schema-markup-generator'); ?>
                                </div>
                                <p><?php esc_html_e('Full support for all field types including repeaters, flexible content, and galleries.', 'schema-markup-generator'); ?></p>
                            <?php else: ?>
                                <div class="mds-badge mds-badge-info">
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

            <?php if (post_type_exists('mpcs-lesson')): ?>
                <?php $this->renderSection(
                    __('MemberPress Courses Settings', 'schema-markup-generator'),
                    __('Configure MemberPress Courses integration for educational content.', 'schema-markup-generator')
                ); ?>

                <div class="mds-grid mds-grid-auto">
                    <?php
                    $this->renderCard(__('Course Hierarchy', 'schema-markup-generator'), function () use ($settings) {
                        $this->renderToggle(
                            'smg_integrations_settings[mpcs_auto_parent_course]',
                            $settings['mpcs_auto_parent_course'] ?? true,
                            __('Auto-detect Parent Course', 'schema-markup-generator'),
                            __('Automatically link lessons to their parent course in the schema (isPartOf).', 'schema-markup-generator')
                        );

                        $this->renderToggle(
                            'smg_integrations_settings[mpcs_include_curriculum]',
                            $settings['mpcs_include_curriculum'] ?? false,
                            __('Include Curriculum in Course Schema', 'schema-markup-generator'),
                            __('Add sections and lessons list to Course schema (may increase page size).', 'schema-markup-generator')
                        );
                    }, 'dashicons-welcome-learn-more');

                    $this->renderCard(__('Integration Status', 'schema-markup-generator'), function () {
                        global $wpdb;
                        $tableName = $wpdb->prefix . 'mpcs_sections';
                        $sectionsExist = (bool) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tableName));
                        
                        $courseCount = wp_count_posts('mpcs-course');
                        $lessonCount = wp_count_posts('mpcs-lesson');
                        ?>
                        <div class="mds-stack-gap mds-stack-gap-sm">
                            <div class="mds-cluster mds-cluster-sm">
                                <span class="dashicons dashicons-yes-alt" style="color: var(--mds-success);"></span>
                                <span><?php printf(__('%d Courses detected', 'schema-markup-generator'), $courseCount->publish ?? 0); ?></span>
                            </div>
                            <div class="mds-cluster mds-cluster-sm">
                                <span class="dashicons dashicons-yes-alt" style="color: var(--mds-success);"></span>
                                <span><?php printf(__('%d Lessons detected', 'schema-markup-generator'), $lessonCount->publish ?? 0); ?></span>
                            </div>
                            <div class="mds-cluster mds-cluster-sm">
                                <?php if ($sectionsExist): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: var(--mds-success);"></span>
                                    <span><?php esc_html_e('Sections table found', 'schema-markup-generator'); ?></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-warning" style="color: var(--mds-warning);"></span>
                                    <span><?php esc_html_e('Sections table not found', 'schema-markup-generator'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="mds-text-muted">
                            <?php esc_html_e('Schema types: Course (mpcs-course) → LearningResource (mpcs-lesson)', 'schema-markup-generator'); ?>
                        </p>
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

                <div class="mds-grid mds-grid-auto">
                    <?php
                    $this->renderCard(__('Product Schema', 'schema-markup-generator'), function () use ($settings) {
                        $this->renderToggle(
                            'smg_integrations_settings[woo_auto_product]',
                            $settings['woo_auto_product'] ?? true,
                            __('Auto-generate Product Schema', 'schema-markup-generator'),
                            __('Automatically generate Product schema for WooCommerce products.', 'schema-markup-generator')
                        );

                        $this->renderToggle(
                            'smg_integrations_settings[woo_include_reviews]',
                            $settings['woo_include_reviews'] ?? true,
                            __('Include Reviews', 'schema-markup-generator'),
                            __('Include product reviews in the schema aggregate rating.', 'schema-markup-generator')
                        );

                        $this->renderToggle(
                            'smg_integrations_settings[woo_include_offers]',
                            $settings['woo_include_offers'] ?? true,
                            __('Include Offers', 'schema-markup-generator'),
                            __('Include pricing and availability as Offer schema.', 'schema-markup-generator')
                        );
                    }, 'dashicons-cart');
                    ?>
                </div>
            <?php endif; ?>

        </div>
        <?php
    }

    /**
     * Render integration card
     */
    private function renderIntegrationCard(string $name, bool $active, string $slug, string $description, array $settings): void
    {
        ?>
        <div class="mds-integration-card <?php echo $active ? 'active' : 'inactive'; ?>">
            <div class="mds-integration-header">
                <div class="mds-integration-status">
                    <?php if ($active): ?>
                        <span class="mds-status-badge active">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e('Active', 'schema-markup-generator'); ?>
                        </span>
                    <?php else: ?>
                        <span class="mds-status-badge inactive">
                            <span class="dashicons dashicons-marker"></span>
                            <?php esc_html_e('Not Detected', 'schema-markup-generator'); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <h3 class="mds-integration-title"><?php echo esc_html($name); ?></h3>
            </div>
            <div class="mds-integration-body">
                <p><?php echo esc_html($description); ?></p>
            </div>
            <?php if ($active): ?>
                <div class="mds-integration-footer">
                    <label class="mds-toggle-inline">
                        <input type="checkbox"
                               name="smg_integrations_settings[integration_<?php echo esc_attr($slug); ?>_enabled]"
                               value="1"
                               <?php checked($settings['integration_' . $slug . '_enabled'] ?? true); ?>>
                        <span class="mds-toggle-slider-small"></span>
                        <?php esc_html_e('Enabled', 'schema-markup-generator'); ?>
                    </label>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

}

