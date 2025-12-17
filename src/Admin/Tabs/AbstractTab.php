<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Admin\Tabs;

/**
 * Abstract Tab
 *
 * Base class for settings page tabs.
 *
 * @package Metodo\SchemaMarkupGenerator\Admin\Tabs
 * @author  Michele Marri <plugins@metodo.dev>
 */
abstract class AbstractTab
{
    /**
     * Get tab title
     */
    abstract public function getTitle(): string;

    /**
     * Get tab icon (dashicons class)
     */
    abstract public function getIcon(): string;

    /**
     * Render tab content
     */
    abstract public function render(): void;

    /**
     * Get the settings group for this tab
     *
     * Each tab should have its own settings group to avoid conflicts.
     * Override this method to specify the group name.
     *
     * @return string Settings group name (e.g., 'smg_general')
     */
    public function getSettingsGroup(): string
    {
        return '';
    }

    /**
     * Check if this tab supports auto-save
     *
     * When enabled, fields will save automatically when changed
     * without requiring a form submission.
     *
     * @return bool True if auto-save is enabled
     */
    public function isAutoSaveEnabled(): bool
    {
        return false;
    }

    /**
     * Get the option name for auto-save
     *
     * This is the WordPress option key where settings are stored.
     * Only used when isAutoSaveEnabled() returns true.
     *
     * @return string Option name (e.g., 'smg_advanced_settings')
     */
    public function getAutoSaveOptionName(): string
    {
        return '';
    }

    /**
     * Get the options registered by this tab
     *
     * Returns an array of option configurations:
     * [
     *     'option_name' => [
     *         'type' => 'array',
     *         'sanitize_callback' => callable,
     *         'default' => mixed,
     *     ],
     * ]
     *
     * @return array<string, array{type: string, sanitize_callback?: callable, default?: mixed}>
     */
    public function getRegisteredOptions(): array
    {
        return [];
    }

    /**
     * Register tab-specific settings
     *
     * This method is called automatically by SettingsPage.
     * Tabs should override getSettingsGroup() and getRegisteredOptions() instead.
     */
    public function registerSettings(): void
    {
        $group = $this->getSettingsGroup();
        $options = $this->getRegisteredOptions();

        if (empty($group) || empty($options)) {
            return;
        }

        foreach ($options as $optionName => $config) {
            register_setting($group, $optionName, [
                'type' => $config['type'] ?? 'array',
                'sanitize_callback' => $config['sanitize_callback'] ?? null,
                'default' => $config['default'] ?? [],
            ]);
        }
    }

    /**
     * Extract setting key from field name
     *
     * Converts 'smg_advanced_settings[organization_name]' to 'organization_name'
     *
     * @param string $name Full field name
     * @return string The setting key
     */
    protected function extractSettingKey(string $name): string
    {
        if (preg_match('/\[([^\]]+)\]/', $name, $matches)) {
            return $matches[1];
        }
        return $name;
    }

    /**
     * Get auto-save attributes for a field
     *
     * Returns HTML attributes string for auto-save functionality.
     *
     * @param string $name Field name
     * @return string HTML attributes
     */
    protected function getAutoSaveAttributes(string $name): string
    {
        if (!$this->isAutoSaveEnabled()) {
            return '';
        }

        $optionName = $this->getAutoSaveOptionName();
        $settingKey = $this->extractSettingKey($name);

        if (empty($optionName) || empty($settingKey)) {
            return '';
        }

        return sprintf(
            'data-autosave="true" data-option="%s" data-key="%s"',
            esc_attr($optionName),
            esc_attr($settingKey)
        );
    }

