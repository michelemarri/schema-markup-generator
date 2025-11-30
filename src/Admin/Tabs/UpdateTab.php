<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Admin\Tabs;

use Metodo\SchemaMarkupGenerator\Updater\GitHubUpdater;
use Metodo\SchemaMarkupGenerator\Security\Encryption;

/**
 * Update Tab
 *
 * Settings for plugin updates and GitHub integration.
 *
 * @package Metodo\SchemaMarkupGenerator\Admin\Tabs
 * @author  Michele Marri <info@metodo.dev>
 */
class UpdateTab extends AbstractTab
{
    public function getTitle(): string
    {
        return __('Update', 'schema-markup-generator');
    }

    public function getIcon(): string
    {
        return 'dashicons-update';
    }

    public function getSettingsGroup(): string
    {
        return 'smg_update';
    }

    public function getRegisteredOptions(): array
    {
        return [
            'smg_update_settings' => [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeUpdateSettings'],
                'default' => [
                    'auto_update' => false,
                ],
            ],
        ];
    }

    /**
     * Sanitize update settings
     */
    public function sanitizeUpdateSettings(?array $input): array
    {
        $input = $input ?? [];
        
        // Get existing settings to preserve encrypted token
        $existing = get_option('smg_update_settings', []);
        if (!is_array($existing)) {
            $existing = [];
        }
        
        $sanitized = [];

        // Handle GitHub token - encrypt before saving
        if (isset($input['github_token'])) {
            $token = sanitize_text_field($input['github_token']);
            
            if (!empty($token)) {
                // Only re-encrypt if it's a new token (not the masked placeholder)
                if ($token !== '••••••••••••••••') {
                    $encryption = new Encryption();
                    $sanitized['github_token_encrypted'] = $encryption->encrypt($token);
                } else {
                    // Keep existing encrypted token
                    $sanitized['github_token_encrypted'] = $existing['github_token_encrypted'] ?? '';
                }
            }
        } else {
            // Preserve existing token if not in input
            if (isset($existing['github_token_encrypted'])) {
                $sanitized['github_token_encrypted'] = $existing['github_token_encrypted'];
            }
        }

        // Auto-update setting
        $sanitized['auto_update'] = !empty($input['auto_update']);

        return $sanitized;
    }

