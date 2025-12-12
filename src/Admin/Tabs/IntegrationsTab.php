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

    /**
     * Return empty string to disable the form wrapper and Save button
     * All integration settings are auto-saved via AJAX
     */
    public function getSettingsGroup(): string
    {
        return '';
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
            'rankmath_disable_all_schemas' => !empty($input['rankmath_disable_all_schemas']),
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
            'integration_youtube_enabled' => !empty($input['integration_youtube_enabled']),
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

                // YouTube API Integration - always show (API key needed)
                $youtubeIntegration = new \Metodo\SchemaMarkupGenerator\Integration\YouTubeIntegration();
                $this->renderIntegrationCard(
                    'YouTube Data API',
                    true, // Always "detected" since it's an external API
                    'youtube',
                    __('Get accurate video durations for Course schemas. Requires free Google API key.', 'schema-markup-generator'),
                    $settings,
                    $youtubeIntegration->hasApiKey() // Custom check for API key
                );
                ?>
            </div>

        </div>

            <?php 
            // Render integration modals
            $this->renderIntegrationModals($settings);
            ?>
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
     * @param bool   $hasApiKey   Optional: for API integrations, whether key is set
     */
    private function renderIntegrationCard(string $name, bool $detected, string $slug, string $description, array $settings, ?bool $hasApiKey = null): void
    {
        $isEnabled = $settings['integration_' . $slug . '_enabled'] ?? true;
        $isActive = $detected && $isEnabled;
        
        // For API integrations, check if API key is configured
        if ($hasApiKey !== null) {
            $isActive = $isEnabled && $hasApiKey;
        }
        $hasSettings = $this->integrationHasSettings($slug);

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
                    <?php if ($hasSettings && $isEnabled): ?>
                        <button type="button" 
                                class="smg-btn smg-btn-sm smg-btn-secondary smg-open-integration-modal"
                                data-integration="<?php echo esc_attr($slug); ?>"
                                data-title="<?php echo esc_attr($name); ?>">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <?php esc_html_e('Settings', 'schema-markup-generator'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Check if an integration has configurable settings
     */
    private function integrationHasSettings(string $slug): bool
    {
        return in_array($slug, ['rankmath', 'acf', 'woocommerce', 'memberpress_courses', 'youtube'], true);
    }

    /**
     * Render all integration setting modals
     */
    private function renderIntegrationModals(array $settings): void
    {
        // Rank Math Modal
        if (class_exists('RankMath') && ($settings['integration_rankmath_enabled'] ?? true)) {
            $this->renderRankMathModal($settings);
        }

        // ACF Modal
        $acfIntegration = $this->getAcfIntegration();
        if ($acfIntegration->isAvailable() && ($settings['integration_acf_enabled'] ?? true)) {
            $this->renderAcfModal($settings, $acfIntegration);
        }

        // WooCommerce Modal
        if (class_exists('WooCommerce') && ($settings['integration_woocommerce_enabled'] ?? true)) {
            $this->renderWooCommerceModal($settings);
        }

        // MemberPress Courses Modal
        if (post_type_exists('mpcs-lesson') && ($settings['integration_memberpress_courses_enabled'] ?? true)) {
            $this->renderMemberPressCoursesModal($settings);
        }

        // YouTube API Modal
        if ($settings['integration_youtube_enabled'] ?? true) {
            $this->renderYouTubeModal($settings);
        }
    }

    /**
     * Render Rank Math settings modal
     */
    private function renderRankMathModal(array $settings): void
    {
        $takeoverTypes = $settings['rankmath_takeover_types'] ?? [];
        $disableAll = $settings['rankmath_disable_all_schemas'] ?? false;
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
        <div class="smg-modal smg-modal-lg smg-integration-modal" id="smg-integration-modal-rankmath">
            <div class="smg-modal-overlay"></div>
            <div class="smg-modal-content">
                <button type="button" class="smg-modal-close">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
                <h3 class="smg-modal-title">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e('Rank Math SEO Settings', 'schema-markup-generator'); ?>
                </h3>
                <div class="smg-modal-body">
                    <div class="flex flex-col gap-6">
                        <!-- Schema Control -->
                        <div class="smg-modal-section">
                            <h4 class="smg-modal-section-title">
                                <span class="dashicons dashicons-dismiss"></span>
                                <?php esc_html_e('Schema Control', 'schema-markup-generator'); ?>
                            </h4>
                            <?php $this->renderToggle(
                                'smg_integrations_settings[rankmath_disable_all_schemas]',
                                $settings['rankmath_disable_all_schemas'] ?? false,
                                __('Disable All Rank Math Schemas', 'schema-markup-generator'),
                                __('Completely disable all schema markup generated by Rank Math, letting SMG handle all structured data.', 'schema-markup-generator')
                            ); ?>
                        </div>

                        <!-- Duplicate Prevention -->
                        <div class="smg-modal-section">
                            <h4 class="smg-modal-section-title">
                                <span class="dashicons dashicons-admin-generic"></span>
                                <?php esc_html_e('Duplicate Prevention', 'schema-markup-generator'); ?>
                            </h4>
                            <div class="smg-rankmath-conditional <?php echo $disableAll ? 'opacity-50 pointer-events-none' : ''; ?>">
                                <?php $this->renderToggle(
                                    'smg_integrations_settings[rankmath_avoid_duplicates]',
                                    $settings['rankmath_avoid_duplicates'] ?? true,
                                    __('Avoid Duplicate Schemas', 'schema-markup-generator'),
                                    __('Automatically skip schema types that Rank Math already generates.', 'schema-markup-generator')
                                ); ?>
                            </div>
                        </div>

                        <!-- Schema Takeover -->
                        <div class="smg-modal-section">
                            <h4 class="smg-modal-section-title">
                                <span class="dashicons dashicons-superhero"></span>
                                <?php esc_html_e('Schema Takeover', 'schema-markup-generator'); ?>
                            </h4>
                            <div class="smg-rankmath-conditional <?php echo $disableAll ? 'opacity-50 pointer-events-none' : ''; ?>">
                                <p class="smg-field-description mb-3">
                                    <?php esc_html_e('Select schema types that SMG should handle instead of Rank Math.', 'schema-markup-generator'); ?>
                                </p>
                                <div class="flex flex-wrap items-center gap-3">
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
                            </div>
                        </div>
                    </div>
                </div>
                <div class="smg-modal-footer">
                    <p class="smg-text-muted text-xs m-0">
                        <span class="dashicons dashicons-saved" style="font-size: 14px; width: 14px; height: 14px;"></span>
                        <?php esc_html_e('Changes are saved automatically.', 'schema-markup-generator'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render ACF settings modal
     */
    private function renderAcfModal(array $settings, ACFIntegration $acfIntegration): void
    {
        $isProVersion = $acfIntegration->isProActive();
        $detectedName = $acfIntegration->getDetectedPluginName();
        $pluginLabel = $acfIntegration->getPluginLabel();
        ?>
        <div class="smg-modal smg-modal-lg smg-integration-modal" id="smg-integration-modal-acf">
            <div class="smg-modal-overlay"></div>
            <div class="smg-modal-content">
                <button type="button" class="smg-modal-close">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
                <h3 class="smg-modal-title">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php
                    /* translators: %s: plugin name (ACF/SCF) */
                    printf(esc_html__('%s Settings', 'schema-markup-generator'), esc_html($detectedName));
                    ?>
                </h3>
                <div class="smg-modal-body">
                    <div class="flex flex-col gap-6">
                        <!-- Field Discovery -->
                        <div class="smg-modal-section">
                            <h4 class="smg-modal-section-title">
                                <span class="dashicons dashicons-list-view"></span>
                                <?php esc_html_e('Field Discovery', 'schema-markup-generator'); ?>
                            </h4>
                            <?php $this->renderToggle(
                                'smg_integrations_settings[acf_auto_discover]',
                                $settings['acf_auto_discover'] ?? true,
                                __('Auto-discover Custom Fields', 'schema-markup-generator'),
                                __('Automatically include custom fields in the field mapping dropdown.', 'schema-markup-generator')
                            ); ?>
                            <div class="mt-4">
                                <?php $this->renderToggle(
                                    'smg_integrations_settings[acf_include_nested]',
                                    $settings['acf_include_nested'] ?? true,
                                    __('Include Nested Fields', 'schema-markup-generator'),
                                    __('Include fields from repeaters, groups, and flexible content.', 'schema-markup-generator')
                                ); ?>
                            </div>
                        </div>

                        <!-- Plugin Version Info -->
                        <div class="smg-modal-section">
                            <h4 class="smg-modal-section-title">
                                <span class="dashicons dashicons-info"></span>
                                <?php esc_html_e('Plugin Version', 'schema-markup-generator'); ?>
                            </h4>
                            <div class="flex flex-col gap-3">
                                <?php if ($isProVersion): ?>
                                    <div class="smg-badge smg-badge-success">
                                        <span class="dashicons dashicons-star-filled"></span>
                                        <?php
                                        /* translators: %s: plugin name (ACF/SCF) */
                                        printf(esc_html__('%s Pro', 'schema-markup-generator'), esc_html($detectedName));
                                        ?>
                                    </div>
                                    <p class="text-sm text-gray-600"><?php esc_html_e('Full support for all field types including repeaters, flexible content, and galleries.', 'schema-markup-generator'); ?></p>
                                <?php else: ?>
                                    <div class="smg-badge smg-badge-info">
                                        <span class="dashicons dashicons-yes"></span>
                                        <?php echo esc_html($pluginLabel); ?>
                                    </div>
                                    <p class="text-sm text-gray-600"><?php esc_html_e('Basic field types are supported. Upgrade to Pro for advanced field support.', 'schema-markup-generator'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="smg-modal-footer">
                    <p class="smg-text-muted text-xs m-0">
                        <span class="dashicons dashicons-saved" style="font-size: 14px; width: 14px; height: 14px;"></span>
                        <?php esc_html_e('Changes are saved automatically.', 'schema-markup-generator'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render WooCommerce settings modal
     */
    private function renderWooCommerceModal(array $settings): void
    {
        $currencyCode = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : get_option('woocommerce_currency', 'EUR');
        $currencySymbol = function_exists('get_woocommerce_currency_symbol') ? get_woocommerce_currency_symbol() : '€';

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
        ?>
        <div class="smg-modal smg-modal-xl smg-integration-modal" id="smg-integration-modal-woocommerce">
            <div class="smg-modal-overlay"></div>
            <div class="smg-modal-content">
                <button type="button" class="smg-modal-close">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
                <h3 class="smg-modal-title">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e('WooCommerce Settings', 'schema-markup-generator'); ?>
                </h3>
                <div class="smg-modal-body">
                    <div class="flex flex-col gap-6">
                        <!-- Product Schema Options -->
                        <div class="smg-modal-section">
                            <h4 class="smg-modal-section-title">
                                <span class="dashicons dashicons-cart"></span>
                                <?php esc_html_e('Product Schema', 'schema-markup-generator'); ?>
                            </h4>
                            <?php $this->renderToggle(
                                'smg_integrations_settings[woo_auto_product]',
                                $settings['woo_auto_product'] ?? true,
                                __('Auto-enhance Product Schema', 'schema-markup-generator'),
                                __('Automatically populate SKU, GTIN, brand, offers, and reviews from WooCommerce data.', 'schema-markup-generator')
                            ); ?>
                            <div class="mt-4">
                                <?php $this->renderToggle(
                                    'smg_integrations_settings[woo_include_reviews]',
                                    $settings['woo_include_reviews'] ?? true,
                                    __('Include Aggregate Rating', 'schema-markup-generator'),
                                    __('Auto-populate aggregateRating from WooCommerce product reviews.', 'schema-markup-generator')
                                ); ?>
                            </div>
                            <div class="mt-4">
                                <?php $this->renderToggle(
                                    'smg_integrations_settings[woo_include_offers]',
                                    $settings['woo_include_offers'] ?? true,
                                    __('Include Offers', 'schema-markup-generator'),
                                    __('Auto-populate price, currency, availability, and priceValidUntil.', 'schema-markup-generator')
                                ); ?>
                            </div>
                        </div>

                        <!-- Current Settings Info -->
                        <div class="smg-modal-section">
                            <h4 class="smg-modal-section-title">
                                <span class="dashicons dashicons-admin-settings"></span>
                                <?php esc_html_e('Current WooCommerce Settings', 'schema-markup-generator'); ?>
                            </h4>
                            <div class="flex flex-wrap gap-4">
                                <div class="flex items-center gap-2">
                                    <span class="dashicons dashicons-money-alt" style="color: var(--smg-success);"></span>
                                    <span class="text-sm"><?php printf(__('Currency: %s (%s)', 'schema-markup-generator'), esc_html($currencyCode), esc_html($currencySymbol)); ?></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="dashicons dashicons-weight" style="color: var(--smg-info);"></span>
                                    <span class="text-sm"><?php printf(__('Weight: %s', 'schema-markup-generator'), esc_html(get_option('woocommerce_weight_unit', 'kg'))); ?></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="dashicons dashicons-image-crop" style="color: var(--smg-info);"></span>
                                    <span class="text-sm"><?php printf(__('Dimensions: %s', 'schema-markup-generator'), esc_html(get_option('woocommerce_dimension_unit', 'cm'))); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Available Fields -->
                        <div class="smg-modal-section">
                            <h4 class="smg-modal-section-title">
                                <span class="dashicons dashicons-list-view"></span>
                                <?php esc_html_e('Available Product Fields', 'schema-markup-generator'); ?>
                            </h4>
                            <p class="smg-field-description mb-4">
                                <?php esc_html_e('These virtual fields are available for mapping when configuring the Product schema.', 'schema-markup-generator'); ?>
                            </p>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                <?php foreach ($fieldGroups as $groupName => $fields): ?>
                                    <div class="smg-field-group-card">
                                        <h5 class="text-xs font-semibold text-gray-700 mb-2 flex items-center gap-2">
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
                                            <span class="dashicons <?php echo esc_attr($icon); ?>" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                            <?php echo esc_html($groupName); ?>
                                        </h5>
                                        <ul class="text-[11px] text-gray-600 space-y-1">
                                            <?php foreach ($fields as $fieldKey => $fieldDesc): ?>
                                                <li class="flex flex-col">
                                                    <code class="text-[10px] bg-gray-100 px-1 rounded"><?php echo esc_html($fieldKey); ?></code>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="smg-modal-footer">
                    <p class="smg-text-muted text-xs m-0">
                        <span class="dashicons dashicons-saved" style="font-size: 14px; width: 14px; height: 14px;"></span>
                        <?php esc_html_e('Changes are saved automatically.', 'schema-markup-generator'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render MemberPress Courses settings modal
     */
    private function renderMemberPressCoursesModal(array $settings): void
    {
        global $wpdb;
        $tableName = $wpdb->prefix . 'mpcs_sections';
        $sectionsExist = (bool) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tableName));
        
        $courseCount = wp_count_posts('mpcs-course');
        $lessonCount = wp_count_posts('mpcs-lesson');
        ?>
        <div class="smg-modal smg-modal-lg smg-integration-modal" id="smg-integration-modal-memberpress_courses">
            <div class="smg-modal-overlay"></div>
            <div class="smg-modal-content">
                <button type="button" class="smg-modal-close">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
                <h3 class="smg-modal-title">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e('MemberPress Courses Settings', 'schema-markup-generator'); ?>
                </h3>
                <div class="smg-modal-body">
                    <div class="flex flex-col gap-6">
                        <!-- Course Hierarchy -->
                        <div class="smg-modal-section">
                            <h4 class="smg-modal-section-title">
                                <span class="dashicons dashicons-welcome-learn-more"></span>
                                <?php esc_html_e('Course Hierarchy', 'schema-markup-generator'); ?>
                            </h4>
                            <?php $this->renderToggle(
                                'smg_integrations_settings[mpcs_auto_parent_course]',
                                $settings['mpcs_auto_parent_course'] ?? true,
                                __('Auto-detect Parent Course', 'schema-markup-generator'),
                                __('Automatically link lessons to their parent course in the schema (isPartOf).', 'schema-markup-generator')
                            ); ?>
                            <div class="mt-4">
                                <?php $this->renderToggle(
                                    'smg_integrations_settings[mpcs_include_curriculum]',
                                    $settings['mpcs_include_curriculum'] ?? false,
                                    __('Include Curriculum in Course Schema', 'schema-markup-generator'),
                                    __('Add sections and lessons list to Course schema (may increase page size).', 'schema-markup-generator')
                                ); ?>
                            </div>
                        </div>

                        <!-- Video Duration Calculator -->
                        <div class="smg-modal-section">
                            <h4 class="smg-modal-section-title">
                                <span class="dashicons dashicons-clock"></span>
                                <?php esc_html_e('Course Duration Calculator', 'schema-markup-generator'); ?>
                            </h4>
                            <p class="smg-text-muted text-sm mb-4">
                                <?php esc_html_e('Scan all lessons for embedded videos (YouTube/Vimeo) and calculate total course duration for schema.org timeRequired property.', 'schema-markup-generator'); ?>
                            </p>
                            <div id="smg-duration-calculator">
                                <button type="button" id="smg-calculate-durations-btn" class="smg-btn smg-btn-primary">
                                    <span class="dashicons dashicons-video-alt3"></span>
                                    <?php esc_html_e('Calculate All Course Durations', 'schema-markup-generator'); ?>
                                </button>
                                <div id="smg-duration-progress" class="hidden mt-4">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="smg-spinner"></span>
                                        <span id="smg-duration-status" class="text-sm"><?php esc_html_e('Calculating...', 'schema-markup-generator'); ?></span>
                                    </div>
                                    <div class="smg-progress-bar">
                                        <div id="smg-duration-progress-bar" class="smg-progress-fill" style="width: 0%"></div>
                                    </div>
                                </div>
                                <div id="smg-duration-results" class="hidden mt-4">
                                    <h5 class="text-sm font-semibold mb-2">
                                        <span class="dashicons dashicons-yes-alt" style="color: var(--smg-success);"></span>
                                        <?php esc_html_e('Results', 'schema-markup-generator'); ?>
                                    </h5>
                                    <div id="smg-duration-results-list" class="smg-duration-results-list"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Integration Status -->
                        <div class="smg-modal-section">
                            <h4 class="smg-modal-section-title">
                                <span class="dashicons dashicons-info"></span>
                                <?php esc_html_e('Integration Status', 'schema-markup-generator'); ?>
                            </h4>
                            <div class="flex flex-col gap-3">
                                <div class="flex items-center gap-2">
                                    <span class="dashicons dashicons-yes-alt" style="color: var(--smg-success);"></span>
                                    <span class="text-sm"><?php printf(__('%d Courses detected', 'schema-markup-generator'), $courseCount->publish ?? 0); ?></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="dashicons dashicons-yes-alt" style="color: var(--smg-success);"></span>
                                    <span class="text-sm"><?php printf(__('%d Lessons detected', 'schema-markup-generator'), $lessonCount->publish ?? 0); ?></span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <?php if ($sectionsExist): ?>
                                        <span class="dashicons dashicons-yes-alt" style="color: var(--smg-success);"></span>
                                        <span class="text-sm"><?php esc_html_e('Sections table found', 'schema-markup-generator'); ?></span>
                                    <?php else: ?>
                                        <span class="dashicons dashicons-warning" style="color: var(--smg-warning);"></span>
                                        <span class="text-sm"><?php esc_html_e('Sections table not found', 'schema-markup-generator'); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p class="smg-text-muted text-sm mt-3">
                                <?php esc_html_e('Schema types: Course (mpcs-course) → LearningResource (mpcs-lesson)', 'schema-markup-generator'); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="smg-modal-footer">
                    <p class="smg-text-muted text-xs m-0">
                        <span class="dashicons dashicons-saved" style="font-size: 14px; width: 14px; height: 14px;"></span>
                        <?php esc_html_e('Changes are saved automatically.', 'schema-markup-generator'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render YouTube API settings modal
     */
    private function renderYouTubeModal(array $settings): void
    {
        $youtubeIntegration = new \Metodo\SchemaMarkupGenerator\Integration\YouTubeIntegration();
        $hasApiKey = $youtubeIntegration->hasApiKey();
        $maskedKey = $youtubeIntegration->getMaskedApiKey();
        $quotaInfo = $youtubeIntegration->getQuotaInfo();
        ?>
        <div class="smg-modal smg-modal-lg smg-integration-modal" id="smg-integration-modal-youtube">
            <div class="smg-modal-overlay"></div>
            <div class="smg-modal-content">
                <button type="button" class="smg-modal-close">
                    <span class="dashicons dashicons-no-alt"></span>
                </button>
                <h3 class="smg-modal-title">
                    <span class="dashicons dashicons-youtube"></span>
                    <?php esc_html_e('YouTube Data API Settings', 'schema-markup-generator'); ?>
                </h3>
                <div class="smg-modal-body">
                    <div class="flex flex-col gap-6">
                        <!-- API Key Configuration -->
                        <div class="smg-modal-section">
                            <h4 class="smg-modal-section-title">
                                <span class="dashicons dashicons-admin-network"></span>
                                <?php esc_html_e('API Key Configuration', 'schema-markup-generator'); ?>
                            </h4>
                            
                            <?php if ($hasApiKey): ?>
                                <!-- API Key is set -->
                                <div class="smg-api-key-status mb-4">
                                    <div class="flex items-center gap-2 p-3 bg-emerald-50 border border-emerald-200 rounded-lg">
                                        <span class="dashicons dashicons-yes-alt" style="color: var(--smg-success);"></span>
                                        <span class="text-sm font-medium text-emerald-800">
                                            <?php esc_html_e('API Key configured', 'schema-markup-generator'); ?>
                                        </span>
                                        <code class="ml-2 px-2 py-1 bg-emerald-100 rounded text-xs"><?php echo esc_html($maskedKey); ?></code>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" id="smg-test-youtube-api" class="smg-btn smg-btn-secondary smg-btn-sm">
                                        <span class="dashicons dashicons-yes"></span>
                                        <?php esc_html_e('Test API Key', 'schema-markup-generator'); ?>
                                    </button>
                                    <button type="button" id="smg-change-youtube-api" class="smg-btn smg-btn-secondary smg-btn-sm">
                                        <span class="dashicons dashicons-edit"></span>
                                        <?php esc_html_e('Change Key', 'schema-markup-generator'); ?>
                                    </button>
                                    <button type="button" id="smg-remove-youtube-api" class="smg-btn smg-btn-danger smg-btn-sm">
                                        <span class="dashicons dashicons-trash"></span>
                                        <?php esc_html_e('Remove', 'schema-markup-generator'); ?>
                                    </button>
                                </div>
                            <?php endif; ?>

                            <!-- API Key Input Form (hidden if key exists) -->
                            <div id="smg-youtube-api-form" class="<?php echo $hasApiKey ? 'hidden' : ''; ?>">
                                <p class="smg-text-muted text-sm mb-3">
                                    <?php esc_html_e('Enter your YouTube Data API v3 key. The key will be encrypted before saving.', 'schema-markup-generator'); ?>
                                </p>
                                <div class="flex gap-2">
                                    <input type="password" 
                                           id="smg-youtube-api-key-input" 
                                           class="smg-input flex-1" 
                                           placeholder="AIza..."
                                           autocomplete="off">
                                    <button type="button" id="smg-save-youtube-api" class="smg-btn smg-btn-primary">
                                        <span class="dashicons dashicons-saved"></span>
                                        <?php esc_html_e('Save & Test', 'schema-markup-generator'); ?>
                                    </button>
                                </div>
                                <div id="smg-youtube-api-result" class="hidden mt-3"></div>
                            </div>
                        </div>

                        <!-- How to get API Key -->
                        <div class="smg-modal-section">
                            <h4 class="smg-modal-section-title">
                                <span class="dashicons dashicons-info-outline"></span>
                                <?php esc_html_e('How to Get a Free API Key', 'schema-markup-generator'); ?>
                            </h4>
                            <ol class="text-sm text-gray-600 space-y-2 list-decimal list-inside">
                                <li><?php printf(
                                    __('Go to %s', 'schema-markup-generator'),
                                    '<a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="text-blue-600 hover:underline">Google Cloud Console</a>'
                                ); ?></li>
                                <li><?php esc_html_e('Create a new project (or select existing)', 'schema-markup-generator'); ?></li>
                                <li><?php esc_html_e('Enable "YouTube Data API v3" in the API Library', 'schema-markup-generator'); ?></li>
                                <li><?php esc_html_e('Go to Credentials → Create Credentials → API Key', 'schema-markup-generator'); ?></li>
                                <li><?php esc_html_e('(Optional) Restrict the key to YouTube Data API v3 only', 'schema-markup-generator'); ?></li>
                                <li><?php esc_html_e('Copy and paste the key above', 'schema-markup-generator'); ?></li>
                            </ol>
                        </div>

                        <!-- Quota Info -->
                        <div class="smg-modal-section">
                            <h4 class="smg-modal-section-title">
                                <span class="dashicons dashicons-performance"></span>
                                <?php esc_html_e('API Quota', 'schema-markup-generator'); ?>
                            </h4>
                            <div class="flex flex-col gap-2 text-sm">
                                <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                                    <span class="text-gray-600"><?php esc_html_e('Daily Limit (Free)', 'schema-markup-generator'); ?></span>
                                    <span class="font-semibold"><?php echo number_format($quotaInfo['daily_limit']); ?> <?php esc_html_e('units', 'schema-markup-generator'); ?></span>
                                </div>
                                <div class="flex items-center justify-between p-2 bg-gray-50 rounded">
                                    <span class="text-gray-600"><?php esc_html_e('Cost per Video', 'schema-markup-generator'); ?></span>
                                    <span class="font-semibold"><?php echo $quotaInfo['cost_per_video']; ?> <?php esc_html_e('unit', 'schema-markup-generator'); ?></span>
                                </div>
                                <p class="smg-text-muted text-xs mt-2">
                                    <?php echo esc_html($quotaInfo['note']); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Integration Status -->
                        <div class="smg-modal-section">
                            <h4 class="smg-modal-section-title">
                                <span class="dashicons dashicons-info"></span>
                                <?php esc_html_e('Features', 'schema-markup-generator'); ?>
                            </h4>
                            <ul class="text-sm text-gray-600 space-y-2">
                                <li class="flex items-center gap-2">
                                    <span class="dashicons dashicons-yes" style="color: var(--smg-success);"></span>
                                    <?php esc_html_e('Accurate video duration for Course timeRequired', 'schema-markup-generator'); ?>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="dashicons dashicons-yes" style="color: var(--smg-success);"></span>
                                    <?php esc_html_e('Video title and thumbnail extraction', 'schema-markup-generator'); ?>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="dashicons dashicons-yes" style="color: var(--smg-success);"></span>
                                    <?php esc_html_e('Results cached for 1 week (minimal API usage)', 'schema-markup-generator'); ?>
                                </li>
                                <li class="flex items-center gap-2">
                                    <span class="dashicons dashicons-yes" style="color: var(--smg-success);"></span>
                                    <?php esc_html_e('API key encrypted with AES-256-CBC', 'schema-markup-generator'); ?>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="smg-modal-footer">
                    <p class="smg-text-muted text-xs m-0">
                        <span class="dashicons dashicons-lock" style="font-size: 14px; width: 14px; height: 14px;"></span>
                        <?php esc_html_e('Your API key is encrypted and stored securely.', 'schema-markup-generator'); ?>
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
}

