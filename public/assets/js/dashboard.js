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

const I18N_TEXT = {
    'Dashboard': 'Dashibodi',
    'Inventory': 'Hesabu',
    'Customers': 'Wateja',
    'Suppliers': 'Wasambazaji',
    'Reports': 'Ripoti',
    'Receiving': 'Mapokezi',
    'Sales': 'Mauzo',
    'Deliveries': 'Uwasilishaji',
    'Expenses': 'Matumizi',
    'Appointments': 'Miadi',
    'Employees': 'Wafanyakazi',
    'Store Config': 'Mipangilio',
    'Invoices': 'Ankara',
    'Quotations': 'Nukuu',
    'Purchase Orders': 'Oda za Manunuzi',
    'Returns': 'Marejesho',
    'Locations': 'Matawi',
    'Messages': 'Ujumbe',
    'Logout': 'Toka',
    'Add': 'Ongeza',
    'Shop': 'Duka',
    'Product Inventory': 'Hesabu ya Bidhaa',
    'Manage your products and stock levels': 'Simamia bidhaa na viwango vya stoo',
    'Import Excel': 'Ingiza Excel',
    'Export XLSX': 'Hamisha XLSX',
    'Add Product': 'Ongeza Bidhaa',
    'Search products...': 'Tafuta bidhaa...',
    'All Products': 'Bidhaa Zote',
    'Low Stock Only': 'Stoo Ndogo Tu',
    'In Stock': 'Stoo Ipo',
    'Product Name': 'Jina la Bidhaa',
    'Stock Qty': 'Kiasi Stoo',
    'Reorder Level': 'Kiwango cha Kuagiza',
    'Unit Price': 'Bei ya Kipande',
    'Status': 'Hali',
    'Actions': 'Vitendo',
    'Low Stock': 'Stoo Ndogo',
    'Customer Management': 'Usimamizi wa Wateja',
    'View and manage your customers': 'Tazama na simamia wateja wako',
    'Add Customer': 'Ongeza Mteja',
    'Search customers...': 'Tafuta wateja...',
    'Customer Name': 'Jina la Mteja',
    'Phone': 'Simu',
    'Total Orders': 'Jumla ya Oda',
    'Total Spent': 'Jumla Iliyotumika',
    'Member Since': 'Mwanachama Tangu',
    'Point of Sale': 'Sehemu ya Mauzo',
    'Create and manage sales': 'Unda na simamia mauzo',
    'New Sale': 'Uuzaji Mpya',
    'Transaction History': 'Historia ya Miamala',
    'View all transactions': 'Tazama miamala yote',
    'Search transactions...': 'Tafuta miamala...',
    'All Payments': 'Malipo Yote',
    'Transaction #': 'Namba ya Muamala',
    'Payment Method': 'Njia ya Malipo',
    'Date & Time': 'Tarehe na Muda',
    'View Receipt': 'Tazama Risiti',
    'Print': 'Chapisha',
    'Reports & Analytics': 'Ripoti na Uchambuzi',
    'View business insights and generate reports': 'Tazama takwimu za biashara na toa ripoti',
    'Daily Sales Report': 'Ripoti ya Mauzo ya Siku',
    "View today's sales summary and transactions": 'Tazama muhtasari wa mauzo ya leo na miamala',
    'Weekly Sales Report': 'Ripoti ya Mauzo ya Wiki',
    'Sales performance for the past 7 days': 'Utendaji wa mauzo kwa siku 7 zilizopita',
    'Monthly Sales Report': 'Ripoti ya Mauzo ya Mwezi',
    'Complete monthly breakdown and trends': 'Muhtasari wa mwezi na mwelekeo',
    'Inventory Report': 'Ripoti ya Hesabu',
    'Stock levels and low inventory alerts': 'Viwango vya stoo na tahadhari za stoo ndogo',
    'Customer Report': 'Ripoti ya Wateja',
    'Customer purchases and loyalty insights': 'Manunuzi ya wateja na uaminifu',
    'Profit & Loss': 'Faida na Hasara',
    'Revenue, expenses, and profit margins': 'Mapato, matumizi na kiwango cha faida',
    'Suppliers Management': 'Usimamizi wa Wasambazaji',
    'Add Supplier': 'Ongeza Msambazaji',
    'No suppliers added yet': 'Hakuna msambazaji aliyeongezwa bado',
    'Employees Management': 'Usimamizi wa Wafanyakazi',
    'Add Employee': 'Ongeza Mfanyakazi',
    'No employees added yet': 'Hakuna mfanyakazi aliyeongezwa bado',
    'Expenses Management': 'Usimamizi wa Matumizi',
    'Add Expense': 'Ongeza Matumizi',
    'No expenses recorded yet': 'Hakuna matumizi yaliyorekodiwa bado',
    'Invoices Management': 'Usimamizi wa Ankara',
    'Create Invoice': 'Unda Ankara',
    'No invoices created yet': 'Hakuna ankara iliyoundwa bado',
    'Deliveries Management': 'Usimamizi wa Uwasilishaji',
    'Add Delivery': 'Ongeza Uwasilishaji',
    'No deliveries recorded yet': 'Hakuna uwasilishaji uliorekodiwa bado',
    'Receiving Management': 'Usimamizi wa Mapokezi',
    'Add Receiving': 'Ongeza Mapokezi',
    'No receiving records yet': 'Hakuna rekodi za mapokezi bado',
    'Quotations Management': 'Usimamizi wa Nukuu',
    'Create Quotation': 'Unda Nukuu',
    'No quotations created yet': 'Hakuna nukuu iliyoundwa bado',
    'Purchase Orders Management': 'Usimamizi wa Oda za Manunuzi',
    'Create PO': 'Unda Oda',
    'No purchase orders created yet': 'Hakuna oda ya manunuzi iliyoundwa bado',
    'Returns Management': 'Usimamizi wa Marejesho',
    'Add Return': 'Ongeza Rejesho',
    'No returns recorded yet': 'Hakuna marejesho yaliyorekodiwa bado',
    'Appointments Management': 'Usimamizi wa Miadi',
    'Schedule Appointment': 'Panga Miadi',
    'No appointments scheduled yet': 'Hakuna miadi iliyopangwa bado',
    'Store Locations': 'Matawi ya Duka',
    'No locations added yet': 'Hakuna tawi lililoongezwa bado',
    'Messages': 'Ujumbe',
    'New Message': 'Ujumbe Mpya',
    'No messages yet': 'Hakuna ujumbe bado',
    'Store Name': 'Jina la Duka',
    'Store Email': 'Barua Pepe ya Duka',
    'Store Phone': 'Simu ya Duka',
    'Store Address': 'Anwani ya Duka',
    'Default City': 'Mji Chaguo-msingi',
    'Starting Amount (Tsh)': 'Kiasi cha Kuanza (Tsh)',
    'Profile': 'Wasifu',
    'Setup': 'Mipangilio',
    'Cash': 'Pesa Taslimu',
    'Add Location': 'Ongeza Tawi',
    'Save': 'Hifadhi',
    'Cash, city, branches.': 'Pesa, mji, matawi.',
    'Settings saved successfully!': 'Mipangilio imehifadhiwa vizuri!',
    'Quick Add': 'Ongeza Haraka',
    'Cancel': 'Ghairi',
    'Create New Sale': 'Unda Uuzaji Mpya',
    'Customer': 'Mteja',
    'Amount (Tsh)': 'Kiasi (Tsh)',
    'Enter amount': 'Weka kiasi',
    'Create Sale': 'Unda Uuzaji',
    'Add New Product': 'Ongeza Bidhaa Mpya',
    'SKU': 'SKU',
    'Unit Price (Tsh)': 'Bei ya Kipande (Tsh)',
    'Stock Quantity': 'Kiasi cha Stoo',
    'Reorder Level': 'Kiwango cha Kuagiza',
    'Add New Customer': 'Ongeza Mteja Mpya',
    'Phone Number': 'Namba ya Simu',
    'Record Expense': 'Rekodi Matumizi',
    'Description': 'Maelezo',
    'Category': 'Kundi',
    'Compose Message': 'Andika Ujumbe',
    'Recipient': 'Mpokeaji',
    'Subject': 'Mada',
    'Message': 'Ujumbe',
    'Confirm Logout': 'Thibitisha Kutoka',
    'Are you sure you want to logout?': 'Una uhakika unataka kutoka?',
    'End of Day Summary': 'Muhtasari wa Mwisho wa Siku',
    'Close Day': 'Funga Siku',
    'Notifications': 'Arifa',
    'Low Stock Alert': 'Tahadhari ya Stoo Ndogo',
    'products need restocking': 'bidhaa zinahitaji kujazwa stoo',
    'All Good!': 'Kila kitu sawa!',
    'No alerts at this time': 'Hakuna tahadhari kwa sasa',
    'Back to Dashboard': 'Rudi Dashibodi',
    'This section is under construction and will be available soon.': 'Sehemu hii inaandaliwa na itapatikana hivi karibuni.',
    'Welcome to Mchongoma Limited,': 'Karibu Mchongoma Limited,',
    'Choose a common task below to get started.': 'Chagua kazi hapa chini kuanza.',
    'Start a New Sale': 'Anza Uuzaji Mpya',
    'View All Products': 'Tazama Bidhaa Zote',
    'View Customers': 'Tazama Wateja',
    'View All Reports': 'Tazama Ripoti Zote',
    'All Transactions': 'Miamala Yote',
    'Manage Suppliers': 'Simamia Wasambazaji',
    'End of Day Report': 'Ripoti ya Mwisho wa Siku',
    'Total Sales': 'Jumla ya Mauzo',
    'Total Customers': 'Jumla ya Wateja',
    'Total Products': 'Jumla ya Bidhaa',
    'Transactions Today': 'Miamala ya Leo',
    'Setup Check': 'Ukaguzi wa Mfumo',
    'PDO MySQL Driver': 'Kifaa cha PDO MySQL',
    'MySQL Connection': 'Muunganisho wa MySQL',
    'Core Tables': 'Jedwali Muhimu',
    'Loaded': 'Imepakiwa',
    'Missing': 'Haipo',
    'Connected': 'Imeunganishwa',
    'Failed': 'Imeshindwa',
    'Ready': 'Tayari',
    'If any item shows Missing/Failed, enable pdo_mysql in php.ini, restart Apache, start MySQL, and import sql/schema.sql.': 'Kama kipengele chochote kinaonyesha Haipo/Imeshindwa, washa pdo_mysql kwenye php.ini, anzisha upya Apache, washa MySQL, kisha ingiza sql/schema.sql.',
    'Sales Information': 'Taarifa za Mauzo',
    'Week': 'Wiki',
    'Month': 'Mwezi',
    'Low Stock Alerts': 'Tahadhari za Stoo Ndogo',
    'left': 'imebaki',
    'Recent Sales': 'Mauzo ya Hivi Karibuni',
    'Error:': 'Hitilafu:',
    'Name': 'Jina',
    'Contact': 'Mawasiliano',
    'Email': 'Barua Pepe',
    'Position': 'Cheo',
    'Salary': 'Mshahara',
    'Date': 'Tarehe',
    'Reason': 'Sababu',
    'Quantity': 'Kiasi',
    'Title': 'Kichwa',
    'Address': 'Anwani',
    'City': 'Mji',
    'From': 'Kutoka',
    'To': 'Kwenda',
    'Payment': 'Malipo',
    'Cash': 'Taslimu',
    'Mobile Money': 'Pesa Mtandao',
    'Card': 'Kadi',
    'Bank Transfer': 'Uhamisho wa Benki',
    'Excel File (.xlsx or .csv)': 'Faili ya Excel (.xlsx au .csv)',
    'Upload Excel .xlsx directly, or use CSV.': 'Pakia Excel .xlsx moja kwa moja, au tumia CSV.',
    'Expected columns:': 'Nguzo zinazotarajiwa:',
    'Max file size is 5MB and max 5000 data rows.': 'Ukubwa wa juu wa faili ni 5MB na mistari ya juu ni 5000.',
    'The importer creates new products and updates existing ones by SKU.': 'Kiingizaji huunda bidhaa mpya na kusasisha zilizopo kwa SKU.',
    'Import': 'Ingiza',
    'Add New Product': 'Ongeza Bidhaa Mpya',
    'Add New Customer': 'Ongeza Mteja Mpya',
    'Add Supplier': 'Ongeza Msambazaji',
    'Add Employee': 'Ongeza Mfanyakazi',
    'Record Expense': 'Rekodi Matumizi',
    'Create Invoice': 'Unda Ankara',
    'Schedule Delivery': 'Panga Uwasilishaji',
    'Record Receiving': 'Rekodi Mapokezi',
    'Create Quotation': 'Unda Nukuu',
    'Create Purchase Order': 'Unda Oda ya Manunuzi',
    'Record Return': 'Rekodi Rejesho',
    'Schedule Appointment': 'Panga Miadi',
    'Compose Message': 'Andika Ujumbe',
    'Confirm Delete': 'Thibitisha Kufuta',
    'Delete': 'Futa',
    'This action cannot be undone.': 'Kitendo hiki hakiwezi kurudishwa.',
    'Are you sure you want to delete this product?': 'Una uhakika unataka kufuta bidhaa hii?',
    'Are you sure you want to delete this customer?': 'Una uhakika unataka kufuta mteja huyu?',
    'Customer deleted!': 'Mteja amefutwa!',
    'Invalid transaction number': 'Namba ya muamala si sahihi',
    'Transaction:': 'Muamala:',
    'Receipt details would appear here': 'Maelezo ya risiti yataonekana hapa',
    'Receipt': 'Risiti',
    'Printing receipt...': 'Inachapisha risiti...',
    'Printing report...': 'Inachapisha ripoti...',
    'Invalid report type': 'Aina ya ripoti si sahihi',
    'At least one location row is required.': 'Angalau mstari mmoja wa tawi unahitajika.',
    'Location name': 'Jina la tawi',
    'Could not save the record. Please check your input and try again.': 'Imeshindikana kuhifadhi rekodi. Tafadhali angalia taarifa na ujaribu tena.',
    'Save failed:': 'Hifadhi imeshindikana:',
    'Day closed successfully!': 'Siku imefungwa vizuri!',
    'Print Report': 'Chapisha Ripoti',
    'No suppliers added yet': 'Hakuna wasambazaji bado',
    'No employees added yet': 'Hakuna wafanyakazi bado',
    'No expenses recorded yet': 'Hakuna matumizi bado',
    'No invoices created yet': 'Hakuna ankara bado',
    'No deliveries recorded yet': 'Hakuna uwasilishaji bado',
    'No receiving records yet': 'Hakuna mapokezi bado',
    'No quotations created yet': 'Hakuna nukuu bado',
    'No purchase orders created yet': 'Hakuna oda za manunuzi bado',
    'No returns recorded yet': 'Hakuna marejesho bado',
    'No appointments scheduled yet': 'Hakuna miadi bado',
    'Import Products': 'Ingiza Bidhaa',
    'New Product': 'Bidhaa Mpya',
    'New Customer': 'Mteja Mpya',
    'New Expense': 'Matumizi Mapya',
    'Walk-in Customer': 'Mteja wa Dukani',
    'Enter product name': 'Weka jina la bidhaa',
    'e.g., SKU-PRD-001': 'mfano, SKU-PRD-001',
    'Enter price': 'Weka bei',
    'Enter quantity': 'Weka kiasi',
    'Low stock alert level': 'Kiwango cha tahadhari ya stoo ndogo',
    'Enter customer name': 'Weka jina la mteja',
    'e.g., 255700000000': 'mfano, 255700000000',
    'Supplier Name': 'Jina la Msambazaji',
    'Enter supplier name': 'Weka jina la msambazaji',
    'Contact Person': 'Mtu wa Mawasiliano',
    'Enter contact person': 'Weka mtu wa mawasiliano',
    'Enter phone number': 'Weka namba ya simu',
    'Enter email address': 'Weka anwani ya barua pepe',
    'Employee Name': 'Jina la Mfanyakazi',
    'Enter employee name': 'Weka jina la mfanyakazi',
    'e.g. Cashier': 'mfano, Karani',
    'Salary (Tsh)': 'Mshahara (Tsh)',
    'Enter salary': 'Weka mshahara',
    'Expense description': 'Maelezo ya matumizi',
    'Utilities, Transport, etc.': 'Huduma, Usafiri, n.k.',
    'Customer ID': 'Kitambulisho cha Mteja',
    'Enter customer ID': 'Weka kitambulisho cha mteja',
    'Supplier ID': 'Kitambulisho cha Msambazaji',
    'Enter supplier ID': 'Weka kitambulisho cha msambazaji',
    'Product ID': 'Kitambulisho cha Bidhaa',
    'Enter product ID': 'Weka kitambulisho cha bidhaa',
    'Optional reason': 'Sababu (si lazima)',
    'Appointment title': 'Kichwa cha miadi',
    'Location Name': 'Jina la Tawi',
    'Enter location name': 'Weka jina la tawi',
    'Enter city': 'Weka mji',
    'Enter phone': 'Weka simu',
    'Send Message': 'Tuma Ujumbe',
    'recipient@example.com': 'mpokeaji@example.com',
    'Message subject': 'Mada ya ujumbe',
    'Type your message': 'Andika ujumbe wako',
    'Transactions': 'Miamala',
    'Daily Sales': 'Mauzo ya Siku',
    'Monthly Sales': 'Mauzo ya Mwezi',
    'Close': 'Funga',
    'Sale created successfully!': 'Uuzaji umeundwa vizuri!',
    'Product added successfully!': 'Bidhaa imeongezwa vizuri!',
    'Customer added successfully!': 'Mteja ameongezwa vizuri!',
    'Edit functionality requires API integration': 'Kazi ya kuhariri inahitaji kuunganishwa na API',
    'View functionality requires API integration': 'Kazi ya kutazama inahitaji kuunganishwa na API',
    'Product deleted successfully!': 'Bidhaa imefutwa vizuri!',
    'Exporting ': 'Inahamisha ',
    ' Report PDF...': ' Ripoti PDF...',
    'Mobile Money Provider': 'Mtoa Huduma ya Pesa Mtandao',
    'Mobile Number': 'Namba ya Simu ya Malipo',
    'Payment Reference (Optional)': 'Kumbukumbu ya Malipo (si lazima)',
    '07XXXXXXXX or 2557XXXXXXXX': '07XXXXXXXX au 2557XXXXXXXX',
    'Invoice number or note': 'Namba ya ankara au maelezo',
    'Mobile Money Gateway': 'Lango la Pesa Mtandao',
    'Gateway Mode': 'Hali ya Lango',
    'Mock (Testing)': 'Mock (Majaribio)',
    'Live': 'Halisi',
    'Gateway Timeout (seconds)': 'Muda wa Kusubiri Lango (sekunde)',
    'M-Pesa (Vodacom)': 'M-Pesa (Vodacom)',
    'M-Pesa API URL': 'URL ya API ya M-Pesa',
    'M-Pesa API Token': 'Token ya API ya M-Pesa',
    'Enter token': 'Weka token',
    'M-Pesa Business ID': 'Kitambulisho cha Biashara cha M-Pesa',
    'Paybill/Till number': 'Namba ya Paybill/Till',
    'M-Pesa Command': 'Amri ya M-Pesa',
    'Tigo Pesa API URL': 'URL ya API ya Tigo Pesa',
    'Tigo Pesa API Token': 'Token ya API ya Tigo Pesa',
    'Airtel Money API URL': 'URL ya API ya Airtel Money',
    'Airtel Money API Token': 'Token ya API ya Airtel Money',
    'Callback Secret': 'Siri ya Callback',
    'Set long secret token': 'Weka token ndefu ya siri',
    'Callback URL': 'URL ya Callback',
    'Mobile Money Payment Status': 'Hali ya Malipo ya Pesa Mtandao',
    'Provider': 'Mtoa Huduma',
    'Reference': 'Kumbukumbu',
    'Sale': 'Uuzaji',
    'No mobile money payments yet': 'Bado hakuna malipo ya pesa mtandao'
};

