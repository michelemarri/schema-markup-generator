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
     * Initialize the updater
     */
    public function init(): void
    {
        // Check if Plugin Update Checker is available
        if (!class_exists('\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
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
}

