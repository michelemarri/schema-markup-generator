<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Admin;

use Metodo\SchemaMarkupGenerator\Discovery\PostTypeDiscovery;
use Metodo\SchemaMarkupGenerator\Discovery\CustomFieldDiscovery;
use Metodo\SchemaMarkupGenerator\Discovery\TaxonomyDiscovery;
use Metodo\SchemaMarkupGenerator\Integration\ACFIntegration;
use Metodo\SchemaMarkupGenerator\Admin\Tabs\GeneralTab;
use Metodo\SchemaMarkupGenerator\Admin\Tabs\PostTypesTab;
use Metodo\SchemaMarkupGenerator\Admin\Tabs\TaxonomiesTab;
use Metodo\SchemaMarkupGenerator\Admin\Tabs\PagesTab;
use Metodo\SchemaMarkupGenerator\Admin\Tabs\SchemaTypesTab;
use Metodo\SchemaMarkupGenerator\Admin\Tabs\IntegrationsTab;
use Metodo\SchemaMarkupGenerator\Admin\Tabs\ToolsTab;
use Metodo\SchemaMarkupGenerator\Admin\Tabs\UpdateTab;
use Metodo\SchemaMarkupGenerator\Admin\Tabs\Settings\GeneralTab as SettingsGeneralTab;
use Metodo\SchemaMarkupGenerator\Admin\Tabs\Settings\OrganizationTab;
use Metodo\SchemaMarkupGenerator\Admin\Tabs\Settings\PerformanceTab;
use Metodo\SchemaMarkupGenerator\Admin\Tabs\Settings\DebugTab;

