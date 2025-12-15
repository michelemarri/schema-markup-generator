<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator;

use Metodo\SchemaMarkupGenerator\Admin\SettingsPage;
use Metodo\SchemaMarkupGenerator\Admin\MetaBox;
use Metodo\SchemaMarkupGenerator\Admin\PreviewHandler;
use Metodo\SchemaMarkupGenerator\Admin\SchemaPropertiesHandler;
use Metodo\SchemaMarkupGenerator\Admin\MetaBoxPropertiesHandler;
use Metodo\SchemaMarkupGenerator\Admin\MappingSaveHandler;
use Metodo\SchemaMarkupGenerator\Admin\RandomExampleHandler;
use Metodo\SchemaMarkupGenerator\Discovery\PostTypeDiscovery;
use Metodo\SchemaMarkupGenerator\Discovery\CustomFieldDiscovery;
use Metodo\SchemaMarkupGenerator\Discovery\TaxonomyDiscovery;
use Metodo\SchemaMarkupGenerator\Schema\SchemaRenderer;
use Metodo\SchemaMarkupGenerator\Schema\SchemaFactory;
use Metodo\SchemaMarkupGenerator\Cache\CacheInterface;
use Metodo\SchemaMarkupGenerator\Cache\ObjectCache;
use Metodo\SchemaMarkupGenerator\Cache\TransientCache;
use Metodo\SchemaMarkupGenerator\Integration\RankMathIntegration;
use Metodo\SchemaMarkupGenerator\Integration\ACFIntegration;
use Metodo\SchemaMarkupGenerator\Integration\MemberPressCoursesIntegration;
use Metodo\SchemaMarkupGenerator\Integration\MemberPressMembershipIntegration;
use Metodo\SchemaMarkupGenerator\Integration\WooCommerceIntegration;
use Metodo\SchemaMarkupGenerator\Integration\YouTubeIntegration;
use Metodo\SchemaMarkupGenerator\Rest\SchemaEndpoint;
use Metodo\SchemaMarkupGenerator\Logger\Logger;
use Metodo\SchemaMarkupGenerator\Updater\GitHubUpdater;

/**
 * Main Plugin Class
 *
 * Bootstraps all plugin components and manages initialization.
 *
 * @package Metodo\SchemaMarkupGenerator
 * @author  Michele Marri <plugins@metodo.dev>
 */
class Plugin
{
    /**
     * Plugin settings
     */
    private array $settings = [];

    /**
     * Service container
     */
    private array $services = [];

    /**
     * Initialize the plugin
     */
    public function init(): void
    {
        $this->loadSettings();
        $this->registerServices();
        $this->registerHooks();

        // Check if plugin was updated and flush cache if needed
        $this->maybeFlushCacheOnUpdate();
    }

    /**
     * Flush cache if plugin was updated
     * 
     * Compares saved version with current version.
     * If different, flushes all schema cache to ensure fresh generation.
     */
    private function maybeFlushCacheOnUpdate(): void
    {
        $savedVersion = get_option('smg_version', '');

        if ($savedVersion !== SMG_VERSION) {
            // Version changed - flush cache
            if (isset($this->services['cache'])) {
                $this->services['cache']->flush();
                $this->services['logger']->info(
                    "Plugin updated from {$savedVersion} to " . SMG_VERSION . " - cache flushed"
                );
            }

            // Update saved version
            update_option('smg_version', SMG_VERSION);
        }
    }

    /**
     * Load plugin settings
     */
    private function loadSettings(): void
    {
        // Merge settings from all sections
        $general = \Metodo\SchemaMarkupGenerator\smg_get_settings('general');
        $advanced = \Metodo\SchemaMarkupGenerator\smg_get_settings('advanced');

        $this->settings = array_merge($general, $advanced);
    }

