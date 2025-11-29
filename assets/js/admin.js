/**
 * Schema Markup Generator - Admin JavaScript
 *
 * Handles UI interactions for the plugin settings.
 *
 * @package flavor\SchemaMarkupGenerator
 * @author  Michele Marri <info@metodo.dev>
 */

(function($) {
    'use strict';

    const SMGAdmin = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initPreview();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Toggle field mappings
            $(document).on('click', '.smg-toggle-fields', this.toggleFields);

            // Schema type change - update field mappings
            $(document).on('change', '.smg-schema-select', this.onSchemaTypeChange);

            // Refresh preview
            $(document).on('click', '.smg-refresh-preview', this.refreshPreview);

            // Copy schema
            $(document).on('click', '.smg-copy-schema', this.copySchema);

            // Google Rich Results Test link
            $(document).on('click', '#smg-test-google', this.openGoogleTest);

            // Schema.org Validator link
            $(document).on('click', '#smg-validate-schema', this.openSchemaValidator);
        },

        /**
         * Toggle field mappings panel
         */
        toggleFields: function(e) {
            e.preventDefault();
            const $button = $(this);
            const $card = $button.closest('.smg-post-type-card');
            const $fields = $card.find('.smg-post-type-fields');
            const isExpanded = $button.attr('aria-expanded') === 'true';

            $button.attr('aria-expanded', !isExpanded);
            $fields.slideToggle(200);
        },

        /**
         * Handle schema type change
         */
        onSchemaTypeChange: function() {
            const $select = $(this);
            const postType = $select.data('post-type');
            const schemaType = $select.val();

            // Could refresh field mappings via AJAX here
            // For now, page reload is required to see updated mappings
        },

        /**
         * Initialize preview functionality
         */
        initPreview: function() {
            const $preview = $('#smg-schema-preview');
            if ($preview.length && smgAdmin) {
                this.validateCurrentSchema();
            }
        },

        /**
         * Refresh schema preview
         */
        refreshPreview: function(e) {
            e.preventDefault();
            const $button = $(this);
            const $preview = $('#smg-schema-preview');
            const $status = $('#smg-validation-status');
            const postId = $('input[name="smg_post_id"]').val();

            if (!postId) return;

            $button.prop('disabled', true);
            $preview.css('opacity', '0.5');

            $.ajax({
                url: smgAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smg_preview_schema',
                    nonce: smgAdmin.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        $preview.text(response.data.json);
                        SMGAdmin.showValidation(response.data.validation, $status);
                    }
                },
                complete: function() {
                    $button.prop('disabled', false);
                    $preview.css('opacity', '1');
                }
            });
        },

        /**
         * Validate current schema
         */
        validateCurrentSchema: function() {
            const $preview = $('#smg-schema-preview');
            const $status = $('#smg-validation-status');
            const postId = $('input[name="smg_post_id"]').val();

            if (!postId || !$preview.length) return;

            $.ajax({
                url: smgAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'smg_preview_schema',
                    nonce: smgAdmin.nonce,
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        SMGAdmin.showValidation(response.data.validation, $status);
                    }
                }
            });
        },

        /**
         * Show validation status
         */
        showValidation: function(validation, $container) {
            if (!validation) return;

            let html = '';

            if (validation.valid) {
                html = '<div class="smg-validation-status valid">';
                html += '<span class="dashicons dashicons-yes-alt"></span> ';
                html += smgAdmin.strings.valid;
                html += '</div>';
            } else {
                html = '<div class="smg-validation-status invalid">';
                html += '<span class="dashicons dashicons-warning"></span> ';
                html += smgAdmin.strings.invalid;

                if (validation.errors && validation.errors.length) {
                    html += '<ul>';
                    validation.errors.forEach(function(error) {
                        html += '<li>' + error + '</li>';
                    });
                    html += '</ul>';
                }

                html += '</div>';
            }

            if (validation.warnings && validation.warnings.length) {
                html += '<div class="smg-validation-warnings">';
                html += '<strong>Warnings:</strong>';
                html += '<ul>';
                validation.warnings.forEach(function(warning) {
                    html += '<li>' + warning + '</li>';
                });
                html += '</ul>';
                html += '</div>';
            }

            $container.html(html);
        },

        /**
         * Copy schema to clipboard
         */
        copySchema: function(e) {
            e.preventDefault();
            const $preview = $('#smg-schema-preview');
            const schema = $preview.text();

            if (!schema) return;

            navigator.clipboard.writeText(schema).then(function() {
                const $button = $(e.currentTarget);
                const originalText = $button.html();

                $button.html('<span class="dashicons dashicons-yes"></span> ' + smgAdmin.strings.copied);

                setTimeout(function() {
                    $button.html(originalText);
                }, 2000);
            });
        },

        /**
         * Open Google Rich Results Test
         */
        openGoogleTest: function(e) {
            e.preventDefault();
            const url = $('#smg-test-url').val() || window.location.origin;
            const testUrl = 'https://search.google.com/test/rich-results?url=' + encodeURIComponent(url);
            window.open(testUrl, '_blank');
        },

        /**
         * Open Schema.org Validator
         */
        openSchemaValidator: function(e) {
            e.preventDefault();
            const url = $('#smg-validate-url').val() || window.location.origin;
            const testUrl = 'https://validator.schema.org/?url=' + encodeURIComponent(url);
            window.open(testUrl, '_blank');
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        SMGAdmin.init();
    });

})(jQuery);

