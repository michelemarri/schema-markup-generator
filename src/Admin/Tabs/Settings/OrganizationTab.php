<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Admin\Tabs\Settings;

use Metodo\SchemaMarkupGenerator\Admin\Tabs\AbstractTab;

/**
 * Organization Tab
 *
 * Organization settings and fallback image configuration.
 *
 * @package Metodo\SchemaMarkupGenerator\Admin\Tabs\Settings
 * @author  Michele Marri <plugins@metodo.dev>
 */
class OrganizationTab extends AbstractTab
{
    public function getTitle(): string
    {
        return __('Organization', 'schema-markup-generator');
    }

    public function getIcon(): string
    {
        return 'dashicons-building';
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
     * Sanitize ALL advanced settings
     * 
     * This is the central sanitize callback for smg_advanced_settings.
     * Other Settings sub-tabs (Performance, Debug) do NOT register this option
     * to avoid callback conflicts. All fields are sanitized here.
     */
    public function sanitizeSettings(?array $input): array
    {
        $input = $input ?? [];

        // Get existing settings to preserve values from other tabs
        $existing = get_option('smg_advanced_settings', []);
        if (!is_array($existing)) {
            $existing = [];
        }

        return [
            // Organization fields
            'organization_name' => sanitize_text_field($input['organization_name'] ?? $existing['organization_name'] ?? ''),
            'organization_url' => esc_url_raw($input['organization_url'] ?? $existing['organization_url'] ?? ''),
            'organization_logo' => absint($input['organization_logo'] ?? $existing['organization_logo'] ?? 0),
            'fallback_image' => absint($input['fallback_image'] ?? $existing['fallback_image'] ?? 0),
            // Performance fields
            'cache_enabled' => isset($input['cache_enabled']) ? !empty($input['cache_enabled']) : ($existing['cache_enabled'] ?? true),
            'cache_ttl' => absint($input['cache_ttl'] ?? $existing['cache_ttl'] ?? 3600),
            // Debug fields
            'debug_mode' => isset($input['debug_mode']) ? !empty($input['debug_mode']) : ($existing['debug_mode'] ?? false),
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
        <div class="smg-tab-panel flex flex-col gap-6" id="tab-settings-organization">
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
        </div>
        <?php
    }
}

