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
     *
     * Each tab registers its own settings under its own group.
     * This prevents settings from being overwritten when saving different tabs.
     */
    public function registerSettings(): void
    {
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

        $currentTab = $_GET['tab'] ?? 'general';

        // Validate current tab
        if (!isset($this->tabs[$currentTab])) {
            $currentTab = 'general';
        }

        $bannerPath = SMG_PLUGIN_DIR . 'assets/images/banner-1544x500.png';
        $bannerUrl = SMG_PLUGIN_URL . 'assets/images/banner-1544x500.png';
        $hasBanner = file_exists($bannerPath);

        ?>
        <div class="wrap mds-wrap">
            <?php if ($hasBanner): ?>
            <div class="mds-hero">
                <img src="<?php echo esc_url($bannerUrl); ?>" alt="Schema Markup Generator" class="mds-hero-banner">
                <div class="mds-hero-overlay">
                    <h1 class="mds-hero-title">
                        <?php esc_html_e('Schema Markup Generator', 'schema-markup-generator'); ?>
                    </h1>
                    <p class="mds-hero-description">
                        <?php esc_html_e('Automatically generate schema.org structured data for your content, optimized for search engines and LLMs.', 'schema-markup-generator'); ?>
                    </p>
                    <span class="mds-hero-version">v<?php echo esc_html(SMG_VERSION); ?></span>
                </div>
            </div>
            <?php else: ?>
            <h1 class="mds-page-title">
                <span class="mds-logo">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <?php esc_html_e('Schema Markup Generator', 'schema-markup-generator'); ?>
            </h1>
            <p class="mds-description">
                <?php esc_html_e('Automatically generate schema.org structured data for your content, optimized for search engines and LLMs.', 'schema-markup-generator'); ?>
            </p>
            <?php endif; ?>

            <nav class="mds-tabs-nav">
                <?php foreach ($this->tabs as $tabId => $tab): ?>
                    <a href="<?php echo esc_url(admin_url('options-general.php?page=schema-markup-generator&tab=' . $tabId)); ?>"
                       class="mds-tab-link <?php echo $currentTab === $tabId ? 'active' : ''; ?>">
                        <span class="dashicons <?php echo esc_attr($tab->getIcon()); ?>"></span>
                        <?php echo esc_html($tab->getTitle()); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="mds-tab-content">
                <?php
                // Check if the current tab has settings to save
                $settingsGroup = $this->tabs[$currentTab]->getSettingsGroup();
                $hasSettings = !empty($settingsGroup);

                if ($hasSettings):
                ?>
                <form method="post" action="options.php" id="mds-settings-form">
                    <?php
                    // Use the tab's specific settings group
                    settings_fields($settingsGroup);

                    // Render current tab
                    $this->tabs[$currentTab]->render();
                    ?>

                    <div class="mds-actions">
                        <?php submit_button(__('Save Changes', 'schema-markup-generator'), 'primary', 'submit', false); ?>
                    </div>
                </form>
                <?php else: ?>
                <div id="mds-settings-readonly">
                    <?php $this->tabs[$currentTab]->render(); ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="mds-footer">
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

