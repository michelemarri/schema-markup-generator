<?php

declare(strict_types=1);

namespace Metodo\SchemaMarkupGenerator\Admin\Tabs;

use Metodo\SchemaMarkupGenerator\Tools\Exporter;
use Metodo\SchemaMarkupGenerator\Tools\Importer;

/**
 * Tools Tab
 *
 * Import/export and validation tools.
 *
 * @package Metodo\SchemaMarkupGenerator\Admin\Tabs
 * @author  Michele Marri <plugins@metodo.dev>
 */
class ToolsTab extends AbstractTab
{
    public function getTitle(): string
    {
        return __('Tools', 'schema-markup-generator');
    }

    public function getIcon(): string
    {
        return 'dashicons-admin-tools';
    }

    public function render(): void
    {
        $this->handleExport();
        $this->handleImport();

        ?>
        <div class="smg-tab-panel flex flex-col gap-6" id="tab-tools">
            <?php $this->renderSection(
                __('Import & Export', 'schema-markup-generator'),
                __('Backup your settings or transfer them to another site.', 'schema-markup-generator')
            ); ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php
                $this->renderCard(__('Export Settings', 'schema-markup-generator'), function () {
                    ?>
                    <p><?php esc_html_e('Download all plugin settings as a JSON file.', 'schema-markup-generator'); ?></p>
                    <form method="post" class="flex flex-col gap-4">
                        <?php wp_nonce_field('smg_export', 'smg_export_nonce'); ?>
                        <label class="smg-checkbox">
                            <input type="checkbox" name="include_mappings" value="1" checked>
                            <?php esc_html_e('Include post type mappings', 'schema-markup-generator'); ?>
                        </label>
                        <label class="smg-checkbox">
                            <input type="checkbox" name="include_field_mappings" value="1" checked>
                            <?php esc_html_e('Include field mappings', 'schema-markup-generator'); ?>
                        </label>
                        <button type="submit" name="smg_export" class="smg-btn smg-btn-primary">
                            <span class="dashicons dashicons-download"></span>
                            <?php esc_html_e('Export Settings', 'schema-markup-generator'); ?>
                        </button>
                    </form>
                    <?php
                }, 'dashicons-download');
                ?>

                <?php
                $this->renderCard(__('Import Settings', 'schema-markup-generator'), function () {
                    ?>
                    <p><?php esc_html_e('Upload a previously exported JSON file to restore settings.', 'schema-markup-generator'); ?></p>
                    <form method="post" enctype="multipart/form-data" class="flex flex-col gap-4">
                        <?php wp_nonce_field('smg_import', 'smg_import_nonce'); ?>
                        <div class="smg-file-input">
                            <input type="file" name="import_file" accept=".json" required class="smg-input">
                        </div>
                        <label class="smg-checkbox">
                            <input type="checkbox" name="backup_current" value="1" checked>
                            <?php esc_html_e('Create backup before importing', 'schema-markup-generator'); ?>
                        </label>
                        <label class="smg-checkbox">
                            <input type="checkbox" name="merge_settings" value="1">
                            <?php esc_html_e('Merge with existing settings', 'schema-markup-generator'); ?>
                        </label>
                        <button type="submit" name="smg_import" class="smg-btn smg-btn-secondary">
                            <span class="dashicons dashicons-upload"></span>
                            <?php esc_html_e('Import Settings', 'schema-markup-generator'); ?>
                        </button>
                    </form>
                    <?php
                }, 'dashicons-upload');
                ?>
            </div>

            <?php $this->renderSection(
                __('Validation Tools', 'schema-markup-generator'),
                __('Test and validate your schema markup.', 'schema-markup-generator')
            ); ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php
                $this->renderCard(__('Google Rich Results Test', 'schema-markup-generator'), function () {
                    ?>
                    <p><?php esc_html_e('Test your pages with Google\'s official Rich Results Test tool.', 'schema-markup-generator'); ?></p>
                    <div class="flex flex-wrap items-center gap-3">
                        <input type="url" id="smg-test-url" class="smg-input smg-flex-1" placeholder="<?php esc_attr_e('Enter page URL...', 'schema-markup-generator'); ?>" value="<?php echo esc_url(home_url('/')); ?>">
                        <a href="#" id="smg-test-google" class="smg-btn smg-btn-primary smg-shrink-0" target="_blank">
                            <span class="dashicons dashicons-external"></span>
                            <?php esc_html_e('Test on Google', 'schema-markup-generator'); ?>
                        </a>
                    </div>
                    <?php
                }, 'dashicons-google');
                ?>

                <?php
                $this->renderCard(__('Schema.org Validator', 'schema-markup-generator'), function () {
                    ?>
                    <p><?php esc_html_e('Validate your markup against schema.org specifications.', 'schema-markup-generator'); ?></p>
                    <div class="flex flex-wrap items-center gap-3">
                        <input type="url" id="smg-validate-url" class="smg-input smg-flex-1" placeholder="<?php esc_attr_e('Enter page URL...', 'schema-markup-generator'); ?>" value="<?php echo esc_url(home_url('/')); ?>">
                        <a href="#" id="smg-validate-schema" class="smg-btn smg-btn-secondary smg-shrink-0" target="_blank">
                            <span class="dashicons dashicons-yes-alt"></span>
                            <?php esc_html_e('Validate', 'schema-markup-generator'); ?>
                        </a>
                    </div>
                    <?php
                }, 'dashicons-yes-alt');
                ?>
            </div>

        </div>
        <?php
    }

    /**
     * Handle export request
     */
    private function handleExport(): void
    {
        if (!isset($_POST['smg_export']) || !check_admin_referer('smg_export', 'smg_export_nonce')) {
            return;
        }

        $exporter = new Exporter();
        $data = $exporter->export(
            !empty($_POST['include_mappings']),
            !empty($_POST['include_field_mappings'])
        );

        $filename = 'smg-settings-' . date('Y-m-d-His') . '.json';

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        echo wp_json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Handle import request
     */
    private function handleImport(): void
    {
        if (!isset($_POST['smg_import']) || !check_admin_referer('smg_import', 'smg_import_nonce')) {
            return;
        }

        if (empty($_FILES['import_file']['tmp_name'])) {
            add_settings_error('smg_import', 'no_file', __('Please select a file to import.', 'schema-markup-generator'));
            return;
        }

        $content = file_get_contents($_FILES['import_file']['tmp_name']);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            add_settings_error('smg_import', 'invalid_json', __('Invalid JSON file.', 'schema-markup-generator'));
            return;
        }

        $importer = new Importer();

        // Backup current settings if requested
        if (!empty($_POST['backup_current'])) {
            $exporter = new Exporter();
            $backup = $exporter->export(true, true);
            update_option('smg_settings_backup', $backup);
        }

        $result = $importer->import($data, !empty($_POST['merge_settings']));

        if ($result) {
            add_settings_error('smg_import', 'success', __('Settings imported successfully.', 'schema-markup-generator'), 'success');
        } else {
            add_settings_error('smg_import', 'failed', __('Failed to import settings.', 'schema-markup-generator'));
        }
    }

}

