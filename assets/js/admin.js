(() => {
  // assets/src/js/admin.js
  (function() {
    "use strict";
    const SMGAdmin = {
      /**
       * Configuration
       */
      config: {
        animationDuration: 200,
        toastDuration: 3e3,
        debounceDelay: 300
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
          settingsForm: document.getElementById("smg-settings-form"),
          tabsNav: document.querySelector(".smg-tabs-nav"),
          tabContent: document.querySelector(".smg-tab-content"),
          schemaPreview: document.getElementById("smg-schema-preview"),
          validationStatus: document.getElementById("smg-validation-status")
        };
      },
      /**
       * Bind event handlers
       */
      bindEvents() {
        document.addEventListener("click", (e) => {
          if (e.target.closest(".smg-toggle-fields")) {
            this.handleToggleFields(e);
          }
        });
        document.addEventListener("change", (e) => {
          if (e.target.classList.contains("smg-schema-select")) {
            this.handleSchemaTypeChange(e);
          }
        });
        document.addEventListener("click", (e) => {
          if (e.target.closest(".smg-refresh-preview")) {
            this.handleRefreshPreview(e);
          }
        });
        document.addEventListener("click", (e) => {
          if (e.target.closest(".smg-copy-schema")) {
            this.handleCopySchema(e);
          }
        });
        document.addEventListener("click", (e) => {
          if (e.target.id === "smg-test-google" || e.target.closest("#smg-test-google")) {
            this.handleGoogleTest(e);
          }
        });
        document.addEventListener("click", (e) => {
          if (e.target.id === "smg-validate-schema" || e.target.closest("#smg-validate-schema")) {
            this.handleSchemaValidator(e);
          }
        });
        if (this.elements.settingsForm) {
          this.elements.settingsForm.addEventListener("submit", (e) => {
            this.handleFormSubmit(e);
          });
        }
        document.addEventListener("change", (e) => {
          if (e.target.closest(".smg-toggle input")) {
            this.animateToggle(e.target);
          }
        });
        document.addEventListener("click", (e) => {
          if (e.target.closest(".smg-apply-suggestion")) {
            this.handleApplySuggestion(e);
          }
        });
        document.addEventListener("keypress", (e) => {
          if (e.target.classList.contains("smg-pagination-input") && e.key === "Enter") {
            this.handlePaginationInput(e);
          }
        });
        document.addEventListener("change", (e) => {
          if (e.target.classList.contains("smg-pagination-input")) {
            this.handlePaginationInput(e);
          }
        });
        document.addEventListener("click", (e) => {
          if (e.target.closest(".smg-toggle-password")) {
            this.handleTogglePassword(e);
          }
        });
        document.addEventListener("click", (e) => {
          if (e.target.closest("#smg-remove-token")) {
            this.handleRemoveToken(e);
          }
        });
        document.addEventListener("click", (e) => {
          if (e.target.closest("#smg-check-updates")) {
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
        const cards = document.querySelectorAll(".smg-card, .smg-post-type-card, .smg-integration-card, .smg-step");
        cards.forEach((card, index) => {
          card.style.opacity = "0";
          card.style.transform = "translateY(10px)";
          setTimeout(() => {
            card.style.transition = "opacity 0.3s ease, transform 0.3s ease";
            card.style.opacity = "1";
            card.style.transform = "translateY(0)";
          }, 50 * index);
        });
        const schemaItems = document.querySelectorAll(".smg-schema-item");
        schemaItems.forEach((item, index) => {
          item.style.opacity = "0";
          setTimeout(() => {
            item.style.transition = "opacity 0.3s ease";
            item.style.opacity = "1";
          }, 30 * index);
        });
        const pageRows = document.querySelectorAll(".smg-page-row");
        pageRows.forEach((row, index) => {
          row.style.opacity = "0";
          row.style.transform = "translateX(-10px)";
          setTimeout(() => {
            row.style.transition = "opacity 0.3s ease, transform 0.3s ease";
            row.style.opacity = "1";
            row.style.transform = "translateX(0)";
          }, 30 * index);
        });
      },
      /**
       * Handle toggle fields button click
       */
      handleToggleFields(e) {
        e.preventDefault();
        const button = e.target.closest(".smg-toggle-fields");
        const card = button.closest(".smg-post-type-card");
        const fields = card.querySelector(".smg-post-type-fields");
        const isExpanded = button.getAttribute("aria-expanded") === "true";
        button.setAttribute("aria-expanded", !isExpanded);
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
        const card = select.closest(".smg-post-type-card");
        const fieldsContainer = card.querySelector(".smg-field-mappings");
        if (!fieldsContainer)
          return;
        if (schemaType) {
          card.classList.add("smg-mapped");
        } else {
          card.classList.remove("smg-mapped");
        }
        fieldsContainer.style.opacity = "0.5";
        fieldsContainer.innerHTML = `
                <div class="smg-loading-fields">
                    <span class="dashicons dashicons-update smg-spin"></span>
                    ${typeof smgAdmin !== "undefined" && smgAdmin.strings?.loading ? smgAdmin.strings.loading : "Loading..."}
                </div>
            `;
        try {
          const response = await this.fetchSchemaProperties(postType, schemaType);
          if (response.success && response.data.html) {
            fieldsContainer.innerHTML = response.data.html;
            fieldsContainer.style.opacity = "1";
            const rows = fieldsContainer.querySelectorAll(".smg-mapping-row");
            rows.forEach((row, index) => {
              row.style.opacity = "0";
              row.style.transform = "translateY(10px)";
              setTimeout(() => {
                row.style.transition = "opacity 0.3s ease, transform 0.3s ease";
                row.style.opacity = "1";
                row.style.transform = "translateY(0)";
              }, 50 * index);
            });
            const fieldsSection = card.querySelector(".smg-post-type-fields");
            const toggleButton = card.querySelector(".smg-toggle-fields");
            if (fieldsSection && fieldsSection.style.display === "none" && schemaType) {
              this.slideDown(fieldsSection);
              if (toggleButton) {
                toggleButton.setAttribute("aria-expanded", "true");
              }
            }
          } else {
            fieldsContainer.innerHTML = `
                        <p class="smg-notice">
                            <span class="dashicons dashicons-warning"></span>
                            ${response.data?.message || "Failed to load schema properties"}
                        </p>
                    `;
            fieldsContainer.style.opacity = "1";
          }
        } catch (error) {
          console.error("Failed to load schema properties:", error);
          fieldsContainer.innerHTML = `
                    <p class="smg-notice">
                        <span class="dashicons dashicons-warning"></span>
                        Failed to load schema properties. Please try again.
                    </p>
                `;
          fieldsContainer.style.opacity = "1";
        }
      },
      /**
       * Fetch schema properties via AJAX
       */
      fetchSchemaProperties(postType, schemaType) {
        return new Promise((resolve, reject) => {
          const formData = new FormData();
          formData.append("action", "smg_get_schema_properties");
          formData.append("nonce", typeof smgAdmin !== "undefined" ? smgAdmin.nonce : "");
          formData.append("post_type", postType);
          formData.append("schema_type", schemaType);
          fetch(typeof smgAdmin !== "undefined" ? smgAdmin.ajaxUrl : ajaxurl, {
            method: "POST",
            body: formData,
            credentials: "same-origin"
          }).then((response) => response.json()).then((data) => resolve(data)).catch((error) => reject(error));
        });
      },
      /**
       * Initialize preview functionality
       */
      initPreview() {
        if (this.elements.schemaPreview && typeof smgAdmin !== "undefined") {
          this.validateCurrentSchema();
        }
      },
      /**
       * Handle refresh preview
       */
      async handleRefreshPreview(e) {
        e.preventDefault();
        const button = e.target.closest(".smg-refresh-preview");
        const postIdInput = document.querySelector('input[name="smg_post_id"]');
        if (!postIdInput)
          return;
        const postId = postIdInput.value;
        button.disabled = true;
        button.classList.add("loading");
        this.elements.schemaPreview.style.opacity = "0.5";
        try {
          const response = await this.fetchPreview(postId);
          if (response.success) {
            this.elements.schemaPreview.textContent = response.data.json;
            this.showValidation(response.data.validation);
            this.elements.schemaPreview.style.transition = "opacity 0.3s ease";
            this.elements.schemaPreview.style.opacity = "1";
          }
        } catch (error) {
          console.error("Preview refresh failed:", error);
          this.showToast("Failed to refresh preview", "error");
        } finally {
          button.disabled = false;
          button.classList.remove("loading");
        }
      },
      /**
       * Fetch preview via AJAX
       */
      fetchPreview(postId) {
        return new Promise((resolve, reject) => {
          const formData = new FormData();
          formData.append("action", "smg_preview_schema");
          formData.append("nonce", smgAdmin.nonce);
          formData.append("post_id", postId);
          fetch(smgAdmin.ajaxUrl, {
            method: "POST",
            body: formData,
            credentials: "same-origin"
          }).then((response) => response.json()).then((data) => resolve(data)).catch((error) => reject(error));
        });
      },
      /**
       * Validate current schema
       */
      async validateCurrentSchema() {
        const postIdInput = document.querySelector('input[name="smg_post_id"]');
        if (!postIdInput || !this.elements.schemaPreview)
          return;
        try {
          const response = await this.fetchPreview(postIdInput.value);
          if (response.success && response.data.validation) {
            this.showValidation(response.data.validation);
          }
        } catch (error) {
          console.error("Validation failed:", error);
        }
      },
      /**
       * Show validation status
       */
      showValidation(validation) {
        if (!validation || !this.elements.validationStatus)
          return;
        let html = "";
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
                                ${validation.errors.map((error) => `<li>${error}</li>`).join("")}
                            </ul>
                        ` : ""}
                    </div>
                `;
        }
        if (validation.warnings && validation.warnings.length) {
          html += `
                    <div class="smg-validation-warnings smg-animate-fade-in">
                        <strong>Warnings:</strong>
                        <ul>
                            ${validation.warnings.map((warning) => `<li>${warning}</li>`).join("")}
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
        const button = e.target.closest(".smg-copy-schema");
        const schema = this.elements.schemaPreview?.textContent;
        if (!schema)
          return;
        try {
          await navigator.clipboard.writeText(schema);
          const originalHtml = button.innerHTML;
          button.innerHTML = '<span class="dashicons dashicons-yes"></span> ' + smgAdmin.strings.copied;
          button.classList.add("smg-btn-success");
          setTimeout(() => {
            button.innerHTML = originalHtml;
            button.classList.remove("smg-btn-success");
          }, 2e3);
          this.showToast(smgAdmin.strings.copied, "success");
        } catch (error) {
          console.error("Copy failed:", error);
          this.showToast("Failed to copy", "error");
        }
      },
      /**
       * Handle Google test
       */
      handleGoogleTest(e) {
        e.preventDefault();
        const urlInput = document.getElementById("smg-test-url");
        const url = urlInput?.value || window.location.origin;
        const testUrl = `https://search.google.com/test/rich-results?url=${encodeURIComponent(url)}`;
        window.open(testUrl, "_blank", "noopener,noreferrer");
      },
      /**
       * Handle Schema validator
       */
      handleSchemaValidator(e) {
        e.preventDefault();
        const urlInput = document.getElementById("smg-validate-url");
        const url = urlInput?.value || window.location.origin;
        const testUrl = `https://validator.schema.org/?url=${encodeURIComponent(url)}`;
        window.open(testUrl, "_blank", "noopener,noreferrer");
      },
      /**
       * Handle form submit
       */
      handleFormSubmit(e) {
        const submitBtn = this.elements.settingsForm.querySelector('[type="submit"]');
        if (submitBtn) {
          submitBtn.classList.add("loading");
        }
      },
      /**
       * Handle apply suggestion button click (Pages tab)
       */
      handleApplySuggestion(e) {
        e.preventDefault();
        const button = e.target.closest(".smg-apply-suggestion");
        const pageId = button.dataset.pageId;
        const schema = button.dataset.schema;
        const select = document.querySelector(`select[name="smg_page_mappings[${pageId}]"]`);
        if (select && schema) {
          select.value = schema;
          const row = button.closest(".smg-page-row");
          row.style.transition = "background-color 0.3s ease";
          row.style.backgroundColor = "var(--smg-success-50)";
          setTimeout(() => {
            row.style.backgroundColor = "";
          }, 1e3);
          const cell = button.closest(".smg-col-suggestion");
          cell.innerHTML = `
                    <span class="smg-suggestion-applied">
                        <span class="dashicons dashicons-yes-alt"></span>
                    </span>
                `;
          select.dispatchEvent(new Event("change", { bubbles: true }));
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
        const button = e.target.closest(".smg-toggle-password");
        const targetId = button.dataset.target;
        const input = document.getElementById(targetId);
        const icon = button.querySelector(".dashicons");
        if (!input)
          return;
        if (input.type === "password") {
          input.type = "text";
          icon.classList.remove("dashicons-visibility");
          icon.classList.add("dashicons-hidden");
        } else {
          input.type = "password";
          icon.classList.remove("dashicons-hidden");
          icon.classList.add("dashicons-visibility");
        }
      },
      /**
       * Handle remove token (Update tab)
       */
      handleRemoveToken(e) {
        e.preventDefault();
        if (!confirm("Are you sure you want to remove the GitHub token?")) {
          return;
        }
        const tokenInput = document.getElementById("smg_github_token");
        if (tokenInput) {
          tokenInput.value = "";
          const statusElement = document.querySelector(".smg-token-status");
          if (statusElement) {
            statusElement.remove();
          }
          const removeButton = document.getElementById("smg-remove-token");
          if (removeButton) {
            removeButton.style.display = "none";
          }
          this.showToast("Token will be removed when you save settings", "info");
        }
      },
      /**
       * Handle check for updates (Update tab)
       */
      async handleCheckUpdates(e) {
        e.preventDefault();
        const button = e.target.closest("#smg-check-updates");
        const resultDiv = document.getElementById("smg-update-result");
        button.disabled = true;
        const originalHtml = button.innerHTML;
        button.innerHTML = '<span class="dashicons dashicons-update smg-spin"></span> Checking...';
        if (resultDiv) {
          resultDiv.style.display = "none";
        }
        try {
          const formData = new FormData();
          formData.append("action", "smg_check_updates");
          formData.append("nonce", typeof smgAdmin !== "undefined" ? smgAdmin.nonce : "");
          const response = await fetch(typeof smgAdmin !== "undefined" ? smgAdmin.ajaxUrl : ajaxurl, {
            method: "POST",
            body: formData,
            credentials: "same-origin"
          });
          const data = await response.json();
          if (resultDiv) {
            resultDiv.style.display = "block";
            if (data.success) {
              if (data.data.update_available) {
                resultDiv.className = "smg-update-result smg-result-success";
                resultDiv.innerHTML = `
                                <span class="dashicons dashicons-yes-alt"></span>
                                New version available: <strong>${data.data.new_version}</strong>
                                <a href="${data.data.update_url}" class="smg-btn smg-btn-sm smg-btn-primary" style="margin-left: 10px;">Update Now</a>
                            `;
              } else {
                resultDiv.className = "smg-update-result smg-result-info";
                resultDiv.innerHTML = `
                                <span class="dashicons dashicons-yes"></span>
                                You have the latest version installed.
                            `;
              }
            } else {
              resultDiv.className = "smg-update-result smg-result-error";
              resultDiv.innerHTML = `
                            <span class="dashicons dashicons-warning"></span>
                            ${data.data?.message || "Could not check for updates."}
                        `;
            }
          }
        } catch (error) {
          console.error("Update check failed:", error);
          if (resultDiv) {
            resultDiv.style.display = "block";
            resultDiv.className = "smg-update-result smg-result-error";
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
        const toggle = input.closest(".smg-toggle");
        if (toggle) {
          toggle.style.transform = "scale(0.95)";
          setTimeout(() => {
            toggle.style.transform = "scale(1)";
          }, 100);
        }
      },
      /**
       * Initialize tooltips
       */
      initTooltips() {
      },
      /**
       * Initialize collapsible panels
       */
      initCollapsibles() {
        document.querySelectorAll(".smg-meta-box-panel-header").forEach((header) => {
          header.addEventListener("click", () => {
            const panel = header.closest(".smg-meta-box-panel");
            panel.classList.toggle("collapsed");
          });
        });
      },
      /**
       * Slide up animation
       */
      slideUp(element) {
        element.style.height = element.scrollHeight + "px";
        element.offsetHeight;
        element.style.transition = `height ${this.config.animationDuration}ms ease`;
        element.style.height = "0";
        element.style.overflow = "hidden";
        setTimeout(() => {
          element.style.display = "none";
          element.style.height = "";
          element.style.overflow = "";
          element.style.transition = "";
        }, this.config.animationDuration);
      },
      /**
       * Slide down animation
       */
      slideDown(element) {
        element.style.display = "block";
        element.style.height = "0";
        element.style.overflow = "hidden";
        element.offsetHeight;
        const height = element.scrollHeight;
        element.style.transition = `height ${this.config.animationDuration}ms ease`;
        element.style.height = height + "px";
        setTimeout(() => {
          element.style.height = "";
          element.style.overflow = "";
          element.style.transition = "";
        }, this.config.animationDuration);
      },
      /**
       * Show toast notification
       */
      showToast(message, type = "info") {
        const toast = document.createElement("div");
        toast.className = `smg-toast smg-toast-${type} smg-animate-slide-in-right`;
        toast.innerHTML = `
                <span class="dashicons dashicons-${type === "success" ? "yes-alt" : type === "error" ? "warning" : "info"}"></span>
                ${message}
            `;
        let container = document.querySelector(".smg-toast-container");
        if (!container) {
          container = document.createElement("div");
          container.className = "smg-toast-container";
          container.style.cssText = "position: fixed; top: 50px; right: 20px; z-index: 99999; display: flex; flex-direction: column; gap: 10px;";
          document.body.appendChild(container);
        }
        container.appendChild(toast);
        setTimeout(() => {
          toast.style.opacity = "0";
          toast.style.transform = "translateX(20px)";
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
      }
    };
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", () => SMGAdmin.init());
    } else {
      SMGAdmin.init();
    }
    window.SMGAdmin = SMGAdmin;
  })();
})();