const I18N_KEYS_SORTED = Object.keys(I18N_TEXT).sort((a, b) => b.length - a.length);

function getCurrentLanguage() {
    return localStorage.getItem('pos_language') === 'sw' ? 'sw' : 'en';
}

function translateValue(value, targetLang) {
    if (!value || typeof value !== 'string') {
        return value;
    }

    if (targetLang !== 'sw') {
        return value;
    }

    let translated = value;
    I18N_KEYS_SORTED.forEach(en => {
        translated = translated.split(en).join(I18N_TEXT[en]);
    });

    return translated;
}

function applyLanguage(targetLang) {
    document.documentElement.lang = targetLang === 'sw' ? 'sw' : 'en';

    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    let node = walker.nextNode();
    while (node) {
        if (node.parentElement && ['SCRIPT', 'STYLE'].includes(node.parentElement.tagName)) {
            node = walker.nextNode();
            continue;
        }

        const rawText = node.nodeValue || '';
        if (rawText.trim() !== '') {
            if (typeof node.__i18nOriginal === 'undefined') {
                node.__i18nOriginal = rawText;
            }
            node.nodeValue = targetLang === 'sw'
                ? translateValue(node.__i18nOriginal, 'sw')
                : node.__i18nOriginal;
        }
        node = walker.nextNode();
    }

    document.querySelectorAll('input[placeholder], textarea[placeholder]').forEach(input => {
        if (!input.dataset.i18nPlaceholder) {
            input.dataset.i18nPlaceholder = input.getAttribute('placeholder') || '';
        }

        const source = input.dataset.i18nPlaceholder || '';
        input.setAttribute('placeholder', targetLang === 'sw' ? translateValue(source, 'sw') : source);
    });

    document.querySelectorAll('[title]').forEach(el => {
        if (!el.dataset.i18nTitle) {
            el.dataset.i18nTitle = el.getAttribute('title') || '';
        }

        const source = el.dataset.i18nTitle || '';
        el.setAttribute('title', targetLang === 'sw' ? translateValue(source, 'sw') : source);
    });

    if (!window.__i18nOriginalTitle) {
        window.__i18nOriginalTitle = document.title;
    }
    document.title = targetLang === 'sw'
        ? translateValue(window.__i18nOriginalTitle, 'sw')
        : window.__i18nOriginalTitle;

    updateLanguageButtons(targetLang);
}