    /**
     * Register all services
     */
    private function registerServices(): void
    {
        // Logger (initialize first for other services)
        $this->services['logger'] = new Logger(
            SMG_PLUGIN_DIR . 'logs',
            $this->settings['debug_mode'] ?? false
        );

        // Cache
        $this->services['cache'] = $this->createCacheService();

        // Discovery services
        $this->services['post_type_discovery'] = new PostTypeDiscovery();
        $this->services['custom_field_discovery'] = new CustomFieldDiscovery();
        $this->services['taxonomy_discovery'] = new TaxonomyDiscovery();

        // Schema services
        $this->services['schema_factory'] = new SchemaFactory();
        $this->services['schema_renderer'] = new SchemaRenderer(
            $this->services['schema_factory'],
            $this->services['cache'],
            $this->services['logger']
        );

        // Integrations
        $this->services['rankmath_integration'] = new RankMathIntegration();
        $this->services['acf_integration'] = new ACFIntegration();
        $this->services['memberpress_courses_integration'] = new MemberPressCoursesIntegration();
        $this->services['memberpress_membership_integration'] = new MemberPressMembershipIntegration();
        $this->services['woocommerce_integration'] = new WooCommerceIntegration();
        $this->services['youtube_integration'] = new YouTubeIntegration();

        // REST API
        $this->services['rest_endpoint'] = new SchemaEndpoint(
            $this->services['schema_renderer']
        );

        // Updater
        $this->services['updater'] = new GitHubUpdater();

        // Admin (only in admin context)
        if (is_admin()) {
            $this->services['settings_page'] = new SettingsPage(
                $this->services['post_type_discovery'],
                $this->services['custom_field_discovery'],
                $this->services['taxonomy_discovery'],
                $this->services['acf_integration']
            );
            $this->services['metabox'] = new MetaBox(
                $this->services['schema_factory'],
                $this->services['schema_renderer']
            );
            // Inject discovery services for field overrides
            $this->services['metabox']->setDiscoveryServices(
                $this->services['custom_field_discovery'],
                $this->services['taxonomy_discovery']
            );
            $this->services['preview_handler'] = new PreviewHandler(
                $this->services['schema_renderer']
            );
            $this->services['schema_properties_handler'] = new SchemaPropertiesHandler(
                $this->services['schema_factory'],
                $this->services['custom_field_discovery'],
                $this->services['taxonomy_discovery']
            );
            $this->services['metabox_properties_handler'] = new MetaBoxPropertiesHandler(
                $this->services['schema_factory'],
                $this->services['custom_field_discovery'],
                $this->services['taxonomy_discovery']
            );
            $this->services['mapping_save_handler'] = new MappingSaveHandler();
            $this->services['random_example_handler'] = new RandomExampleHandler(
                $this->services['schema_renderer'],
                $this->services['schema_factory']
            );
        }
    }

    /**
     * Create the appropriate cache service
     */
    private function createCacheService(): CacheInterface
    {
        if (!($this->settings['cache_enabled'] ?? true)) {
            return new TransientCache(0); // Disabled cache
        }

        $ttl = (int) ($this->settings['cache_ttl'] ?? 3600);

        // Use object cache if available (Redis/Memcached)
        if (wp_using_ext_object_cache()) {
            return new ObjectCache($ttl);
        }

        // Fallback to transients
        return new TransientCache($ttl);
    }

    /**
     * Initialize integrations based on settings
     *
     * Each integration is only initialized if:
     * 1. The plugin is detected (checked by integration's isAvailable())
     * 2. The integration is enabled in settings (default: true)
     */
    private function initializeIntegrations(): void
    {
        $integrationSettings = \Metodo\SchemaMarkupGenerator\smg_get_settings('integrations');

        // Map of integration service keys to their setting keys
        $integrations = [
            'rankmath_integration' => 'integration_rankmath_enabled',
            'acf_integration' => 'integration_acf_enabled',
            'memberpress_courses_integration' => 'integration_memberpress_courses_enabled',
            'memberpress_membership_integration' => 'integration_memberpress_memberships_enabled',
            'woocommerce_integration' => 'integration_woocommerce_enabled',
            'youtube_integration' => 'integration_youtube_enabled',
        ];

        foreach ($integrations as $serviceKey => $settingKey) {
            // Check if integration is enabled (default to true for backwards compatibility)
            $isEnabled = $integrationSettings[$settingKey] ?? true;

            if ($isEnabled && isset($this->services[$serviceKey])) {
                $this->services[$serviceKey]->init();
            }
        }
    }

