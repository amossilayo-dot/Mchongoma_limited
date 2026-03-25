/**
 * Mchongoma POS Dashboard JavaScript
 * Handles all interactive functionality
 */

// ============================================
// SECURITY UTILITIES
// ============================================

/**
 * Escape HTML entities to prevent XSS attacks
 */
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
}

/**
 * Validate that a string contains only safe characters for IDs/transaction numbers
 */
function isValidIdentifier(str) {
    return /^[A-Za-z0-9\-_]+$/.test(str);
}

function getAppConfig() {
    const configEl = document.getElementById('appConfig');
    if (!configEl) {
        return { salesChartData: { week: { labels: [], values: [] }, month: { labels: [], values: [] } }, currentPage: 'dashboard', csrfToken: '' };
    }

    try {
        return JSON.parse(configEl.textContent || '{}');
    } catch (error) {
        return { salesChartData: { week: { labels: [], values: [] }, month: { labels: [], values: [] } }, currentPage: 'dashboard', csrfToken: '' };
    }
}

const APP_CONFIG = getAppConfig();

// ============================================
// INITIALIZATION
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    initActionBindings();
    initClock();
    initChart();
    initChartTabs();
    initSidebarOverlay();
    initKeyboardShortcuts();
});

// ============================================
// REAL-TIME CLOCK
// ============================================

function initClock() {
    const clockEl = document.getElementById('clock');
    if (!clockEl) return;

    function updateClock() {
        const now = new Date();
        const hours = now.getHours();
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const ampm = hours >= 12 ? 'pm' : 'am';
        const displayHours = hours % 12 || 12;

        const day = now.getDate().toString().padStart(2, '0');
        const month = (now.getMonth() + 1).toString().padStart(2, '0');
        const year = now.getFullYear();

        clockEl.textContent = `${displayHours}:${minutes} ${ampm} ${day}-${month}-${year}`;
    }

    updateClock();
    setInterval(updateClock, 1000);
}

// ============================================
// SALES CHART
// ============================================

let salesChart = null;

function initChart() {
    const ctx = document.getElementById('salesChart');
    if (!ctx) return;

    const chartData = APP_CONFIG.salesChartData?.week || { labels: [], values: [] };

    salesChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartData.labels,
            datasets: [{
                label: 'Sales',
                data: chartData.values,
                backgroundColor: '#4F46E5',
                borderRadius: 8,
                barThickness: 24,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1F2937',
                    titleColor: '#F9FAFB',
                    bodyColor: '#F9FAFB',
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            const value = context.parsed.y || 0;
                            return 'Tsh ' + new Intl.NumberFormat().format(value);
                        },
                    },
                },
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: {
                        color: '#9CA3AF',
                        font: { family: 'Poppins', size: 11 },
                    },
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#9CA3AF',
                        font: { family: 'Poppins', size: 11 },
                        callback: function(value) {
                            if (value >= 1000000) {
                                return (value / 1000000).toFixed(1) + 'M';
                            } else if (value >= 1000) {
                                return (value / 1000).toFixed(0) + 'K';
                            }
                            return value.toLocaleString();
                        },
                    },
                    grid: {
                        color: '#F3F4F6',
                    },
                    border: { display: false },
                },
            },
            animation: {
                duration: 500,
                easing: 'easeOutQuart',
            },
        },
    });
}

function initChartTabs() {
    const tabs = document.getElementById('chartTabs');
    if (!tabs) return;

    tabs.querySelectorAll('span').forEach(tab => {
        tab.addEventListener('click', function() {
            // Update active tab
            tabs.querySelectorAll('span').forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            // Update chart data
            const period = this.dataset.period;
            const chartData = APP_CONFIG.salesChartData?.[period] || { labels: [], values: [] };

            if (salesChart) {
                salesChart.data.labels = chartData.labels;
                salesChart.data.datasets[0].data = chartData.values;
                salesChart.update('active');
            }
        });
    });
}

// ============================================
// SIDEBAR & MOBILE MENU
// ============================================

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay');

    sidebar.classList.toggle('open');
    if (overlay) {
        overlay.classList.toggle('active');
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay');

    sidebar.classList.remove('open');
    if (overlay) {
        overlay.classList.remove('active');
    }
}

function initSidebarOverlay() {
    // Create overlay if it doesn't exist
    if (!document.querySelector('.sidebar-overlay')) {
        const overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        overlay.onclick = closeSidebar;
        document.body.appendChild(overlay);
    }
}

