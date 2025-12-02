<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Admin\Tabs;

use Metodo\SchemaMarkupGenerator\Schema\SchemaFactory;

/**
 * Schema Types Tab
 *
 * Overview and configuration of available schema types.
 *
 * @package Metodo\SchemaMarkupGenerator\Admin\Tabs
 * @author  Michele Marri <plugins@metodo.dev>
 */
class SchemaTypesTab extends AbstractTab
{
    public function getTitle(): string
    {
        return __('Schema Types', 'schema-markup-generator');
    }

    public function getIcon(): string
    {
        return 'dashicons-editor-code';
    }

    public function render(): void
    {
        $schemaFactory = new SchemaFactory();
        $typesGrouped = $schemaFactory->getTypesGrouped();
        $typesWithDescriptions = $schemaFactory->getTypesWithDescriptions();

        ?>
        <div class="smg-tab-panel flex flex-col gap-6" id="tab-schema-types">
            <?php $this->renderSection(
                __('Available Schema Types', 'schema-markup-generator'),
                __('Overview of all supported schema.org types and their use cases.', 'schema-markup-generator')
            ); ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach ($typesGrouped as $groupName => $types): ?>
                    <div class="smg-card">
                        <div class="smg-card-header">
                            <h3><?php echo esc_html($groupName); ?></h3>
                        </div>
                        <div class="smg-card-body flex flex-col gap-4">
                            <?php foreach ($types as $type => $label): ?>
                                <?php
                                $description = $typesWithDescriptions[$type]['description'] ?? '';
                                $schema = $schemaFactory->create($type);
                                $requiredProps = $schema ? $schema->getRequiredProperties() : [];
                                ?>
                                <div class="smg-schema-item">
                                    <div class="smg-flex-between">
                                        <h4><?php echo esc_html($label); ?></h4>
                                        <code><?php echo esc_html($type); ?></code>
                                    </div>
                                    <?php if ($description): ?>
                                        <p class="smg-schema-description"><?php echo esc_html($description); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($requiredProps)): ?>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="smg-props-label"><?php esc_html_e('Required:', 'schema-markup-generator'); ?></span>
                                            <?php foreach ($requiredProps as $prop): ?>
                                                <span class="smg-badge smg-badge-sm"><?php echo esc_html($prop); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <a href="https://schema.org/<?php echo esc_attr($type); ?>"
                                       target="_blank"
                                       rel="noopener"
                                       class="smg-link-external">
                                        <?php esc_html_e('View on schema.org', 'schema-markup-generator'); ?>
                                        <span class="dashicons dashicons-external"></span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="smg-alert smg-alert-info">
                <span class="dashicons dashicons-plus-alt smg-shrink-0"></span>
                <div class="smg-flex-1">
                    <h4><?php esc_html_e('Need a custom schema type?', 'schema-markup-generator'); ?></h4>
                    <p><?php esc_html_e('You can register custom schema types using the smg_register_schema_types filter.', 'schema-markup-generator'); ?></p>
                    <code>add_filter('smg_register_schema_types', function($types) {
    $types['CustomType'] = MyCustomSchema::class;
    return $types;
});</code>
                </div>
            </div>
        </div>
        <?php
    }
}

