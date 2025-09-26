/**
 * SoSol Admin JavaScript
 * Administrative interface functionality
 */

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeAdminFeatures();
});

/**
 * Initialize all admin features
 */
function initializeAdminFeatures() {
    initializeSidebar();
    initializeThemeSwitcher();
    initializeDataTables();
    initializeTooltips();
    initializeCollapseMenus();
    initializeFormValidations();
    initializeAjaxHandlers();
    initializeNotificationSystem();
}

/**
 * Initialize sidebar functionality
 */
function initializeSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    if (!sidebar) return;

    // Auto-expand active submenus
    const activeMenuItems = document.querySelectorAll('.sidebar-nav .nav-link.active');
    activeMenuItems.forEach(item => {
        const parentCollapse = item.closest('.collapse');
        if (parentCollapse) {
            const bsCollapse = new bootstrap.Collapse(parentCollapse, {
                show: true
            });
        }
    });

    // Handle submenu clicks
    // Handle submenu clicks (anchors using data-bs-toggle="collapse")
    const submenuToggles = document.querySelectorAll('.sidebar-nav a[data-bs-toggle="collapse"]');
    submenuToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            // Prevent default navigation and toggle collapse programmatically to keep aria-expanded in sync
            e.preventDefault();
            const targetSelector = this.getAttribute('data-bs-target') || this.getAttribute('href');
            if (!targetSelector) return;
            const collapseElement = document.querySelector(targetSelector);
            if (!collapseElement) return;

            const bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapseElement, { toggle: false });
            bsCollapse.toggle();

            // update aria-expanded
            const isExpanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', (!isExpanded).toString());

            // If we're on a small screen and the sidebar is shown as offcanvas, hide it after a navigation click
            if (window.innerWidth < 992) {
                const bsOffcanvas = bootstrap.Offcanvas.getInstance(sidebar) || bootstrap.Offcanvas.getOrCreateInstance(sidebar);
                if (bsOffcanvas) bsOffcanvas.hide();
            }
        });
    });

    // Ensure offcanvas is visible (docked) on large screens and behaves as offcanvas on small screens
    function syncOffcanvasState() {
        if (window.innerWidth >= 992) {
            // Show the sidebar as docked (no backdrop)
            const bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(sidebar, { backdrop: false });
            bsOffcanvas.show();
            sidebar.classList.add('docked');

            // If Bootstrap added 'modal-open' or overflow styles when showing, remove them so page can scroll
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';

            // Remove any existing offcanvas backdrop elements (safety)
            const backdrops = document.querySelectorAll('.offcanvas-backdrop');
            backdrops.forEach(b => b.remove());
        } else {
            // Hide it by default on small screens
            const existing = bootstrap.Offcanvas.getInstance(sidebar);
            if (existing) existing.hide();
            sidebar.classList.remove('docked');
        }
    }

    // Sync on load and on resize (debounced)
    syncOffcanvasState();
    window.addEventListener('resize', debounce(syncOffcanvasState, 150));
}

/**
 * Initialize theme switcher functionality
 */
function initializeThemeSwitcher() {
    const themeToggle = document.getElementById('themeToggle');
    if (!themeToggle) return;

    const themeIcon = themeToggle.querySelector('i');
    
    // Check for saved theme preference or use preferred color scheme
    const currentTheme = localStorage.getItem('admin-theme') || 
                        (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    
    // Apply saved theme
    if (currentTheme === 'dark') {
        document.documentElement.setAttribute('data-bs-theme', 'dark');
        if (themeIcon) {
            themeIcon.classList.replace('fa-moon', 'fa-sun');
        }
    }
    // Set aria-pressed for accessibility
    themeToggle.setAttribute('aria-pressed', document.documentElement.getAttribute('data-bs-theme') === 'dark');
    
    // Theme toggle event
    themeToggle.addEventListener('click', function() {
        const currentTheme = document.documentElement.getAttribute('data-bs-theme');
        if (currentTheme === 'dark') {
            document.documentElement.setAttribute('data-bs-theme', 'light');
            if (themeIcon) {
                themeIcon.classList.replace('fa-sun', 'fa-moon');
            }
            localStorage.setItem('admin-theme', 'light');
        } else {
            document.documentElement.setAttribute('data-bs-theme', 'dark');
            if (themeIcon) {
                themeIcon.classList.replace('fa-moon', 'fa-sun');
            }
            localStorage.setItem('admin-theme', 'dark');
        }
        
        // Update aria-pressed
        themeToggle.setAttribute('aria-pressed', document.documentElement.getAttribute('data-bs-theme') === 'dark');

        // Dispatch custom event for theme change
        document.dispatchEvent(new CustomEvent('themeChanged', {
            detail: { theme: document.documentElement.getAttribute('data-bs-theme') }
        }));
    });
}

/**
 * Initialize DataTables with custom configuration
 */
function initializeDataTables() {
    if (typeof $.fn.DataTable === 'undefined') return;
    
    $('.datatable').each(function() {
        const table = $(this);
        const options = {
            responsive: true,
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search...",
                lengthMenu: "_MENU_ records per page",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                infoEmpty: "Showing 0 to 0 of 0 entries",
                infoFiltered: "(filtered from _MAX_ total entries)",
                zeroRecords: "No matching records found",
                paginate: {
                    first: "First",
                    last: "Last",
                    next: "Next",
                    previous: "Previous"
                }
            },
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                 '<"row"<"col-sm-12"tr>>' +
                 '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            pageLength: 25,
            order: [], // No default ordering
            columnDefs: [
                { orderable: false, targets: 'no-sort' }
            ]
        };
        
        // Initialize DataTable
        table.DataTable(options);
    });
}