function initActionBindings() {
    document.addEventListener('click', function(event) {
        const target = event.target.closest('[data-action]');
        if (!target) {
            return;
        }

        const action = target.getAttribute('data-action') || '';
        const value = target.getAttribute('data-value') || '';

        if (target.tagName === 'A' || target.tagName === 'BUTTON' || action === 'go') {
            event.preventDefault();
        }

        const actionMap = {
            toggleSidebar,
            showAddModal,
            showNotifications,
            showLogoutConfirm,
            showNewSaleModal,
            showEndOfDayReport,
            showImportProductsModal,
            showAddProductModal,
            showAddCustomerModal,
            openAddSupplierModal,
            openAddEmployeeModal,
            openAddExpenseModal,
            openAddInvoiceModal,
            openAddDeliveryModal,
            openAddReceivingModal,
            openAddQuotationModal,
            openAddPOModal,
            openAddReturnModal,
            openAddAppointmentModal,
            openAddLocationModal,
            openComposeMessageModal,
            saveSettings,
            closeModal,
            closeNotifications,
        };

        if (action === 'go') {
            if (/^\?page=[A-Za-z0-9\-]+$/.test(value)) {
                window.location.href = value;
            }
            return;
        }

        if (action === 'generateReport') {
            generateReport(value);
            return;
        }

        if (action === 'editProduct') {
            editProduct(parseInt(value, 10));
            return;
        }

        if (action === 'deleteProduct') {
            deleteProduct(parseInt(value, 10));
            return;
        }

        if (action === 'viewCustomer') {
            viewCustomer(parseInt(value, 10));
            return;
        }

        if (action === 'editCustomer') {
            editCustomer(parseInt(value, 10));
            return;
        }

        if (action === 'deleteCustomer') {
            deleteCustomer(parseInt(value, 10));
            return;
        }

        if (action === 'viewReceipt') {
            viewReceipt(value);
            return;
        }

        if (action === 'printReceipt') {
            printReceipt(value);
            return;
        }

        if (actionMap[action]) {
            actionMap[action]();
        }
    });
}

// ============================================
// MODAL SYSTEM
// ============================================