    /**
     * Register WordPress hooks
     */
    private function registerHooks(): void
    {
        // Only render if enabled
        if ($this->settings['enabled'] ?? true) {
            // Frontend schema output
            add_action('wp_head', [$this->services['schema_renderer'], 'render'], 99);
        }

        // Cache invalidation on post save
        add_action('save_post', [$this, 'invalidatePostCache'], 10, 2);
        add_action('delete_post', [$this, 'invalidatePostCache']);

        // REST API
        add_action('rest_api_init', [$this->services['rest_endpoint'], 'register']);

        // Initialize integrations (only if enabled in settings)
        $this->initializeIntegrations();

        // Initialize updater
        $this->services['updater']->init();

        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', [$this->services['settings_page'], 'addMenuPage']);
            add_action('admin_init', [$this->services['settings_page'], 'registerSettings']);
            add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
            add_action('admin_enqueue_scripts', [$this, 'deregisterThirdPartyStyles'], 999);
            add_action('add_meta_boxes', [$this->services['metabox'], 'register']);
            add_action('save_post', [$this->services['metabox'], 'save'], 10, 2);
            add_action('wp_ajax_smg_preview_schema', [$this->services['preview_handler'], 'handle']);
            add_action('wp_ajax_smg_check_updates', [$this, 'handleCheckUpdates']);
            add_action('wp_ajax_smg_get_schema_properties', [$this->services['schema_properties_handler'], 'handle']);
            add_action('wp_ajax_smg_save_schema_mapping', [$this->services['mapping_save_handler'], 'handleSaveSchemaMapping']);
            add_action('wp_ajax_smg_save_field_mapping', [$this->services['mapping_save_handler'], 'handleSaveFieldMapping']);
            add_action('wp_ajax_smg_save_taxonomy_mapping', [$this->services['mapping_save_handler'], 'handleSaveTaxonomyMapping']);
            add_action('wp_ajax_smg_save_integration_setting', [$this->services['mapping_save_handler'], 'handleSaveIntegrationSetting']);
            add_action('wp_ajax_smg_get_random_example', [$this->services['random_example_handler'], 'handle']);
            add_action('wp_ajax_smg_get_metabox_properties', [$this->services['metabox_properties_handler'], 'handle']);
        }

