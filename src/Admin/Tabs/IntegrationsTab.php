<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Admin\Tabs;

use Metodo\SchemaMarkupGenerator\Integration\ACFIntegration;

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
    private ?ACFIntegration $acfIntegration = null;

    /**
     * Get ACF Integration instance (lazy loaded)
     */
    private function getAcfIntegration(): ACFIntegration
    {
        if ($this->acfIntegration === null) {
            $this->acfIntegration = new ACFIntegration();
        }
        return $this->acfIntegration;
    }
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
            'integration_memberpress_memberships_enabled' => !empty($input['integration_memberpress_memberships_enabled']),
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
        <div class="smg-tab-panel flex flex-col gap-6" id="tab-integrations">
            <?php $this->renderSection(
                __('Plugin Integrations', 'schema-markup-generator'),
                __('Configure how Schema Markup Generator works with other plugins.', 'schema-markup-generator')
            ); ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php
                $this->renderIntegrationCard(
                    'Rank Math SEO',
                    class_exists('RankMath'),
                    'rankmath',
                    __('Prevent duplicate schemas and sync with Rank Math SEO settings.', 'schema-markup-generator'),
                    $settings
                );

                $acfIntegration = $this->getAcfIntegration();
                $this->renderIntegrationCard(
                    $acfIntegration->isAvailable() ? $acfIntegration->getPluginLabel() : __('Custom Fields', 'schema-markup-generator'),
                    $acfIntegration->isAvailable(),
                    'acf',
                    __('Map custom fields to schema properties for dynamic content generation.', 'schema-markup-generator'),
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

                $this->renderIntegrationCard(
                    'MemberPress Memberships',
                    post_type_exists('memberpressproduct') || class_exists('MeprProduct') || defined('MEPR_VERSION'),
                    'memberpress_memberships',
                    __('Generate Product schemas for MemberPress memberships with pricing, trial info, and registration URLs.', 'schema-markup-generator'),
                    $settings
                );
                ?>
            </div>

            <?php if (class_exists('RankMath') && ($settings['integration_rankmath_enabled'] ?? true)): ?>
                <?php $this->renderSection(
                    __('Rank Math Settings', 'schema-markup-generator'),
                    __('Configure how Schema Markup Generator interacts with Rank Math.', 'schema-markup-generator')
                ); ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                        <p class="smg-field-description">
                            <?php esc_html_e('Select schema types that SMG should handle instead of Rank Math.', 'schema-markup-generator'); ?>
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <?php foreach ($schemaTypes as $type => $label): ?>
                                <label class="smg-checkbox-label">
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

            <?php
            $acfIntegration = $this->getAcfIntegration();
            if ($acfIntegration->isAvailable() && ($settings['integration_acf_enabled'] ?? true)):
                $pluginLabel = $acfIntegration->getPluginLabel();
            ?>
                <?php $this->renderSection(
                    /* translators: %s: plugin name (ACF/SCF) */
                    sprintf(__('%s Settings', 'schema-markup-generator'), $acfIntegration->getDetectedPluginName()),
                    /* translators: %s: plugin name (Advanced Custom Fields/Secure Custom Fields) */
                    sprintf(__('Configure %s integration.', 'schema-markup-generator'), $pluginLabel)
                ); ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php
                    $this->renderCard(__('Field Discovery', 'schema-markup-generator'), function () use ($settings) {
                        $this->renderToggle(
                            'smg_integrations_settings[acf_auto_discover]',
                            $settings['acf_auto_discover'] ?? true,
                            __('Auto-discover Custom Fields', 'schema-markup-generator'),
                            __('Automatically include custom fields in the field mapping dropdown.', 'schema-markup-generator')
                        );

                        $this->renderToggle(
                            'smg_integrations_settings[acf_include_nested]',
                            $settings['acf_include_nested'] ?? true,
                            __('Include Nested Fields', 'schema-markup-generator'),
                            __('Include fields from repeaters, groups, and flexible content.', 'schema-markup-generator')
                        );
                    }, 'dashicons-list-view');

                    $isProVersion = $acfIntegration->isProActive();
                    $detectedName = $acfIntegration->getDetectedPluginName();
                    $this->renderCard(__('Plugin Version', 'schema-markup-generator'), function () use ($isProVersion, $detectedName, $pluginLabel) {
                        ?>
                        <div class="flex flex-col gap-4">
                            <?php if ($isProVersion): ?>
                                <div class="smg-badge smg-badge-success">
                                    <span class="dashicons dashicons-star-filled"></span>
                                    <?php
                                    /* translators: %s: plugin name (ACF/SCF) */
                                    printf(esc_html__('%s Pro', 'schema-markup-generator'), esc_html($detectedName));
                                    ?>
                                </div>
                                <p><?php esc_html_e('Full support for all field types including repeaters, flexible content, and galleries.', 'schema-markup-generator'); ?></p>
                            <?php else: ?>
                                <div class="smg-badge smg-badge-info">
                                    <span class="dashicons dashicons-yes"></span>
                                    <?php echo esc_html($pluginLabel); ?>
                                </div>
                                <p><?php esc_html_e('Basic field types are supported. Upgrade to Pro for advanced field support.', 'schema-markup-generator'); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php
                    }, 'dashicons-info');
                    ?>
                </div>
            <?php endif; ?>

            <?php if (post_type_exists('mpcs-lesson') && ($settings['integration_memberpress_courses_enabled'] ?? true)): ?>
                <?php $this->renderSection(
                    __('MemberPress Courses Settings', 'schema-markup-generator'),
                    __('Configure MemberPress Courses integration for educational content.', 'schema-markup-generator')
                ); ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                        <div class="flex flex-col gap-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="dashicons dashicons-yes-alt" style="color: var(--smg-success);"></span>
                                <span><?php printf(__('%d Courses detected', 'schema-markup-generator'), $courseCount->publish ?? 0); ?></span>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="dashicons dashicons-yes-alt" style="color: var(--smg-success);"></span>
                                <span><?php printf(__('%d Lessons detected', 'schema-markup-generator'), $lessonCount->publish ?? 0); ?></span>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <?php if ($sectionsExist): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: var(--smg-success);"></span>
                                    <span><?php esc_html_e('Sections table found', 'schema-markup-generator'); ?></span>
                                <?php else: ?>
                                    <span class="dashicons dashicons-warning" style="color: var(--smg-warning);"></span>
                                    <span><?php esc_html_e('Sections table not found', 'schema-markup-generator'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="smg-text-muted">
                            <?php esc_html_e('Schema types: Course (mpcs-course) → LearningResource (mpcs-lesson)', 'schema-markup-generator'); ?>
                        </p>
                        <?php
                    }, 'dashicons-info');
                    ?>
                </div>
            <?php endif; ?>

            <?php if ((post_type_exists('memberpressproduct') || class_exists('MeprProduct') || defined('MEPR_VERSION')) && ($settings['integration_memberpress_memberships_enabled'] ?? true)): ?>
                <?php $this->renderSection(
                    __('MemberPress Memberships Settings', 'schema-markup-generator'),
                    __('Configure MemberPress Memberships integration for subscription/product content.', 'schema-markup-generator')
                ); ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php
                    $this->renderCard(__('Membership Fields', 'schema-markup-generator'), function () {
                        // Get MemberPress currency settings
                        $currencyCode = 'USD';
                        $currencySymbol = '$';
                        if (class_exists('MeprOptions')) {
                            $options = \MeprOptions::fetch();
                            $currencyCode = $options->currency_code ?? 'USD';
                            $currencySymbol = $options->currency_symbol ?? '$';
                        }
                        ?>
                        <div class="flex flex-col gap-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="dashicons dashicons-money-alt" style="color: var(--smg-success);"></span>
                                <span><?php printf(__('Currency: %s (%s)', 'schema-markup-generator'), esc_html($currencyCode), esc_html($currencySymbol)); ?></span>
                            </div>
                            <p class="smg-text-muted text-sm"><?php esc_html_e('Available fields for schema mapping:', 'schema-markup-generator'); ?></p>
                            <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
                                <li><?php esc_html_e('Price, Period, Period Type', 'schema-markup-generator'); ?></li>
                                <li><?php esc_html_e('Trial settings (days, amount)', 'schema-markup-generator'); ?></li>
                                <li><?php esc_html_e('Pricing display options', 'schema-markup-generator'); ?></li>
                                <li><?php esc_html_e('Benefits list', 'schema-markup-generator'); ?></li>
                                <li><code>mepr_currency_code</code> - <?php esc_html_e('ISO 4217 code (e.g. EUR, USD)', 'schema-markup-generator'); ?></li>
                            </ul>
                            <p class="smg-text-muted text-xs">
                                <?php esc_html_e('Computed fields: Formatted Price, Billing Description, Registration URL, Currency Code', 'schema-markup-generator'); ?>
                            </p>
                        </div>
                        <?php
                    }, 'dashicons-list-view');

                    $this->renderCard(__('Integration Status', 'schema-markup-generator'), function () {
                        $membershipCount = wp_count_posts('memberpressproduct');
                        ?>
                        <div class="flex flex-col gap-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="dashicons dashicons-yes-alt" style="color: var(--smg-success);"></span>
                                <span><?php printf(__('%d Memberships detected', 'schema-markup-generator'), $membershipCount->publish ?? 0); ?></span>
                            </div>
                            <?php if (class_exists('MeprProduct')): ?>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="dashicons dashicons-yes-alt" style="color: var(--smg-success);"></span>
                                    <span><?php esc_html_e('MemberPress API available', 'schema-markup-generator'); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <p class="smg-text-muted mt-4">
                            <?php esc_html_e('Schema type: Product with Offer (memberpressproduct)', 'schema-markup-generator'); ?>
                        </p>
                        <?php
                    }, 'dashicons-info');
                    ?>
                </div>
            <?php endif; ?>

            <?php if (class_exists('WooCommerce') && ($settings['integration_woocommerce_enabled'] ?? true)): ?>
                <?php $this->renderSection(
                    __('WooCommerce Settings', 'schema-markup-generator'),
                    __('Configure WooCommerce product schema generation. 40+ product fields available for mapping.', 'schema-markup-generator')
                ); ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php
                    $this->renderCard(__('Product Schema', 'schema-markup-generator'), function () use ($settings) {
                        $this->renderToggle(
                            'smg_integrations_settings[woo_auto_product]',
                            $settings['woo_auto_product'] ?? true,
                            __('Auto-enhance Product Schema', 'schema-markup-generator'),
                            __('Automatically populate SKU, GTIN, brand, offers, and reviews from WooCommerce data.', 'schema-markup-generator')
                        );

                        $this->renderToggle(
                            'smg_integrations_settings[woo_include_reviews]',
                            $settings['woo_include_reviews'] ?? true,
                            __('Include Aggregate Rating', 'schema-markup-generator'),
                            __('Auto-populate aggregateRating from WooCommerce product reviews.', 'schema-markup-generator')
                        );

                        $this->renderToggle(
                            'smg_integrations_settings[woo_include_offers]',
                            $settings['woo_include_offers'] ?? true,
                            __('Include Offers', 'schema-markup-generator'),
                            __('Auto-populate price, currency, availability, and priceValidUntil.', 'schema-markup-generator')
                        );
                    }, 'dashicons-cart');

                    $currencyCode = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : get_option('woocommerce_currency', 'EUR');
                    $currencySymbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '€';

                    $this->renderCard(__('Current Settings', 'schema-markup-generator'), function () use ($currencyCode, $currencySymbol) {
                        ?>
                        <div class="flex flex-col gap-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="dashicons dashicons-money-alt" style="color: var(--smg-success);"></span>
                                <span><?php printf(__('Currency: %s (%s)', 'schema-markup-generator'), esc_html($currencyCode), esc_html($currencySymbol)); ?></span>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="dashicons dashicons-weight" style="color: var(--smg-info);"></span>
                                <span><?php printf(__('Weight: %s', 'schema-markup-generator'), esc_html(get_option('woocommerce_weight_unit', 'kg'))); ?></span>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="dashicons dashicons-image-crop" style="color: var(--smg-info);"></span>
                                <span><?php printf(__('Dimensions: %s', 'schema-markup-generator'), esc_html(get_option('woocommerce_dimension_unit', 'cm'))); ?></span>
                            </div>
                        </div>
                        <?php
                    }, 'dashicons-admin-settings');
                    ?>
                </div>

                <?php $this->renderSection(
                    __('Available Product Fields', 'schema-markup-generator'),
                    __('These virtual fields are available for mapping when configuring the Product schema for WooCommerce products.', 'schema-markup-generator')
                ); ?>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php
                    // Define field groups
                    $fieldGroups = [
                        'Pricing' => [
                            'woo_price' => __('Current active price', 'schema-markup-generator'),
                            'woo_regular_price' => __('Regular/list price', 'schema-markup-generator'),
                            'woo_sale_price' => __('Discounted price', 'schema-markup-generator'),
                        ],
                        'Identifiers' => [
                            'woo_sku' => __('Stock Keeping Unit', 'schema-markup-generator'),
                            'woo_gtin' => __('GTIN/EAN/UPC (auto-detected)', 'schema-markup-generator'),
                            'woo_mpn' => __('Manufacturer Part Number', 'schema-markup-generator'),
                        ],
                        'Stock' => [
                            'woo_stock_status' => __('Schema.org availability', 'schema-markup-generator'),
                            'woo_stock_quantity' => __('Items in stock', 'schema-markup-generator'),
                            'woo_is_in_stock' => __('In stock (boolean)', 'schema-markup-generator'),
                        ],
                        'Reviews' => [
                            'woo_average_rating' => __('Average rating (1-5)', 'schema-markup-generator'),
                            'woo_review_count' => __('Number of reviews', 'schema-markup-generator'),
                            'woo_rating_count' => __('Number of ratings', 'schema-markup-generator'),
                        ],
                        'Promotions' => [
                            'woo_is_on_sale' => __('Currently on sale', 'schema-markup-generator'),
                            'woo_sale_price_dates_to' => __('Sale end date', 'schema-markup-generator'),
                        ],
                        'Dimensions' => [
                            'woo_weight' => __('Weight with unit', 'schema-markup-generator'),
                            'woo_dimensions' => __('L × W × H formatted', 'schema-markup-generator'),
                            'woo_length' => __('Product length', 'schema-markup-generator'),
                        ],
                        'Taxonomies' => [
                            'woo_product_category' => __('Primary category', 'schema-markup-generator'),
                            'woo_product_brand' => __('Brand (auto-detected)', 'schema-markup-generator'),
                            'woo_product_tags' => __('Product tags', 'schema-markup-generator'),
                        ],
                        'Images' => [
                            'woo_main_image' => __('Main product image', 'schema-markup-generator'),
                            'woo_gallery_images' => __('Gallery images (array)', 'schema-markup-generator'),
                            'woo_all_images' => __('All images combined', 'schema-markup-generator'),
                        ],
                        'Global' => [
                            'woo_currency_code' => __('ISO 4217 currency code', 'schema-markup-generator'),
                            'woo_currency_symbol' => __('Currency symbol', 'schema-markup-generator'),
                        ],
                    ];

                    foreach ($fieldGroups as $groupName => $fields): ?>
                        <div class="smg-field-group-card">
                            <h4 class="text-sm font-semibold text-gray-700 mb-2 flex items-center gap-2">
                                <?php
                                $icon = match ($groupName) {
                                    'Pricing' => 'dashicons-tag',
                                    'Identifiers' => 'dashicons-admin-network',
                                    'Stock' => 'dashicons-archive',
                                    'Reviews' => 'dashicons-star-filled',
                                    'Promotions' => 'dashicons-megaphone',
                                    'Dimensions' => 'dashicons-image-crop',
                                    'Taxonomies' => 'dashicons-category',
                                    'Images' => 'dashicons-format-gallery',
                                    'Global' => 'dashicons-admin-site-alt3',
                                    default => 'dashicons-list-view',
                                };
                                ?>
                                <span class="dashicons <?php echo esc_attr($icon); ?>" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                <?php echo esc_html($groupName); ?>
                            </h4>
                            <ul class="text-xs text-gray-600 space-y-1">
                                <?php foreach ($fields as $fieldKey => $fieldDesc): ?>
                                    <li class="flex flex-col">
                                        <code class="text-xs bg-gray-100 px-1 rounded"><?php echo esc_html($fieldKey); ?></code>
                                        <span class="text-gray-500 text-xs"><?php echo esc_html($fieldDesc); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p class="smg-text-muted text-sm mt-4">
                    <span class="dashicons dashicons-info" style="font-size: 14px; width: 14px; height: 14px;"></span>
                    <?php esc_html_e('Product-specific fields are only available for the "product" post type. Global fields are available for all post types.', 'schema-markup-generator'); ?>
                </p>
            <?php endif; ?>

        </div>
        <?php
    }

    /**
     * Render integration card
     *
     * @param string $name        Integration display name
     * @param bool   $detected    Whether the plugin is installed/detected
     * @param string $slug        Integration slug for settings
     * @param string $description Integration description
     * @param array  $settings    Current settings array
     */
    private function renderIntegrationCard(string $name, bool $detected, string $slug, string $description, array $settings): void
    {
        $isEnabled = $settings['integration_' . $slug . '_enabled'] ?? true;
        $isActive = $detected && $isEnabled;

        // Card class: active (green), detected (neutral), inactive (grey)
        $cardClass = 'inactive';
        if ($detected) {
            $cardClass = $isActive ? 'active' : 'detected';
        }
        ?>
        <div class="smg-integration-card <?php echo esc_attr($cardClass); ?>">
            <div class="smg-integration-header">
                <div class="smg-integration-status">
                    <?php if (!$detected): ?>
                        <span class="smg-status-badge inactive">
                            <span class="dashicons dashicons-marker"></span>
                            <?php esc_html_e('Not Detected', 'schema-markup-generator'); ?>
                        </span>
                    <?php elseif ($isActive): ?>
                        <span class="smg-status-badge active">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e('Active', 'schema-markup-generator'); ?>
                        </span>
                    <?php else: ?>
                        <span class="smg-status-badge detected">
                            <span class="dashicons dashicons-visibility"></span>
                            <?php esc_html_e('Detected', 'schema-markup-generator'); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <h3 class="smg-integration-title"><?php echo esc_html($name); ?></h3>
            </div>
            <div class="smg-integration-body">
                <p><?php echo esc_html($description); ?></p>
            </div>
            <?php if ($detected): ?>
                <div class="smg-integration-footer">
                    <label class="smg-toggle-inline">
                        <input type="checkbox"
                               name="smg_integrations_settings[integration_<?php echo esc_attr($slug); ?>_enabled]"
                               value="1"
                               <?php checked($isEnabled); ?>>
                        <span class="smg-toggle-slider-small"></span>
                        <?php esc_html_e('Enabled', 'schema-markup-generator'); ?>
                    </label>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

}

