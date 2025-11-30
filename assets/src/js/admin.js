/**
 * Schema Markup Generator - Admin JavaScript
 *
 * Modern ES6+ implementation with smooth animations and better UX.
 *
 * @package Metodo\SchemaMarkupGenerator
 * @author  Michele Marri <plugins@metodo.dev>
 */

(function() {
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
                settingsForm: document.getElementById('mds-settings-form'),
                tabsNav: document.querySelector('.mds-tabs-nav'),
                tabContent: document.querySelector('.mds-tab-content'),
                schemaPreview: document.getElementById('mds-schema-preview'),
                validationStatus: document.getElementById('mds-validation-status'),
            };
        },

        /**
         * Bind event handlers
         */
        bindEvents() {
            // Toggle field mappings
            document.addEventListener('click', (e) => {
                if (e.target.closest('.mds-toggle-fields')) {
                    this.handleToggleFields(e);
                }
            });

            // Schema type change
            document.addEventListener('change', (e) => {
                if (e.target.classList.contains('mds-schema-select')) {
                    this.handleSchemaTypeChange(e);
                }
            });

            // Refresh preview
            document.addEventListener('click', (e) => {
                if (e.target.closest('.mds-refresh-preview')) {
                    this.handleRefreshPreview(e);
                }
            });

            // Copy schema
            document.addEventListener('click', (e) => {
                if (e.target.closest('.mds-copy-schema')) {
                    this.handleCopySchema(e);
                }
            });

            // Google test
            document.addEventListener('click', (e) => {
                if (e.target.id === 'mds-test-google' || e.target.closest('#mds-test-google')) {
                    this.handleGoogleTest(e);
                }
            });

            // Schema validator
            document.addEventListener('click', (e) => {
                if (e.target.id === 'mds-validate-schema' || e.target.closest('#mds-validate-schema')) {
                    this.handleSchemaValidator(e);
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
                if (e.target.closest('.mds-toggle input')) {
                    this.animateToggle(e.target);
                }
            });

            // Pages tab: Apply suggestion
            document.addEventListener('click', (e) => {
                if (e.target.closest('.mds-apply-suggestion')) {
                    this.handleApplySuggestion(e);
                }
            });

            // Pages tab: Pagination input
            document.addEventListener('keypress', (e) => {
                if (e.target.classList.contains('mds-pagination-input') && e.key === 'Enter') {
                    this.handlePaginationInput(e);
                }
            });

            // Pages tab: Pagination input blur
            document.addEventListener('change', (e) => {
                if (e.target.classList.contains('mds-pagination-input')) {
                    this.handlePaginationInput(e);
                }
            });

            // Update tab: Toggle password visibility
            document.addEventListener('click', (e) => {
                if (e.target.closest('.mds-toggle-password')) {
                    this.handleTogglePassword(e);
                }
            });

            // Update tab: Remove token
            document.addEventListener('click', (e) => {
                if (e.target.closest('#mds-remove-token')) {
                    this.handleRemoveToken(e);
                }
            });

            // Update tab: Check for updates
            document.addEventListener('click', (e) => {
                if (e.target.closest('#mds-check-updates')) {
                    this.handleCheckUpdates(e);
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
            const cards = document.querySelectorAll('.mds-card, .mds-post-type-card, .mds-integration-card, .mds-step');
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
            const schemaItems = document.querySelectorAll('.mds-schema-item');
            schemaItems.forEach((item, index) => {
                item.style.opacity = '0';
                
                setTimeout(() => {
                    item.style.transition = 'opacity 0.3s ease';
                    item.style.opacity = '1';
                }, 30 * index);
            });

            // Animate page rows in Pages tab
            const pageRows = document.querySelectorAll('.mds-page-row');
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
            const button = e.target.closest('.mds-toggle-fields');
            const card = button.closest('.mds-post-type-card');
            const fields = card.querySelector('.mds-post-type-fields');
            const isExpanded = button.getAttribute('aria-expanded') === 'true';

            button.setAttribute('aria-expanded', !isExpanded);
            
            if (isExpanded) {
                this.slideUp(fields);
            } else {
                this.slideDown(fields);
            }
        },

        /**
         * Handle schema type change
         * 
         * Dynamically loads field mappings when schema type changes
         */
        async handleSchemaTypeChange(e) {
            const select = e.target;
            const postType = select.dataset.postType;
            const schemaType = select.value;
            const card = select.closest('.mds-post-type-card');
            const fieldsContainer = card.querySelector('.mds-field-mappings');
            
            if (!fieldsContainer) return;

            // Update mapped state
            if (schemaType) {
                card.classList.add('mds-mapped');
            } else {
                card.classList.remove('mds-mapped');
            }

            // Show loading state
            fieldsContainer.style.opacity = '0.5';
            fieldsContainer.innerHTML = `
                <div class="mds-loading-fields">
                    <span class="dashicons dashicons-update mds-spin"></span>
                    ${typeof smgAdmin !== 'undefined' && smgAdmin.strings?.loading ? smgAdmin.strings.loading : 'Loading...'}
                </div>
            `;

            try {
                const response = await this.fetchSchemaProperties(postType, schemaType);
                
                if (response.success && response.data.html) {
                    // Update the fields container with new HTML
                    fieldsContainer.innerHTML = response.data.html;
                    fieldsContainer.style.opacity = '1';
                    
                    // Animate the new rows
                    const rows = fieldsContainer.querySelectorAll('.mds-mapping-row');
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
                    const fieldsSection = card.querySelector('.mds-post-type-fields');
                    const toggleButton = card.querySelector('.mds-toggle-fields');
                    
                    if (fieldsSection && fieldsSection.style.display === 'none' && schemaType) {
                        this.slideDown(fieldsSection);
                        if (toggleButton) {
                            toggleButton.setAttribute('aria-expanded', 'true');
                        }
                    }
                } else {
                    fieldsContainer.innerHTML = `
                        <p class="mds-notice">
                            <span class="dashicons dashicons-warning"></span>
                            ${response.data?.message || 'Failed to load schema properties'}
                        </p>
                    `;
                    fieldsContainer.style.opacity = '1';
                }
            } catch (error) {
                console.error('Failed to load schema properties:', error);
                fieldsContainer.innerHTML = `
                    <p class="mds-notice">
                        <span class="dashicons dashicons-warning"></span>
                        Failed to load schema properties. Please try again.
                    </p>
                `;
                fieldsContainer.style.opacity = '1';
            }
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
            const button = e.target.closest('.mds-refresh-preview');
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
                    <div class="mds-validation-status valid mds-animate-fade-in">
                        <span class="dashicons dashicons-yes-alt"></span>
                        ${smgAdmin.strings.valid}
                    </div>
                `;
            } else {
                html = `
                    <div class="mds-validation-status invalid mds-animate-fade-in">
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
                    <div class="mds-validation-warnings mds-animate-fade-in">
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
            const button = e.target.closest('.mds-copy-schema');
            const schema = this.elements.schemaPreview?.textContent;

            if (!schema) return;

            try {
                await navigator.clipboard.writeText(schema);
                
                // Visual feedback
                const originalHtml = button.innerHTML;
                button.innerHTML = '<span class="dashicons dashicons-yes"></span> ' + smgAdmin.strings.copied;
                button.classList.add('mds-btn-success');

                setTimeout(() => {
                    button.innerHTML = originalHtml;
                    button.classList.remove('mds-btn-success');
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
            const urlInput = document.getElementById('mds-test-url');
            const url = urlInput?.value || window.location.origin;
            const testUrl = `https://search.google.com/test/rich-results?url=${encodeURIComponent(url)}`;
            window.open(testUrl, '_blank', 'noopener,noreferrer');
        },

        /**
         * Handle Schema validator
         */
        handleSchemaValidator(e) {
            e.preventDefault();
            const urlInput = document.getElementById('mds-validate-url');
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
            const button = e.target.closest('.mds-apply-suggestion');
            const pageId = button.dataset.pageId;
            const schema = button.dataset.schema;
            const select = document.querySelector(`select[name="smg_page_mappings[${pageId}]"]`);
            
            if (select && schema) {
                select.value = schema;
                
                // Visual feedback
                const row = button.closest('.mds-page-row');
                row.style.transition = 'background-color 0.3s ease';
                row.style.backgroundColor = 'var(--mds-success-50)';
                
                setTimeout(() => {
                    row.style.backgroundColor = '';
                }, 1000);
                
                // Replace button with check mark
                const cell = button.closest('.mds-col-suggestion');
                cell.innerHTML = `
                    <span class="mds-suggestion-applied">
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
            const button = e.target.closest('.mds-toggle-password');
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
                const statusElement = document.querySelector('.mds-token-status');
                if (statusElement) {
                    statusElement.remove();
                }
                
                // Hide remove button
                const removeButton = document.getElementById('mds-remove-token');
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
            const button = e.target.closest('#mds-check-updates');
            const resultDiv = document.getElementById('mds-update-result');
            
            // Add loading state
            button.disabled = true;
            const originalHtml = button.innerHTML;
            button.innerHTML = '<span class="dashicons dashicons-update mds-spin"></span> Checking...';
            
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
                            resultDiv.className = 'mds-update-result mds-result-success';
                            resultDiv.innerHTML = `
                                <span class="dashicons dashicons-yes-alt"></span>
                                New version available: <strong>${data.data.new_version}</strong>
                                <a href="${data.data.update_url}" class="mds-btn mds-btn-sm mds-btn-primary" style="margin-left: 10px;">Update Now</a>
                            `;
                        } else {
                            resultDiv.className = 'mds-update-result mds-result-info';
                            resultDiv.innerHTML = `
                                <span class="dashicons dashicons-yes"></span>
                                You have the latest version installed.
                            `;
                        }
                    } else {
                        resultDiv.className = 'mds-update-result mds-result-error';
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
                    resultDiv.className = 'mds-update-result mds-result-error';
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
            const toggle = input.closest('.mds-toggle');
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
            document.querySelectorAll('.mds-meta-box-panel-header').forEach(header => {
                header.addEventListener('click', () => {
                    const panel = header.closest('.mds-meta-box-panel');
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
         * Show toast notification
         */
        showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `mds-toast mds-toast-${type} mds-animate-slide-in-right`;
            toast.innerHTML = `
                <span class="dashicons dashicons-${type === 'success' ? 'yes-alt' : type === 'error' ? 'warning' : 'info'}"></span>
                ${message}
            `;

            // Create container if doesn't exist
            let container = document.querySelector('.mds-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.className = 'mds-toast-container';
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

