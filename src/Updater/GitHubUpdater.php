<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Updater;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
use flavor\SchemaMarkupGenerator\Security\Encryption;

/**
 * GitHub Updater
 *
 * Handles automatic updates from GitHub releases.
 * Supports both public and private repositories.
 *
 * For private repositories, define the GitHub token in wp-config.php:
 * define('SMG_GITHUB_TOKEN', 'your-github-token-here');
 *
 * @package flavor\SchemaMarkupGenerator\Updater
 * @author  Michele Marri <info@metodo.dev>
 */
class GitHubUpdater
{
    /**
     * GitHub repository URL
     */
    private const REPO_URL = 'https://github.com/michelemarri/schema-markup-generator';

    /**
     * Plugin Update Checker instance
     */
    private $updateChecker = null;

    /**
     * Whether the repository requires authentication
     */
    private bool $isPrivateRepo = true;

    /**
     * Whether a valid token is configured
     */
    private bool $hasToken = false;

    /**
     * Initialize the updater
     */
    public function init(): void
    {
        // Check if Plugin Update Checker is available
        if (!class_exists('\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
            return;
        }

        // Check for token first
        $token = $this->getGitHubToken();
        $this->hasToken = !empty($token);

        // If private repo and no token, show admin notice and skip update checker
        if ($this->isPrivateRepo && !$this->hasToken) {
            add_action('admin_notices', [$this, 'showMissingTokenNotice']);
            return;
        }

        $this->updateChecker = PucFactory::buildUpdateChecker(
            self::REPO_URL,
            SMG_PLUGIN_FILE,
            'schema-markup-generator'
        );

        // Configure authentication for private repositories
        $this->configureAuthentication();

        // Set the branch to check for updates (uses releases by default)
        // $this->updateChecker->setBranch('main');

        // Enable release assets (download ZIP from releases)
        $this->updateChecker->getVcsApi()->enableReleaseAssets();

        // Add plugin icons
        $this->updateChecker->addResultFilter(function ($pluginInfo) {
            $iconsPath = SMG_PLUGIN_DIR . 'assets/images/';
            $iconsUrl = SMG_PLUGIN_URL . 'assets/images/';

            // Add icons for the plugin update screen
            $icons = [];
            if (file_exists($iconsPath . 'icon-256x256.png')) {
                $icons['2x'] = $iconsUrl . 'icon-256x256.png';
                $icons['default'] = $iconsUrl . 'icon-256x256.png';
            }
            if (file_exists($iconsPath . 'icon-128x128.png')) {
                $icons['1x'] = $iconsUrl . 'icon-128x128.png';
                if (empty($icons['default'])) {
                    $icons['default'] = $iconsUrl . 'icon-128x128.png';
                }
            }
            if (!empty($icons)) {
                $pluginInfo->icons = $icons;
            }

            // Add banners for plugin details modal (optional)
            $banners = [];
            if (file_exists($iconsPath . 'banner-772x250.png')) {
                $banners['low'] = $iconsUrl . 'banner-772x250.png';
            }
            if (file_exists($iconsPath . 'banner-1544x500.png')) {
                $banners['high'] = $iconsUrl . 'banner-1544x500.png';
            }
            if (!empty($banners)) {
                $pluginInfo->banners = $banners;
            }

            return $pluginInfo;
        });

        /**
         * Action when update checker is initialized
         *
         * @param object $updateChecker The update checker instance
         */
        do_action('smg_update_checker_init', $this->updateChecker);
    }

    /**
     * Configure authentication for private repositories
     *
     * Token can be defined via:
     * 1. SMG_GITHUB_TOKEN constant in wp-config.php
     * 2. smg_github_token filter
     * 3. smg_settings option (github_token field)
     */
    private function configureAuthentication(): void
    {
        $token = $this->getGitHubToken();

        if (!empty($token)) {
            $this->updateChecker->setAuthentication($token);
        }
    }

    /**
     * Get GitHub token from various sources
     * 
     * Priority:
     * 1. SMG_GITHUB_TOKEN constant (most secure - wp-config.php)
     * 2. smg_github_token filter (dynamic retrieval)
     * 3. Encrypted token from plugin settings (database)
     */
    private function getGitHubToken(): ?string
    {
        // 1. Check for constant in wp-config.php (most secure)
        if (defined('SMG_GITHUB_TOKEN') && !empty(SMG_GITHUB_TOKEN)) {
            return SMG_GITHUB_TOKEN;
        }

        // 2. Check for filter (allows dynamic token retrieval)
        $filtered_token = apply_filters('smg_github_token', null);
        if (!empty($filtered_token)) {
            return $filtered_token;
        }

        // 3. Check encrypted token from update settings
        $updateSettings = get_option('smg_update_settings', []);
        if (!empty($updateSettings['github_token_encrypted'])) {
            $encryption = new Encryption();
            $decrypted = $encryption->decrypt($updateSettings['github_token_encrypted']);
            
            if ($decrypted !== false) {
                return $decrypted;
            }
        }

        return null;
    }

    /**
     * Get the update checker instance
     */
    public function getUpdateChecker(): ?object
    {
        return $this->updateChecker;
    }

    /**
     * Check for updates manually
     */
    public function checkForUpdates(): ?object
    {
        if ($this->updateChecker) {
            return $this->updateChecker->checkForUpdates();
        }

        return null;
    }

    /**
     * Get repository URL
     */
    public function getRepoUrl(): string
    {
        return self::REPO_URL;
    }

    /**
     * Check if token is configured
     */
    public function hasToken(): bool
    {
        return $this->hasToken;
    }

    /**
     * Check if repository is private
     */
    public function isPrivateRepo(): bool
    {
        return $this->isPrivateRepo;
    }

    /**
     * Show admin notice when GitHub token is missing for private repository
     */
    public function showMissingTokenNotice(): void
    {
        // Only show on plugins page
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'plugins') {
            return;
        }

        $settings_url = admin_url('admin.php?page=schema-markup-generator&tab=update');
        
        printf(
            '<div class="notice notice-warning is-dismissible"><p><strong>%s:</strong> %s <a href="%s">%s</a></p></div>',
            esc_html__('Schema Markup Generator', 'flavor-schema-markup'),
            esc_html__('Cannot check for updates. This plugin uses a private GitHub repository. Please configure a GitHub Personal Access Token to enable automatic updates.', 'flavor-schema-markup'),
            esc_url($settings_url),
            esc_html__('Configure token →', 'flavor-schema-markup')
        );
    }
}