    /**
     * Render a section header
     */
    protected function renderSection(string $title, string $description = ''): void
    {
        ?>
        <div class="smg-section">
            <h2 class="smg-section-title"><?php echo esc_html($title); ?></h2>
            <?php if ($description): ?>
                <p class="smg-section-description"><?php echo esc_html($description); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render a card
     */
    protected function renderCard(string $title, callable $content, string $icon = ''): void
    {
        ?>
        <div class="smg-card">
            <div class="smg-card-header">
                <?php if ($icon): ?>
                    <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                <?php endif; ?>
                <h3><?php echo esc_html($title); ?></h3>
            </div>
            <div class="smg-card-body">
                <?php $content(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render a toggle switch
     */
    protected function renderToggle(string $name, bool $checked, string $label, string $description = ''): void
    {
        $autoSaveAttrs = $this->getAutoSaveAttributes($name);
        ?>
        <div class="smg-field smg-field-toggle">
            <label class="smg-toggle">
                <input type="checkbox"
                       name="<?php echo esc_attr($name); ?>"
                       value="1"
                       class="smg-autosave-field"
                       <?php echo $autoSaveAttrs; ?>
                       <?php checked($checked); ?>>
                <span class="smg-toggle-slider"></span>
            </label>
            <div class="smg-field-content">
                <span class="smg-field-label"><?php echo esc_html($label); ?></span>
                <?php if ($description): ?>
                    <span class="smg-field-description"><?php echo esc_html($description); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render a select field
     */
    protected function renderSelect(string $name, array $options, string $selected, string $label, string $description = ''): void
    {
        $autoSaveAttrs = $this->getAutoSaveAttributes($name);
        ?>
        <div class="smg-field smg-field-select">
            <label class="smg-field-label" for="<?php echo esc_attr($name); ?>">
                <?php echo esc_html($label); ?>
            </label>
            <select name="<?php echo esc_attr($name); ?>" 
                    id="<?php echo esc_attr($name); ?>" 
                    class="smg-select smg-autosave-field"
                    <?php echo $autoSaveAttrs; ?>>
                <?php foreach ($options as $value => $optionLabel): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($selected, $value); ?>>
                        <?php echo esc_html($optionLabel); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($description): ?>
                <span class="smg-field-description"><?php echo esc_html($description); ?></span>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render a text field
     */
    protected function renderTextField(string $name, string $value, string $label, string $description = '', string $placeholder = ''): void
    {
        $autoSaveAttrs = $this->getAutoSaveAttributes($name);
        ?>
        <div class="smg-field smg-field-text">
            <label class="smg-field-label" for="<?php echo esc_attr($name); ?>">
                <?php echo esc_html($label); ?>
            </label>
            <input type="text"
                   name="<?php echo esc_attr($name); ?>"
                   id="<?php echo esc_attr($name); ?>"
                   value="<?php echo esc_attr($value); ?>"
                   placeholder="<?php echo esc_attr($placeholder); ?>"
                   class="smg-input smg-autosave-field"
                   <?php echo $autoSaveAttrs; ?>>
            <?php if ($description): ?>
                <span class="smg-field-description"><?php echo esc_html($description); ?></span>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render a number field
     */
    protected function renderNumberField(string $name, int $value, string $label, string $description = '', int $min = 0, int $max = 0): void
    {
        $autoSaveAttrs = $this->getAutoSaveAttributes($name);
        ?>
        <div class="smg-field smg-field-number">
            <label class="smg-field-label" for="<?php echo esc_attr($name); ?>">
                <?php echo esc_html($label); ?>
            </label>
            <input type="number"
                   name="<?php echo esc_attr($name); ?>"
                   id="<?php echo esc_attr($name); ?>"
                   value="<?php echo esc_attr((string) $value); ?>"
                   <?php if ($min > 0): ?>min="<?php echo esc_attr((string) $min); ?>"<?php endif; ?>
                   <?php if ($max > 0): ?>max="<?php echo esc_attr((string) $max); ?>"<?php endif; ?>
                   class="smg-input smg-input-number smg-autosave-field"
                   <?php echo $autoSaveAttrs; ?>>
            <?php if ($description): ?>
                <span class="smg-field-description"><?php echo esc_html($description); ?></span>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render a textarea field
     */
    protected function renderTextarea(string $name, string $value, string $label, string $description = '', int $rows = 5): void
    {
        $autoSaveAttrs = $this->getAutoSaveAttributes($name);
        ?>
        <div class="smg-field smg-field-textarea">
            <label class="smg-field-label" for="<?php echo esc_attr($name); ?>">
                <?php echo esc_html($label); ?>
            </label>
            <textarea name="<?php echo esc_attr($name); ?>"
                      id="<?php echo esc_attr($name); ?>"
                      rows="<?php echo esc_attr((string) $rows); ?>"
                      class="smg-textarea smg-autosave-field"
                      <?php echo $autoSaveAttrs; ?>><?php echo esc_textarea($value); ?></textarea>
            <?php if ($description): ?>
                <span class="smg-field-description"><?php echo esc_html($description); ?></span>
            <?php endif; ?>
        </div>
        <?php
    }
}

