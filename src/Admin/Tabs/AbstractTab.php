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
     * Render a section header
     */
    protected function renderSection(string $title, string $description = ''): void
    {
        ?>
        <div class="mds-section">
            <h2 class="mds-section-title"><?php echo esc_html($title); ?></h2>
            <?php if ($description): ?>
                <p class="mds-section-description"><?php echo esc_html($description); ?></p>
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
        <div class="mds-card">
            <div class="mds-card-header">
                <?php if ($icon): ?>
                    <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                <?php endif; ?>
                <h3><?php echo esc_html($title); ?></h3>
            </div>
            <div class="mds-card-body">
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
        ?>
        <div class="mds-field mds-field-toggle">
            <label class="mds-toggle">
                <input type="checkbox"
                       name="<?php echo esc_attr($name); ?>"
                       value="1"
                       <?php checked($checked); ?>>
                <span class="mds-toggle-slider"></span>
            </label>
            <div class="mds-field-content">
                <span class="mds-field-label"><?php echo esc_html($label); ?></span>
                <?php if ($description): ?>
                    <span class="mds-field-description"><?php echo esc_html($description); ?></span>
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
        ?>
        <div class="mds-field mds-field-select">
            <label class="mds-field-label" for="<?php echo esc_attr($name); ?>">
                <?php echo esc_html($label); ?>
            </label>
            <select name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name); ?>" class="mds-select">
                <?php foreach ($options as $value => $optionLabel): ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($selected, $value); ?>>
                        <?php echo esc_html($optionLabel); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($description): ?>
                <span class="mds-field-description"><?php echo esc_html($description); ?></span>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render a text field
     */
    protected function renderTextField(string $name, string $value, string $label, string $description = '', string $placeholder = ''): void
    {
        ?>
        <div class="mds-field mds-field-text">
            <label class="mds-field-label" for="<?php echo esc_attr($name); ?>">
                <?php echo esc_html($label); ?>
            </label>
            <input type="text"
                   name="<?php echo esc_attr($name); ?>"
                   id="<?php echo esc_attr($name); ?>"
                   value="<?php echo esc_attr($value); ?>"
                   placeholder="<?php echo esc_attr($placeholder); ?>"
                   class="mds-input">
            <?php if ($description): ?>
                <span class="mds-field-description"><?php echo esc_html($description); ?></span>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render a number field
     */
    protected function renderNumberField(string $name, int $value, string $label, string $description = '', int $min = 0, int $max = 0): void
    {
        ?>
        <div class="mds-field mds-field-number">
            <label class="mds-field-label" for="<?php echo esc_attr($name); ?>">
                <?php echo esc_html($label); ?>
            </label>
            <input type="number"
                   name="<?php echo esc_attr($name); ?>"
                   id="<?php echo esc_attr($name); ?>"
                   value="<?php echo esc_attr((string) $value); ?>"
                   <?php if ($min > 0): ?>min="<?php echo esc_attr((string) $min); ?>"<?php endif; ?>
                   <?php if ($max > 0): ?>max="<?php echo esc_attr((string) $max); ?>"<?php endif; ?>
                   class="mds-input mds-input-number">
            <?php if ($description): ?>
                <span class="mds-field-description"><?php echo esc_html($description); ?></span>
            <?php endif; ?>
        </div>
        <?php
    }
}

