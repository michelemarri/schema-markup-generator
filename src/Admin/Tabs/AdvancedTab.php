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
                    'organization_name' => '',
                    'organization_url' => '',
                    'organization_logo' => 0,
                    'fallback_image' => 0,
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
            'organization_name' => sanitize_text_field($input['organization_name'] ?? ''),
            'organization_url' => esc_url_raw($input['organization_url'] ?? ''),
            'organization_logo' => absint($input['organization_logo'] ?? 0),
            'fallback_image' => absint($input['fallback_image'] ?? 0),
        ];
    }

    public function render(): void
    {
        $settings = \Metodo\SchemaMarkupGenerator\smg_get_settings('advanced');

        // Get fallback values from WordPress
        $fallbackName = get_bloginfo('name');
        $fallbackUrl = home_url('/');
        $fallbackLogoId = get_theme_mod('custom_logo');

        ?>
        <div class="smg-tab-panel flex flex-col gap-6" id="tab-advanced">
            <?php $this->renderSection(
                __('Organization Info', 'schema-markup-generator'),
                __('Customize organization data used in schema markup. Leave fields empty to use WordPress defaults.', 'schema-markup-generator')
            ); ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php
                $this->renderCard(__('Organization Details', 'schema-markup-generator'), function () use ($settings, $fallbackName, $fallbackUrl) {
                    $orgName = $settings['organization_name'] ?? '';
                    $orgUrl = $settings['organization_url'] ?? '';

                    $this->renderTextField(
                        'smg_advanced_settings[organization_name]',
                        $orgName,
                        __('Organization Name', 'schema-markup-generator'),
                        sprintf(
                            /* translators: %s: fallback value */
                            __('Leave empty to use: %s', 'schema-markup-generator'),
                            $fallbackName
                        ),
                        $fallbackName
                    );

                    $this->renderTextField(
                        'smg_advanced_settings[organization_url]',
                        $orgUrl,
                        __('Organization URL', 'schema-markup-generator'),
                        sprintf(
                            /* translators: %s: fallback value */
                            __('Leave empty to use: %s', 'schema-markup-generator'),
                            $fallbackUrl
                        ),
                        $fallbackUrl
                    );
                }, 'dashicons-building');
                ?>

                <?php
                $this->renderCard(__('Organization Logo', 'schema-markup-generator'), function () use ($settings, $fallbackLogoId) {
                    $orgLogoId = $settings['organization_logo'] ?? 0;
                    ?>
                    <div class="smg-field smg-field-media">
                        <div class="smg-media-field flex items-center gap-4">
                            <div class="smg-media-preview" id="smg-logo-preview">
                                <?php
                                $displayLogoId = $orgLogoId ?: $fallbackLogoId;
                                if ($displayLogoId) {
                                    $logoUrl = wp_get_attachment_image_url($displayLogoId, 'thumbnail');
                                    if ($logoUrl) {
                                        echo '<img src="' . esc_url($logoUrl) . '" alt="" class="max-h-16 rounded border border-gray-200">';
                                    }
                                } else {
                                    echo '<span class="smg-no-image text-gray-400">' . esc_html__('No logo set', 'schema-markup-generator') . '</span>';
                                }
                                ?>
                            </div>
                            <div class="smg-media-buttons flex gap-2">
                                <button type="button" class="smg-btn smg-btn-secondary smg-btn-sm" id="smg-select-logo">
                                    <span class="dashicons dashicons-upload"></span>
                                    <?php esc_html_e('Select Logo', 'schema-markup-generator'); ?>
                                </button>
                                <button type="button" class="smg-btn smg-btn-ghost smg-btn-sm <?php echo $orgLogoId ? '' : 'hidden'; ?>" id="smg-remove-logo">
                                    <span class="dashicons dashicons-no-alt"></span>
                                    <?php esc_html_e('Remove', 'schema-markup-generator'); ?>
                                </button>
                            </div>
                            <input type="hidden" name="smg_advanced_settings[organization_logo]" id="smg-organization-logo" value="<?php echo esc_attr($orgLogoId); ?>">
                        </div>
                        <span class="smg-field-description mt-3">
                            <?php
                            if ($fallbackLogoId && !$orgLogoId) {
                                printf(
                                    /* translators: %s: customizer link */
                                    esc_html__('Currently using Custom Logo from %s.', 'schema-markup-generator'),
                                    '<a href="' . esc_url(admin_url('customize.php')) . '">' . esc_html__('Customizer', 'schema-markup-generator') . '</a>'
                                );
                            } else {
                                esc_html_e('Recommended: square image, at least 112×112 pixels.', 'schema-markup-generator');
                            }
                            ?>
                        </span>
                    </div>
                    <?php
                }, 'dashicons-format-image');
                ?>
            </div>

            <?php $this->renderSection(
                __('Fallback Image', 'schema-markup-generator'),
                __('Configure a fallback image for schema types that require an image (e.g., Product, Article, Course). If not set, the site favicon will be used.', 'schema-markup-generator')
            ); ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php
                $this->renderCard(__('Fallback Image', 'schema-markup-generator'), function () use ($settings) {
                    $fallbackImageId = $settings['fallback_image'] ?? 0;
                    ?>
                    <div class="smg-field smg-field-media">
                        <div class="smg-media-field flex items-center gap-4">
                            <div class="smg-media-preview" id="smg-fallback-image-preview">
                                <?php
                                if ($fallbackImageId) {
                                    $imageUrl = wp_get_attachment_image_url($fallbackImageId, 'thumbnail');
                                    if ($imageUrl) {
                                        echo '<img src="' . esc_url($imageUrl) . '" alt="" class="max-h-16 rounded border border-gray-200">';
                                    }
                                } else {
                                    echo '<span class="smg-no-image text-gray-400">' . esc_html__('No image set (will use favicon)', 'schema-markup-generator') . '</span>';
                                }
                                ?>
                            </div>
                            <div class="smg-media-buttons flex gap-2">
                                <button type="button" class="smg-btn smg-btn-secondary smg-btn-sm" id="smg-select-fallback-image">
                                    <span class="dashicons dashicons-upload"></span>
                                    <?php esc_html_e('Select Image', 'schema-markup-generator'); ?>
                                </button>
                                <button type="button" class="smg-btn smg-btn-ghost smg-btn-sm <?php echo $fallbackImageId ? '' : 'hidden'; ?>" id="smg-remove-fallback-image">
                                    <span class="dashicons dashicons-no-alt"></span>
                                    <?php esc_html_e('Remove', 'schema-markup-generator'); ?>
                                </button>
                            </div>
                            <input type="hidden" name="smg_advanced_settings[fallback_image]" id="smg-fallback-image" value="<?php echo esc_attr($fallbackImageId); ?>">
                        </div>
                        <span class="smg-field-description mt-3">
                            <?php esc_html_e('This image will be used for schemas that require an image when the post has no featured image. Recommended: at least 1200×630 pixels (social sharing size).', 'schema-markup-generator'); ?>
                        </span>
                    </div>
                    <?php
                }, 'dashicons-format-image');
                ?>

                <?php
                $this->renderCard(__('How it works', 'schema-markup-generator'), function () {
                    ?>
                    <div class="smg-info-list text-sm text-gray-600 space-y-2">
                        <p><span class="dashicons dashicons-yes text-green-500"></span> <?php esc_html_e('First, the featured image of the post is checked', 'schema-markup-generator'); ?></p>
                        <p><span class="dashicons dashicons-yes text-green-500"></span> <?php esc_html_e('If no featured image, the fallback image is used', 'schema-markup-generator'); ?></p>
                        <p><span class="dashicons dashicons-yes text-green-500"></span> <?php esc_html_e('If no fallback image, the site favicon is used', 'schema-markup-generator'); ?></p>
                    </div>
                    <p class="text-xs text-gray-500 mt-4">
                        <?php esc_html_e('This applies to: Product, Article, Course, LearningResource, Event, Recipe, HowTo, Person, and other schema types that require images.', 'schema-markup-generator'); ?>
                    </p>
                    <?php
                }, 'dashicons-info');
                ?>
            </div>

            <?php $this->renderSection(
                __('Performance', 'schema-markup-generator'),
                __('Configure caching and performance settings.', 'schema-markup-generator')
            ); ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
        </div>
        <?php
    }
}