function updateLanguageButtons(lang) {
    document.querySelectorAll('.lang-btn').forEach(btn => {
        const value = (btn.getAttribute('data-value') || 'en').toLowerCase();
        btn.classList.toggle('active', value === lang);
    });
}

function setLanguage(lang) {
    const target = lang === 'sw' ? 'sw' : 'en';
    localStorage.setItem('pos_language', target);
    applyLanguage(target);
}

function initLanguage() {
    applyLanguage(getCurrentLanguage());
}

// ============================================
// INITIALIZATION
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    initLanguage();
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
            addLocationRow,
            saveSettings,
            closeModal,
            closeNotifications,
        };

        if (action === 'removeLocationRow') {
            removeLocationRow(target);
            return;
        }

        if (action === 'go') {
            if (/^\?page=[A-Za-z0-9\-]+$/.test(value)) {
                window.location.href = value;
            }
            return;
        }

        if (action === 'setLanguage') {
            setLanguage(value);
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
    applyLanguage(getCurrentLanguage());
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
                <select id="salePaymentMethod" name="payment_method">
                    <option value="Cash">Cash</option>
                    <option value="Mobile Money">Mobile Money</option>
                    <option value="Card">Card</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                </select>
            </div>
            <div id="mobileMoneyFields" style="display:none; border:1px solid #E5E7EB; border-radius:10px; padding:12px; background:#F9FAFB;">
                <div class="form-group">
                    <label>Mobile Money Provider</label>
                    <select id="mobileMoneyProvider" name="mobile_money_provider">
                        <option value="mpesa">M-Pesa</option>
                        <option value="tigo_pesa">Tigo Pesa</option>
                        <option value="airtel_money">Airtel Money</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Mobile Number</label>
                    <input id="mobileMoneyPhone" type="tel" name="mobile_money_phone" placeholder="07XXXXXXXX or 2557XXXXXXXX">
                </div>
                <div class="form-group">
                    <label>Payment Reference (Optional)</label>
                    <input type="text" name="mobile_money_reference" placeholder="Invoice number or note">
                </div>
            </div>
        </form>
    `;
    openModal('Create New Sale', content, [
        { text: 'Cancel', class: 'btn-secondary', onclick: 'closeModal()' },
        { text: 'Create Sale', class: 'btn-primary', onclick: 'document.getElementById("newSaleForm").requestSubmit()' }
    ]);

    const paymentMethodEl = document.getElementById('salePaymentMethod');
    const mobileMoneyFields = document.getElementById('mobileMoneyFields');
    const mobileMoneyProvider = document.getElementById('mobileMoneyProvider');
    const mobileMoneyPhone = document.getElementById('mobileMoneyPhone');

    const toggleMobileMoneyFields = () => {
        const isMobileMoney = paymentMethodEl?.value === 'Mobile Money';
        if (mobileMoneyFields) {
            mobileMoneyFields.style.display = isMobileMoney ? 'block' : 'none';
        }
        if (mobileMoneyProvider) {
            mobileMoneyProvider.required = !!isMobileMoney;
        }
        if (mobileMoneyPhone) {
            mobileMoneyPhone.required = !!isMobileMoney;
        }
    };

    paymentMethodEl?.addEventListener('change', toggleMobileMoneyFields);
    toggleMobileMoneyFields();
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

function addLocationRow() {
    const rows = document.getElementById('locationRows');
    if (!rows) {
        return;
    }

    const defaultCityInput = document.getElementById('defaultCity');
    const defaultCity = escapeHtml(defaultCityInput?.value || 'Dar es Salaam');

    const row = document.createElement('div');
    row.setAttribute('data-location-row', '');
    row.className = 'location-row';

    row.innerHTML = `
        <input type="text" name="location_name[]" placeholder="Location name" class="form-control">
        <input type="text" name="location_address[]" placeholder="Address" class="form-control">
        <input type="text" name="location_city[]" value="${defaultCity}" placeholder="City" class="form-control">
        <input type="text" name="location_phone[]" placeholder="Phone" class="form-control">
        <button type="button" class="btn btn-secondary" data-action="removeLocationRow"><i class="fa-solid fa-minus"></i></button>
    `;

    rows.appendChild(row);
}

function removeLocationRow(trigger) {
    const rows = document.getElementById('locationRows');
    if (!rows) {
        return;
    }

    const rowElements = rows.querySelectorAll('[data-location-row]');
    if (rowElements.length <= 1) {
        showToast('warning', 'At least one location row is required.');
        return;
    }

    const row = trigger.closest('[data-location-row]');
    if (row) {
        row.remove();
    }
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
    const localizedMessage = getCurrentLanguage() === 'sw' ? translateValue(message, 'sw') : message;

    const icons = {
        success: 'fa-check-circle',
        error: 'fa-times-circle',
        warning: 'fa-exclamation-triangle'
    };

    // Create elements safely to prevent XSS
    const icon = document.createElement('i');
    icon.className = `fa-solid ${icons[type] || 'fa-info-circle'}`;

    const span = document.createElement('span');
    span.textContent = localizedMessage; // Safe: textContent escapes HTML

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
    if (getCurrentLanguage() === 'sw') {
        const localized = translateValue(names[type], 'sw');
        showToast('success', `Inahamisha PDF ya Ripoti ya ${localized}...`);
    } else {
        showToast('success', `Exporting ${names[type]} Report PDF...`);
    }
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