/**
 * Settings Page
 *
 * Main plugin settings page with tabbed interface.
 *
 * @package Metodo\SchemaMarkupGenerator\Admin
 * @author  Michele Marri <plugins@metodo.dev>
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

    /**
     * Tab groups with sub-tabs
     */
    private array $tabGroups = [];

    /**
     * Whether tabs have been registered
     */
    private bool $tabsRegistered = false;

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

        // Note: registerTabs() is called lazily in ensureTabsRegistered()
        // to avoid loading translations too early (before 'init' hook)
    }

    /**
     * Ensure tabs are registered (lazy loading to avoid early translation calls)
     */
    private function ensureTabsRegistered(): void
    {
        if ($this->tabsRegistered) {
            return;
        }
        $this->tabsRegistered = true;
        $this->registerTabs();
    }

    /**
     * Register tabs
     */
    private function registerTabs(): void
    {
        // Define sub-tabs for the "Schemas" group
        $schemasSubTabs = [
            'schemas-post-types' => new PostTypesTab(
                $this->postTypeDiscovery,
                $this->customFieldDiscovery,
                $this->taxonomyDiscovery,
                $this->acfIntegration
            ),
            'schemas-taxonomies' => new TaxonomiesTab($this->taxonomyDiscovery),
            'schemas-pages' => new PagesTab(),
        ];

        // Define sub-tabs for the "Settings" group
        $settingsSubTabs = [
            'settings-general' => new SettingsGeneralTab(),
            'settings-organization' => new OrganizationTab(),
            'settings-performance' => new PerformanceTab(),
            'settings-debug' => new DebugTab(),
            'settings-update' => new UpdateTab(),
        ];

        // Register tab groups (tabs with sub-tabs)
        $this->tabGroups = [
            'schemas' => [
                'title' => __('Schemas', 'schema-markup-generator'),
                'icon' => 'dashicons-networking',
                'subtabs' => $schemasSubTabs,
                'default' => 'schemas-post-types',
            ],
            'settings' => [
                'title' => __('Settings', 'schema-markup-generator'),
                'icon' => 'dashicons-admin-generic',
                'subtabs' => $settingsSubTabs,
                'default' => 'settings-general',
            ],
        ];

        // Register all tabs (flat structure for settings registration)
        $this->tabs = [
            'general' => new GeneralTab(),
            // Schemas sub-tabs
            'schemas-post-types' => $schemasSubTabs['schemas-post-types'],
            'schemas-taxonomies' => $schemasSubTabs['schemas-taxonomies'],
            'schemas-pages' => $schemasSubTabs['schemas-pages'],
            // Other tabs
            'schema-types' => new SchemaTypesTab(),
            'integrations' => new IntegrationsTab(),
            'tools' => new ToolsTab(),
            // Settings sub-tabs
            'settings-general' => $settingsSubTabs['settings-general'],
            'settings-organization' => $settingsSubTabs['settings-organization'],
            'settings-performance' => $settingsSubTabs['settings-performance'],
            'settings-debug' => $settingsSubTabs['settings-debug'],
            'settings-update' => $settingsSubTabs['settings-update'],
        ];

        /**
         * Filter registered tabs
         *
         * @param array $tabs Array of tab instances
         */
        $this->tabs = apply_filters('smg_settings_tabs', $this->tabs);

        /**
         * Filter tab groups
         *
         * @param array $tabGroups Array of tab group configurations
         */
        $this->tabGroups = apply_filters('smg_settings_tab_groups', $this->tabGroups);
    }

    /**
     * Get the parent tab ID for a given tab
     */
    private function getParentTabId(string $tabId): ?string
    {
        $this->ensureTabsRegistered();
        foreach ($this->tabGroups as $groupId => $group) {
            if (isset($group['subtabs'][$tabId])) {
                return $groupId;
            }
        }
        return null;
    }

    /**
     * Check if a tab ID belongs to a group
     */
    private function isSubTab(string $tabId): bool
    {
        return $this->getParentTabId($tabId) !== null;
    }

    /**
     * Get the active sub-tab for a group
     */
    private function getActiveSubTab(string $groupId, string $currentTab): string
    {
        $this->ensureTabsRegistered();
        if (!isset($this->tabGroups[$groupId])) {
            return '';
        }

        $group = $this->tabGroups[$groupId];
        
        // If current tab is a subtab of this group, return it
        if (isset($group['subtabs'][$currentTab])) {
            return $currentTab;
        }

        // Return default subtab
        return $group['default'] ?? array_key_first($group['subtabs']);
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
     *
     * Each tab registers its own settings under its own group.
     * This prevents settings from being overwritten when saving different tabs.
     */
    public function registerSettings(): void
    {
        $this->ensureTabsRegistered();
        // Let each tab register its settings under its own group
        foreach ($this->tabs as $tab) {
            $tab->registerSettings();
        }
    }

    /**
     * Render the settings page
     */
    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $this->ensureTabsRegistered();

        $currentTab = $_GET['tab'] ?? 'general';

        // Validate current tab
        if (!isset($this->tabs[$currentTab])) {
            $currentTab = 'general';
        }

        // Determine if we're in a tab group
        $currentParentTab = $this->getParentTabId($currentTab);

        $bannerPath = SMG_PLUGIN_DIR . 'assets/images/banner-1544x500.png';
        $bannerUrl = SMG_PLUGIN_URL . 'assets/images/banner-1544x500.png';
        $hasBanner = file_exists($bannerPath);

        ?>
        <div class="wrap smg-wrap">
            <div class="flex flex-col gap-6">
                <?php if ($hasBanner): ?>
                <div class="smg-hero">
                    <img src="<?php echo esc_url($bannerUrl); ?>" alt="Schema Markup Generator" class="smg-hero-banner">
                    <div class="smg-hero-overlay">
                        <h1 class="smg-hero-title">
                            <?php esc_html_e('Schema Markup Generator', 'schema-markup-generator'); ?>
                        </h1>
                        <p class="smg-hero-description">
                            <?php esc_html_e('Automatically generate schema.org structured data for your content, optimized for search engines and LLMs.', 'schema-markup-generator'); ?>
                        </p>
                        <span class="smg-hero-version">v<?php echo esc_html(SMG_VERSION); ?></span>
                    </div>
                </div>
                <?php else: ?>
                <div class="mb-4">
                    <h1 class="flex items-center gap-4 text-3xl font-bold text-gray-900 mb-2">
                        <span class="smg-logo">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <?php esc_html_e('Schema Markup Generator', 'schema-markup-generator'); ?>
                    </h1>
                    <p class="text-lg text-gray-500 max-w-xl">
                        <?php esc_html_e('Automatically generate schema.org structured data for your content, optimized for search engines and LLMs.', 'schema-markup-generator'); ?>
                    </p>
                </div>
                <?php endif; ?>

                <!-- Main Tabs Navigation -->
                <nav class="smg-tabs-nav">
                    <?php $this->renderMainTabsNav($currentTab, $currentParentTab); ?>
                </nav>

                <!-- Sub-tabs Navigation (if applicable) -->
                <?php if ($currentParentTab): ?>
                    <?php $this->renderSubTabsNav($currentParentTab, $currentTab); ?>
                <?php endif; ?>

                <div class="smg-tab-content">
                    <?php
                    // Check if the current tab has settings to save
                    $settingsGroup = $this->tabs[$currentTab]->getSettingsGroup();
                    $hasSettings = !empty($settingsGroup);
                    
                    // Check if auto-save is enabled for this tab
                    $isAutoSave = $this->tabs[$currentTab]->isAutoSaveEnabled();

                    if ($hasSettings):
                    ?>
                    <form method="post" action="options.php" id="smg-settings-form" <?php echo $isAutoSave ? 'data-autosave="true"' : ''; ?>>
                        <?php
                        // Use the tab's specific settings group
                        settings_fields($settingsGroup);

                        // Render current tab
                        $this->tabs[$currentTab]->render();
                        ?>

                        <?php if (!$isAutoSave): ?>
                        <div class="smg-actions">
                            <?php submit_button(__('Save Changes', 'schema-markup-generator'), 'primary', 'submit', false); ?>
                        </div>
                        <?php else: ?>
                        <div class="smg-autosave-indicator" id="smg-autosave-indicator">
                            <span class="dashicons dashicons-saved"></span>
                            <span class="smg-autosave-text"><?php esc_html_e('Changes are saved automatically', 'schema-markup-generator'); ?></span>
                        </div>
                        <?php endif; ?>
                    </form>
                    <?php else: ?>
                    <div id="smg-settings-readonly">
                        <?php $this->tabs[$currentTab]->render(); ?>
                    </div>
                    <?php endif; ?>
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
        </div>
        <?php
    }

    /**
     * Render main tabs navigation
     */
    private function renderMainTabsNav(string $currentTab, ?string $currentParentTab): void
    {
        $this->ensureTabsRegistered();

        // Build the navigation structure
        $navItems = [];

        // Add regular tabs (not in groups)
        foreach ($this->tabs as $tabId => $tab) {
            // Skip if this tab is part of a group
            if ($this->isSubTab($tabId)) {
                continue;
            }

            $navItems[$tabId] = [
                'type' => 'tab',
                'tab' => $tab,
            ];
        }

        // Insert tab groups in their correct position
        $finalNav = [];
        $groupInserted = [];

        // First add 'general'
        if (isset($navItems['general'])) {
            $finalNav['general'] = $navItems['general'];
        }

        // Add 'schemas' group after general
        foreach ($this->tabGroups as $groupId => $group) {
            $finalNav[$groupId] = [
                'type' => 'group',
                'group' => $group,
            ];
        }

        // Add remaining tabs
        foreach ($navItems as $tabId => $item) {
            if ($tabId === 'general') {
                continue;
            }
            $finalNav[$tabId] = $item;
        }

        // Render navigation items
        foreach ($finalNav as $itemId => $item) {
            if ($item['type'] === 'group') {
                $group = $item['group'];
                $isActive = $currentParentTab === $itemId;
                $defaultSubTab = $group['default'] ?? array_key_first($group['subtabs']);
                ?>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=schema-markup-generator&tab=' . $defaultSubTab)); ?>"
                   class="smg-tab-link smg-tab-link-group <?php echo $isActive ? 'active' : ''; ?>">
                    <span class="dashicons <?php echo esc_attr($group['icon']); ?>"></span>
                    <?php echo esc_html($group['title']); ?>
                </a>
                <?php
            } else {
                $tab = $item['tab'];
                $isActive = $currentTab === $itemId && !$currentParentTab;
                ?>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=schema-markup-generator&tab=' . $itemId)); ?>"
                   class="smg-tab-link <?php echo $isActive ? 'active' : ''; ?>">
                    <span class="dashicons <?php echo esc_attr($tab->getIcon()); ?>"></span>
                    <?php echo esc_html($tab->getTitle()); ?>
                </a>
                <?php
            }
        }
    }

    /**
     * Render sub-tabs navigation
     */
    private function renderSubTabsNav(string $groupId, string $currentTab): void
    {
        $this->ensureTabsRegistered();
        if (!isset($this->tabGroups[$groupId])) {
            return;
        }

        $group = $this->tabGroups[$groupId];
        $subtabs = $group['subtabs'];

        ?>
        <nav class="smg-subtabs-nav">
            <?php foreach ($subtabs as $tabId => $tab): ?>
                <a href="<?php echo esc_url(admin_url('options-general.php?page=schema-markup-generator&tab=' . $tabId)); ?>"
                   class="smg-subtab-link <?php echo $currentTab === $tabId ? 'active' : ''; ?>">
                    <span class="dashicons <?php echo esc_attr($tab->getIcon()); ?>"></span>
                    <?php echo esc_html($tab->getTitle()); ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php
    }
}

