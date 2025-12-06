/**
 * Schema Markup Generator - Admin JavaScript
 *
 * Modern ES6+ implementation with smooth animations and better UX.
 *
 * @package Metodo\SchemaMarkupGenerator
 * @author  Michele Marri <plugins@metodo.dev>
 */

(function () {
    'use strict';

    /**
     * SMGAdmin - Main admin module
     */
    const SMGAdmin = {
        /**
         * Configuration
         */
        config: {
            animationDuration: 200,
            toastDuration: 3000,
            debounceDelay: 300,
        },

        /**
         * DOM elements cache
         */
        elements: {},

        /**
         * Initialize the module
         */
        init() {
            this.cacheElements();
            this.bindEvents();
            this.initComponents();
            this.initAnimations();
        },

        /**
         * Cache frequently used DOM elements
         */
        cacheElements() {
            this.elements = {
                settingsForm: document.getElementById('smg-settings-form'),
                tabsNav: document.querySelector('.smg-tabs-nav'),
                tabContent: document.querySelector('.smg-tab-content'),
                schemaPreview: document.getElementById('smg-schema-preview'),
                validationStatus: document.getElementById('smg-validation-status'),
            };
        },

        /**
         * Bind event handlers
         */
        bindEvents() {
            // Toggle field mappings
            document.addEventListener('click', (e) => {
                if (e.target.closest('.smg-toggle-fields')) {
                    this.handleToggleFields(e);
                }
            });

            // Schema type change (auto-save)
            document.addEventListener('change', (e) => {
                if (e.target.classList.contains('smg-schema-select')) {
                    this.handleSchemaTypeChange(e);
                }
            });

            // Field mapping change (auto-save)
            document.addEventListener('change', (e) => {
                if (e.target.classList.contains('smg-field-select')) {
                    this.handleFieldMappingChange(e);
                }
            });

            // Refresh preview
            document.addEventListener('click', (e) => {
                if (e.target.closest('.smg-refresh-preview')) {
                    this.handleRefreshPreview(e);
                }
            });

            // Copy schema
            document.addEventListener('click', (e) => {
                if (e.target.closest('.smg-copy-schema')) {
                    this.handleCopySchema(e);
                }
            });

            // Google test
            document.addEventListener('click', (e) => {
                if (e.target.id === 'smg-test-google' || e.target.closest('#smg-test-google')) {
                    this.handleGoogleTest(e);
                }
            });

            // Schema validator
            document.addEventListener('click', (e) => {
                if (e.target.id === 'smg-validate-schema' || e.target.closest('#smg-validate-schema')) {
                    this.handleSchemaValidator(e);
                }
            });

            // Property name click (open modal)
            document.addEventListener('click', (e) => {
                if (e.target.closest('.smg-property-name')) {
                    this.handlePropertyClick(e);
                }
            });

            // Modal close button
            document.addEventListener('click', (e) => {
                if (e.target.closest('.smg-modal-close') || e.target.classList.contains('smg-modal-overlay')) {
                    this.closePropertyModal();
                }
            });

            // Close modal on Escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.closePropertyModal();
                }
            });

            // Form submit animation
            if (this.elements.settingsForm) {
                this.elements.settingsForm.addEventListener('submit', (e) => {
                    this.handleFormSubmit(e);
                });
            }

            // Toggle switch animation
            document.addEventListener('change', (e) => {
                if (e.target.closest('.smg-toggle input')) {
                    this.animateToggle(e.target);
                }
            });

            // Pages tab: Apply suggestion
            document.addEventListener('click', (e) => {
                if (e.target.closest('.smg-apply-suggestion')) {
                    this.handleApplySuggestion(e);
                }
            });

            // Pages tab: Pagination input
            document.addEventListener('keypress', (e) => {
                if (e.target.classList.contains('smg-pagination-input') && e.key === 'Enter') {
                    this.handlePaginationInput(e);
                }
            });

            // Pages tab: Pagination input blur
            document.addEventListener('change', (e) => {
                if (e.target.classList.contains('smg-pagination-input')) {
                    this.handlePaginationInput(e);
                }
            });

            // Update tab: Toggle password visibility
            document.addEventListener('click', (e) => {
                if (e.target.closest('.smg-toggle-password')) {
                    this.handleTogglePassword(e);
                }
            });

            // Update tab: Remove token
            document.addEventListener('click', (e) => {
                if (e.target.closest('#smg-remove-token')) {
                    this.handleRemoveToken(e);
                }
            });

            // Update tab: Check for updates
            document.addEventListener('click', (e) => {
                if (e.target.closest('#smg-check-updates')) {
                    this.handleCheckUpdates(e);
                }
            });

            // General tab: Select logo
            document.addEventListener('click', (e) => {
                if (e.target.closest('#smg-select-logo')) {
                    this.handleSelectLogo(e);
                }
            });

            // General tab: Remove logo
            document.addEventListener('click', (e) => {
                if (e.target.closest('#smg-remove-logo')) {
                    this.handleRemoveLogo(e);
                }
            });

            // View example button click
            document.addEventListener('click', (e) => {
                if (e.target.closest('.smg-view-example-btn')) {
                    this.handleViewExample(e);
                }
            });

            // Copy example schema
            document.addEventListener('click', (e) => {
                if (e.target.closest('.smg-copy-example')) {
                    this.handleCopyExample(e);
                }
            });

            // Refresh example (new random)
            document.addEventListener('click', (e) => {
                if (e.target.closest('.smg-refresh-example')) {
                    this.handleRefreshExample(e);
                }
            });

            // Close example modal
            document.addEventListener('click', (e) => {
                const modal = document.getElementById('smg-example-modal');
                if (modal && (e.target.closest('.smg-modal-close') || e.target.classList.contains('smg-modal-overlay'))) {
                    if (modal.contains(e.target)) {
                        this.closeExampleModal();
                    }
                }
            });
        },

        /**
         * Initialize UI components
         */
        initComponents() {
            this.initPreview();
            this.initTooltips();
            this.initCollapsibles();
        },

        /**
         * Initialize staggered animations for list items
         */
        initAnimations() {
            // Animate cards on page load
            const cards = document.querySelectorAll('.smg-card, .smg-post-type-card, .smg-integration-card, .smg-step');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(10px)';

                setTimeout(() => {
                    card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 50 * index);
            });

            // Animate schema items
            const schemaItems = document.querySelectorAll('.smg-schema-item');
            schemaItems.forEach((item, index) => {
                item.style.opacity = '0';

                setTimeout(() => {
                    item.style.transition = 'opacity 0.3s ease';
                    item.style.opacity = '1';
                }, 30 * index);
            });

            // Animate page rows in Pages tab
            const pageRows = document.querySelectorAll('.smg-page-row');
            pageRows.forEach((row, index) => {
                row.style.opacity = '0';
                row.style.transform = 'translateX(-10px)';

                setTimeout(() => {
                    row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    row.style.opacity = '1';
                    row.style.transform = 'translateX(0)';
                }, 30 * index);
            });
        },

        /**
         * Handle toggle fields button click
         */
        handleToggleFields(e) {
            e.preventDefault();
            const button = e.target.closest('.smg-toggle-fields');
            const card = button.closest('.smg-post-type-card');
            const fields = card.querySelector('.smg-post-type-fields');
            const isExpanded = button.getAttribute('aria-expanded') === 'true';

            button.setAttribute('aria-expanded', !isExpanded);

            if (isExpanded) {
                this.slideUp(fields);
            } else {
                this.slideDown(fields);
            }
        },

        /**
         * Handle property name click - Opens the property details modal
         */
        handlePropertyClick(e) {
            e.preventDefault();
            const link = e.target.closest('.smg-property-name');

            if (!link) return;

            const propertyName = link.dataset.property;
            const description = link.dataset.description;
            const example = link.dataset.example;
            const schemaUrl = link.dataset.schemaUrl;

            this.openPropertyModal({
                name: propertyName,
                description: description,
                example: example,
                schemaUrl: schemaUrl
            });
        },

        /**
         * Open the property details modal
         */
        openPropertyModal(data) {
            const modal = document.getElementById('smg-property-modal');

            if (!modal) return;

            // Populate modal content
            const titleEl = modal.querySelector('.smg-modal-title');
            const descriptionEl = modal.querySelector('.smg-modal-description');
            const examplesEl = modal.querySelector('.smg-modal-examples');
            const examplesList = modal.querySelector('.smg-examples-list');
            const linkEl = modal.querySelector('.smg-modal-link');

            if (titleEl) {
                titleEl.textContent = data.name || '';
            }

            if (descriptionEl) {
                descriptionEl.textContent = data.description || '';
            }

            // Handle examples
            if (examplesList && data.example) {
                // Split examples by comma and create list items
                const examples = data.example.split(',').map(ex => ex.trim()).filter(ex => ex);

                if (examples.length > 0) {
                    examplesList.innerHTML = examples.map(ex => `<li><code>${this.escapeHtml(ex)}</code></li>`).join('');
                    if (examplesEl) examplesEl.style.display = 'block';
                } else {
                    if (examplesEl) examplesEl.style.display = 'none';
                }
            } else {
                if (examplesEl) examplesEl.style.display = 'none';
            }

            // Handle schema.org link
            if (linkEl) {
                if (data.schemaUrl) {
                    linkEl.href = data.schemaUrl;
                    linkEl.style.display = 'inline-flex';
                } else {
                    linkEl.style.display = 'none';
                }
            }

            // Show modal with animation
            modal.style.display = 'flex';
            modal.offsetHeight; // Force reflow
            modal.classList.add('smg-modal-open');

            // Prevent body scrolling
            document.body.style.overflow = 'hidden';
        },

        /**
         * Close the property details modal
         */
        closePropertyModal() {
            const modal = document.getElementById('smg-property-modal');

            if (!modal || modal.style.display === 'none') return;

            modal.classList.remove('smg-modal-open');

            setTimeout(() => {
                modal.style.display = 'none';
            }, this.config.animationDuration);

            // Restore body scrolling
            document.body.style.overflow = '';
        },

        /**
         * Escape HTML special characters
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Handle schema type change
         * 
         * Dynamically loads field mappings and auto-saves when schema type changes
         */
        async handleSchemaTypeChange(e) {
            const select = e.target;
            const postType = select.dataset.postType;
            const schemaType = select.value;
            const card = select.closest('.smg-post-type-card');
            const fieldsContainer = card.querySelector('.smg-field-mappings');

            if (!fieldsContainer) return;

            // Update mapped state
            if (schemaType) {
                card.classList.add('smg-mapped');
            } else {
                card.classList.remove('smg-mapped');
            }

            // Show loading state on card
            card.classList.add('smg-saving');

            // Show loading state
            fieldsContainer.style.opacity = '0.5';
            fieldsContainer.innerHTML = `
                <div class="smg-loading-fields">
                    <span class="dashicons dashicons-update smg-spin"></span>
                    ${typeof smgAdmin !== 'undefined' && smgAdmin.strings?.loading ? smgAdmin.strings.loading : 'Loading...'}
                </div>
            `;

            try {
                // Auto-save the schema mapping
                await this.saveSchemaMapping(postType, schemaType);

                const response = await this.fetchSchemaProperties(postType, schemaType);

                if (response.success && response.data.html) {
                    // Update the fields container with new HTML
                    fieldsContainer.innerHTML = response.data.html;
                    fieldsContainer.style.opacity = '1';

                    // Animate the new rows
                    const rows = fieldsContainer.querySelectorAll('.smg-mapping-row');
                    rows.forEach((row, index) => {
                        row.style.opacity = '0';
                        row.style.transform = 'translateY(10px)';

                        setTimeout(() => {
                            row.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                            row.style.opacity = '1';
                            row.style.transform = 'translateY(0)';
                        }, 50 * index);
                    });

                    // Auto-expand fields section if collapsed
                    const fieldsSection = card.querySelector('.smg-post-type-fields');
                    const toggleButton = card.querySelector('.smg-toggle-fields');

                    if (fieldsSection && fieldsSection.style.display === 'none' && schemaType) {
                        this.slideDown(fieldsSection);
                        if (toggleButton) {
                            toggleButton.setAttribute('aria-expanded', 'true');
                        }
                    }

                    // Show save success toast notification
                    this.showToast(
                        typeof smgAdmin !== 'undefined' && smgAdmin.strings?.saved 
                            ? smgAdmin.strings.saved 
                            : 'Saved',
                        'success'
                    );
                } else {
                    fieldsContainer.innerHTML = `
                        <p class="smg-notice">
                            <span class="dashicons dashicons-warning"></span>
                            ${response.data?.message || 'Failed to load schema properties'}
                        </p>
                    `;
                    fieldsContainer.style.opacity = '1';
                }
            } catch (error) {
                console.error('Failed to save/load schema:', error);
                fieldsContainer.innerHTML = `
                    <p class="smg-notice">
                        <span class="dashicons dashicons-warning"></span>
                        Failed to save. Please try again.
                    </p>
                `;
                fieldsContainer.style.opacity = '1';
                this.showToast(
                    typeof smgAdmin !== 'undefined' && smgAdmin.strings?.saveFailed 
                        ? smgAdmin.strings.saveFailed 
                        : 'Failed to save',
                    'error'
                );
            } finally {
                card.classList.remove('smg-saving');
            }
        },

        /**
         * Handle field mapping change
         * 
         * Auto-saves field mapping when selection changes
         */
        async handleFieldMappingChange(e) {
            const select = e.target;
            const card = select.closest('.smg-post-type-card');
            const postType = card?.dataset.postType;

            if (!postType) return;

            // Extract property name from select name attribute
            // Format: smg_field_mappings[post_type][property_name]
            const nameMatch = select.name.match(/smg_field_mappings\[([^\]]+)\]\[([^\]]+)\]/);
            if (!nameMatch) return;

            const property = nameMatch[2];
            const fieldKey = select.value;

            // Show saving state
            const row = select.closest('.smg-mapping-row, tr');
            if (row) {
                row.classList.add('smg-saving');
            }

            try {
                await this.saveFieldMapping(postType, property, fieldKey);

                // Show success feedback
                if (row) {
                    row.classList.remove('smg-saving');
                    row.classList.add('smg-saved');
                    setTimeout(() => {
                        row.classList.remove('smg-saved');
                    }, 1500);
                }
                
                // Show toast notification
                this.showToast(
                    typeof smgAdmin !== 'undefined' && smgAdmin.strings?.saved 
                        ? smgAdmin.strings.saved 
                        : 'Saved',
                    'success'
                );
            } catch (error) {
                console.error('Failed to save field mapping:', error);
                if (row) {
                    row.classList.remove('smg-saving');
                }
                this.showToast(
                    typeof smgAdmin !== 'undefined' && smgAdmin.strings?.saveFailed
                        ? smgAdmin.strings.saveFailed
                        : 'Failed to save',
                    'error'
                );
            }
        },

        /**
         * Save schema mapping via AJAX
         */
        saveSchemaMapping(postType, schemaType) {
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('action', 'smg_save_schema_mapping');
                formData.append('nonce', typeof smgAdmin !== 'undefined' ? smgAdmin.nonce : '');
                formData.append('post_type', postType);
                formData.append('schema_type', schemaType);

                fetch(typeof smgAdmin !== 'undefined' ? smgAdmin.ajaxUrl : ajaxurl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            resolve(data);
                        } else {
                            reject(new Error(data.data?.message || 'Save failed'));
                        }
                    })
                    .catch(error => reject(error));
            });
        },

        /**
         * Save field mapping via AJAX
         */
        saveFieldMapping(postType, property, fieldKey) {
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('action', 'smg_save_field_mapping');
                formData.append('nonce', typeof smgAdmin !== 'undefined' ? smgAdmin.nonce : '');
                formData.append('post_type', postType);
                formData.append('property', property);
                formData.append('field_key', fieldKey);

                fetch(typeof smgAdmin !== 'undefined' ? smgAdmin.ajaxUrl : ajaxurl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            resolve(data);
                        } else {
                            reject(new Error(data.data?.message || 'Save failed'));
                        }
                    })
                    .catch(error => reject(error));
            });
        },


        /**
         * Fetch schema properties via AJAX
         */
        fetchSchemaProperties(postType, schemaType) {
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('action', 'smg_get_schema_properties');
                formData.append('nonce', typeof smgAdmin !== 'undefined' ? smgAdmin.nonce : '');
                formData.append('post_type', postType);
                formData.append('schema_type', schemaType);

                fetch(typeof smgAdmin !== 'undefined' ? smgAdmin.ajaxUrl : ajaxurl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                })
                    .then(response => response.json())
                    .then(data => resolve(data))
                    .catch(error => reject(error));
            });
        },

        /**
         * Initialize preview functionality
         */
        initPreview() {
            if (this.elements.schemaPreview && typeof smgAdmin !== 'undefined') {
                this.validateCurrentSchema();
            }
        },

        /**
         * Handle refresh preview
         */
        async handleRefreshPreview(e) {
            e.preventDefault();
            const button = e.target.closest('.smg-refresh-preview');
            const postIdInput = document.querySelector('input[name="smg_post_id"]');

            if (!postIdInput) return;

            const postId = postIdInput.value;

            // Add loading state
            button.disabled = true;
            button.classList.add('loading');
            this.elements.schemaPreview.style.opacity = '0.5';

            try {
                const response = await this.fetchPreview(postId);

                if (response.success) {
                    this.elements.schemaPreview.textContent = response.data.json;
                    this.showValidation(response.data.validation);

                    // Flash animation
                    this.elements.schemaPreview.style.transition = 'opacity 0.3s ease';
                    this.elements.schemaPreview.style.opacity = '1';
                }
            } catch (error) {
                console.error('Preview refresh failed:', error);
                this.showToast('Failed to refresh preview', 'error');
            } finally {
                button.disabled = false;
                button.classList.remove('loading');
            }
        },

        /**
         * Fetch preview via AJAX
         */
        fetchPreview(postId) {
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('action', 'smg_preview_schema');
                formData.append('nonce', smgAdmin.nonce);
                formData.append('post_id', postId);

                fetch(smgAdmin.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                })
                    .then(response => response.json())
                    .then(data => resolve(data))
                    .catch(error => reject(error));
            });
        },

        /**
         * Validate current schema
         */
        async validateCurrentSchema() {
            const postIdInput = document.querySelector('input[name="smg_post_id"]');

            if (!postIdInput || !this.elements.schemaPreview) return;

            try {
                const response = await this.fetchPreview(postIdInput.value);

                if (response.success && response.data.validation) {
                    this.showValidation(response.data.validation);
                }
            } catch (error) {
                console.error('Validation failed:', error);
            }
        },

        /**
         * Show validation status
         */
        showValidation(validation) {
            if (!validation || !this.elements.validationStatus) return;

            let html = '';

            if (validation.valid) {
                html = `
                    <div class="smg-validation-status valid smg-animate-fade-in">
                        <span class="dashicons dashicons-yes-alt"></span>
                        ${smgAdmin.strings.valid}
                    </div>
                `;
            } else {
                html = `
                    <div class="smg-validation-status invalid smg-animate-fade-in">
                        <span class="dashicons dashicons-warning"></span>
                        ${smgAdmin.strings.invalid}
                        ${validation.errors && validation.errors.length ? `
                            <ul>
                                ${validation.errors.map(error => `<li>${error}</li>`).join('')}
                            </ul>
                        ` : ''}
                    </div>
                `;
            }

            if (validation.warnings && validation.warnings.length) {
                html += `
                    <div class="smg-validation-warnings smg-animate-fade-in">
                        <strong>Warnings:</strong>
                        <ul>
                            ${validation.warnings.map(warning => `<li>${warning}</li>`).join('')}
                        </ul>
                    </div>
                `;
            }

            this.elements.validationStatus.innerHTML = html;
        },

        /**
         * Handle copy schema
         */
        async handleCopySchema(e) {
            e.preventDefault();
            const button = e.target.closest('.smg-copy-schema');
            const schema = this.elements.schemaPreview?.textContent;

            if (!schema) return;

            try {
                await navigator.clipboard.writeText(schema);

                // Visual feedback
                const originalHtml = button.innerHTML;
                button.innerHTML = '<span class="dashicons dashicons-yes"></span> ' + smgAdmin.strings.copied;
                button.classList.add('smg-btn-success');

                setTimeout(() => {
                    button.innerHTML = originalHtml;
                    button.classList.remove('smg-btn-success');
                }, 2000);

                this.showToast(smgAdmin.strings.copied, 'success');
            } catch (error) {
                console.error('Copy failed:', error);
                this.showToast('Failed to copy', 'error');
            }
        },

        /**
         * Handle Google test
         */
        handleGoogleTest(e) {
            e.preventDefault();
            const urlInput = document.getElementById('smg-test-url');
            const url = urlInput?.value || window.location.origin;
            const testUrl = `https://search.google.com/test/rich-results?url=${encodeURIComponent(url)}`;
            window.open(testUrl, '_blank', 'noopener,noreferrer');
        },

        /**
         * Handle Schema validator
         */
        handleSchemaValidator(e) {
            e.preventDefault();
            const urlInput = document.getElementById('smg-validate-url');
            const url = urlInput?.value || window.location.origin;
            const testUrl = `https://validator.schema.org/?url=${encodeURIComponent(url)}`;
            window.open(testUrl, '_blank', 'noopener,noreferrer');
        },

        /**
         * Handle form submit
         */
        handleFormSubmit(e) {
            const submitBtn = this.elements.settingsForm.querySelector('[type="submit"]');
            if (submitBtn) {
                submitBtn.classList.add('loading');
            }
        },

        /**
         * Handle apply suggestion button click (Pages tab)
         */
        handleApplySuggestion(e) {
            e.preventDefault();
            const button = e.target.closest('.smg-apply-suggestion');
            const pageId = button.dataset.pageId;
            const schema = button.dataset.schema;
            const select = document.querySelector(`select[name="smg_page_mappings[${pageId}]"]`);

            if (select && schema) {
                select.value = schema;

                // Visual feedback
                const row = button.closest('.smg-page-row');
                row.style.transition = 'background-color 0.3s ease';
                row.style.backgroundColor = 'var(--smg-success-50)';

                setTimeout(() => {
                    row.style.backgroundColor = '';
                }, 1000);

                // Replace button with check mark
                const cell = button.closest('.smg-col-suggestion');
                cell.innerHTML = `
                    <span class="smg-suggestion-applied">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </span>
                `;

                // Trigger change event for any listeners
                select.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },

        /**
         * Handle pagination input (Pages tab)
         */
        handlePaginationInput(e) {
            e.preventDefault();
            const input = e.target;
            const baseUrl = input.dataset.baseUrl;
            const page = parseInt(input.value, 10);
            const max = parseInt(input.max, 10);

            if (page && page >= 1 && page <= max && baseUrl) {
                window.location.href = `${baseUrl}&paged=${page}`;
            }
        },

        /**
         * Handle toggle password visibility (Update tab)
         */
        handleTogglePassword(e) {
            e.preventDefault();
            const button = e.target.closest('.smg-toggle-password');
            const targetId = button.dataset.target;
            const input = document.getElementById(targetId);
            const icon = button.querySelector('.dashicons');

            if (!input) return;

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('dashicons-visibility');
                icon.classList.add('dashicons-hidden');
            } else {
                input.type = 'password';
                icon.classList.remove('dashicons-hidden');
                icon.classList.add('dashicons-visibility');
            }
        },

        /**
         * Handle remove token (Update tab)
         */
        handleRemoveToken(e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to remove the GitHub token?')) {
                return;
            }

            const tokenInput = document.getElementById('smg_github_token');
            if (tokenInput) {
                tokenInput.value = '';

                // Update status
                const statusElement = document.querySelector('.smg-token-status');
                if (statusElement) {
                    statusElement.remove();
                }

                // Hide remove button
                const removeButton = document.getElementById('smg-remove-token');
                if (removeButton) {
                    removeButton.style.display = 'none';
                }

                this.showToast('Token will be removed when you save settings', 'info');
            }
        },

        /**
         * Handle check for updates (Update tab)
         */
        async handleCheckUpdates(e) {
            e.preventDefault();
            const button = e.target.closest('#smg-check-updates');
            const resultDiv = document.getElementById('smg-update-result');

            // Add loading state
            button.disabled = true;
            const originalHtml = button.innerHTML;
            button.innerHTML = '<span class="dashicons dashicons-update smg-spin"></span> Checking...';

            if (resultDiv) {
                resultDiv.style.display = 'none';
            }

            try {
                // Trigger WordPress update check
                const formData = new FormData();
                formData.append('action', 'smg_check_updates');
                formData.append('nonce', typeof smgAdmin !== 'undefined' ? smgAdmin.nonce : '');

                const response = await fetch(typeof smgAdmin !== 'undefined' ? smgAdmin.ajaxUrl : ajaxurl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                });

                const data = await response.json();

                if (resultDiv) {
                    resultDiv.style.display = 'block';

                    if (data.success) {
                        if (data.data.update_available) {
                            resultDiv.className = 'smg-update-result smg-result-success';
                            resultDiv.innerHTML = `
                                <span class="dashicons dashicons-yes-alt"></span>
                                New version available: <strong>${data.data.new_version}</strong>
                                <a href="${data.data.update_url}" class="smg-btn smg-btn-sm smg-btn-primary" style="margin-left: 10px;">Update Now</a>
                            `;
                        } else {
                            resultDiv.className = 'smg-update-result smg-result-info';
                            resultDiv.innerHTML = `
                                <span class="dashicons dashicons-yes"></span>
                                You have the latest version installed.
                            `;
                        }
                    } else {
                        resultDiv.className = 'smg-update-result smg-result-error';
                        resultDiv.innerHTML = `
                            <span class="dashicons dashicons-warning"></span>
                            ${data.data?.message || 'Could not check for updates.'}
                        `;
                    }
                }
            } catch (error) {
                console.error('Update check failed:', error);
                if (resultDiv) {
                    resultDiv.style.display = 'block';
                    resultDiv.className = 'smg-update-result smg-result-error';
                    resultDiv.innerHTML = `
                        <span class="dashicons dashicons-warning"></span>
                        Failed to check for updates. Please try again.
                    `;
                }
            } finally {
                button.disabled = false;
                button.innerHTML = originalHtml;
            }
        },

        /**
         * Animate toggle switch
         */
        animateToggle(input) {
            const toggle = input.closest('.smg-toggle');
            if (toggle) {
                toggle.style.transform = 'scale(0.95)';
                setTimeout(() => {
                    toggle.style.transform = 'scale(1)';
                }, 100);
            }
        },

        /**
         * Initialize tooltips
         */
        initTooltips() {
            // Could implement custom tooltips here
        },

        /**
         * Initialize collapsible panels
         */
        initCollapsibles() {
            document.querySelectorAll('.smg-meta-box-panel-header').forEach(header => {
                header.addEventListener('click', () => {
                    const panel = header.closest('.smg-meta-box-panel');
                    panel.classList.toggle('collapsed');
                });
            });
        },

        /**
         * Slide up animation
         */
        slideUp(element) {
            element.style.height = element.scrollHeight + 'px';
            element.offsetHeight; // Force reflow
            element.style.transition = `height ${this.config.animationDuration}ms ease`;
            element.style.height = '0';
            element.style.overflow = 'hidden';

            setTimeout(() => {
                element.style.display = 'none';
                element.style.height = '';
                element.style.overflow = '';
                element.style.transition = '';
            }, this.config.animationDuration);
        },

        /**
         * Slide down animation
         */
        slideDown(element) {
            element.style.display = 'block';
            element.style.height = '0';
            element.style.overflow = 'hidden';
            element.offsetHeight; // Force reflow

            const height = element.scrollHeight;
            element.style.transition = `height ${this.config.animationDuration}ms ease`;
            element.style.height = height + 'px';

            setTimeout(() => {
                element.style.height = '';
                element.style.overflow = '';
                element.style.transition = '';
            }, this.config.animationDuration);
        },

        /**
         * Handle view example button click
         */
        async handleViewExample(e) {
            e.preventDefault();
            const button = e.target.closest('.smg-view-example-btn');
            const postType = button.dataset.postType;
            const card = button.closest('.smg-post-type-card');
            const schemaSelect = card?.querySelector('.smg-schema-select');
            const schemaType = schemaSelect?.value || '';

            // Store current post type for refresh
            this.currentExamplePostType = postType;
            this.currentExampleSchemaType = schemaType;

            await this.loadAndShowExample(postType, schemaType);
        },

        /**
         * Load and show schema example
         */
        async loadAndShowExample(postType, schemaType) {
            const modal = document.getElementById('smg-example-modal');
            if (!modal) return;

            // Show modal with loading state
            const schemaPreview = modal.querySelector('.smg-example-schema');
            const postTitleEl = modal.querySelector('.smg-example-post-title');
            const editLink = modal.querySelector('.smg-example-edit-link');
            const viewLink = modal.querySelector('.smg-example-view-link');
            const infoEl = modal.querySelector('.smg-example-info');

            schemaPreview.textContent = typeof smgAdmin !== 'undefined' && smgAdmin.strings?.loading
                ? smgAdmin.strings.loading
                : 'Loading...';
            postTitleEl.textContent = '';
            editLink.style.display = 'none';
            viewLink.style.display = 'none';
            infoEl.classList.add('smg-loading');

            // Show modal
            modal.style.display = 'flex';
            modal.offsetHeight; // Force reflow
            modal.classList.add('smg-modal-open');
            document.body.style.overflow = 'hidden';

            try {
                const response = await this.fetchRandomExample(postType, schemaType);

                if (response.success) {
                    schemaPreview.textContent = response.data.json;
                    postTitleEl.textContent = response.data.post_title;
                    
                    if (response.data.edit_url) {
                        editLink.href = response.data.edit_url;
                        editLink.style.display = 'inline-flex';
                    }
                    
                    if (response.data.view_url) {
                        viewLink.href = response.data.view_url;
                        viewLink.style.display = 'inline-flex';
                    }
                } else {
                    schemaPreview.textContent = response.data?.message || 'Failed to load example';
                }
            } catch (error) {
                console.error('Failed to load example:', error);
                schemaPreview.textContent = 'Failed to load example. Please try again.';
            } finally {
                infoEl.classList.remove('smg-loading');
            }
        },

        /**
         * Fetch random example via AJAX
         */
        fetchRandomExample(postType, schemaType) {
            return new Promise((resolve, reject) => {
                const formData = new FormData();
                formData.append('action', 'smg_get_random_example');
                formData.append('nonce', typeof smgAdmin !== 'undefined' ? smgAdmin.nonce : '');
                formData.append('post_type', postType);
                formData.append('schema_type', schemaType);

                fetch(typeof smgAdmin !== 'undefined' ? smgAdmin.ajaxUrl : ajaxurl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                })
                    .then(response => response.json())
                    .then(data => resolve(data))
                    .catch(error => reject(error));
            });
        },

        /**
         * Handle copy example schema
         */
        async handleCopyExample(e) {
            e.preventDefault();
            const button = e.target.closest('.smg-copy-example');
            const modal = document.getElementById('smg-example-modal');
            const schema = modal?.querySelector('.smg-example-schema')?.textContent;

            if (!schema) return;

            try {
                await navigator.clipboard.writeText(schema);

                // Visual feedback
                const originalHtml = button.innerHTML;
                button.innerHTML = '<span class="dashicons dashicons-yes"></span> ' + 
                    (typeof smgAdmin !== 'undefined' && smgAdmin.strings?.copied ? smgAdmin.strings.copied : 'Copied');
                button.classList.add('smg-btn-success');

                setTimeout(() => {
                    button.innerHTML = originalHtml;
                    button.classList.remove('smg-btn-success');
                }, 2000);

                this.showToast(
                    typeof smgAdmin !== 'undefined' && smgAdmin.strings?.copied 
                        ? smgAdmin.strings.copied 
                        : 'Copied to clipboard',
                    'success'
                );
            } catch (error) {
                console.error('Copy failed:', error);
                this.showToast('Failed to copy', 'error');
            }
        },

        /**
         * Handle refresh example (load new random post)
         */
        async handleRefreshExample(e) {
            e.preventDefault();
            const button = e.target.closest('.smg-refresh-example');

            // Add loading state
            button.disabled = true;
            const originalHtml = button.innerHTML;
            button.innerHTML = '<span class="dashicons dashicons-update smg-spin"></span>';

            try {
                await this.loadAndShowExample(this.currentExamplePostType, this.currentExampleSchemaType);
            } finally {
                button.disabled = false;
                button.innerHTML = originalHtml;
            }
        },

        /**
         * Close the example modal
         */
        closeExampleModal() {
            const modal = document.getElementById('smg-example-modal');

            if (!modal || modal.style.display === 'none') return;

            modal.classList.remove('smg-modal-open');

            setTimeout(() => {
                modal.style.display = 'none';
            }, this.config.animationDuration);

            // Restore body scrolling
            document.body.style.overflow = '';
        },

        /**
         * Handle select logo button click
         * Opens WordPress media library to select organization logo
         */
        handleSelectLogo(e) {
            e.preventDefault();

            // Create media frame if it doesn't exist
            if (!this.logoMediaFrame) {
                this.logoMediaFrame = wp.media({
                    title: typeof smgAdmin !== 'undefined' && smgAdmin.strings?.selectLogo
                        ? smgAdmin.strings.selectLogo
                        : 'Select Organization Logo',
                    button: {
                        text: typeof smgAdmin !== 'undefined' && smgAdmin.strings?.useLogo
                            ? smgAdmin.strings.useLogo
                            : 'Use this logo',
                    },
                    library: {
                        type: 'image',
                    },
                    multiple: false,
                });

                // Handle selection
                this.logoMediaFrame.on('select', () => {
                    const attachment = this.logoMediaFrame.state().get('selection').first().toJSON();
                    this.updateLogoPreview(attachment);
                });
            }

            this.logoMediaFrame.open();
        },

        /**
         * Handle remove logo button click
         */
        handleRemoveLogo(e) {
            e.preventDefault();

            const preview = document.getElementById('smg-logo-preview');
            const input = document.getElementById('smg-organization-logo');
            const removeBtn = document.getElementById('smg-remove-logo');

            if (preview) {
                preview.innerHTML = '<span class="smg-no-image text-gray-400">' +
                    (typeof smgAdmin !== 'undefined' && smgAdmin.strings?.noLogo
                        ? smgAdmin.strings.noLogo
                        : 'No logo set') +
                    '</span>';
            }

            if (input) {
                input.value = '';
            }

            if (removeBtn) {
                removeBtn.classList.add('hidden');
            }

            this.showToast(
                typeof smgAdmin !== 'undefined' && smgAdmin.strings?.logoRemoved
                    ? smgAdmin.strings.logoRemoved
                    : 'Logo removed. Save settings to apply.',
                'info'
            );
        },

        /**
         * Update logo preview after selection
         */
        updateLogoPreview(attachment) {
            const preview = document.getElementById('smg-logo-preview');
            const input = document.getElementById('smg-organization-logo');
            const removeBtn = document.getElementById('smg-remove-logo');

            if (preview && attachment.url) {
                // Use thumbnail size if available, otherwise use full
                const url = attachment.sizes?.thumbnail?.url || attachment.url;
                preview.innerHTML = `<img src="${url}" alt="" class="max-h-16 rounded border border-gray-200">`;
            }

            if (input) {
                input.value = attachment.id;
            }

            if (removeBtn) {
                removeBtn.classList.remove('hidden');
            }

            this.showToast(
                typeof smgAdmin !== 'undefined' && smgAdmin.strings?.logoSelected
                    ? smgAdmin.strings.logoSelected
                    : 'Logo selected. Save settings to apply.',
                'success'
            );
        },

        /**
         * Show toast notification
         */
        showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `smg-toast smg-toast-${type} smg-animate-slide-in-right`;
            toast.innerHTML = `
                <span class="dashicons dashicons-${type === 'success' ? 'yes-alt' : type === 'error' ? 'warning' : 'info'}"></span>
                ${message}
            `;

            // Create container if doesn't exist
            let container = document.querySelector('.smg-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'smg-toast-container';
                container.style.cssText = 'position: fixed; top: 50px; right: 20px; z-index: 99999; display: flex; flex-direction: column; gap: 10px;';
                document.body.appendChild(container);
            }

            container.appendChild(toast);

            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(20px)';
                setTimeout(() => toast.remove(), 300);
            }, this.config.toastDuration);
        },

        /**
         * Debounce utility
         */
        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },
    };

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => SMGAdmin.init());
    } else {
        SMGAdmin.init();
    }

    // Expose for external use if needed
    window.SMGAdmin = SMGAdmin;

})();