    public function render(): void
    {
        $settings = get_option('smg_update_settings', []);
        $hasToken = !empty($settings['github_token_encrypted']);
        
        // Check if token is defined via constant
        $tokenViaConstant = defined('SMG_GITHUB_TOKEN') && !empty(SMG_GITHUB_TOKEN);

        ?>
        <div class="mds-tab-panel" id="tab-update">
            <?php $this->renderSection(
                __('Plugin Updates', 'schema-markup-generator'),
                __('Configure automatic updates from GitHub repository.', 'schema-markup-generator')
            ); ?>

            <div class="mds-cards-grid">
                <?php
                $this->renderCard(__('Current Version', 'schema-markup-generator'), function () {
                    $this->renderVersionInfo();
                }, 'dashicons-info');
                ?>

                <?php
                $this->renderCard(__('GitHub Authentication', 'schema-markup-generator'), function () use ($settings, $hasToken, $tokenViaConstant) {
                    $this->renderGitHubSettings($settings, $hasToken, $tokenViaConstant);
                }, 'dashicons-lock');
                ?>
            </div>

            <?php $this->renderSection(
                __('Update Settings', 'schema-markup-generator'),
                __('Configure how the plugin handles updates.', 'schema-markup-generator')
            ); ?>

            <div class="mds-cards-grid">
                <?php
                $this->renderCard(__('Auto-Update', 'schema-markup-generator'), function () use ($settings) {
                    $this->renderToggle(
                        'smg_update_settings[auto_update]',
                        $settings['auto_update'] ?? false,
                        __('Enable Auto-Updates', 'schema-markup-generator'),
                        __('Automatically update the plugin when a new version is available.', 'schema-markup-generator')
                    );
                }, 'dashicons-update-alt');
                ?>

                <?php
                $this->renderCard(__('Manual Check', 'schema-markup-generator'), function () {
                    $this->renderManualCheck();
                }, 'dashicons-search');
                ?>
            </div>

            <?php $this->renderSection(
                __('Security Information', 'schema-markup-generator'),
                __('How your GitHub token is protected.', 'schema-markup-generator')
            ); ?>

            <div class="mds-cards-grid mds-cards-grid-single">
                <?php
                $this->renderCard(__('Token Security', 'schema-markup-generator'), function () {
                    $this->renderSecurityInfo();
                }, 'dashicons-shield');
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render version info card content
     */
    private function renderVersionInfo(): void
    {
        ?>
        <div class="mds-version-info">
            <div class="mds-info-grid">
                <div class="mds-info-item">
                    <span class="mds-info-label"><?php esc_html_e('Installed Version', 'schema-markup-generator'); ?></span>
                    <span class="mds-info-value mds-version-badge">
                        <span class="mds-badge mds-badge-primary">v<?php echo esc_html(SMG_VERSION); ?></span>
                    </span>
                </div>
                <div class="mds-info-item">
                    <span class="mds-info-label"><?php esc_html_e('Repository', 'schema-markup-generator'); ?></span>
                    <span class="mds-info-value">
                        <a href="https://github.com/michelemarri/schema-markup-generator" target="_blank" rel="noopener">
                            michelemarri/schema-markup-generator
                            <span class="dashicons dashicons-external"></span>
                        </a>
                    </span>
                </div>
                <div class="mds-info-item">
                    <span class="mds-info-label"><?php esc_html_e('Last Check', 'schema-markup-generator'); ?></span>
                    <span class="mds-info-value">
                        <?php
                        $lastCheck = get_site_transient('update_plugins');
                        if ($lastCheck && isset($lastCheck->last_checked)) {
                            echo esc_html(human_time_diff($lastCheck->last_checked) . ' ' . __('ago', 'schema-markup-generator'));
                        } else {
                            esc_html_e('Never', 'schema-markup-generator');
                        }
                        ?>
                    </span>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render GitHub settings
     */
    private function renderGitHubSettings(array $settings, bool $hasToken, bool $tokenViaConstant): void
    {
        if ($tokenViaConstant) {
            ?>
            <div class="mds-notice mds-notice-info">
                <span class="dashicons dashicons-info"></span>
                <div>
                    <strong><?php esc_html_e('Token configured via wp-config.php', 'schema-markup-generator'); ?></strong>
                    <p><?php esc_html_e('Your GitHub token is defined using the SMG_GITHUB_TOKEN constant. This is the most secure method.', 'schema-markup-generator'); ?></p>
                </div>
            </div>
            <?php
            return;
        }
        ?>

        <div class="mds-field mds-field-text">
            <label class="mds-field-label" for="smg_github_token">
                <?php esc_html_e('GitHub Personal Access Token', 'schema-markup-generator'); ?>
            </label>
            
            <div class="mds-token-input-wrapper">
                <input type="password"
                       name="smg_update_settings[github_token]"
                       id="smg_github_token"
                       value="<?php echo $hasToken ? '••••••••••••••••' : ''; ?>"
                       placeholder="<?php esc_attr_e('ghp_xxxxxxxxxxxxxxxxxxxx', 'schema-markup-generator'); ?>"
                       class="mds-input mds-input-token"
                       autocomplete="off">
                <button type="button" class="mds-btn mds-btn-icon mds-toggle-password" data-target="smg_github_token">
                    <span class="dashicons dashicons-visibility"></span>
                </button>
            </div>

            <?php if ($hasToken): ?>
                <div class="mds-token-status mds-token-status-active">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e('Token configured and encrypted', 'schema-markup-generator'); ?>
                </div>
            <?php endif; ?>

            <span class="mds-field-description">
                <?php
                printf(
                    /* translators: %s: GitHub link */
                    esc_html__('Required for private repositories. %s', 'schema-markup-generator'),
                    '<a href="https://github.com/settings/tokens?type=beta" target="_blank" rel="noopener">' . 
                    esc_html__('Create a token on GitHub', 'schema-markup-generator') . 
                    ' <span class="dashicons dashicons-external"></span></a>'
                );
                ?>
            </span>
        </div>

        <?php if ($hasToken): ?>
        <div class="mds-field">
            <button type="button" class="mds-btn mds-btn-danger mds-btn-sm" id="mds-remove-token">
                <span class="dashicons dashicons-trash"></span>
                <?php esc_html_e('Remove Token', 'schema-markup-generator'); ?>
            </button>
        </div>
        <?php endif;
    }

    /**
     * Render manual check section
     */
    private function renderManualCheck(): void
    {
        ?>
        <p class="mds-info">
            <?php esc_html_e('Force a check for available updates from the GitHub repository.', 'schema-markup-generator'); ?>
        </p>
        
        <button type="button" class="mds-btn mds-btn-secondary" id="mds-check-updates">
            <span class="dashicons dashicons-update"></span>
            <?php esc_html_e('Check for Updates', 'schema-markup-generator'); ?>
        </button>
        
        <div id="mds-update-result" class="mds-update-result" style="display: none;"></div>
        <?php
    }

    /**
     * Render security information
     */
    private function renderSecurityInfo(): void
    {
        ?>
        <div class="mds-security-info">
            <ul class="mds-security-list">
                <li>
                    <span class="dashicons dashicons-yes"></span>
                    <strong><?php esc_html_e('AES-256-CBC Encryption', 'schema-markup-generator'); ?></strong>
                    <span><?php esc_html_e('Your token is encrypted using industry-standard AES-256-CBC encryption before being stored.', 'schema-markup-generator'); ?></span>
                </li>
                <li>
                    <span class="dashicons dashicons-yes"></span>
                    <strong><?php esc_html_e('Unique Encryption Key', 'schema-markup-generator'); ?></strong>
                    <span><?php esc_html_e('The encryption uses your WordPress AUTH_KEY, making it unique to your installation.', 'schema-markup-generator'); ?></span>
                </li>
                <li>
                    <span class="dashicons dashicons-yes"></span>
                    <strong><?php esc_html_e('Never Displayed', 'schema-markup-generator'); ?></strong>
                    <span><?php esc_html_e('The original token is never displayed after being saved. Only a masked placeholder is shown.', 'schema-markup-generator'); ?></span>
                </li>
                <li>
                    <span class="dashicons dashicons-info"></span>
                    <strong><?php esc_html_e('Alternative Method', 'schema-markup-generator'); ?></strong>
                    <span>
                        <?php esc_html_e('For maximum security, you can define the token in wp-config.php:', 'schema-markup-generator'); ?>
                        <code>define('SMG_GITHUB_TOKEN', 'your-token');</code>
                    </span>
                </li>
            </ul>
        </div>
        <?php
    }
}

