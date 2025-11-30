<?php

declare(strict_types=1);

namespace flavor\SchemaMarkupGenerator\Admin\Tabs;

use flavor\SchemaMarkupGenerator\Schema\SchemaFactory;

/**
 * Schema Types Tab
 *
 * Overview and configuration of available schema types.
 *
 * @package flavor\SchemaMarkupGenerator\Admin\Tabs
 * @author  Michele Marri <info@metodo.dev>
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
        <div class="mds-tab-panel" id="tab-schema-types">
            <?php $this->renderSection(
                __('Available Schema Types', 'schema-markup-generator'),
                __('Overview of all supported schema.org types and their use cases.', 'schema-markup-generator')
            ); ?>

            <div class="mds-schema-types-grid">
                <?php foreach ($typesGrouped as $groupName => $types): ?>
                    <div class="mds-schema-group">
                        <h3 class="mds-group-title"><?php echo esc_html($groupName); ?></h3>
                        <div class="mds-schema-list">
                            <?php foreach ($types as $type => $label): ?>
                                <?php
                                $description = $typesWithDescriptions[$type]['description'] ?? '';
                                $schema = $schemaFactory->create($type);
                                $requiredProps = $schema ? $schema->getRequiredProperties() : [];
                                ?>
                                <div class="mds-schema-item">
                                    <div class="mds-schema-header">
                                        <h4><?php echo esc_html($label); ?></h4>
                                        <code><?php echo esc_html($type); ?></code>
                                    </div>
                                    <?php if ($description): ?>
                                        <p class="mds-schema-description"><?php echo esc_html($description); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($requiredProps)): ?>
                                        <div class="mds-schema-props">
                                            <span class="mds-props-label"><?php esc_html_e('Required:', 'schema-markup-generator'); ?></span>
                                            <?php foreach ($requiredProps as $prop): ?>
                                                <span class="mds-prop-badge"><?php echo esc_html($prop); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    <a href="https://schema.org/<?php echo esc_attr($type); ?>"
                                       target="_blank"
                                       rel="noopener"
                                       class="mds-schema-link">
                                        <?php esc_html_e('View on schema.org', 'schema-markup-generator'); ?>
                                        <span class="dashicons dashicons-external"></span>
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mds-extend-notice">
                <span class="dashicons dashicons-plus-alt"></span>
                <div>
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