/**
 * Initialize Bootstrap tooltips
 */
function initializeTooltips() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Initialize collapse menus
 */
function initializeCollapseMenus() {
    const collapseElements = document.querySelectorAll('.collapse');
    collapseElements.forEach(function(collapseEl) {
        // Initialize but don't toggle by default
        new bootstrap.Collapse(collapseEl, {
            toggle: false
        });
    });
}

/**
 * Initialize form validations
 */
function initializeFormValidations() {
    // Custom validation for admin forms
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
    
    // Real-time validation
    const validateInputs = document.querySelectorAll('[data-validate]');
    validateInputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });
        
        input.addEventListener('input', function() {
            if (this.classList.contains('is-invalid')) {
                validateField(this);
            }
        });
    });
}

/**
 * Validate individual form field
 */
function validateField(field) {
    const isValid = field.checkValidity();
    if (isValid) {
        field.classList.remove('is-invalid');
        field.classList.add('is-valid');
    } else {
        field.classList.remove('is-valid');
        field.classList.add('is-invalid');
    }
    return isValid;
}

/**
 * Initialize AJAX handlers
 */
function initializeAjaxHandlers() {
    // Global AJAX setup
    $.ajaxSetup({
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        error: function(xhr, status, error) {
            showNotification('Error: ' + error, 'danger');
        }
    });
    
    // Handle AJAX forms
    $(document).on('submit', '.ajax-form', function(e) {
        e.preventDefault();
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        const originalText = submitBtn.html();
        
        // Show loading state
        submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
        
        $.ajax({
            url: form.attr('action'),
            method: form.attr('method'),
            data: form.serialize(),
            success: function(response) {
                if (response.success) {
                    showNotification(response.message, 'success');
                    if (response.redirect) {
                        setTimeout(() => {
                            window.location.href = response.redirect;
                        }, 1500);
                    }
                } else {
                    showNotification(response.message, 'danger');
                }
            },
            error: function(xhr) {
                let message = 'An error occurred';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                showNotification(message, 'danger');
            },
            complete: function() {
                submitBtn.prop('disabled', false).html(originalText);
            }
        });
    });
}

/**
 * Initialize notification system
 */
function initializeNotificationSystem() {
    // Create notification container if it doesn't exist
    if (!document.getElementById('notification-container')) {
        const container = document.createElement('div');
        container.id = 'notification-container';
        container.className = 'position-fixed top-0 end-0 p-3';
        container.style.zIndex = '1060';
        document.body.appendChild(container);
    }
}

/**
 * Show notification message
 */
function showNotification(message, type = 'info') {
    const container = document.getElementById('notification-container');
    if (!container) return;
    
    const alertClass = {
        'success': 'alert-success',
        'danger': 'alert-danger',
        'warning': 'alert-warning',
        'info': 'alert-info'
    }[type] || 'alert-info';
    
    const alertId = 'alert-' + Date.now();
    const alert = document.createElement('div');
    alert.id = alertId;
    alert.className = `alert ${alertClass} alert-dismissible fade show`;
    alert.role = 'alert';
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;
    
    container.appendChild(alert);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    }, 5000);
}

/**
 * Confirm action with Bootstrap modal
 */
function confirmAction(message, callback) {
    // Create modal if it doesn't exist
    if (!document.getElementById('confirmModal')) {
        const modalHtml = `
            <div class="modal fade" id="confirmModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Confirm Action</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p id="confirmMessage"></p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-danger" id="confirmButton">Confirm</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHtml);
    }
    
    const modal = new bootstrap.Modal(document.getElementById('confirmModal'));
    document.getElementById('confirmMessage').textContent = message;
    
    // Remove previous event listeners
    const confirmButton = document.getElementById('confirmButton');
    const newConfirmButton = confirmButton.cloneNode(true);
    confirmButton.parentNode.replaceChild(newConfirmButton, confirmButton);
    
    // Add new event listener
    newConfirmButton.addEventListener('click', function() {
        modal.hide();
        if (typeof callback === 'function') {
            callback();
        }
    });
    
    modal.show();
}

/**
 * Format currency values
 */
function formatCurrency(amount, currency = 'USD') {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency
    }).format(amount);
}

/**
 * Format date values
 */
function formatDate(date, format = 'medium') {
    const dateObj = new Date(date);
    const options = {
        'short': { dateStyle: 'short', timeStyle: 'short' },
        'medium': { dateStyle: 'medium', timeStyle: 'medium' },
        'long': { dateStyle: 'long', timeStyle: 'long' },
        'date-only': { dateStyle: 'medium' },
        'time-only': { timeStyle: 'medium' }
    }[format] || { dateStyle: 'medium', timeStyle: 'medium' };
    
    return new Intl.DateTimeFormat('en-US', options).format(dateObj);
}

/**
 * Debounce function for search inputs
 */
function debounce(func, wait) {
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

/**
 * Toggle loading state for buttons
 */
function setButtonLoading(button, isLoading) {
    if (isLoading) {
        button.disabled = true;
        button.setAttribute('data-original-text', button.innerHTML);
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
    } else {
        button.disabled = false;
        button.innerHTML = button.getAttribute('data-original-text') || button.innerHTML;
    }
}

// Export functions for global use (if using modules)
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        initializeAdminFeatures,
        showNotification,
        confirmAction,
        formatCurrency,
        formatDate,
        debounce,
        setButtonLoading
    };
}