        // Plugin action links
        add_filter('plugin_action_links_' . SMG_PLUGIN_BASENAME, [$this, 'addActionLinks']);
    }

    /**
     * Enqueue admin assets
     * 
     * Assets are loaded only on plugin pages (settings page and configured post types)
     * to avoid impacting other admin pages.
     */
    public function enqueueAdminAssets(string $hook): void
    {
        // Check if we're on the settings page
        $isSettingsPage = $hook === 'settings_page_schema-markup-generator';
        
        // Check if we're on a post edit screen with schema enabled
        $isSchemaPostEdit = false;
        if (in_array($hook, ['post.php', 'post-new.php'], true)) {
            $isSchemaPostEdit = $this->isSchemaEnabledForCurrentPost();
        }
        
        // Only load assets on relevant pages
        if (!$isSettingsPage && !$isSchemaPostEdit) {
            return;
        }

        // Load media uploader on settings page for logo selection
        if ($isSettingsPage) {
            wp_enqueue_media();
        }

        // Enqueue Inter font for modern typography
        wp_enqueue_style(
            'smg-fonts',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap',
            [],
            null
        );

        wp_enqueue_style(
            'smg-admin',
            SMG_PLUGIN_URL . 'assets/css/admin.css',
            ['smg-fonts'],
            SMG_VERSION
        );

        wp_enqueue_script(
            'smg-admin',
            SMG_PLUGIN_URL . 'assets/js/admin.js',
            [], // No jQuery dependency - pure ES6
            SMG_VERSION,
            true
        );

        wp_localize_script('smg-admin', 'smgAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smg_admin_nonce'),
            'isSettingsPage' => $isSettingsPage,
            'postTypeMappings' => get_option('smg_post_type_mappings', []),
            'strings' => [
                'validating' => __('Validating...', 'schema-markup-generator'),
                'valid' => __('Schema is valid', 'schema-markup-generator'),
                'invalid' => __('Schema has errors', 'schema-markup-generator'),
                'preview' => __('Preview', 'schema-markup-generator'),
                'copied' => __('Copied to clipboard', 'schema-markup-generator'),
                'copyFailed' => __('Failed to copy', 'schema-markup-generator'),
                'refreshFailed' => __('Failed to refresh preview', 'schema-markup-generator'),
                'loading' => __('Loading...', 'schema-markup-generator'),
                'saved' => __('Saved', 'schema-markup-generator'),
                'saving' => __('Saving...', 'schema-markup-generator'),
                'saveFailed' => __('Failed to save', 'schema-markup-generator'),
                'selectLogo' => __('Select Organization Logo', 'schema-markup-generator'),
                'useLogo' => __('Use this logo', 'schema-markup-generator'),
                'noLogo' => __('No logo set', 'schema-markup-generator'),
                'logoSelected' => __('Logo selected. Save settings to apply.', 'schema-markup-generator'),
                'logoRemoved' => __('Logo removed. Save settings to apply.', 'schema-markup-generator'),
                'show' => __('Show', 'schema-markup-generator'),
                'hide' => __('Hide', 'schema-markup-generator'),
            ],
        ]);
    }

    /**
     * Check if schema is enabled for the current post being edited
     * 
     * Returns true for all public post types where the metabox is shown,
     * so CSS/JS are always loaded when the metabox is visible.
     */
    private function isSchemaEnabledForCurrentPost(): bool
    {
        global $post;
        
        if (!$post) {
            return false;
        }

        // Get all public post types (same logic as MetaBox::register())
        $postTypes = get_post_types(['public' => true], 'names');
        unset($postTypes['attachment']);
        
        // Load assets for all public post types where metabox is shown
        return isset($postTypes[$post->post_type]);
    }

    /**
     * Deregister third-party plugin styles on our settings page
     * 
     * This prevents CSS conflicts from other poorly-coded plugins
     * that load their styles globally instead of only on their pages.
     * Only runs on settings page, not on post edit screens.
     */
    public function deregisterThirdPartyStyles(string $hook): void
    {
        // Only run on our settings page (not post edit screens)
        if ($hook !== 'settings_page_schema-markup-generator') {
            return;
        }

        global $wp_styles;
        
        if (!($wp_styles instanceof \WP_Styles)) {
            return;
        }

        // WordPress core handle prefixes to always keep
        $wpCorePrefixes = [
            'wp-',
            'admin-',
            'common',
            'dashicons',
            'buttons',
            'forms',
            'l10n',
            'list-tables',
            'edit',
            'media',
            'nav-menus',
            'widgets',
            'site-icon',
            'colors',
            'ie',
            'thickbox',
            'farbtastic',
            'jcrop',
            'imgareaselect',
            'jquery-ui',
        ];

        // Our plugin handle prefixes (whitelist)
        $ourPrefixes = [
            'smg-', // Schema Markup Generator
        ];

        /**
         * Filter to add additional style handle prefixes to whitelist
         * 
         * @param array $prefixes Array of handle prefixes to keep
         * @param string $hook Current admin page hook
         */
        $ourPrefixes = apply_filters('smg_allowed_style_prefixes', $ourPrefixes, $hook);

        foreach ($wp_styles->registered as $handle => $style) {
            // Skip inline styles (src = true means inline/no external file)
            $src = $style->src ?? '';
            if ($src === true || $src === '') {
                continue;
            }

            // Auto-detect WordPress core styles by file path
            if (is_string($src) && (str_contains($src, '/wp-admin/') || str_contains($src, '/wp-includes/'))) {
                continue;
            }

            // Skip WordPress core handles by prefix
            $isWpCore = false;
            foreach ($wpCorePrefixes as $prefix) {
                if (str_starts_with($handle, $prefix)) {
                    $isWpCore = true;
                    break;
                }
            }
            if ($isWpCore) {
                continue;
            }

            // Skip our plugin handles
            $isOurs = false;
            foreach ($ourPrefixes as $prefix) {
                if (str_starts_with($handle, $prefix)) {
                    $isOurs = true;
                    break;
                }
            }
            if ($isOurs) {
                continue;
            }

            // Dequeue and deregister third-party styles
            wp_dequeue_style($handle);
            wp_deregister_style($handle);
        }
    }

    /**
     * Invalidate cache for a post
     */
    public function invalidatePostCache(int $postId, ?\WP_Post $post = null): void
    {
        $this->services['cache']->delete('schema_' . $postId);
        $this->services['logger']->debug("Cache invalidated for post {$postId}");
    }

    /**
     * Handle AJAX check for updates
     */
    public function handleCheckUpdates(): void
    {
        check_ajax_referer('smg_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
            return;
        }

        try {
            /** @var GitHubUpdater $updater */
            $updater = $this->services['updater'] ?? null;
            
            if (!$updater) {
                wp_send_json_error(['message' => 'Updater not available']);
                return;
            }

            $updateChecker = $updater->getUpdateChecker();
            
            if (!$updateChecker) {
                wp_send_json_error(['message' => 'Update checker not initialized. Check if the GitHub token is configured.']);
                return;
            }

            // Force check using Plugin Update Checker directly
            // This bypasses any WordPress caching
            $update = $updateChecker->checkForUpdates();
            
            // Also refresh WordPress transient
            delete_site_transient('update_plugins');
            wp_update_plugins();

            if ($update !== null && isset($update->version)) {
                // Compare versions
                if (version_compare($update->version, SMG_VERSION, '>')) {
                    wp_send_json_success([
                        'update_available' => true,
                        'new_version' => $update->version,
                        'current_version' => SMG_VERSION,
                        'update_url' => admin_url('plugins.php'),
                        'download_url' => $update->download_url ?? '',
                    ]);
                } else {
                    wp_send_json_success([
                        'update_available' => false,
                        'current_version' => SMG_VERSION,
                        'latest_version' => $update->version,
                    ]);
                }
            } else {
                // Fallback to WordPress transient check
                $updatePlugins = get_site_transient('update_plugins');
                $pluginFile = SMG_PLUGIN_BASENAME;

                if (isset($updatePlugins->response[$pluginFile])) {
                    $wpUpdate = $updatePlugins->response[$pluginFile];
                    wp_send_json_success([
                        'update_available' => true,
                        'new_version' => $wpUpdate->new_version ?? 'Unknown',
                        'update_url' => admin_url('plugins.php'),
                    ]);
                } else {
                    wp_send_json_success([
                        'update_available' => false,
                        'current_version' => SMG_VERSION,
                    ]);
                }
            }
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Add plugin action links
     */
    public function addActionLinks(array $links): array
    {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('options-general.php?page=schema-markup-generator'),
            __('Settings', 'schema-markup-generator')
        );

        array_unshift($links, $settings_link);

        return $links;
    }

    /**
     * Get a registered service
     */
    public function getService(string $name): ?object
    {
        return $this->services[$name] ?? null;
    }

    /**
     * Get plugin settings
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * Get a specific setting
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }
}