function openModal(title, content, buttons = []) {
    const overlay = document.getElementById('modalOverlay');
    const modal = document.getElementById('modal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    const modalFooter = document.getElementById('modalFooter');

    modalTitle.textContent = title;
    modalBody.innerHTML = content;

    // Generate buttons safely using DOM manipulation
    modalFooter.innerHTML = '';
    buttons.forEach(btn => {
        const button = document.createElement('button');
        button.className = `btn ${btn.class || 'btn-secondary'}`;
        button.textContent = btn.text;

        // Use event listener instead of inline onclick for security
        if (typeof btn.handler === 'function') {
            button.addEventListener('click', btn.handler);
        } else if (btn.onclick) {
            // For legacy onclick strings, use a safe evaluation approach
            button.addEventListener('click', function() {
                // Only allow known safe function calls
                const safeActions = {
                    'closeModal()': closeModal,
                    'printReport()': printReport,
                    'logout()': logout,
                };
                if (safeActions[btn.onclick]) {
                    safeActions[btn.onclick]();
                } else if (btn.onclick.startsWith('closeModal();')) {
                    closeModal();
                    // Handle chained calls like "closeModal(); showToast(...)"
                    const remainder = btn.onclick.replace('closeModal();', '').trim();
                    if (remainder.startsWith('showToast(')) {
                        const match = remainder.match(/showToast\s*\(\s*["'](\w+)["']\s*,\s*["']([^"']+)["']\s*\)/);
                        if (match) {
                            showToast(match[1], match[2]);
                        }
                    } else if (remainder.startsWith('window.location')) {
                        // Handle safe redirects
                        const urlMatch = remainder.match(/window\.location\s*=\s*['"](\?page=\w+)['"]/);
                        if (urlMatch && /^\?page=\w+$/.test(urlMatch[1])) {
                            window.location = urlMatch[1];
                        }
                    }
                } else if (btn.onclick.startsWith('document.getElementById')) {
                    // Handle form submission
                    const match = btn.onclick.match(/document\.getElementById\s*\(\s*["'](\w+)["']\s*\)\.requestSubmit\s*\(\s*\)/);
                    if (match) {
                        const form = document.getElementById(match[1]);
                        if (form) form.requestSubmit();
                    }
                } else if (btn.onclick.startsWith('confirmDeleteProduct(')) {
                    const match = btn.onclick.match(/confirmDeleteProduct\s*\(\s*(\d+)\s*\)/);
                    if (match) confirmDeleteProduct(parseInt(match[1], 10));
                } else if (btn.onclick.startsWith('printReceipt(')) {
                    const match = btn.onclick.match(/printReceipt\s*\(\s*["']([A-Za-z0-9\-_]+)["']\s*\)/);
                    if (match) printReceipt(match[1]);
                }
            });
        } else {
            button.addEventListener('click', closeModal);
        }

        modalFooter.appendChild(button);
    });

    overlay.classList.add('active');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    const overlay = document.getElementById('modalOverlay');
    const modal = document.getElementById('modal');

    overlay.classList.remove('active');
    modal.classList.remove('active');
    document.body.style.overflow = '';
}

// ============================================
// QUICK ACTION MODALS
// ============================================

function showAddModal() {
    const content = `
        <div class="quick-add-grid">
            <button class="quick-add-item" data-action="quickNewSale">
                <i class="fa-solid fa-cart-plus"></i>
                <span>New Sale</span>
            </button>
            <button class="quick-add-item" data-action="quickAddProduct">
                <i class="fa-solid fa-cube"></i>
                <span>New Product</span>
            </button>
            <button class="quick-add-item" data-action="quickImportProducts">
                <i class="fa-solid fa-file-import"></i>
                <span>Import Products</span>
            </button>
            <button class="quick-add-item" data-action="quickAddCustomer">
                <i class="fa-solid fa-user-plus"></i>
                <span>New Customer</span>
            </button>
            <button class="quick-add-item" data-action="quickGoExpenses">
                <i class="fa-solid fa-receipt"></i>
                <span>New Expense</span>
            </button>
        </div>
        <style>
            .quick-add-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .quick-add-item {
                display: flex; flex-direction: column; align-items: center; gap: 8px;
                padding: 24px; border: 1px solid #E5E7EB; border-radius: 12px;
                background: white; cursor: pointer; transition: all 0.2s;
                font-family: inherit;
            }
            .quick-add-item:hover {
                border-color: #4F46E5; background: rgba(79, 70, 229, 0.05);
                transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }
            .quick-add-item i { font-size: 24px; color: #4F46E5; }
            .quick-add-item span { font-weight: 600; color: #1F2937; }
        </style>
    `;
    openModal('Quick Add', content, [
        { text: 'Cancel', class: 'btn-secondary', onclick: 'closeModal()' }
    ]);

    const modalBody = document.getElementById('modalBody');
    modalBody?.querySelector('[data-action="quickNewSale"]')?.addEventListener('click', () => {
        closeModal();
        showNewSaleModal();
    });
    modalBody?.querySelector('[data-action="quickAddProduct"]')?.addEventListener('click', () => {
        closeModal();
        showAddProductModal();
    });
    modalBody?.querySelector('[data-action="quickImportProducts"]')?.addEventListener('click', () => {
        closeModal();
        showImportProductsModal();
    });
    modalBody?.querySelector('[data-action="quickAddCustomer"]')?.addEventListener('click', () => {
        closeModal();
        showAddCustomerModal();
    });
    modalBody?.querySelector('[data-action="quickGoExpenses"]')?.addEventListener('click', () => {
        closeModal();
        window.location.href = '?page=expenses';
    });
}

function showNewSaleModal() {
    const csrfToken = escapeHtml(APP_CONFIG.csrfToken || '');
    const content = `
        <form id="newSaleForm" method="POST" action="?page=sales">
            <input type="hidden" name="action" value="create_entity">
            <input type="hidden" name="entity" value="sale">
            <input type="hidden" name="csrf_token" value="${csrfToken}">
            <div class="form-group">
                <label>Customer</label>
                <select name="customer_id" required>
                    <option value="1">Walk-in Customer</option>
                    <option value="2">Mchina</option>
                </select>
            </div>
            <div class="form-group">
                <label>Amount (Tsh)</label>
                <input type="number" name="amount" placeholder="Enter amount" required min="1">
            </div>
            <div class="form-group">
                <label>Payment Method</label>
                <select name="payment_method">
                    <option value="Cash">Cash</option>
                    <option value="Mobile Money">Mobile Money</option>
                    <option value="Card">Card</option>
                </select>
            </div>
        </form>
    `;
    openModal('Create New Sale', content, [
        { text: 'Cancel', class: 'btn-secondary', onclick: 'closeModal()' },
        { text: 'Create Sale', class: 'btn-primary', onclick: 'document.getElementById("newSaleForm").requestSubmit()' }
    ]);
}

function showAddProductModal() {
    const csrfToken = escapeHtml(APP_CONFIG.csrfToken || '');
    const content = `
        <form id="addProductForm" method="POST" action="?page=inventory">
            <input type="hidden" name="action" value="create_entity">
            <input type="hidden" name="entity" value="product">
            <input type="hidden" name="csrf_token" value="${csrfToken}">
            <div class="form-group">
                <label>Product Name</label>
                <input type="text" name="name" placeholder="Enter product name" required>
            </div>
            <div class="form-group">
                <label>SKU</label>
                <input type="text" name="sku" placeholder="e.g., SKU-PRD-001" required>
            </div>
            <div class="form-group">
                <label>Unit Price (Tsh)</label>
                <input type="number" name="unit_price" placeholder="Enter price" required min="0">
            </div>
            <div class="form-group">
                <label>Stock Quantity</label>
                <input type="number" name="stock_qty" placeholder="Enter quantity" value="0" min="0">
            </div>
            <div class="form-group">
                <label>Reorder Level</label>
                <input type="number" name="reorder_level" placeholder="Low stock alert level" value="5" min="0">
            </div>
        </form>
    `;
    openModal('Add New Product', content, [
        { text: 'Cancel', class: 'btn-secondary', onclick: 'closeModal()' },
        { text: 'Add Product', class: 'btn-primary', onclick: 'document.getElementById("addProductForm").requestSubmit()' }
    ]);
}

function showImportProductsModal() {
    const csrfToken = escapeHtml(APP_CONFIG.csrfToken || '');

    const content = `
        <form id="importProductsForm" action="?page=inventory" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="import_products">
            <input type="hidden" name="csrf_token" value="${csrfToken}">
            <div class="form-group">
                <label>Excel File (.xlsx or .csv)</label>
                <input type="file" name="product_import_file" accept=".xlsx,.csv,text/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
            </div>
            <div class="form-group" style="background:#F9FAFB;border:1px solid #E5E7EB;border-radius:8px;padding:12px;">
                <small style="color:#4B5563;line-height:1.5;display:block;">
                    Upload Excel .xlsx directly, or use CSV.<br>
                    Expected columns: <strong>name, sku, unit_price, stock_qty, reorder_level</strong>.<br>
                    Max file size is 5MB and max 5000 data rows.<br>
                    The importer creates new products and updates existing ones by SKU.
                </small>
            </div>
        </form>
    `;

    openModal('Import Products', content, [
        { text: 'Cancel', class: 'btn-secondary', onclick: 'closeModal()' },
        { text: 'Import', class: 'btn-primary', onclick: 'document.getElementById("importProductsForm").requestSubmit()' }
    ]);
}

function showAddCustomerModal() {
    const csrfToken = escapeHtml(APP_CONFIG.csrfToken || '');
    const content = `
        <form id="addCustomerForm" method="POST" action="?page=customers">
            <input type="hidden" name="action" value="create_entity">
            <input type="hidden" name="entity" value="customer">
            <input type="hidden" name="csrf_token" value="${csrfToken}">
            <div class="form-group">
                <label>Customer Name</label>
                <input type="text" name="name" placeholder="Enter customer name" required>
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone" placeholder="e.g., 255700000000">
            </div>
        </form>
    `;
    openModal('Add New Customer', content, [
        { text: 'Cancel', class: 'btn-secondary', onclick: 'closeModal()' },
        { text: 'Add Customer', class: 'btn-primary', onclick: 'document.getElementById("addCustomerForm").requestSubmit()' }
    ]);
}

function openEntityModal(config) {
    const formId = `${config.key}Form`;
    const csrfToken = escapeHtml(APP_CONFIG.csrfToken || '');
    const fieldsMarkup = config.fields.map(field => {
        if (field.type === 'textarea') {
            return `
                <div class="form-group">
                    <label>${field.label}</label>
                    <textarea name="${field.name}" placeholder="${field.placeholder || ''}" rows="${field.rows || 3}" ${field.required ? 'required' : ''}></textarea>
                </div>
            `;
        }

        return `
            <div class="form-group">
                <label>${field.label}</label>
                <input type="${field.type || 'text'}" name="${field.name}" placeholder="${field.placeholder || ''}" ${field.required ? 'required' : ''}>
            </div>
        `;
    }).join('');

    const content = `
        <form id="${formId}" method="POST" action="?page=${escapeHtml(config.page || APP_CONFIG.currentPage || 'dashboard')}">
            <input type="hidden" name="action" value="create_entity">
            <input type="hidden" name="entity" value="${escapeHtml(config.entity)}">
            <input type="hidden" name="csrf_token" value="${csrfToken}">
            ${fieldsMarkup}
        </form>
    `;

    openModal(config.title, content, [
        { text: 'Cancel', class: 'btn-secondary', onclick: 'closeModal()' },
        { text: config.submitText || 'Save', class: 'btn-primary', onclick: `document.getElementById("${formId}").requestSubmit()` }
    ]);

}

function openAddSupplierModal() {
    openEntityModal({
        key: 'supplier',
        page: 'suppliers',
        entity: 'supplier',
        title: 'Add Supplier',
        entityName: 'Supplier',
        submitText: 'Add Supplier',
        successMessage: 'Supplier added successfully!',
        fields: [
            { label: 'Supplier Name', name: 'name', required: true, placeholder: 'Enter supplier name' },
            { label: 'Contact Person', name: 'contact_person', placeholder: 'Enter contact person' },
            { label: 'Phone', name: 'phone', type: 'tel', placeholder: 'Enter phone number' },
            { label: 'Email', name: 'email', type: 'email', placeholder: 'Enter email address' },
            { label: 'Address', name: 'address', type: 'textarea', placeholder: 'Enter address', rows: 3 },
        ]
    });
}

function openAddEmployeeModal() {
    openEntityModal({
        key: 'employee',
        page: 'employees',
        entity: 'employee',
        title: 'Add Employee',
        entityName: 'Employee',
        submitText: 'Add Employee',
        successMessage: 'Employee added successfully!',
        fields: [
            { label: 'Employee Name', name: 'name', required: true, placeholder: 'Enter employee name' },
            { label: 'Position', name: 'position', placeholder: 'e.g. Cashier' },
            { label: 'Phone', name: 'phone', type: 'tel', placeholder: 'Enter phone number' },
            { label: 'Email', name: 'email', type: 'email', placeholder: 'Enter email address' },
            { label: 'Salary (Tsh)', name: 'salary', type: 'number', placeholder: 'Enter salary' },
        ]
    });
}

function openAddExpenseModal() {
    openEntityModal({
        key: 'expense',
        page: 'expenses',
        entity: 'expense',
        title: 'Record Expense',
        entityName: 'Expense',
        submitText: 'Save Expense',
        successMessage: 'Expense saved successfully!',
        fields: [
            { label: 'Description', name: 'description', required: true, placeholder: 'Expense description' },
            { label: 'Amount (Tsh)', name: 'amount', type: 'number', required: true, placeholder: 'Enter amount' },
            { label: 'Category', name: 'category', placeholder: 'Utilities, Transport, etc.' },
        ]
    });
}

function openAddInvoiceModal() {
    openEntityModal({
        key: 'invoice',
        page: 'invoices',
        entity: 'invoice',
        title: 'Create Invoice',
        entityName: 'Invoice',
        submitText: 'Create Invoice',
        successMessage: 'Invoice created successfully!',
        fields: [
            { label: 'Customer ID', name: 'customer_id', type: 'number', required: true, placeholder: 'Enter customer ID' },
            { label: 'Amount (Tsh)', name: 'amount', type: 'number', required: true, placeholder: 'Enter amount' },
        ]
    });
}

function openAddDeliveryModal() {
    openEntityModal({
        key: 'delivery',
        page: 'deliveries',
        entity: 'delivery',
        title: 'Schedule Delivery',
        entityName: 'Delivery',
        submitText: 'Save Delivery',
        successMessage: 'Delivery saved successfully!',
        fields: [
            { label: 'Customer ID', name: 'customer_id', type: 'number', required: true, placeholder: 'Enter customer ID' },
            { label: 'Amount (Tsh)', name: 'amount', type: 'number', placeholder: 'Enter amount' },
        ]
    });
}

function openAddReceivingModal() {
    openEntityModal({
        key: 'receiving',
        page: 'receiving',
        entity: 'receiving',
        title: 'Record Receiving',
        entityName: 'Receiving',
        submitText: 'Save Receiving',
        successMessage: 'Receiving record saved successfully!',
        fields: [
            { label: 'Supplier ID', name: 'supplier_id', type: 'number', required: true, placeholder: 'Enter supplier ID' },
            { label: 'Amount (Tsh)', name: 'amount', type: 'number', placeholder: 'Enter amount' },
        ]
    });
}

function openAddQuotationModal() {
    openEntityModal({
        key: 'quotation',
        page: 'quotations',
        entity: 'quotation',
        title: 'Create Quotation',
        entityName: 'Quotation',
        submitText: 'Create Quotation',
        successMessage: 'Quotation created successfully!',
        fields: [
            { label: 'Customer ID', name: 'customer_id', type: 'number', required: true, placeholder: 'Enter customer ID' },
            { label: 'Amount (Tsh)', name: 'amount', type: 'number', required: true, placeholder: 'Enter amount' },
        ]
    });
}

function openAddPOModal() {
    openEntityModal({
        key: 'purchaseOrder',
        page: 'purchase-orders',
        entity: 'purchase_order',
        title: 'Create Purchase Order',
        entityName: 'Purchase Order',
        submitText: 'Create PO',
        successMessage: 'Purchase order created successfully!',
        fields: [
            { label: 'Supplier ID', name: 'supplier_id', type: 'number', required: true, placeholder: 'Enter supplier ID' },
            { label: 'Amount (Tsh)', name: 'amount', type: 'number', required: true, placeholder: 'Enter amount' },
        ]
    });
}

function openAddReturnModal() {
    openEntityModal({
        key: 'return',
        page: 'returns',
        entity: 'return',
        title: 'Record Return',
        entityName: 'Return',
        submitText: 'Save Return',
        successMessage: 'Return recorded successfully!',
        fields: [
            { label: 'Product ID', name: 'product_id', type: 'number', required: true, placeholder: 'Enter product ID' },
            { label: 'Quantity', name: 'quantity', type: 'number', required: true, placeholder: 'Enter quantity' },
            { label: 'Reason', name: 'reason', type: 'textarea', placeholder: 'Optional reason', rows: 3 },
        ]
    });
}

function openAddAppointmentModal() {
    openEntityModal({
        key: 'appointment',
        page: 'appointments',
        entity: 'appointment',
        title: 'Schedule Appointment',
        entityName: 'Appointment',
        submitText: 'Save Appointment',
        successMessage: 'Appointment scheduled successfully!',
        fields: [
            { label: 'Title', name: 'title', required: true, placeholder: 'Appointment title' },
            { label: 'Customer ID', name: 'customer_id', type: 'number', required: true, placeholder: 'Enter customer ID' },
            { label: 'Date & Time', name: 'appointment_date', type: 'datetime-local', required: true, placeholder: '' },
        ]
    });
}

function openAddLocationModal() {
    openEntityModal({
        key: 'location',
        page: 'locations',
        entity: 'location',
        title: 'Add Location',
        entityName: 'Location',
        submitText: 'Save Location',
        successMessage: 'Location added successfully!',
        fields: [
            { label: 'Location Name', name: 'name', required: true, placeholder: 'Enter location name' },
            { label: 'Address', name: 'address', type: 'textarea', required: true, placeholder: 'Enter address', rows: 3 },
            { label: 'City', name: 'city', placeholder: 'Enter city' },
            { label: 'Phone', name: 'phone', type: 'tel', placeholder: 'Enter phone' },
        ]
    });
}

function openComposeMessageModal() {
    openEntityModal({
        key: 'message',
        page: 'messages',
        entity: 'message',
        title: 'Compose Message',
        entityName: 'Message',
        submitText: 'Send Message',
        successMessage: 'Message sent successfully!',
        fields: [
            { label: 'Recipient', name: 'recipient', required: true, placeholder: 'recipient@example.com' },
            { label: 'Subject', name: 'subject', required: true, placeholder: 'Message subject' },
            { label: 'Message', name: 'message', type: 'textarea', required: true, placeholder: 'Type your message', rows: 4 },
        ]
    });
}

function saveSettings() {
    showToast('success', 'Settings saved successfully!');
}

function showEndOfDayReport() {
    const now = new Date();
    const dateStr = now.toLocaleDateString('en-US', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    });

    const content = `
        <div class="eod-report">
            <div class="eod-header">
                <i class="fa-solid fa-calendar-check"></i>
                <h4>End of Day Report</h4>
                <p>${dateStr}</p>
            </div>
            <div class="eod-stats">
                <div class="eod-stat">
                    <span class="label">Total Sales</span>
                    <span class="value">Tsh 1,035,400</span>
                </div>
                <div class="eod-stat">
                    <span class="label">Transactions</span>
                    <span class="value">1</span>
                </div>
                <div class="eod-stat">
                    <span class="label">Cash</span>
                    <span class="value">Tsh 1,035,400</span>
                </div>
                <div class="eod-stat">
                    <span class="label">Mobile Money</span>
                    <span class="value">Tsh 0</span>
                </div>
            </div>
        </div>
        <style>
            .eod-report { text-align: center; }
            .eod-header { margin-bottom: 24px; }
            .eod-header i { font-size: 48px; color: #10B981; margin-bottom: 12px; }
            .eod-header h4 { margin: 0; font-size: 18px; }
            .eod-header p { color: #6B7280; margin: 4px 0 0 0; }
            .eod-stats { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .eod-stat {
                background: #F9FAFB; padding: 16px; border-radius: 8px;
                text-align: center;
            }
            .eod-stat .label { display: block; font-size: 12px; color: #6B7280; margin-bottom: 4px; }
            .eod-stat .value { display: block; font-size: 18px; font-weight: 700; color: #1F2937; }
        </style>
    `;
    openModal('End of Day Summary', content, [
        { text: 'Print Report', class: 'btn-secondary', onclick: 'printReport()' },
        { text: 'Close Day', class: 'btn-primary', onclick: 'closeModal(); showToast("success", "Day closed successfully!")' }
    ]);
}

function showLogoutConfirm() {
    const content = `
        <div style="text-align: center; padding: 20px 0;">
            <i class="fa-solid fa-right-from-bracket" style="font-size: 48px; color: #EF4444; margin-bottom: 16px;"></i>
            <p style="margin: 0; font-size: 16px;">Are you sure you want to logout?</p>
        </div>
    `;
    openModal('Confirm Logout', content, [
        { text: 'Cancel', class: 'btn-secondary', onclick: 'closeModal()' },
        { text: 'Logout', class: 'btn-danger', onclick: 'logout()' }
    ]);
}

// ============================================
// NOTIFICATIONS
// ============================================

function showNotifications() {
    const panel = document.getElementById('notificationPanel');
    panel.classList.toggle('active');
}

function closeNotifications() {
    const panel = document.getElementById('notificationPanel');
    panel.classList.remove('active');
}

// Close notifications when clicking outside
document.addEventListener('click', function(e) {
    const panel = document.getElementById('notificationPanel');
    const btn = document.querySelector('.notification-btn');
    if (panel && !panel.contains(e.target) && !btn?.contains(e.target)) {
        panel.classList.remove('active');
    }
});

// ============================================
// TOAST NOTIFICATIONS
// ============================================

function showToast(type, message) {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;

    const icons = {
        success: 'fa-check-circle',
        error: 'fa-times-circle',
        warning: 'fa-exclamation-triangle'
    };

    // Create elements safely to prevent XSS
    const icon = document.createElement('i');
    icon.className = `fa-solid ${icons[type] || 'fa-info-circle'}`;

    const span = document.createElement('span');
    span.textContent = message; // Safe: textContent escapes HTML

    toast.appendChild(icon);
    toast.appendChild(span);

    container.appendChild(toast);

    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s ease-out reverse';
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// ============================================
// TABLE FILTERING
// ============================================

function filterTable(tableId, searchTerm) {
    const table = document.getElementById(tableId);
    if (!table) return;

    const rows = table.querySelectorAll('tbody tr');
    const term = searchTerm.toLowerCase();

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(term) ? '' : 'none';
    });
}

function filterByStock(value) {
    const table = document.getElementById('productTable');
    if (!table) return;

    const rows = table.querySelectorAll('tbody tr');

    rows.forEach(row => {
        const stockStatus = row.dataset.stock;
        if (value === 'all') {
            row.style.display = '';
        } else {
            row.style.display = stockStatus === value ? '' : 'none';
        }
    });
}

// ============================================
// CRUD OPERATIONS (Demo - shows toast)
// ============================================

function createSale(event) {
    event.preventDefault();
    closeModal();
    showToast('success', 'Sale created successfully!');
    setTimeout(() => window.location.reload(), 1500);
}

function createProduct(event) {
    event.preventDefault();
    closeModal();
    showToast('success', 'Product added successfully!');
    setTimeout(() => window.location.reload(), 1500);
}

function createCustomer(event) {
    event.preventDefault();
    closeModal();
    showToast('success', 'Customer added successfully!');
    setTimeout(() => window.location.reload(), 1500);
}

function editProduct(id) {
    showToast('warning', 'Edit functionality requires API integration');
}

function deleteProduct(id) {
    const content = `
        <div style="text-align: center; padding: 20px 0;">
            <i class="fa-solid fa-trash" style="font-size: 48px; color: #EF4444; margin-bottom: 16px;"></i>
            <p style="margin: 0; font-size: 16px;">Are you sure you want to delete this product?</p>
            <p style="margin: 8px 0 0 0; font-size: 13px; color: #6B7280;">This action cannot be undone.</p>
        </div>
    `;
    openModal('Confirm Delete', content, [
        { text: 'Cancel', class: 'btn-secondary', onclick: 'closeModal()' },
        { text: 'Delete', class: 'btn-danger', onclick: 'confirmDeleteProduct(' + id + ')' }
    ]);
}

function confirmDeleteProduct(id) {
    closeModal();
    showToast('success', 'Product deleted successfully!');
}

function viewCustomer(id) {
    showToast('warning', 'View functionality requires API integration');
}

function editCustomer(id) {
    showToast('warning', 'Edit functionality requires API integration');
}

function deleteCustomer(id) {
    const content = `
        <div style="text-align: center; padding: 20px 0;">
            <i class="fa-solid fa-user-minus" style="font-size: 48px; color: #EF4444; margin-bottom: 16px;"></i>
            <p style="margin: 0; font-size: 16px;">Are you sure you want to delete this customer?</p>
        </div>
    `;
    openModal('Confirm Delete', content, [
        { text: 'Cancel', class: 'btn-secondary', onclick: 'closeModal()' },
        { text: 'Delete', class: 'btn-danger', onclick: 'closeModal(); showToast("success", "Customer deleted!")' }
    ]);
}

function viewReceipt(transactionNo) {
    // Validate transaction number format to prevent XSS
    if (!isValidIdentifier(transactionNo)) {
        showToast('error', 'Invalid transaction number');
        return;
    }

    const safeTransactionNo = escapeHtml(transactionNo);
    const content = `
        <div class="receipt" style="text-align: center; padding: 20px; background: #F9FAFB; border-radius: 8px;">
            <h4 style="margin: 0 0 8px 0;">Mchongoma Limited</h4>
            <p style="font-size: 12px; color: #6B7280; margin: 0 0 16px 0;">Transaction: ${safeTransactionNo}</p>
            <hr style="border: none; border-top: 1px dashed #D1D5DB; margin: 16px 0;">
            <p style="font-size: 13px; margin: 0;">Receipt details would appear here</p>
        </div>
    `;
    openModal('Receipt', content, [
        { text: 'Print', class: 'btn-secondary', onclick: `printReceipt("${safeTransactionNo}")` },
        { text: 'Close', class: 'btn-primary', onclick: 'closeModal()' }
    ]);
}

function printReceipt(transactionNo) {
    showToast('success', 'Printing receipt...');
}

function printReport() {
    showToast('success', 'Printing report...');
}

function generateReport(type) {
    const names = {
        daily: 'Daily Sales',
        weekly: 'Weekly Sales',
        monthly: 'Monthly Sales',
        inventory: 'Inventory',
        customers: 'Customer',
        profit: 'Profit & Loss'
    };
    if (!names[type]) {
        showToast('error', 'Invalid report type');
        return;
    }

    window.open(`export_report_pdf.php?type=${encodeURIComponent(type)}`, '_blank', 'noopener');
    showToast('success', `Exporting ${names[type]} Report PDF...`);
}

function logout() {
    closeModal();
    const logoutForm = document.getElementById('logoutForm');
    if (logoutForm) {
        logoutForm.requestSubmit();
        return;
    }

    window.location.href = 'login.php';
}

// ============================================
// KEYBOARD SHORTCUTS
// ============================================

function initKeyboardShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Escape to close modal
        if (e.key === 'Escape') {
            closeModal();
            closeNotifications();
            closeSidebar();
        }

        // Ctrl+K for quick search
        if (e.ctrlKey && e.key === 'k') {
            e.preventDefault();
            const searchInput = document.querySelector('.search-box input');
            if (searchInput) {
                searchInput.focus();
            }
        }

        // Ctrl+N for new sale
        if (e.ctrlKey && e.key === 'n' && !e.shiftKey) {
            e.preventDefault();
            showNewSaleModal();
        }
    });
}

// ============================================
// UTILITY FUNCTIONS
// ============================================

function formatMoney(amount) {
    return new Intl.NumberFormat('en-TZ').format(amount);
}
