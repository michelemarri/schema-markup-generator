<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Admin\Tabs;

/**
 * Abstract Tab
 *
 * Base class for settings page tabs.
 *
 * @package flavor\SchemaMarkupGenerator\Admin\Tabs
 * @author  Michele Marri <info@metodo.dev>
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
     * Register tab-specific settings
     */
    public function registerSettings(): void
    {
        // Override in child classes if needed
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
        ?>
        <div class="smg-field smg-field-toggle">
            <label class="smg-toggle">
                <input type="checkbox"
                       name="<?php echo esc_attr($name); ?>"
                       value="1"
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
        ?>
        <div class="smg-field smg-field-select">
            <label class="smg-field-label" for="<?php echo esc_attr($name); ?>">
                <?php echo esc_html($label); ?>
            </label>
            <select name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name); ?>" class="smg-select">
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
                   class="smg-input">
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
                   class="smg-input smg-input-number">
            <?php if ($description): ?>
                <span class="smg-field-description"><?php echo esc_html($description); ?></span>
            <?php endif; ?>
        </div>
        <?php
    }
}

