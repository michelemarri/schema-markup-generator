<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Admin;

use flavor\SchemaMarkupGenerator\Discovery\PostTypeDiscovery;
use flavor\SchemaMarkupGenerator\Discovery\CustomFieldDiscovery;
use flavor\SchemaMarkupGenerator\Discovery\TaxonomyDiscovery;
use flavor\SchemaMarkupGenerator\Integration\ACFIntegration;
use flavor\SchemaMarkupGenerator\Admin\Tabs\GeneralTab;
use flavor\SchemaMarkupGenerator\Admin\Tabs\PostTypesTab;
use flavor\SchemaMarkupGenerator\Admin\Tabs\PagesTab;
use flavor\SchemaMarkupGenerator\Admin\Tabs\SchemaTypesTab;
use flavor\SchemaMarkupGenerator\Admin\Tabs\IntegrationsTab;
use flavor\SchemaMarkupGenerator\Admin\Tabs\ToolsTab;
use flavor\SchemaMarkupGenerator\Admin\Tabs\AdvancedTab;
use flavor\SchemaMarkupGenerator\Admin\Tabs\UpdateTab;

/**
 * Settings Page
 *
 * Main plugin settings page with tabbed interface.
 *
 * @package flavor\SchemaMarkupGenerator\Admin
 * @author  Michele Marri <info@metodo.dev>
 */
class SettingsPage
{
    private PostTypeDiscovery $postTypeDiscovery;
    private CustomFieldDiscovery $customFieldDiscovery;
    private TaxonomyDiscovery $taxonomyDiscovery;
    private ACFIntegration $acfIntegration;

    /**
     * Registered tabs
     */
    private array $tabs = [];

    public function __construct(
        PostTypeDiscovery $postTypeDiscovery,
        CustomFieldDiscovery $customFieldDiscovery,
        TaxonomyDiscovery $taxonomyDiscovery,
        ACFIntegration $acfIntegration
    ) {
        $this->postTypeDiscovery = $postTypeDiscovery;
        $this->customFieldDiscovery = $customFieldDiscovery;
        $this->taxonomyDiscovery = $taxonomyDiscovery;
        $this->acfIntegration = $acfIntegration;

        $this->registerTabs();
    }

    /**
     * Register tabs
     */
    private function registerTabs(): void
    {
        $this->tabs = [
            'general' => new GeneralTab(),
            'post-types' => new PostTypesTab(
                $this->postTypeDiscovery,
                $this->customFieldDiscovery,
                $this->taxonomyDiscovery,
                $this->acfIntegration
            ),
            'pages' => new PagesTab(),
            'schema-types' => new SchemaTypesTab(),
            'integrations' => new IntegrationsTab(),
            'tools' => new ToolsTab(),
            'advanced' => new AdvancedTab(),
            'update' => new UpdateTab(),
        ];

        /**
         * Filter registered tabs
         *
         * @param array $tabs Array of tab instances
         */
        $this->tabs = apply_filters('smg_settings_tabs', $this->tabs);
    }

    /**
     * Add menu page
     */
    public function addMenuPage(): void
    {
        add_options_page(
            __('Schema Markup Generator', 'schema-markup-generator'),
            __('Schema Markup', 'schema-markup-generator'),
            'manage_options',
            'schema-markup-generator',
            [$this, 'renderPage']
        );
    }

    /**
     * Register settings
     */
    public function registerSettings(): void
    {
        // Register main settings
        register_setting('smg_settings', 'smg_settings', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitizeSettings'],
        ]);

