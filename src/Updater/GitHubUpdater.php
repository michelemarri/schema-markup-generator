<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Updater;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * GitHub Updater
 *
 * Handles automatic updates from GitHub releases.
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

