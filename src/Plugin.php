<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator;

use flavor\SchemaMarkupGenerator\Admin\SettingsPage;
use flavor\SchemaMarkupGenerator\Admin\MetaBox;
use flavor\SchemaMarkupGenerator\Admin\PreviewHandler;
use flavor\SchemaMarkupGenerator\Discovery\PostTypeDiscovery;
use flavor\SchemaMarkupGenerator\Discovery\CustomFieldDiscovery;
use flavor\SchemaMarkupGenerator\Discovery\TaxonomyDiscovery;
use flavor\SchemaMarkupGenerator\Schema\SchemaRenderer;
use flavor\SchemaMarkupGenerator\Schema\SchemaFactory;
use flavor\SchemaMarkupGenerator\Cache\CacheInterface;
use flavor\SchemaMarkupGenerator\Cache\ObjectCache;
use flavor\SchemaMarkupGenerator\Cache\TransientCache;
use flavor\SchemaMarkupGenerator\Integration\RankMathIntegration;
use flavor\SchemaMarkupGenerator\Integration\ACFIntegration;
use flavor\SchemaMarkupGenerator\Rest\SchemaEndpoint;
use flavor\SchemaMarkupGenerator\Logger\Logger;
use flavor\SchemaMarkupGenerator\Updater\GitHubUpdater;

/**
 * Main Plugin Class
 *
 * Bootstraps all plugin components and manages initialization.
 *
 * @package flavor\SchemaMarkupGenerator
 * @author  Michele Marri <info@metodo.dev>
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
    }

    /**
     * Load plugin settings
     */
    private function loadSettings(): void
    {
        $this->settings = get_option('smg_settings', [
            'enabled' => true,
            'output_format' => 'json-ld',
            'debug_mode' => false,
            'cache_enabled' => true,
            'cache_ttl' => 3600,
        ]);
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
            $this->services['preview_handler'] = new PreviewHandler(
                $this->services['schema_renderer']
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

        // Initialize integrations
        $this->services['rankmath_integration']->init();
        $this->services['acf_integration']->init();

        // Initialize updater
        $this->services['updater']->init();

        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', [$this->services['settings_page'], 'addMenuPage']);
            add_action('admin_init', [$this->services['settings_page'], 'registerSettings']);
            add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
            add_action('add_meta_boxes', [$this->services['metabox'], 'register']);
            add_action('save_post', [$this->services['metabox'], 'save'], 10, 2);
            add_action('wp_ajax_smg_preview_schema', [$this->services['preview_handler'], 'handle']);
        }

        // Plugin action links
        add_filter('plugin_action_links_' . SMG_PLUGIN_BASENAME, [$this, 'addActionLinks']);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets(string $hook): void
    {
        // Only load on our settings page and post edit screens
        $screens = ['settings_page_schema-markup-generator', 'post.php', 'post-new.php'];

        if (!in_array($hook, $screens, true)) {
            return;
        }

        wp_enqueue_style(
            'smg-admin',
            SMG_PLUGIN_URL . 'assets/css/admin.css',
            [],
            SMG_VERSION
        );

        wp_enqueue_script(
            'smg-admin',
            SMG_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            SMG_VERSION,
            true
        );

        wp_localize_script('smg-admin', 'smgAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smg_admin_nonce'),
            'strings' => [
                'validating' => __('Validating...', 'schema-markup-generator'),
                'valid' => __('Schema is valid', 'schema-markup-generator'),
                'invalid' => __('Schema has errors', 'schema-markup-generator'),
                'preview' => __('Preview', 'schema-markup-generator'),
                'copied' => __('Copied to clipboard', 'schema-markup-generator'),
            ],
        ]);
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