        register_setting('smg_settings', 'smg_post_type_mappings', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitizePostTypeMappings'],
        ]);

        register_setting('smg_settings', 'smg_field_mappings', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitizeFieldMappings'],
        ]);

        register_setting('smg_settings', 'smg_page_mappings', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitizePageMappings'],
        ]);

        // Let each tab register its settings
        foreach ($this->tabs as $tab) {
            $tab->registerSettings();
        }
    }

    /**
     * Sanitize settings
     *
     * Each tab only updates its own settings, preserving other tabs' values.
     */
    public function sanitizeSettings(?array $input): array
    {
        $input = $input ?? [];

        // Get existing settings to preserve values from other tabs
        $existing = get_option('smg_settings', []);
        if (!is_array($existing)) {
            $existing = [];
        }

        // Start with existing values
        $sanitized = $existing;

        // Get which tab is being saved
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified by settings_fields()
        $currentTab = isset($_POST['smg_current_tab']) ? sanitize_key($_POST['smg_current_tab']) : '';

        // Define which settings belong to which tab
        $tabSettings = $this->getTabSettingsMap();

        // If we know which tab is saving, only update those settings
        if ($currentTab && isset($tabSettings[$currentTab])) {
            foreach ($tabSettings[$currentTab] as $key => $config) {
                $sanitized[$key] = $this->sanitizeSettingValue($key, $input, $config);
            }
        } else {
            // Fallback: sanitize all settings (for backward compatibility)
            foreach ($tabSettings as $settings) {
                foreach ($settings as $key => $config) {
                    $sanitized[$key] = $this->sanitizeSettingValue($key, $input, $config);
                }
            }
        }

        return $sanitized;
    }

    /**
     * Get map of settings per tab
     *
     * @return array<string, array<string, array{type: string, default: mixed}>>
     */
    private function getTabSettingsMap(): array
    {
        return [
            'general' => [
                'enabled' => ['type' => 'bool', 'default' => true],
                'enable_website_schema' => ['type' => 'bool', 'default' => true],
                'enable_breadcrumb_schema' => ['type' => 'bool', 'default' => true],
                'output_format' => ['type' => 'string', 'default' => 'json-ld'],
            ],
            'advanced' => [
                'cache_enabled' => ['type' => 'bool', 'default' => true],
                'cache_ttl' => ['type' => 'int', 'default' => 3600],
                'debug_mode' => ['type' => 'bool', 'default' => false],
            ],
            'integrations' => [
                'rankmath_avoid_duplicates' => ['type' => 'bool', 'default' => true],
                'rankmath_takeover_types' => ['type' => 'array', 'default' => []],
                'integration_rankmath_enabled' => ['type' => 'bool', 'default' => true],
                'integration_acf_enabled' => ['type' => 'bool', 'default' => true],
                'integration_woocommerce_enabled' => ['type' => 'bool', 'default' => true],
                'integration_memberpress_courses_enabled' => ['type' => 'bool', 'default' => true],
                'acf_auto_discover' => ['type' => 'bool', 'default' => true],
                'acf_include_nested' => ['type' => 'bool', 'default' => true],
                'mpcs_auto_parent_course' => ['type' => 'bool', 'default' => true],
                'mpcs_include_curriculum' => ['type' => 'bool', 'default' => false],
                'woo_auto_product' => ['type' => 'bool', 'default' => true],
                'woo_include_reviews' => ['type' => 'bool', 'default' => true],
                'woo_include_offers' => ['type' => 'bool', 'default' => true],
            ],
        ];
    }

    /**
     * Sanitize a single setting value based on its type
     *
     * @param string $key Setting key
     * @param array $input Input data
     * @param array $config Setting configuration
     * @return mixed Sanitized value
     */
    private function sanitizeSettingValue(string $key, array $input, array $config): mixed
    {
        $type = $config['type'];
        $default = $config['default'];

        switch ($type) {
            case 'bool':
                return !empty($input[$key]);

            case 'int':
                return absint($input[$key] ?? $default);

            case 'string':
                return sanitize_text_field($input[$key] ?? $default);

            case 'array':
                if (!isset($input[$key]) || !is_array($input[$key])) {
                    return [];
                }
                return array_map('sanitize_text_field', $input[$key]);

            default:
                return $default;
        }
    }

    /**
     * Sanitize post type mappings
     */
    public function sanitizePostTypeMappings(?array $input): array
    {
        if ($input === null) {
            return [];
        }

        $sanitized = [];

        foreach ($input as $postType => $schemaType) {
            $sanitized[sanitize_key($postType)] = sanitize_text_field($schemaType);
        }

        return $sanitized;
    }

    /**
     * Sanitize field mappings
     */
    public function sanitizeFieldMappings(?array $input): array
    {
        if ($input === null) {
            return [];
        }

        $sanitized = [];

        foreach ($input as $postType => $mappings) {
            if (!is_array($mappings)) {
                continue;
            }

            $sanitized[sanitize_key($postType)] = [];

            foreach ($mappings as $schemaProperty => $fieldKey) {
                $sanitized[sanitize_key($postType)][sanitize_key($schemaProperty)] = sanitize_text_field($fieldKey);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize page mappings
     */
    public function sanitizePageMappings(?array $input): array
    {
        if ($input === null) {
            return [];
        }

        // Get existing mappings to merge with new ones (for pagination support)
        $existing = get_option('smg_page_mappings', []);
        $sanitized = is_array($existing) ? $existing : [];

        foreach ($input as $pageId => $schemaType) {
            $pageId = absint($pageId);
            if ($pageId === 0) {
                continue;
            }

            $schemaType = sanitize_text_field($schemaType);

            // Remove empty mappings, keep valid ones
            if (empty($schemaType)) {
                unset($sanitized[$pageId]);
            } else {
                $sanitized[$pageId] = $schemaType;
            }
        }

        return $sanitized;
    }

    /**
     * Render the settings page
     */
    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $currentTab = $_GET['tab'] ?? 'general';

        // Validate current tab
        if (!isset($this->tabs[$currentTab])) {
            $currentTab = 'general';
        }

        ?>
        <div class="wrap smg-settings-wrap">
            <h1 class="smg-page-title">
                <span class="smg-logo">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <?php esc_html_e('Schema Markup Generator', 'schema-markup-generator'); ?>
            </h1>

            <p class="smg-description">
                <?php esc_html_e('Automatically generate schema.org structured data for your content, optimized for search engines and LLMs.', 'schema-markup-generator'); ?>
            </p>

            <nav class="smg-tabs-nav">
                <?php foreach ($this->tabs as $tabId => $tab): ?>
                    <a href="<?php echo esc_url(admin_url('options-general.php?page=schema-markup-generator&tab=' . $tabId)); ?>"
                       class="smg-tab-link <?php echo $currentTab === $tabId ? 'active' : ''; ?>">
                        <span class="dashicons <?php echo esc_attr($tab->getIcon()); ?>"></span>
                        <?php echo esc_html($tab->getTitle()); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="smg-tab-content">
                <form method="post" action="options.php" id="smg-settings-form">
                    <?php
                    settings_fields('smg_settings');

                    // Add hidden field to identify which tab is being saved
                    ?>
                    <input type="hidden" name="smg_current_tab" value="<?php echo esc_attr($currentTab); ?>">
                    <?php

                    // Render current tab
                    $this->tabs[$currentTab]->render();
                    ?>

                    <div class="smg-actions">
                        <?php submit_button(__('Save Changes', 'schema-markup-generator'), 'primary', 'submit', false); ?>
                    </div>
                </form>
            </div>

            <div class="smg-footer">
                <p>
                    <?php
                    printf(
                        /* translators: %s: Metodo.dev link */
                        esc_html__('Developed by %s', 'schema-markup-generator'),
                        '<a href="https://metodo.dev" target="_blank" rel="noopener">Michele Marri - Metodo.dev</a>'
                    );
                    ?>
                    &bull;
                    <?php
                    printf(
                        /* translators: %s: version number */
                        esc_html__('Version %s', 'schema-markup-generator'),
                        SMG_VERSION
                    );
                    ?>
                </p>
            </div>
        </div>
        <?php
    }
}

