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
  if (str === null || str === undefined) return "";
  const div = document.createElement("div");
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
  const configEl = document.getElementById("appConfig");
  if (!configEl) {
    return {
      salesChartData: {
        week: { labels: [], values: [] },
        month: { labels: [], values: [] },
      },
      currentPage: "dashboard",
      csrfToken: "",
      canManageProducts: false,
      inventoryProducts: [],
    };
  }

  try {
    return JSON.parse(configEl.textContent || "{}");
  } catch (error) {
    return {
      salesChartData: {
        week: { labels: [], values: [] },
        month: { labels: [], values: [] },
      },
      currentPage: "dashboard",
      csrfToken: "",
      canManageProducts: false,
      inventoryProducts: [],
    };
  }
}

const APP_CONFIG = getAppConfig();

const I18N_TEXT = {
  Dashboard: "Dashibodi",
  Inventory: "Hesabu",
  Customers: "Wateja",
  Suppliers: "Wasambazaji",
  Reports: "Ripoti",
  Receiving: "Mapokezi",
  Sales: "Mauzo",
  Deliveries: "Uwasilishaji",
  Expenses: "Matumizi",
  Appointments: "Miadi",
  Employees: "Wafanyakazi",
  "Store Config": "Mipangilio",
  Invoices: "Ankara",
  Quotations: "Nukuu",
  "Purchase Orders": "Oda za Manunuzi",
  Returns: "Marejesho",
  Locations: "Matawi",
  Messages: "Ujumbe",
  Logout: "Toka",
  Add: "Ongeza",
  Shop: "Duka",
  "Product Inventory": "Hesabu ya Bidhaa",
  "Manage your products and stock levels": "Simamia bidhaa na viwango vya stoo",
  "Import Excel": "Ingiza Excel",
  "Export XLSX": "Hamisha XLSX",
  "Add Product": "Ongeza Bidhaa",
  "Search products...": "Tafuta bidhaa...",
  "All Products": "Bidhaa Zote",
  "Low Stock Only": "Stoo Ndogo Tu",
  "In Stock": "Stoo Ipo",
  "Product Name": "Jina la Bidhaa",
  "Stock Qty": "Kiasi Stoo",
  "Reorder Level": "Kiwango cha Kuagiza",
  "Unit Price": "Bei ya Kipande",
  Status: "Hali",
  Actions: "Vitendo",
  "Low Stock": "Stoo Ndogo",
  "Customer Management": "Usimamizi wa Wateja",
  "View and manage your customers": "Tazama na simamia wateja wako",
  "Add Customer": "Ongeza Mteja",
  "Search customers...": "Tafuta wateja...",
  "Customer Name": "Jina la Mteja",
  Phone: "Simu",
  "Total Orders": "Jumla ya Oda",
  "Total Spent": "Jumla Iliyotumika",
  "Member Since": "Mwanachama Tangu",
  "Point of Sale": "Sehemu ya Mauzo",
  "Create and manage sales": "Unda na simamia mauzo",
  "New Sale": "Uuzaji Mpya",
  "Transaction History": "Historia ya Miamala",
  "View all transactions": "Tazama miamala yote",
  "Search transactions...": "Tafuta miamala...",
  "All Payments": "Malipo Yote",
  "Transaction #": "Namba ya Muamala",
  "Payment Method": "Njia ya Malipo",
  "Date & Time": "Tarehe na Muda",
  "View Receipt": "Tazama Risiti",
  Print: "Chapisha",
  "Reports & Analytics": "Ripoti na Uchambuzi",
  "View business insights and generate reports":
    "Tazama takwimu za biashara na toa ripoti",
  "Daily Sales Report": "Ripoti ya Mauzo ya Siku",
  "View today's sales summary and transactions":
    "Tazama muhtasari wa mauzo ya leo na miamala",
  "Weekly Sales Report": "Ripoti ya Mauzo ya Wiki",
  "Sales performance for the past 7 days":
    "Utendaji wa mauzo kwa siku 7 zilizopita",
  "Monthly Sales Report": "Ripoti ya Mauzo ya Mwezi",
  "Complete monthly breakdown and trends": "Muhtasari wa mwezi na mwelekeo",
  "Inventory Report": "Ripoti ya Hesabu",
  "Stock levels and low inventory alerts":
    "Viwango vya stoo na tahadhari za stoo ndogo",
  "Customer Report": "Ripoti ya Wateja",
  "Customer purchases and loyalty insights": "Manunuzi ya wateja na uaminifu",
  "Profit & Loss": "Faida na Hasara",
  "Revenue, expenses, and profit margins":
    "Mapato, matumizi na kiwango cha faida",
  "Suppliers Management": "Usimamizi wa Wasambazaji",
  "Add Supplier": "Ongeza Msambazaji",
  "No suppliers added yet": "Hakuna msambazaji aliyeongezwa bado",
  "Employees Management": "Usimamizi wa Wafanyakazi",
  "Add Employee": "Ongeza Mfanyakazi",
  "No employees added yet": "Hakuna mfanyakazi aliyeongezwa bado",
  "Expenses Management": "Usimamizi wa Matumizi",
  "Add Expense": "Ongeza Matumizi",
  "No expenses recorded yet": "Hakuna matumizi yaliyorekodiwa bado",
  "Invoices Management": "Usimamizi wa Ankara",
  "Create Invoice": "Unda Ankara",
  "No invoices created yet": "Hakuna ankara iliyoundwa bado",
  "Deliveries Management": "Usimamizi wa Uwasilishaji",
  "Add Delivery": "Ongeza Uwasilishaji",
  "No deliveries recorded yet": "Hakuna uwasilishaji uliorekodiwa bado",
  "Receiving Management": "Usimamizi wa Mapokezi",
  "Add Receiving": "Ongeza Mapokezi",
  "No receiving records yet": "Hakuna rekodi za mapokezi bado",
  "Quotations Management": "Usimamizi wa Nukuu",
  "Create Quotation": "Unda Nukuu",
  "No quotations created yet": "Hakuna nukuu iliyoundwa bado",
  "Purchase Orders Management": "Usimamizi wa Oda za Manunuzi",
  "Create PO": "Unda Oda",
  "No purchase orders created yet": "Hakuna oda ya manunuzi iliyoundwa bado",
  "Returns Management": "Usimamizi wa Marejesho",
  "Add Return": "Ongeza Rejesho",
  "No returns recorded yet": "Hakuna marejesho yaliyorekodiwa bado",
  "Appointments Management": "Usimamizi wa Miadi",
  "Schedule Appointment": "Panga Miadi",
  "No appointments scheduled yet": "Hakuna miadi iliyopangwa bado",
  "Store Locations": "Matawi ya Duka",
  "No locations added yet": "Hakuna tawi lililoongezwa bado",
  Messages: "Ujumbe",
  "New Message": "Ujumbe Mpya",
  "No messages yet": "Hakuna ujumbe bado",
  "Store Name": "Jina la Duka",
  "Store Email": "Barua Pepe ya Duka",
  "Store Phone": "Simu ya Duka",
  "Store Address": "Anwani ya Duka",
  "Default City": "Mji Chaguo-msingi",
  "Starting Amount (Tsh)": "Kiasi cha Kuanza (Tsh)",
  Profile: "Wasifu",
  Setup: "Mipangilio",
  Cash: "Pesa Taslimu",
  "Add Location": "Ongeza Tawi",
  Save: "Hifadhi",
  "Cash, city, branches.": "Pesa, mji, matawi.",
  "Settings saved successfully!": "Mipangilio imehifadhiwa vizuri!",
  "Quick Add": "Ongeza Haraka",
  Cancel: "Ghairi",
  "Create New Sale": "Unda Uuzaji Mpya",
  Customer: "Mteja",
  "Amount (Tsh)": "Kiasi (Tsh)",
  "Enter amount": "Weka kiasi",
  "Create Sale": "Unda Uuzaji",
  "Add New Product": "Ongeza Bidhaa Mpya",
  SKU: "SKU",
  "Unit Price (Tsh)": "Bei ya Kipande (Tsh)",
  "Stock Quantity": "Kiasi cha Stoo",
  "Reorder Level": "Kiwango cha Kuagiza",
  "Add New Customer": "Ongeza Mteja Mpya",
  "Phone Number": "Namba ya Simu",
  "Record Expense": "Rekodi Matumizi",
  Description: "Maelezo",
  Category: "Kundi",
  "Compose Message": "Andika Ujumbe",
  Recipient: "Mpokeaji",
  Subject: "Mada",
  Message: "Ujumbe",
  "Confirm Logout": "Thibitisha Kutoka",
  "Are you sure you want to logout?": "Una uhakika unataka kutoka?",
  "End of Day Summary": "Muhtasari wa Mwisho wa Siku",
  "Close Day": "Funga Siku",
  Notifications: "Arifa",
  "Low Stock Alert": "Tahadhari ya Stoo Ndogo",
  "products need restocking": "bidhaa zinahitaji kujazwa stoo",
  "All Good!": "Kila kitu sawa!",
  "No alerts at this time": "Hakuna tahadhari kwa sasa",
  "Back to Dashboard": "Rudi Dashibodi",
  "This section is under construction and will be available soon.":
    "Sehemu hii inaandaliwa na itapatikana hivi karibuni.",
  "Welcome to Mchongoma Limited,": "Karibu Mchongoma Limited,",
  "Choose a common task below to get started.":
    "Chagua kazi hapa chini kuanza.",
  "Start a New Sale": "Anza Uuzaji Mpya",
  "View All Products": "Tazama Bidhaa Zote",
  "View Customers": "Tazama Wateja",
  "View All Reports": "Tazama Ripoti Zote",
  "All Transactions": "Miamala Yote",
  "Manage Suppliers": "Simamia Wasambazaji",
  "End of Day Report": "Ripoti ya Mwisho wa Siku",
  "Total Sales": "Jumla ya Mauzo",
  "Total Customers": "Jumla ya Wateja",
  "Total Products": "Jumla ya Bidhaa",
  "Total Stock": "Jumla ya Stoo",
  "Transactions Today": "Miamala ya Leo",
  "Setup Check": "Ukaguzi wa Mfumo",
  "PDO MySQL Driver": "Kifaa cha PDO MySQL",
  "MySQL Connection": "Muunganisho wa MySQL",
  "Core Tables": "Jedwali Muhimu",
  Loaded: "Imepakiwa",
  Missing: "Haipo",
  Connected: "Imeunganishwa",
  Failed: "Imeshindwa",
  Ready: "Tayari",
  "If any item shows Missing/Failed, enable pdo_mysql in php.ini, restart Apache, start MySQL, and import sql/schema.sql.":
    "Kama kipengele chochote kinaonyesha Haipo/Imeshindwa, washa pdo_mysql kwenye php.ini, anzisha upya Apache, washa MySQL, kisha ingiza sql/schema.sql.",
  "Sales Information": "Taarifa za Mauzo",
  Week: "Wiki",
  Month: "Mwezi",
  "Low Stock Alerts": "Tahadhari za Stoo Ndogo",
  left: "imebaki",
  "Recent Sales": "Mauzo ya Hivi Karibuni",
  "Error:": "Hitilafu:",
  Name: "Jina",
  Contact: "Mawasiliano",
  Email: "Barua Pepe",
  Position: "Cheo",
  Salary: "Mshahara",
  Date: "Tarehe",
  Reason: "Sababu",
  Quantity: "Kiasi",
  Title: "Kichwa",
  Address: "Anwani",
  City: "Mji",
  From: "Kutoka",
  To: "Kwenda",
  Payment: "Malipo",
  Cash: "Taslimu",
  "Mobile Money": "Pesa Mtandao",
  Card: "Kadi",
  "Bank Transfer": "Uhamisho wa Benki",
  "Excel File (.xlsx or .csv)": "Faili ya Excel (.xlsx au .csv)",
  "Upload Excel .xlsx directly, or use CSV.":
    "Pakia Excel .xlsx moja kwa moja, au tumia CSV.",
  "Expected columns:": "Nguzo zinazotarajiwa:",
  "Max file size is 25MB and max 20000 data rows.":
    "Ukubwa wa juu wa faili ni 25MB na mistari ya juu ni 20000.",
  "The importer creates new products and updates existing ones by SKU.":
    "Kiingizaji huunda bidhaa mpya na kusasisha zilizopo kwa SKU.",
  Import: "Ingiza",
  "Add New Product": "Ongeza Bidhaa Mpya",
  "Add New Customer": "Ongeza Mteja Mpya",
  "Add Supplier": "Ongeza Msambazaji",
  "Add Employee": "Ongeza Mfanyakazi",
  "Record Expense": "Rekodi Matumizi",
  "Create Invoice": "Unda Ankara",
  "Schedule Delivery": "Panga Uwasilishaji",
  "Record Receiving": "Rekodi Mapokezi",
  "Create Quotation": "Unda Nukuu",
  "Create Purchase Order": "Unda Oda ya Manunuzi",
  "Record Return": "Rekodi Rejesho",
  "Schedule Appointment": "Panga Miadi",
  "Compose Message": "Andika Ujumbe",
  "Confirm Delete": "Thibitisha Kufuta",
  Delete: "Futa",
  "This action cannot be undone.": "Kitendo hiki hakiwezi kurudishwa.",
  "Are you sure you want to delete this product?":
    "Una uhakika unataka kufuta bidhaa hii?",
  "Are you sure you want to delete this customer?":
    "Una uhakika unataka kufuta mteja huyu?",
  "Customer deleted!": "Mteja amefutwa!",
  "Invalid transaction number": "Namba ya muamala si sahihi",
  "Transaction:": "Muamala:",
  "Receipt details would appear here": "Maelezo ya risiti yataonekana hapa",
  Receipt: "Risiti",
  "Printing receipt...": "Inachapisha risiti...",
  "Printing report...": "Inachapisha ripoti...",
  "Invalid report type": "Aina ya ripoti si sahihi",
  "At least one location row is required.":
    "Angalau mstari mmoja wa tawi unahitajika.",
  "Location name": "Jina la tawi",
  "Could not save the record. Please check your input and try again.":
    "Imeshindikana kuhifadhi rekodi. Tafadhali angalia taarifa na ujaribu tena.",
  "Save failed:": "Hifadhi imeshindikana:",
  "Day closed successfully!": "Siku imefungwa vizuri!",
  "Print Report": "Chapisha Ripoti",
  "No suppliers added yet": "Hakuna wasambazaji bado",
  "No employees added yet": "Hakuna wafanyakazi bado",
  "No expenses recorded yet": "Hakuna matumizi bado",
  "No invoices created yet": "Hakuna ankara bado",
  "No deliveries recorded yet": "Hakuna uwasilishaji bado",
  "No receiving records yet": "Hakuna mapokezi bado",
  "No quotations created yet": "Hakuna nukuu bado",
  "No purchase orders created yet": "Hakuna oda za manunuzi bado",
  "No returns recorded yet": "Hakuna marejesho bado",
  "No appointments scheduled yet": "Hakuna miadi bado",
  "Import Products": "Ingiza Bidhaa",
  "New Product": "Bidhaa Mpya",
  "New Customer": "Mteja Mpya",
  "New Expense": "Matumizi Mapya",
  "Walk-in Customer": "Mteja wa Dukani",
  "Enter product name": "Weka jina la bidhaa",
  "e.g., SKU-PRD-001": "mfano, SKU-PRD-001",
  "Enter price": "Weka bei",
  "Enter quantity": "Weka kiasi",
  "Low stock alert level": "Kiwango cha tahadhari ya stoo ndogo",
  "Enter customer name": "Weka jina la mteja",
  "e.g., 255700000000": "mfano, 255700000000",
  "Supplier Name": "Jina la Msambazaji",
  "Enter supplier name": "Weka jina la msambazaji",
  "Contact Person": "Mtu wa Mawasiliano",
  "Enter contact person": "Weka mtu wa mawasiliano",
  "Enter phone number": "Weka namba ya simu",
  "Enter email address": "Weka anwani ya barua pepe",
  "Employee Name": "Jina la Mfanyakazi",
  "Enter employee name": "Weka jina la mfanyakazi",
  "e.g. Cashier": "mfano, Karani",
  "Salary (Tsh)": "Mshahara (Tsh)",
  "Enter salary": "Weka mshahara",
  "Expense description": "Maelezo ya matumizi",
  "Utilities, Transport, etc.": "Huduma, Usafiri, n.k.",
  "Customer ID": "Kitambulisho cha Mteja",
  "Enter customer ID": "Weka kitambulisho cha mteja",
  "Supplier ID": "Kitambulisho cha Msambazaji",
  "Enter supplier ID": "Weka kitambulisho cha msambazaji",
  "Product ID": "Kitambulisho cha Bidhaa",
  "Enter product ID": "Weka kitambulisho cha bidhaa",
  "Optional reason": "Sababu (si lazima)",
  "Appointment title": "Kichwa cha miadi",
  "Location Name": "Jina la Tawi",
  "Enter location name": "Weka jina la tawi",
  "Enter city": "Weka mji",
  "Enter phone": "Weka simu",
  "Send Message": "Tuma Ujumbe",
  "recipient@example.com": "mpokeaji@example.com",
  "Message subject": "Mada ya ujumbe",
  "Type your message": "Andika ujumbe wako",
  Transactions: "Miamala",
  "Daily Sales": "Mauzo ya Siku",
  "Monthly Sales": "Mauzo ya Mwezi",
  Close: "Funga",
  "Sale created successfully!": "Uuzaji umeundwa vizuri!",
  "Product added successfully!": "Bidhaa imeongezwa vizuri!",
  "Customer added successfully!": "Mteja ameongezwa vizuri!",
  "Edit functionality requires API integration":
    "Kazi ya kuhariri inahitaji kuunganishwa na API",
  "View functionality requires API integration":
    "Kazi ya kutazama inahitaji kuunganishwa na API",
  "Product deleted successfully!": "Bidhaa imefutwa vizuri!",
  "Exporting ": "Inahamisha ",
  " Report PDF...": " Ripoti PDF...",
  "Mobile Money Provider": "Mtoa Huduma ya Pesa Mtandao",
  "Mobile Number": "Namba ya Simu ya Malipo",
  "Payment Reference (Optional)": "Kumbukumbu ya Malipo (si lazima)",
  "07XXXXXXXX or 2557XXXXXXXX": "07XXXXXXXX au 2557XXXXXXXX",
  "Invoice number or note": "Namba ya ankara au maelezo",
  "Product Category": "Kundi la Bidhaa",
  "e.g., Beverages, Grocery": "mfano, Vinywaji, Vyakula",
  Product: "Bidhaa",
  "Select product": "Chagua bidhaa",
  "Search products...": "Tafuta bidhaa...",
  Quantity: "Idadi",
  "No products available": "Hakuna bidhaa zilizopo",
  "Stock:": "Stoo:",
  "Mobile Money Gateway": "Lango la Pesa Mtandao",
  "Gateway Mode": "Hali ya Lango",
  "Mock (Testing)": "Mock (Majaribio)",
  Live: "Halisi",
  "Gateway Timeout (seconds)": "Muda wa Kusubiri Lango (sekunde)",
  "M-Pesa (Vodacom)": "M-Pesa (Vodacom)",
  "M-Pesa API URL": "URL ya API ya M-Pesa",
  "M-Pesa API Token": "Token ya API ya M-Pesa",
  "Enter token": "Weka token",
  "M-Pesa Business ID": "Kitambulisho cha Biashara cha M-Pesa",
  "Paybill/Till number": "Namba ya Paybill/Till",
  "M-Pesa Command": "Amri ya M-Pesa",
  "Tigo Pesa API URL": "URL ya API ya Tigo Pesa",
  "Tigo Pesa API Token": "Token ya API ya Tigo Pesa",
  "Airtel Money API URL": "URL ya API ya Airtel Money",
  "Airtel Money API Token": "Token ya API ya Airtel Money",
  "Callback Secret": "Siri ya Callback",
  "Set long secret token": "Weka token ndefu ya siri",
  "Callback URL": "URL ya Callback",
  "Mobile Money Payment Status": "Hali ya Malipo ya Pesa Mtandao",
  Provider: "Mtoa Huduma",
  Reference: "Kumbukumbu",
  Sale: "Uuzaji",
  "No mobile money payments yet": "Bado hakuna malipo ya pesa mtandao",
  Edit: "Hariri",
  View: "Tazama",
  "Print Statement": "Chapisha Taarifa",
  "Receive Payment": "Pokea Malipo",
  "Export Debt XLSX": "Hamisha Deni XLSX",
  "Export Debt PDF": "Hamisha Deni PDF",
  "Outstanding Debt": "Deni Lililobaki",
  "Customer Debt Ledger": "Daftari la Deni la Wateja",
  "All Debts": "Madeni Yote",
  "Open/Partial": "Wazi/Sehemu",
  "Overdue Only": "Yaliyochelewa Tu",
  "Due Date": "Tarehe ya Mwisho",
  Overdue: "Imechelewa",
  Paid: "Imelipwa",
  Open: "Wazi",
  Partial: "Sehemu",
  "Automatic Reminder Notes": "Vidokezo vya Kikumbusho Otomatiki",
  "Debt Reminder Notes": "Vidokezo vya Kikumbusho cha Deni",
  "No overdue customer debts today.":
    "Hakuna madeni ya wateja yaliyochelewa leo.",
  "No outstanding customer debts": "Hakuna madeni ya wateja yaliyobaki",
  Reminder: "Kikumbusho",
  owes: "anadaiwa",
  "overdue by": "amechelewa kwa",
  "day(s).": "siku.",
  "Due date was": "Tarehe ya mwisho ilikuwa",
  "Pay Later": "Lipa Baadaye",
  "Discount (Tsh)": "Punguzo (Tsh)",
  Subtotal: "Jumla Ndogo",
  Tax: "Kodi",
  Total: "Jumla",
  Checkout: "Malipo",
  "Complete Payment": "Kamilisha Malipo",
  "Save Credit Sale": "Hifadhi Uuzaji wa Deni",
  Processing: "Inachakata",
  "Processing...": "Inachakata...",
  "Credit Note (Optional)": "Maelezo ya Deni (si lazima)",
  "Customer will pay later": "Mteja atalipa baadaye",
  "No products selected yet.": "Hakuna bidhaa zilizochaguliwa bado.",
  Remove: "Ondoa",
  each: "kila moja",
  "Recent Credit Activity": "Shughuli za Hivi Karibuni za Deni",
  "Customer Details": "Maelezo ya Mteja",
  "Record Payment": "Rekodi Malipo",
  "No credit records for this customer.":
    "Hakuna rekodi za deni kwa mteja huyu.",
  "This customer has no outstanding debt.": "Mteja huyu hana deni lililobaki.",
  "Credit Sale": "Uuzaji wa Deni",
  "Payment Amount (Tsh)": "Kiasi cha Malipo (Tsh)",
  "Reference (Optional)": "Kumbukumbu (si lazima)",
  "Receive Debt Payment": "Pokea Malipo ya Deni",
  "Save Payment": "Hifadhi Malipo",
  "Receipt number or note": "Namba ya risiti au maelezo",
  "Outstanding: Tsh ": "Deni lililobaki: Tsh ",
  "Exporting Daily Report PDF...": "Inahamisha PDF ya Ripoti ya Siku...",
  "Customer debt recorded as pay later.":
    "Deni la mteja limerekodiwa kama lipa baadaye.",
  "Total Credit": "Jumla ya Deni",
  "Total Paid": "Jumla Iliyolipwa",
  Outstanding: "Deni Lililobaki",
  "Due:": "Mwisho:",
  "Sale Ref": "Kumbukumbu ya Uuzaji",
  "Save Changes": "Hifadhi Mabadiliko",
  "Phone:": "Simu:",
  "Name:": "Jina:",
  "Outstanding Tsh ": "Deni lililobaki Tsh ",
  "Payment recorded. Remaining debt: Tsh ":
    "Malipo yamewekwa. Deni lililobaki: Tsh ",
  "Sale created successfully. Stock deducted for ":
    "Uuzaji umeundwa vizuri. Stoo imepunguzwa kwa ",
  " items (qty ": " bidhaa (idadi ",
  Updated: "Imesasishwa",
  "N/A": "Hakuna",
};

const I18N_KEYS_SORTED = Object.keys(I18N_TEXT).sort(
  (a, b) => b.length - a.length,
);

function getCurrentLanguage() {
  return localStorage.getItem("pos_language") === "sw" ? "sw" : "en";
}

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

function replaceTranslatedSegment(source, search, replacement) {
  if (!search) {
    return source;
  }

  const startsWithAlphaNum = /[A-Za-z0-9]/.test(search.charAt(0));
  const endsWithAlphaNum = /[A-Za-z0-9]/.test(search.charAt(search.length - 1));
  const escapedSearch = escapeRegExp(search);

  if (!startsWithAlphaNum && !endsWithAlphaNum) {
    return source.split(search).join(replacement);
  }

  if (startsWithAlphaNum && endsWithAlphaNum) {
    const regex = new RegExp(
      `(^|[^A-Za-z0-9])${escapedSearch}([^A-Za-z0-9]|$)`,
      "g",
    );
    return source.replace(regex, (_, leading, trailing) => {
      return `${leading}${replacement}${trailing}`;
    });
  }

  if (startsWithAlphaNum) {
    const regex = new RegExp(`(^|[^A-Za-z0-9])${escapedSearch}`, "g");
    return source.replace(regex, (_, leading) => `${leading}${replacement}`);
  }

  const regex = new RegExp(`${escapedSearch}([^A-Za-z0-9]|$)`, "g");
  return source.replace(regex, (_, trailing) => `${replacement}${trailing}`);
}

function translateValue(value, targetLang) {
  if (!value || typeof value !== "string") {
    return value;
  }

  if (targetLang !== "sw") {
    return value;
  }

  let translated = value;
  I18N_KEYS_SORTED.forEach((en) => {
    translated = replaceTranslatedSegment(translated, en, I18N_TEXT[en]);
  });

  return translated;
}

function applyLanguage(targetLang) {
  document.documentElement.lang = targetLang === "sw" ? "sw" : "en";

  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  let node = walker.nextNode();
  while (node) {
    if (
      node.parentElement &&
      ["SCRIPT", "STYLE"].includes(node.parentElement.tagName)
    ) {
      node = walker.nextNode();
      continue;
    }

    const rawText = node.nodeValue || "";
    if (rawText.trim() !== "") {
      if (typeof node.__i18nOriginal === "undefined") {
        node.__i18nOriginal = rawText;
      }
      node.nodeValue =
        targetLang === "sw"
          ? translateValue(node.__i18nOriginal, "sw")
          : node.__i18nOriginal;
    }
    node = walker.nextNode();
  }

  document
    .querySelectorAll("input[placeholder], textarea[placeholder]")
    .forEach((input) => {
      if (!input.dataset.i18nPlaceholder) {
        input.dataset.i18nPlaceholder = input.getAttribute("placeholder") || "";
      }

      const source = input.dataset.i18nPlaceholder || "";
      input.setAttribute(
        "placeholder",
        targetLang === "sw" ? translateValue(source, "sw") : source,
      );
    });

  document.querySelectorAll("[title]").forEach((el) => {
    if (!el.dataset.i18nTitle) {
      el.dataset.i18nTitle = el.getAttribute("title") || "";
    }

    const source = el.dataset.i18nTitle || "";
    el.setAttribute(
      "title",
      targetLang === "sw" ? translateValue(source, "sw") : source,
    );
  });

  if (!window.__i18nOriginalTitle) {
    window.__i18nOriginalTitle = document.title;
  }
  document.title =
    targetLang === "sw"
      ? translateValue(window.__i18nOriginalTitle, "sw")
      : window.__i18nOriginalTitle;

  updateLanguageButtons(targetLang);
}

function updateLanguageButtons(lang) {
  document.querySelectorAll(".lang-btn").forEach((btn) => {
    const value = (btn.getAttribute("data-value") || "en").toLowerCase();
    btn.classList.toggle("active", value === lang);
  });
}

function setLanguage(lang) {
  const target = lang === "sw" ? "sw" : "en";
  localStorage.setItem("pos_language", target);
  applyLanguage(target);
}

function initLanguage() {
  const lang = getCurrentLanguage();
  if (lang === "sw") {
    applyLanguage("sw");
    return;
  }

  // Default English content is already rendered server-side.
  updateLanguageButtons("en");
}

// ============================================
// INITIALIZATION
// ============================================

document.addEventListener("DOMContentLoaded", function () {
  initLanguage();
  initActionBindings();
  initClock();
  initChart();
  initChartTabs();
  initSidebarOverlay();
  initKeyboardShortcuts();
  initTableFilters();
  initCustomerDebtFilters();
  initSecurityLogsFilters();
  initSalesPage();
});

// ============================================
// REAL-TIME CLOCK
// ============================================

function initClock() {
  const clockEl = document.getElementById("clock");
  if (!clockEl) return;

  function updateClock() {
    const now = new Date();
    const hours = now.getHours();
    const minutes = now.getMinutes().toString().padStart(2, "0");
    const ampm = hours >= 12 ? "PM" : "AM";
    const displayHours = hours % 12 || 12;

    const day = now.getDate().toString().padStart(2, "0");
    const month = (now.getMonth() + 1).toString().padStart(2, "0");
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
  const ctx = document.getElementById("salesChart");
  if (!ctx) return;

  const chartData = APP_CONFIG.salesChartData?.week || {
    labels: [],
    values: [],
  };

  salesChart = new Chart(ctx, {
    type: "bar",
    data: {
      labels: chartData.labels,
      datasets: [
        {
          label: "Sales",
          data: chartData.values,
          backgroundColor: "#4F46E5",
          borderRadius: 8,
          barThickness: 24,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { display: false },
        tooltip: {
          backgroundColor: "#1F2937",
          titleColor: "#F9FAFB",
          bodyColor: "#F9FAFB",
          padding: 12,
          cornerRadius: 8,
          displayColors: false,
          callbacks: {
            label: function (context) {
              const value = context.parsed.y || 0;
              return "Tsh " + new Intl.NumberFormat().format(value);
            },
          },
        },
      },
      scales: {
        x: {
          grid: { display: false },
          ticks: {
            color: "#9CA3AF",
            font: { family: "Poppins", size: 11 },
          },
        },
        y: {
          beginAtZero: true,
          ticks: {
            color: "#9CA3AF",
            font: { family: "Poppins", size: 11 },
            callback: function (value) {
              if (value >= 1000000) {
                return (value / 1000000).toFixed(1) + "M";
              } else if (value >= 1000) {
                return (value / 1000).toFixed(0) + "K";
              }
              return value.toLocaleString();
            },
          },
          grid: {
            color: "#F3F4F6",
          },
          border: { display: false },
        },
      },
      animation: {
        duration: 500,
        easing: "easeOutQuart",
      },
    },
  });
}

function initChartTabs() {
  const tabs = document.getElementById("chartTabs");
  if (!tabs) return;

  tabs.querySelectorAll("span").forEach((tab) => {
    tab.addEventListener("click", function () {
      // Update active tab
      tabs
        .querySelectorAll("span")
        .forEach((t) => t.classList.remove("active"));
      this.classList.add("active");

      // Update chart data
      const period = this.dataset.period;
      const chartData = APP_CONFIG.salesChartData?.[period] || {
        labels: [],
        values: [],
      };

      if (salesChart) {
        salesChart.data.labels = chartData.labels;
        salesChart.data.datasets[0].data = chartData.values;
        salesChart.update("active");
      }
    });
  });
}

// ============================================
// SIDEBAR & MOBILE MENU
// ============================================

function toggleSidebar() {
  const sidebar = document.getElementById("sidebar");
  const overlay = document.querySelector(".sidebar-overlay");

  sidebar.classList.toggle("open");
  if (overlay) {
    overlay.classList.toggle("active");
  }
}

function closeSidebar() {
  const sidebar = document.getElementById("sidebar");
  const overlay = document.querySelector(".sidebar-overlay");

  sidebar.classList.remove("open");
  if (overlay) {
    overlay.classList.remove("active");
  }
}

function initSidebarOverlay() {
  // Create overlay if it doesn't exist
  if (!document.querySelector(".sidebar-overlay")) {
    const overlay = document.createElement("div");
    overlay.className = "sidebar-overlay";
    overlay.onclick = closeSidebar;
    document.body.appendChild(overlay);
  }
}

function initActionBindings() {
  document.addEventListener("click", function (event) {
    const target = event.target.closest("[data-action]");
    if (!target) {
      return;
    }

    const action = target.getAttribute("data-action") || "";
    const value = target.getAttribute("data-value") || "";

    if (
      target.tagName === "A" ||
      target.tagName === "BUTTON" ||
      action === "go"
    ) {
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
      openAddUserModal,
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

    if (action === "removeLocationRow") {
      removeLocationRow(target);
      return;
    }

    if (action === "go") {
      if (/^\?page=[A-Za-z0-9\-]+$/.test(value)) {
        window.location.href = value;
      }
      return;
    }

    if (action === "setLanguage") {
      setLanguage(value);
      return;
    }

    if (action === "generateReport") {
      generateReport(value);
      return;
    }

    if (action === "editProduct") {
      editProduct(parseInt(value, 10));
      return;
    }

    if (action === "deleteProduct") {
      deleteProduct(parseInt(value, 10));
      return;
    }

    if (action === "viewCustomer") {
      viewCustomer(parseInt(value, 10));
      return;
    }

    if (action === "editCustomer") {
      editCustomer(parseInt(value, 10));
      return;
    }

    if (action === "deleteCustomer") {
      deleteCustomer(parseInt(value, 10));
      return;
    }

    if (action === "deleteAppointment") {
      deleteAppointment(parseInt(value, 10));
      return;
    }

    if (action === "completeAppointment") {
      updateAppointmentStatus(parseInt(value, 10), "Completed");
      return;
    }

    if (action === "cancelAppointment") {
      updateAppointmentStatus(parseInt(value, 10), "Cancelled");
      return;
    }

    if (action === "viewAppointment") {
      viewAppointment(target);
      return;
    }

    if (action === "viewEmployee") {
      viewEmployee(target);
      return;
    }

    if (action === "viewSupplier") {
      viewSupplier(target);
      return;
    }

    if (action === "editSupplier") {
      editSupplier(target);
      return;
    }

    if (action === "deleteSupplier") {
      deleteSupplier(parseInt(value, 10));
      return;
    }

    if (action === "viewReceiving") {
      viewReceiving(parseInt(value, 10));
      return;
    }

    if (action === "viewDelivery") {
      viewDelivery(target);
      return;
    }

    if (action === "viewReturn") {
      viewReturn(target);
      return;
    }

    if (action === "viewPO") {
      viewPurchaseOrder(target);
      return;
    }

    if (action === "viewQuotation") {
      viewQuotation(target);
      return;
    }

    if (action === "editQuotation") {
      editQuotation(target);
      return;
    }

    if (action === "printQuotation") {
      printQuotation(target);
      return;
    }

    if (action === "updateQuotationStatus") {
      const status = target.getAttribute("data-status") || "";
      updateQuotationStatus(parseInt(value, 10), status);
      return;
    }

    if (action === "deleteQuotation") {
      deleteQuotation(parseInt(value, 10));
      return;
    }

    if (action === "updatePOStatus") {
      const status = target.getAttribute("data-status") || "";
      updatePurchaseOrderStatus(parseInt(value, 10), status);
      return;
    }

    if (action === "viewInvoice") {
      viewInvoice(target);
      return;
    }

    if (action === "viewExpense") {
      viewExpense(target);
      return;
    }

    if (action === "editExpense") {
      editExpense(target);
      return;
    }

    if (action === "deleteExpense") {
      deleteExpense(parseInt(value, 10));
      return;
    }

    if (action === "updateReceivingStatus") {
      const status = target.getAttribute("data-status") || "";
      updateReceivingStatus(parseInt(value, 10), status);
      return;
    }

    if (action === "updateDeliveryStatus") {
      const status = target.getAttribute("data-status") || "";
      updateDeliveryStatus(parseInt(value, 10), status);
      return;
    }

    if (action === "updateInvoiceStatus") {
      const status = target.getAttribute("data-status") || "";
      updateInvoiceStatus(parseInt(value, 10), status);
      return;
    }

    if (action === "updateReturnStatus") {
      const status = target.getAttribute("data-status") || "";
      const reason = target.getAttribute("data-reason") || "";
      const isExpired = target.getAttribute("data-is-expired") || "0";
      updateReturnStatus(parseInt(value, 10), status, reason, isExpired);
      return;
    }

    if (action === "printCustomerStatement") {
      printCustomerStatement(target);
      return;
    }

    if (action === "receiveCustomerPayment") {
      receiveCustomerPayment(parseInt(value, 10));
      return;
    }

    if (action === "viewCustomerDebtPayments") {
      viewCustomerDebtPayments(parseInt(value, 10));
      return;
    }

    if (action === "viewReceipt") {
      viewReceipt(value);
      return;
    }

    if (action === "editUserRole") {
      const role = target.getAttribute("data-role") || "Staff";
      const name = target.getAttribute("data-name") || "User";
      editUserRole(parseInt(value, 10), role, name);
      return;
    }

    if (action === "editUserPermissions") {
      const name = target.getAttribute("data-name") || "User";
      editUserPermissions(parseInt(value, 10), name);
      return;
    }

    if (action === "toggleUserStatus") {
      const name = target.getAttribute("data-name") || "User";
      const status = target.getAttribute("data-status") || "active";
      toggleUserStatus(parseInt(value, 10), status, name);
      return;
    }

    if (action === "resetUserPassword") {
      const name = target.getAttribute("data-name") || "User";
      resetUserPassword(parseInt(value, 10), name);
      return;
    }

    if (action === "printReceipt") {
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

function openModal(title, content, buttons = [], options = {}) {
  const overlay = document.getElementById("modalOverlay");
  const modal = document.getElementById("modal");
  const modalTitle = document.getElementById("modalTitle");
  const modalBody = document.getElementById("modalBody");
  const modalFooter = document.getElementById("modalFooter");

  modal.classList.remove("modal-checkout", "modal-receipt");
  if (
    typeof options.modalClass === "string" &&
    options.modalClass.trim() !== ""
  ) {
    modal.classList.add(options.modalClass.trim());
  }

  modalTitle.textContent = title;
  modalBody.innerHTML = content;

  // Generate buttons safely using DOM manipulation
  modalFooter.innerHTML = "";
  buttons.forEach((btn) => {
    const button = document.createElement("button");
    button.className = `btn ${btn.class || "btn-secondary"}`;
    button.textContent = btn.text;

    // Use event listener instead of inline onclick for security
    if (typeof btn.handler === "function") {
      button.addEventListener("click", btn.handler);
    } else if (btn.onclick) {
      // For legacy onclick strings, use a safe evaluation approach
      button.addEventListener("click", function () {
        // Only allow known safe function calls
        const safeActions = {
          "closeModal()": closeModal,
          "printReport()": printReport,
          "logout()": logout,
        };
        if (safeActions[btn.onclick]) {
          safeActions[btn.onclick]();
        } else if (btn.onclick.startsWith("closeModal();")) {
          closeModal();
          // Handle chained calls like "closeModal(); showToast(...)"
          const remainder = btn.onclick.replace("closeModal();", "").trim();
          if (remainder.startsWith("showToast(")) {
            const match = remainder.match(
              /showToast\s*\(\s*["'](\w+)["']\s*,\s*["']([^"']+)["']\s*\)/,
            );
            if (match) {
              showToast(match[1], match[2]);
            }
          } else if (remainder.startsWith("window.location")) {
            // Handle safe redirects
            const urlMatch = remainder.match(
              /window\.location\s*=\s*['"](\?page=\w+)['"]/,
            );
            if (urlMatch && /^\?page=\w+$/.test(urlMatch[1])) {
              window.location = urlMatch[1];
            }
          }
        } else if (btn.onclick.startsWith("document.getElementById")) {
          // Handle form submission
          const match = btn.onclick.match(
            /document\.getElementById\s*\(\s*["'](\w+)["']\s*\)\.requestSubmit\s*\(\s*\)/,
          );
          if (match) {
            const form = document.getElementById(match[1]);
            if (form) form.requestSubmit();
          }
        } else if (btn.onclick.startsWith("printReceipt(")) {
          const match = btn.onclick.match(
            /printReceipt\s*\(\s*["']([A-Za-z0-9\-_]+)["']\s*\)/,
          );
          if (match) printReceipt(match[1]);
        }
      });
    } else {
      button.addEventListener("click", closeModal);
    }

    modalFooter.appendChild(button);
  });

  overlay.classList.add("active");
  modal.classList.add("active");
  document.body.style.overflow = "hidden";
  applyLanguage(getCurrentLanguage());
}

function closeModal() {
  const overlay = document.getElementById("modalOverlay");
  const modal = document.getElementById("modal");

  overlay.classList.remove("active");
  modal.classList.remove("active");
  document.body.style.overflow = "";
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
  openModal("Quick Add", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
  ]);

  const modalBody = document.getElementById("modalBody");
  modalBody
    ?.querySelector('[data-action="quickNewSale"]')
    ?.addEventListener("click", () => {
      closeModal();
      showNewSaleModal();
    });
  modalBody
    ?.querySelector('[data-action="quickAddProduct"]')
    ?.addEventListener("click", () => {
      closeModal();
      showAddProductModal();
    });
  modalBody
    ?.querySelector('[data-action="quickImportProducts"]')
    ?.addEventListener("click", () => {
      closeModal();
      showImportProductsModal();
    });
  modalBody
    ?.querySelector('[data-action="quickAddCustomer"]')
    ?.addEventListener("click", () => {
      closeModal();
      showAddCustomerModal();
    });
  modalBody
    ?.querySelector('[data-action="quickGoExpenses"]')
    ?.addEventListener("click", () => {
      closeModal();
      window.location.href = "?page=expenses";
    });
}

function showNewSaleModal() {
  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const customers = Array.isArray(APP_CONFIG.saleCustomers)
    ? APP_CONFIG.saleCustomers
    : [];
  const products = Array.isArray(APP_CONFIG.saleProducts)
    ? APP_CONFIG.saleProducts
    : [];
  let loadedProducts = [...products];

  const normalizedCustomers = customers
    .filter((customer) => customer && typeof customer === "object")
    .map((customer) => ({
      id: String(customer.id ?? "").trim(),
      name: String(customer.name ?? "").trim(),
    }))
    .filter((customer) => customer.name !== "");

  const existingWalkInIndex = normalizedCustomers.findIndex(
    (customer) => customer.name.toLowerCase() === "walk-in customer",
  );

  const walkInCustomer =
    existingWalkInIndex >= 0
      ? normalizedCustomers[existingWalkInIndex]
      : {
          id: "1",
          name: "Walk-in Customer",
        };

  const saleCustomers = [
    walkInCustomer,
    ...normalizedCustomers.filter((_, index) => index !== existingWalkInIndex),
  ];

  const customerOptions =
    saleCustomers.length > 0
      ? saleCustomers
          .map(
            (customer) =>
              `<option value="${escapeHtml(customer.id)}"${customer.id === walkInCustomer.id ? " selected" : ""}>${escapeHtml(customer.name)}</option>`,
          )
          .join("")
      : '<option value="1" selected>Walk-in Customer</option>';

  const content = `
      <form id="newSaleForm" method="POST" action="?page=sales">
        <input type="hidden" name="action" value="create_entity">
        <input type="hidden" name="entity" value="sale">
        <input type="hidden" name="csrf_token" value="${csrfToken}">

        <div class="form-group">
          <label>Customer</label>
          <select id="saleCustomer" name="customer_id" required>
            ${customerOptions}
          </select>
        </div>

        <div class="form-group">
          <label>Product</label>
          <input id="saleProductSearch" type="text" placeholder="Search products..." autocomplete="off" required>
          <input id="saleProductId" type="hidden" name="product_id">
          <div id="saleProductResults" style="display:none; max-height:220px; overflow:auto; border:1px solid #E5E7EB; border-radius:8px; margin-top:8px; background:#FFFFFF;"></div>
        </div>

        <div class="form-group">
          <label>Quantity</label>
          <input id="saleQuantity" type="number" name="quantity" value="1" min="1" required>
        </div>

        <div class="form-group">
          <label>Amount (Tsh)</label>
          <input id="saleAmount" type="number" name="amount" placeholder="Enter amount" required min="1" readonly>
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
  openModal("Create New Sale", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: "Create Sale",
      class: "btn-primary",
      onclick: 'document.getElementById("newSaleForm").requestSubmit()',
    },
  ]);

  const paymentMethodEl = document.getElementById("salePaymentMethod");
  const productSearchEl = document.getElementById("saleProductSearch");
  const productIdEl = document.getElementById("saleProductId");
  const productResultsEl = document.getElementById("saleProductResults");
  const quantityEl = document.getElementById("saleQuantity");
  const amountEl = document.getElementById("saleAmount");
  const mobileMoneyFields = document.getElementById("mobileMoneyFields");
  const mobileMoneyProvider = document.getElementById("mobileMoneyProvider");
  const mobileMoneyPhone = document.getElementById("mobileMoneyPhone");
  let productSearchTimer = null;
  let productSearchRequestId = 0;
  let productSearchTerm = "";
  let selectedProduct = null;

  const renderProductOptions = (searchTerm = "") => {
    if (!productResultsEl) {
      return;
    }

    const term = (searchTerm || "").toLowerCase().trim();
    const source = loadedProducts.filter((p) => {
      if (term === "") {
        return true;
      }
      const name = String(p.name || "").toLowerCase();
      const category = String(p.category || "").toLowerCase();
      return name.includes(term) || category.includes(term);
    });

    if (source.length === 0) {
      productResultsEl.innerHTML =
        '<div style="padding:10px 12px; color:#6B7280;">No products available</div>';
      productResultsEl.style.display = "block";
      return;
    }

    productResultsEl.innerHTML = source
      .slice(0, 200)
      .map((p) => {
        const category = String(p.category || "").trim();
        const stock = Number.isFinite(Number(p.stock_qty))
          ? Number(p.stock_qty)
          : 0;
        const price = Number.isFinite(Number(p.unit_price))
          ? Number(p.unit_price)
          : 0;
        const id = String(p.id || "");
        const label = `${p.name || ""}${category ? ` - ${category}` : ""} | Stock: ${stock} | Tsh ${price.toLocaleString()}`;
        const isActive = selectedProduct && String(selectedProduct.id) === id;
        return `<button type="button" class="sales-product-result-item${isActive ? " active" : ""}" data-product-id="${escapeHtml(id)}" style="display:block; width:100%; text-align:left; border:none; background:${isActive ? "#EEF2FF" : "#fff"}; padding:10px 12px; cursor:pointer;">${escapeHtml(label)}</button>`;
      })
      .join("");
    productResultsEl.style.display = "block";
  };

  const findProductById = (id) =>
    loadedProducts.find((p) => String(p.id || "") === String(id || "")) || null;

  const formatProductLabel = (product) => {
    if (!product) {
      return "";
    }

    const category = String(product.category || "").trim();
    const stock = Number.isFinite(Number(product.stock_qty))
      ? Number(product.stock_qty)
      : 0;
    const price = Number.isFinite(Number(product.unit_price))
      ? Number(product.unit_price)
      : 0;
    return `${product.name || ""}${category ? ` - ${category}` : ""} | Stock: ${stock} | Tsh ${price.toLocaleString()}`;
  };

  const selectProduct = (product) => {
    selectedProduct = product || null;
    if (productIdEl) {
      productIdEl.value = selectedProduct
        ? String(selectedProduct.id || "")
        : "";
    }
    if (productSearchEl) {
      productSearchEl.value = selectedProduct
        ? formatProductLabel(selectedProduct)
        : "";
    }
    if (productResultsEl) {
      productResultsEl.style.display = "none";
    }
    syncAmount();
  };

  const loadProductsFallback = async () => {
    if (loadedProducts.length > 0) {
      return;
    }

    try {
      const response = await fetch("products_feed.php?limit=300", {
        method: "GET",
        headers: { Accept: "application/json" },
        credentials: "same-origin",
      });
      if (!response.ok) {
        return;
      }

      const data = await response.json();
      if (!data || !Array.isArray(data.items)) {
        return;
      }

      loadedProducts = data.items;
      renderProductOptions(productSearchTerm);
      syncAmount();
    } catch (error) {
      // Keep modal usable even if fallback feed fails.
    }
  };

  const runRemoteProductSearch = async (term) => {
    const requestId = ++productSearchRequestId;
    try {
      const response = await fetch(
        `products_feed.php?q=${encodeURIComponent(term)}&limit=300`,
        {
          method: "GET",
          headers: { Accept: "application/json" },
          credentials: "same-origin",
        },
      );
      if (!response.ok || requestId !== productSearchRequestId) {
        return;
      }

      const data = await response.json();
      if (!data || !Array.isArray(data.items)) {
        return;
      }

      loadedProducts = data.items;
      renderProductOptions(term);
      syncAmount();
    } catch (error) {
      // Keep local filtering fallback when network fails.
    }
  };

  const syncAmount = () => {
    if (!amountEl || !quantityEl) {
      return;
    }

    const unitPrice = Number.isFinite(Number(selectedProduct?.unit_price))
      ? Number(selectedProduct.unit_price)
      : 0;
    const quantity = Math.max(1, parseInt(quantityEl.value || "1", 10));
    amountEl.value = String(Math.max(0, Math.round(unitPrice * quantity)));
  };

  const toggleMobileMoneyFields = () => {
    const isMobileMoney = paymentMethodEl?.value === "Mobile Money";
    if (mobileMoneyFields) {
      mobileMoneyFields.style.display = isMobileMoney ? "block" : "none";
    }
    if (mobileMoneyProvider) {
      mobileMoneyProvider.required = !!isMobileMoney;
    }
    if (mobileMoneyPhone) {
      mobileMoneyPhone.required = !!isMobileMoney;
    }
  };

  const searchProducts = (term) => {
    productSearchTerm = String(term || "").trim();
    if (productIdEl) {
      productIdEl.value = "";
    }
    selectedProduct = null;
    renderProductOptions(productSearchTerm);

    if (productSearchTimer) {
      clearTimeout(productSearchTimer);
    }

    if (productSearchTerm.length < 2) {
      return;
    }

    productSearchTimer = setTimeout(() => {
      runRemoteProductSearch(productSearchTerm);
    }, 220);
  };

  const getFirstResultButton = () =>
    productResultsEl?.querySelector(".sales-product-result-item") || null;

  paymentMethodEl?.addEventListener("change", toggleMobileMoneyFields);
  productSearchEl?.addEventListener("input", () => {
    searchProducts(productSearchEl.value);
    if (productResultsEl) {
      productResultsEl.style.display = "block";
    }
  });

  productSearchEl?.addEventListener("focus", () => {
    renderProductOptions(productSearchTerm || productSearchEl.value || "");
    if (productResultsEl) {
      productResultsEl.style.display = "block";
    }
  });

  productSearchEl?.addEventListener("keydown", (event) => {
    if (event.key === "Escape") {
      if (productResultsEl) {
        productResultsEl.style.display = "none";
      }
      return;
    }

    if (event.key === "ArrowDown") {
      const firstResult = getFirstResultButton();
      if (firstResult) {
        event.preventDefault();
        firstResult.focus();
      }
      return;
    }

    if (event.key === "Enter") {
      const firstResult = getFirstResultButton();
      if (firstResult) {
        event.preventDefault();
        const product = findProductById(
          firstResult.getAttribute("data-product-id") || "",
        );
        selectProduct(product);
      }
    }
  });

  productResultsEl?.addEventListener("click", (event) => {
    const item = event.target.closest("[data-product-id]");
    if (!item) {
      return;
    }

    const product = findProductById(item.getAttribute("data-product-id") || "");
    selectProduct(product);
  });

  document.addEventListener("click", (event) => {
    const target = event.target;
    if (!(target instanceof Element)) {
      return;
    }

    if (
      target.closest("#saleProductSearch") ||
      target.closest("#saleProductResults")
    ) {
      return;
    }

    if (productResultsEl) {
      productResultsEl.style.display = "none";
    }
  });

  const formEl = document.getElementById("newSaleForm");
  formEl?.addEventListener("submit", (event) => {
    if (productIdEl && productIdEl.value.trim() !== "") {
      return;
    }

    event.preventDefault();
    showToast("warning", "Select product");
    productSearchEl?.focus();
  });

  quantityEl?.addEventListener("input", syncAmount);
  if (productSearchEl) {
    productSearchEl.value = "";
  }
  if (productIdEl) {
    productIdEl.value = "";
  }
  if (productResultsEl) {
    productResultsEl.style.display = "none";
  }
  toggleMobileMoneyFields();
  syncAmount();
  loadProductsFallback();
}

function showAddProductModal() {
  if (!APP_CONFIG.canManageProducts) {
    showToast("error", "Only administrators can add products.");
    return;
  }

  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
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
                <label>Product Category</label>
                <input type="text" name="category" placeholder="e.g., Beverages, Grocery">
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
  openModal("Add New Product", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: "Add Product",
      class: "btn-primary",
      onclick: 'document.getElementById("addProductForm").requestSubmit()',
    },
  ]);
}

function showImportProductsModal() {
  if (!APP_CONFIG.canManageProducts) {
    showToast("error", "Only administrators can import products.");
    return;
  }

  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");

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
                    Expected columns: <strong>name, sku, unit_price, stock_qty, reorder_level, category(optional)</strong>.<br>
                    Max file size is 25MB and max 20000 data rows.<br>
                    The importer creates new products and updates existing ones by SKU.
                </small>
            </div>
        </form>
    `;

  openModal("Import Products", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: "Import",
      class: "btn-primary",
      onclick: 'document.getElementById("importProductsForm").requestSubmit()',
    },
  ]);
}

function showAddCustomerModal() {
  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
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
  openModal("Add New Customer", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: "Add Customer",
      class: "btn-primary",
      onclick: 'document.getElementById("addCustomerForm").requestSubmit()',
    },
  ]);
}

function openEntityModal(config) {
  const formId = `${config.key}Form`;
  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const fieldsMarkup = config.fields
    .map((field) => {
      if (field.type === "select") {
        const selectId = `${formId}_${field.name}`;
        const optionsMarkup = (field.options || [])
          .map((option) => {
            const optionValue = escapeHtml(option.value || "");
            const optionLabel = escapeHtml(option.label || option.value || "");
            const isSelected = option.selected ? " selected" : "";
            return `<option value="${optionValue}"${isSelected}>${optionLabel}</option>`;
          })
          .join("");

        const searchMarkup = field.searchable
          ? `<input type="search" data-select-search-target="${escapeHtml(selectId)}" placeholder="${escapeHtml(field.searchPlaceholder || "Search...")}" autocomplete="off">`
          : "";

        return `
                <div class="form-group">
                    <label>${field.label}</label>
                    ${searchMarkup}
                    <select id="${escapeHtml(selectId)}" name="${field.name}" ${field.required ? "required" : ""}>
                        ${optionsMarkup}
                    </select>
                </div>
            `;
      }

      if (field.type === "textarea") {
        return `
                <div class="form-group">
                    <label>${field.label}</label>
                    <textarea name="${field.name}" placeholder="${field.placeholder || ""}" rows="${field.rows || 3}" ${field.required ? "required" : ""}></textarea>
                </div>
            `;
      }

      if (field.type === "checkbox") {
        const checkboxValue =
          field.value === undefined ? "1" : String(field.value);
        return `
            <div class="form-group">
              <label style="display:flex; gap:10px; align-items:center; font-weight:600; cursor:pointer;">
                <input type="checkbox" name="${field.name}" value="${escapeHtml(checkboxValue)}" ${field.checked ? "checked" : ""}>
                <span>${field.checkboxLabel || field.label}</span>
              </label>
            </div>
          `;
      }

      return `
            <div class="form-group">
                <label>${field.label}</label>
            <input type="${field.type || "text"}" name="${field.name}" placeholder="${field.placeholder || ""}" ${field.required ? "required" : ""} ${field.min !== undefined ? `min="${field.min}"` : ""} ${field.max !== undefined ? `max="${field.max}"` : ""} ${field.step !== undefined ? `step="${field.step}"` : ""}>
            </div>
        `;
    })
    .join("");

  const content = `
        <form id="${formId}" method="POST" action="?page=${escapeHtml(config.page || APP_CONFIG.currentPage || "dashboard")}">
            <input type="hidden" name="action" value="create_entity">
            <input type="hidden" name="entity" value="${escapeHtml(config.entity)}">
            <input type="hidden" name="csrf_token" value="${csrfToken}">
            ${fieldsMarkup}
        </form>
    `;

  openModal(config.title, content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: config.submitText || "Save",
      class: "btn-primary",
      onclick: `document.getElementById("${formId}").requestSubmit()`,
    },
  ]);

  initSearchableSelects(formId);
}

function initSearchableSelects(formId) {
  const form = document.getElementById(formId);
  if (!form) {
    return;
  }

  const searchInputs = form.querySelectorAll("[data-select-search-target]");
  searchInputs.forEach((searchInput) => {
    const targetId =
      searchInput.getAttribute("data-select-search-target") || "";
    if (targetId === "") {
      return;
    }

    const select = document.getElementById(targetId);
    if (!select || !form.contains(select)) {
      return;
    }

    const sourceOptions = Array.from(select.options).map((option) => ({
      value: option.value,
      label: option.textContent || "",
    }));

    const getCurrentOptions = () =>
      Array.from(select.options).filter((option) => option.value !== "");

    const highlightMatchLabel = (label, term) => {
      const normalizedTerm = String(term || "").trim();
      if (normalizedTerm === "") {
        return label;
      }

      const labelText = String(label || "");
      const lowerLabel = labelText.toLowerCase();
      const lowerTerm = normalizedTerm.toLowerCase();
      const index = lowerLabel.indexOf(lowerTerm);
      if (index === -1) {
        return labelText;
      }

      const end = index + normalizedTerm.length;
      return `${labelText.slice(0, index)}[${labelText.slice(index, end)}]${labelText.slice(end)}`;
    };

    const renderOptions = (term) => {
      const rawSearchTerm = String(term || "").trim();
      const searchTerm = rawSearchTerm.trim().toLowerCase();
      const previousValue = select.value;
      const filteredOptions = sourceOptions.filter((option) => {
        if (searchTerm === "") {
          return true;
        }

        return option.label.toLowerCase().includes(searchTerm);
      });

      select.innerHTML = "";

      if (filteredOptions.length === 0) {
        const noResultOption = document.createElement("option");
        noResultOption.value = "";
        noResultOption.textContent = "No matching products";
        select.appendChild(noResultOption);
        select.value = "";
        return;
      }

      filteredOptions.forEach((option) => {
        const optionEl = document.createElement("option");
        optionEl.value = option.value;
        optionEl.textContent = highlightMatchLabel(option.label, rawSearchTerm);
        select.appendChild(optionEl);
      });

      const hasPreviousValue = filteredOptions.some(
        (option) => option.value === previousValue,
      );
      if (hasPreviousValue) {
        select.value = previousValue;
      } else {
        select.selectedIndex = 0;
      }
    };

    searchInput.addEventListener("input", () =>
      renderOptions(searchInput.value),
    );

    searchInput.addEventListener("keydown", (event) => {
      const options = getCurrentOptions();

      if (event.key === "ArrowDown" || event.key === "ArrowUp") {
        if (options.length === 0) {
          return;
        }

        event.preventDefault();

        const step = event.key === "ArrowDown" ? 1 : -1;
        const selectedIndex = select.selectedIndex;
        const nextIndex =
          selectedIndex < 0
            ? 0
            : Math.min(Math.max(selectedIndex + step, 0), options.length - 1);

        select.selectedIndex = nextIndex;
        return;
      }

      if (event.key === "Enter") {
        if (options.length > 0) {
          event.preventDefault();
          select.focus();
        }
        return;
      }

      if (event.key === "Escape") {
        event.preventDefault();
        if (searchInput.value !== "") {
          searchInput.value = "";
          renderOptions("");
        } else {
          searchInput.blur();
        }
      }
    });

    // Keep current option list and selected value in sync at startup.
    renderOptions(searchInput.value);
  });
}

function openAddSupplierModal() {
  openEntityModal({
    key: "supplier",
    page: "suppliers",
    entity: "supplier",
    title: "Add Supplier",
    entityName: "Supplier",
    submitText: "Add Supplier",
    successMessage: "Supplier added successfully!",
    fields: [
      {
        label: "Supplier Name",
        name: "name",
        required: true,
        placeholder: "Enter supplier name",
      },
      {
        label: "Contact Person",
        name: "contact_person",
        placeholder: "Enter contact person",
      },
      {
        label: "Phone",
        name: "phone",
        type: "tel",
        placeholder: "Enter phone number",
      },
      {
        label: "Email",
        name: "email",
        type: "email",
        placeholder: "Enter email address",
      },
      {
        label: "Address",
        name: "address",
        type: "textarea",
        placeholder: "Enter address",
        rows: 3,
      },
    ],
  });
}

function openAddEmployeeModal() {
  openEntityModal({
    key: "employee",
    page: "employees",
    entity: "employee",
    title: "Add Employee",
    entityName: "Employee",
    submitText: "Add Employee",
    successMessage: "Employee added successfully!",
    fields: [
      {
        label: "Employee Name",
        name: "name",
        required: true,
        placeholder: "Enter employee name",
      },
      { label: "Position", name: "position", placeholder: "e.g. Cashier" },
      {
        label: "Phone",
        name: "phone",
        type: "tel",
        placeholder: "Enter phone number",
      },
      {
        label: "Email",
        name: "email",
        type: "email",
        placeholder: "Enter email address",
      },
      {
        label: "Salary (Tsh)",
        name: "salary",
        type: "number",
        placeholder: "Enter salary",
      },
    ],
  });
}

function openAddUserModal() {
  openEntityModal({
    key: "user",
    page: "users",
    entity: "user",
    title: "Add User",
    entityName: "User",
    submitText: "Create User",
    successMessage: "User created successfully!",
    fields: [
      {
        label: "Full Name",
        name: "name",
        required: true,
        placeholder: "Enter user full name",
      },
      {
        label: "Email",
        name: "email",
        type: "email",
        required: true,
        placeholder: "user@example.com",
      },
      {
        label: "Password",
        name: "password",
        type: "password",
        required: true,
        placeholder: "At least 8 characters",
      },
      {
        label: "Privilege (Role)",
        name: "role",
        type: "select",
        required: true,
        options: [
          { value: "Admin", label: "Admin" },
          { value: "Manager", label: "Manager" },
          { value: "Staff", label: "Staff" },
          { value: "Cashier", label: "Cashier" },
        ],
      },
    ],
  });

  const form = document.getElementById("userForm");
  if (!form) {
    return;
  }

  form.addEventListener("submit", (event) => {
    const nameInput = form.querySelector('input[name="name"]');
    const emailInput = form.querySelector('input[name="email"]');
    const passwordInput = form.querySelector('input[name="password"]');
    const roleInput = form.querySelector('select[name="role"]');

    const name = String(nameInput?.value || "").trim();
    const email = String(emailInput?.value || "")
      .trim()
      .toLowerCase();
    const password = String(passwordInput?.value || "");
    const role = String(roleInput?.value || "").trim();

    if (name === "" || email === "" || password === "" || role === "") {
      event.preventDefault();
      showToast("warning", "Name, email, password, and role are required.");
      return;
    }

    if (!/^\S+@\S+\.\S+$/.test(email)) {
      event.preventDefault();
      showToast("warning", "Please provide a valid user email address.");
      return;
    }

    if (password.length < 8) {
      event.preventDefault();
      showToast("warning", "Password must be at least 8 characters long.");
      return;
    }

    const existingUsers = Array.isArray(APP_CONFIG.users)
      ? APP_CONFIG.users
      : [];
    const emailExists = existingUsers.some(
      (user) =>
        String(user?.email || "")
          .trim()
          .toLowerCase() === email,
    );
    if (emailExists) {
      event.preventDefault();
      showToast("warning", "A user with this email already exists.");
    }
  });
}

function editUserRole(userId, currentRole, userName) {
  if (!Number.isInteger(userId) || userId <= 0) {
    showToast("error", "Invalid user ID");
    return;
  }

  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const safeRole = escapeHtml(currentRole || "Staff");
  const safeName = escapeHtml(userName || "User");
  const selected = String(currentRole || "Staff").toLowerCase();
  const roleOptions = ["Admin", "Manager", "Staff", "Cashier"]
    .map((role) => {
      const roleEscaped = escapeHtml(role);
      const isSelected = role.toLowerCase() === selected ? "selected" : "";
      return `<option value="${roleEscaped}" ${isSelected}>${roleEscaped}</option>`;
    })
    .join("");

  const content = `
        <form id="editUserRoleForm" method="POST" action="?page=users">
            <input type="hidden" name="action" value="update_entity">
            <input type="hidden" name="entity" value="user">
            <input type="hidden" name="id" value="${userId}">
            <input type="hidden" name="csrf_token" value="${csrfToken}">
            <div class="form-group">
                <label>User</label>
                <input type="text" value="${safeName}" readonly>
            </div>
            <div class="form-group">
                <label>Current Role</label>
                <input type="text" value="${safeRole}" readonly>
            </div>
            <div class="form-group">
                <label>Assign New Privilege (Role)</label>
                <select name="role" required>
                    ${roleOptions}
                </select>
            </div>
        </form>
    `;

  openModal("Assign User Privilege", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: "Save Changes",
      class: "btn-primary",
      onclick: 'document.getElementById("editUserRoleForm").requestSubmit()',
    },
  ]);
}

function editUserPermissions(userId, userName) {
  if (!Number.isInteger(userId) || userId <= 0) {
    showToast("error", "Invalid user ID");
    return;
  }

  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const safeName = escapeHtml(userName || "User");
  const pages = Array.isArray(APP_CONFIG.availablePages)
    ? APP_CONFIG.availablePages
    : [];
  const rawOverrides = APP_CONFIG.userPermissionOverrides || {};
  const userOverrides =
    rawOverrides[userId] || rawOverrides[String(userId)] || {};

  const permissionRows = pages
    .map((page) => {
      const key = String(page?.key || "").trim();
      const title = String(page?.title || key || "Page").trim();
      if (key === "") {
        return "";
      }

      const overrideValue = userOverrides[key];
      let selectedMode = "default";
      if (typeof overrideValue === "boolean") {
        selectedMode = overrideValue ? "allow" : "deny";
      }

      return `
        <div class="form-group" style="margin-bottom:10px;">
          <label style="display:flex; justify-content:space-between; gap:12px; align-items:center;">
            <span>${escapeHtml(title)}</span>
            <select name="permission_mode[${escapeHtml(key)}]" style="max-width:180px;">
              <option value="default"${selectedMode === "default" ? " selected" : ""}>Default</option>
              <option value="allow"${selectedMode === "allow" ? " selected" : ""}>Allow</option>
              <option value="deny"${selectedMode === "deny" ? " selected" : ""}>Deny</option>
            </select>
          </label>
        </div>
      `;
    })
    .filter((row) => row !== "")
    .join("");

  const content = `
    <form id="editUserPermissionsForm" method="POST" action="?page=users">
      <input type="hidden" name="action" value="update_entity">
      <input type="hidden" name="entity" value="user_permission_override">
      <input type="hidden" name="id" value="${userId}">
      <input type="hidden" name="csrf_token" value="${csrfToken}">
      <div class="form-group">
        <label>User</label>
        <input type="text" value="${safeName}" readonly>
      </div>
      ${
        permissionRows !== ""
          ? permissionRows
          : '<p style="margin:0; color:#6B7280;">No page permissions available.</p>'
      }
    </form>
  `;

  openModal("Edit User Permissions", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: "Save Permissions",
      class: "btn-primary",
      onclick:
        'document.getElementById("editUserPermissionsForm").requestSubmit()',
    },
  ]);
}

function toggleUserStatus(userId, currentStatus, userName) {
  if (!Number.isInteger(userId) || userId <= 0) {
    showToast("error", "Invalid user ID");
    return;
  }

  const nextStatus =
    String(currentStatus || "active").toLowerCase() === "active"
      ? "inactive"
      : "active";
  const safeName = escapeHtml(userName || "User");
  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const actionLabel = nextStatus === "active" ? "Activate" : "Deactivate";

  const content = `
    <form id="toggleUserStatusForm" method="POST" action="?page=users">
      <input type="hidden" name="action" value="update_entity">
      <input type="hidden" name="entity" value="user_status">
      <input type="hidden" name="id" value="${userId}">
      <input type="hidden" name="status" value="${nextStatus}">
      <input type="hidden" name="csrf_token" value="${csrfToken}">
      <p>Are you sure you want to ${actionLabel.toLowerCase()} <strong>${safeName}</strong>?</p>
    </form>
  `;

  openModal(`${actionLabel} User`, content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: actionLabel,
      class: nextStatus === "active" ? "btn-primary" : "btn-danger",
      onclick:
        'document.getElementById("toggleUserStatusForm").requestSubmit()',
    },
  ]);
}

function resetUserPassword(userId, userName) {
  if (!Number.isInteger(userId) || userId <= 0) {
    showToast("error", "Invalid user ID");
    return;
  }

  const safeName = escapeHtml(userName || "User");
  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const content = `
    <form id="resetUserPasswordForm" method="POST" action="?page=users">
      <input type="hidden" name="action" value="update_entity">
      <input type="hidden" name="entity" value="user_password_reset">
      <input type="hidden" name="id" value="${userId}">
      <input type="hidden" name="csrf_token" value="${csrfToken}">
      <div class="form-group">
        <label>User</label>
        <input type="text" value="${safeName}" readonly>
      </div>
      <div class="form-group">
        <label>New Password</label>
        <input type="password" name="new_password" minlength="8" required placeholder="At least 8 characters">
      </div>
      <div class="form-group">
        <label>Confirm Password</label>
        <input type="password" name="confirm_password" minlength="8" required placeholder="Repeat password">
      </div>
    </form>
  `;

  openModal("Reset User Password", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: "Reset Password",
      class: "btn-primary",
      onclick:
        'document.getElementById("resetUserPasswordForm").requestSubmit()',
    },
  ]);
}

function openAddExpenseModal() {
  openEntityModal({
    key: "expense",
    page: "expenses",
    entity: "expense",
    title: "Record Expense",
    entityName: "Expense",
    submitText: "Save Expense",
    successMessage: "Expense saved successfully!",
    fields: [
      {
        label: "Description",
        name: "description",
        required: true,
        placeholder: "Expense description",
      },
      {
        label: "Amount (Tsh)",
        name: "amount",
        type: "number",
        required: true,
        placeholder: "Enter amount",
      },
      {
        label: "Category",
        name: "category",
        placeholder: "Utilities, Transport, etc.",
      },
    ],
  });
}

function viewExpense(target) {
  if (!target) {
    return;
  }

  const id = Number.parseInt(target.getAttribute("data-value") || "0", 10);
  const description = target.getAttribute("data-description") || "-";
  const category = target.getAttribute("data-category") || "General";
  const amount = target.getAttribute("data-amount") || "0";
  const status = target.getAttribute("data-status") || "Pending";
  const date = target.getAttribute("data-date") || "-";

  const content = `
    <div style="display:grid; gap:10px;">
      <div><strong>ID:</strong> ${escapeHtml(String(Number.isFinite(id) ? id : 0))}</div>
      <div><strong>Description:</strong> ${escapeHtml(description)}</div>
      <div><strong>Category:</strong> ${escapeHtml(category)}</div>
      <div><strong>Amount:</strong> Tsh ${escapeHtml(amount)}</div>
      <div><strong>Status:</strong> ${escapeHtml(status)}</div>
      <div><strong>Date:</strong> ${escapeHtml(date)}</div>
    </div>
  `;

  openModal("Expense Details", content, [
    { text: "Close", class: "btn-primary", onclick: "closeModal()" },
  ]);
}

function editExpense(target) {
  if (!target) {
    return;
  }

  const id = Number.parseInt(target.getAttribute("data-value") || "0", 10);
  if (!Number.isFinite(id) || id <= 0) {
    showToast("error", "Invalid expense ID");
    return;
  }

  const description = target.getAttribute("data-description") || "";
  const category = target.getAttribute("data-category") || "";
  const amount = target.getAttribute("data-amount") || "0";
  const status = target.getAttribute("data-status") || "Pending";
  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");

  const content = `
    <form id="editExpenseForm" method="POST" action="?page=expenses">
      <input type="hidden" name="action" value="update_entity">
      <input type="hidden" name="entity" value="expense">
      <input type="hidden" name="id" value="${id}">
      <input type="hidden" name="csrf_token" value="${csrfToken}">
      <div class="form-group">
        <label>Description</label>
        <input type="text" name="description" required value="${escapeHtml(description)}">
      </div>
      <div class="form-group">
        <label>Category</label>
        <input type="text" name="category" value="${escapeHtml(category)}" placeholder="Utilities, Transport, etc.">
      </div>
      <div class="form-group">
        <label>Amount (Tsh)</label>
        <input type="number" name="amount" min="0.01" step="0.01" required value="${escapeHtml(amount)}">
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="status">
          <option value="Pending" ${status === "Pending" ? "selected" : ""}>Pending</option>
          <option value="Approved" ${status === "Approved" ? "selected" : ""}>Approved</option>
          <option value="Rejected" ${status === "Rejected" ? "selected" : ""}>Rejected</option>
        </select>
      </div>
    </form>
  `;

  openModal("Edit Expense", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: "Save Changes",
      class: "btn-primary",
      onclick: 'document.getElementById("editExpenseForm").requestSubmit()',
    },
  ]);
}

function deleteExpense(id) {
  const expenseId = Number.parseInt(id, 10);
  if (!Number.isFinite(expenseId) || expenseId <= 0) {
    showToast("error", "Invalid expense ID");
    return;
  }

  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const content = `
    <form id="deleteExpenseForm" method="POST" action="?page=expenses">
      <input type="hidden" name="action" value="update_entity">
      <input type="hidden" name="entity" value="expense_delete">
      <input type="hidden" name="id" value="${expenseId}">
      <input type="hidden" name="csrf_token" value="${csrfToken}">
      <div style="text-align: center; padding: 20px 0;">
        <i class="fa-solid fa-trash" style="font-size: 48px; color: #EF4444; margin-bottom: 16px;"></i>
        <p style="margin: 0; font-size: 16px;">Are you sure you want to delete this expense?</p>
        <p style="margin: 8px 0 0 0; font-size: 13px; color: #6B7280;">This action cannot be undone.</p>
      </div>
    </form>
  `;

  openModal("Delete Expense", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: "Delete",
      class: "btn-danger",
      onclick: 'document.getElementById("deleteExpenseForm").requestSubmit()',
    },
  ]);
}

function openAddInvoiceModal() {
  const customers = Array.isArray(APP_CONFIG.saleCustomers)
    ? APP_CONFIG.saleCustomers
    : [];

  if (customers.length === 0) {
    showToast(
      "warning",
      "Please add at least one customer before creating an invoice.",
    );
    return;
  }

  const customerOptions = customers
    .map((customer) => ({
      value: String(customer.id ?? ""),
      label: String(customer.name ?? `Customer #${customer.id || ""}`),
    }))
    .sort((a, b) =>
      a.label.localeCompare(b.label, undefined, { sensitivity: "base" }),
    );

  openEntityModal({
    key: "invoice",
    page: "invoices",
    entity: "invoice",
    title: "Create Invoice",
    entityName: "Invoice",
    submitText: "Create Invoice",
    successMessage: "Invoice created successfully!",
    fields: [
      {
        label: "Customer",
        name: "customer_id",
        type: "select",
        required: true,
        searchable: true,
        searchPlaceholder: "Search customer by name...",
        options: customerOptions,
      },
      {
        label: "Amount (Tsh)",
        name: "amount",
        type: "number",
        required: true,
        min: 1,
        step: 0.01,
        placeholder: "Enter amount",
      },
      {
        label: "Status",
        name: "status",
        type: "select",
        required: true,
        options: [
          { value: "Pending", label: "Pending" },
          { value: "Paid", label: "Paid" },
          { value: "Cancelled", label: "Cancelled" },
        ],
      },
    ],
  });
}

function openAddDeliveryModal() {
  const customers = Array.isArray(APP_CONFIG.saleCustomers)
    ? APP_CONFIG.saleCustomers
    : [];

  if (customers.length === 0) {
    showToast(
      "warning",
      "Please add at least one customer before creating a delivery.",
    );
    return;
  }

  const normalizedCustomers = customers
    .map((customer) => ({
      value: String(customer.id ?? "").trim(),
      label: String(customer.name ?? `Customer #${customer.id || ""}`).trim(),
    }))
    .filter((customer) => customer.value !== "" && customer.label !== "");

  const walkInIndex = normalizedCustomers.findIndex(
    (customer) => customer.label.toLowerCase() === "walk-in customer",
  );

  const orderedCustomers =
    walkInIndex >= 0
      ? [
          normalizedCustomers[walkInIndex],
          ...normalizedCustomers.filter((_, index) => index !== walkInIndex),
        ]
      : [...normalizedCustomers];

  const customerOptions = orderedCustomers
    .map((customer, index) => ({
      value: customer.value,
      label: customer.label,
      selected:
        customer.label.toLowerCase() === "walk-in customer" ||
        (walkInIndex < 0 && index === 0),
    }))
    .sort((a, b) =>
      a.label.localeCompare(b.label, undefined, { sensitivity: "base" }),
    );

  openEntityModal({
    key: "delivery",
    page: "deliveries",
    entity: "delivery",
    title: "Schedule Delivery",
    entityName: "Delivery",
    submitText: "Save Delivery",
    successMessage: "Delivery saved successfully!",
    fields: [
      {
        label: "Customer",
        name: "customer_id",
        type: "select",
        required: true,
        searchable: true,
        searchPlaceholder: "Search customer by name...",
        options: customerOptions,
      },
      {
        label: "Amount (Tsh)",
        name: "amount",
        type: "number",
        required: true,
        min: 1,
        step: 0.01,
        placeholder: "Enter amount",
      },
    ],
  });
}

function openAddReceivingModal() {
  const suppliers = Array.isArray(APP_CONFIG.suppliers)
    ? APP_CONFIG.suppliers
    : [];
  const purchaseOrders = Array.isArray(APP_CONFIG.receivingPurchaseOrders)
    ? APP_CONFIG.receivingPurchaseOrders
    : [];
  const activeSuppliers = suppliers.filter(
    (supplier) =>
      String(supplier?.status || "Active").toLowerCase() !== "inactive",
  );
  const supplierOptions =
    activeSuppliers.length > 0
      ? activeSuppliers
          .map(
            (supplier) =>
              `<option value="${escapeHtml(String(supplier.id ?? ""))}">${escapeHtml(String(supplier.name ?? "Supplier"))}</option>`,
          )
          .join("")
      : '<option value="">No suppliers available</option>';

  const products = Array.isArray(APP_CONFIG.inventoryProducts)
    ? APP_CONFIG.inventoryProducts
    : [];
  const productOptions = products
    .map(
      (product) =>
        `<option value="${escapeHtml(String(product.id ?? ""))}">${escapeHtml(String(product.name ?? "Product"))}</option>`,
    )
    .join("");

  const productSearchOptions = products
    .map((product) => {
      const productId = String(product.id ?? "").trim();
      const productName = String(product.name ?? "Product").trim();
      const productSku = String(product.sku ?? "").trim();
      const label = productSku
        ? `${productId} - ${productName} (${productSku})`
        : `${productId} - ${productName}`;
      return `<option value="${escapeHtml(label)}"></option>`;
    })
    .join("");

  const purchaseOrderOptions = purchaseOrders
    .map((order) => {
      const orderId = Number.parseInt(String(order?.id || "0"), 10);
      const poNo = String(order?.po_no || "").trim();
      const supplierName = String(order?.supplier_name || "Supplier").trim();
      const status = String(order?.status || "Pending").trim();
      const amount = Number.parseFloat(String(order?.amount || "0"));
      const amountLabel = Number.isFinite(amount) ? formatMoney(amount) : "0";
      const label = `${poNo || `PO #${orderId}`} - ${supplierName} (${status}) Tsh ${amountLabel}`;
      return `<option value="${escapeHtml(String(orderId))}">${escapeHtml(label)}</option>`;
    })
    .join("");

  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const content = `
    <form id="receivingForm" method="POST" action="?page=receiving">
      <input type="hidden" name="action" value="create_entity">
      <input type="hidden" name="entity" value="receiving">
      <input type="hidden" name="csrf_token" value="${csrfToken}">
      <input type="hidden" name="items_json" id="receivingItemsJson" value="[]">

      <div class="form-group">
        <label>Linked Purchase Order (Optional)</label>
        <select name="purchase_order_id" id="receivingPurchaseOrder">
          <option value="">No linked PO</option>
          ${purchaseOrderOptions}
        </select>
        <small style="display:block; margin-top:6px; color:#6B7280;">Selecting a PO auto-fills supplier and amount. Completing this receiving auto-marks that PO as Received.</small>
      </div>

      <div class="form-group">
        <label>Supplier</label>
        <select name="supplier_id" id="receivingSupplier" required>
          <option value="">Select supplier</option>
          ${supplierOptions}
        </select>
      </div>

      <div class="form-group">
        <label>Status</label>
        <select name="status" required>
          <option value="Pending" selected>Pending</option>
          <option value="Received">Received</option>
          <option value="Completed">Completed</option>
        </select>
      </div>

      <div class="form-group">
        <label style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
          <span>Amount (Tsh)</span>
          <button type="button" id="receivingUseCalculatedBtn" class="btn btn-secondary" style="padding:6px 10px; font-size:12px;">Use Calculated</button>
        </label>
        <input type="number" name="amount" id="receivingAmount" min="0" step="0.01" value="0">
        <small id="receivingCalculatedHint" style="display:block; margin-top:6px; color:#6B7280;">Calculated from items: Tsh 0</small>
      </div>

      <div class="form-group">
        <label>Items</label>
        <datalist id="receivingProductDatalist">
          ${productSearchOptions}
        </datalist>
        <small style="display:block; margin-bottom:8px; color:#6B7280;">
          Product search accepts ID or name. The two 0 fields are Rejected Qty and Unit Cost.
        </small>
        <div id="receivingItemsContainer" style="display:grid; gap:8px;"></div>
        <button type="button" class="btn btn-secondary" id="addReceivingItemBtn" style="margin-top:8px;">
          <i class="fa-solid fa-plus"></i> Add Item
        </button>
      </div>
    </form>
  `;

  openModal("Record Receiving", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: "Save Receiving",
      class: "btn-primary",
      onclick: 'document.getElementById("receivingForm").requestSubmit()',
    },
  ]);

  const form = document.getElementById("receivingForm");
  const itemsContainer = document.getElementById("receivingItemsContainer");
  const addItemBtn = document.getElementById("addReceivingItemBtn");
  const amountInput = document.getElementById("receivingAmount");
  const itemsJsonInput = document.getElementById("receivingItemsJson");
  const calculatedHint = document.getElementById("receivingCalculatedHint");
  const useCalculatedBtn = document.getElementById("receivingUseCalculatedBtn");
  const purchaseOrderInput = document.getElementById("receivingPurchaseOrder");
  const supplierInput = document.getElementById("receivingSupplier");

  const purchaseOrderById = purchaseOrders.reduce((acc, order) => {
    const orderId = Number.parseInt(String(order?.id || "0"), 10);
    if (Number.isFinite(orderId) && orderId > 0) {
      acc[orderId] = order;
    }
    return acc;
  }, {});

  if (
    !form ||
    !itemsContainer ||
    !addItemBtn ||
    !amountInput ||
    !itemsJsonInput ||
    !calculatedHint ||
    !useCalculatedBtn ||
    !purchaseOrderInput ||
    !supplierInput
  ) {
    return;
  }

  const parseProductIdFromSearch = (rawValue) => {
    const value = String(rawValue || "").trim();
    if (value === "") {
      return 0;
    }

    if (/^\d+$/.test(value)) {
      return Number.parseInt(value, 10);
    }

    const idMatch = value.match(/^(\d+)\s*-/);
    if (idMatch) {
      return Number.parseInt(idMatch[1], 10);
    }

    const normalized = value.toLowerCase();
    const matchedProduct = products.find(
      (product) =>
        String(product?.name || "")
          .trim()
          .toLowerCase() === normalized,
    );
    return matchedProduct
      ? Number.parseInt(String(matchedProduct.id || "0"), 10)
      : 0;
  };

  const syncRowProductId = (row) => {
    const searchInput = row.querySelector(".receiving-product-search");
    const idInput = row.querySelector(".receiving-product");
    if (!searchInput || !idInput) {
      return;
    }

    const parsedId = parseProductIdFromSearch(searchInput.value);
    idInput.value = parsedId > 0 ? String(parsedId) : "";
  };

  const buildItemRow = () => {
    const row = document.createElement("div");
    row.className = "receiving-item-row";
    row.style.display = "grid";
    row.style.gridTemplateColumns = "2fr 1fr 1fr 1fr auto";
    row.style.gap = "8px";
    row.innerHTML = `
      <input type="text" class="receiving-product-search" list="receivingProductDatalist" placeholder="Search product by ID/name" required>
      <select class="receiving-product" required style="display:none;">
        <option value="">Product</option>
        ${productOptions}
      </select>
      <input type="number" class="receiving-qty" min="0" step="1" placeholder="Received Qty" value="1" required>
      <input type="number" class="receiving-reject" min="0" step="1" placeholder="Rejected Qty" value="0" title="Rejected quantity">
      <input type="number" class="receiving-cost" min="0" step="0.01" placeholder="Unit Cost" value="0" title="Unit cost per received item" required>
      <button type="button" class="btn-icon danger receiving-remove" title="Remove"><i class="fa-solid fa-trash"></i></button>
    `;
    return row;
  };

  const serializeItems = () => {
    const rows = Array.from(
      itemsContainer.querySelectorAll(".receiving-item-row"),
    );
    const items = [];
    let total = 0;

    rows.forEach((row) => {
      const productInput = row.querySelector(".receiving-product");
      syncRowProductId(row);
      const qtyInput = row.querySelector(".receiving-qty");
      const rejectInput = row.querySelector(".receiving-reject");
      const costInput = row.querySelector(".receiving-cost");

      const productId = Number.parseInt(String(productInput?.value || "0"), 10);
      const qtyReceived = Math.max(
        0,
        Number.parseInt(String(qtyInput?.value || "0"), 10) || 0,
      );
      const qtyRejected = Math.max(
        0,
        Number.parseInt(String(rejectInput?.value || "0"), 10) || 0,
      );
      const unitCost = Math.max(
        0,
        Number.parseFloat(String(costInput?.value || "0")) || 0,
      );

      if (productId > 0 && qtyReceived + qtyRejected > 0) {
        const lineTotal = qtyReceived * unitCost;
        total += lineTotal;
        items.push({
          product_id: productId,
          quantity_received: qtyReceived,
          quantity_rejected: qtyRejected,
          unit_cost: unitCost,
          line_total: lineTotal,
        });
      }
    });

    const roundedTotal = Number(total.toFixed(2));
    if (amountInput.dataset.manual !== "1") {
      amountInput.value = String(roundedTotal);
    }
    calculatedHint.textContent = `Calculated from items: Tsh ${formatMoney(roundedTotal)}`;
    itemsJsonInput.value = JSON.stringify(items);
    return { items, total: roundedTotal };
  };

  const addRow = () => {
    const row = buildItemRow();
    itemsContainer.appendChild(row);
    serializeItems();
  };

  addItemBtn.addEventListener("click", addRow);

  itemsContainer.addEventListener("click", (event) => {
    const removeBtn = event.target.closest(".receiving-remove");
    if (!removeBtn) {
      return;
    }

    const row = removeBtn.closest(".receiving-item-row");
    if (row) {
      row.remove();
      serializeItems();
    }
  });

  itemsContainer.addEventListener("input", () => {
    serializeItems();
  });

  amountInput.addEventListener("input", () => {
    amountInput.dataset.manual = "1";
  });

  useCalculatedBtn.addEventListener("click", () => {
    const { total } = serializeItems();
    amountInput.dataset.manual = "0";
    amountInput.value = String(total);
  });

  purchaseOrderInput.addEventListener("change", () => {
    const selectedId = Number.parseInt(
      String(purchaseOrderInput.value || "0"),
      10,
    );
    if (!Number.isFinite(selectedId) || selectedId <= 0) {
      return;
    }

    const selectedOrder = purchaseOrderById[selectedId];
    if (!selectedOrder) {
      return;
    }

    const linkedSupplierId = Number.parseInt(
      String(selectedOrder.supplier_id || "0"),
      10,
    );
    if (Number.isFinite(linkedSupplierId) && linkedSupplierId > 0) {
      supplierInput.value = String(linkedSupplierId);
    }

    const linkedAmount = Number.parseFloat(String(selectedOrder.amount || "0"));
    if (Number.isFinite(linkedAmount) && linkedAmount > 0) {
      amountInput.dataset.manual = "1";
      amountInput.value = String(linkedAmount);
    }
  });

  form.addEventListener("submit", (event) => {
    const supplierId = String(supplierInput?.value || "").trim();
    const payload = serializeItems();
    const items = payload.items;
    const total = Number.parseFloat(String(amountInput.value || "0"));
    const selectedPoId = Number.parseInt(
      String(purchaseOrderInput.value || "0"),
      10,
    );

    if (supplierId === "") {
      event.preventDefault();
      showToast("warning", "Please select a supplier.");
      return;
    }

    if (items.length === 0) {
      event.preventDefault();
      showToast("warning", "Add at least one receiving item.");
      return;
    }

    if (!Number.isFinite(total) || total <= 0) {
      event.preventDefault();
      showToast("warning", "Receiving total must be greater than zero.");
      return;
    }

    if (Number.isFinite(selectedPoId) && selectedPoId > 0) {
      const linkedOrder = purchaseOrderById[selectedPoId];
      const linkedSupplierId = Number.parseInt(
        String(linkedOrder?.supplier_id || "0"),
        10,
      );

      if (
        Number.isFinite(linkedSupplierId) &&
        linkedSupplierId > 0 &&
        Number.parseInt(supplierId, 10) !== linkedSupplierId
      ) {
        event.preventDefault();
        showToast(
          "warning",
          "Selected supplier must match the linked purchase order supplier.",
        );
      }
    }
  });

  addRow();
}

function openAddQuotationModal() {
  const customers = Array.isArray(APP_CONFIG.saleCustomers)
    ? APP_CONFIG.saleCustomers
    : [];

  if (customers.length === 0) {
    showToast(
      "warning",
      "Please add at least one customer before creating a quotation.",
    );
    return;
  }

  const normalizedCustomers = customers
    .map((customer) => ({
      value: String(customer.id ?? "").trim(),
      label: String(customer.name ?? `Customer #${customer.id || ""}`).trim(),
    }))
    .filter((customer) => customer.value !== "" && customer.label !== "");

  const walkInIndex = normalizedCustomers.findIndex(
    (customer) => customer.label.toLowerCase() === "walk-in customer",
  );

  const orderedCustomers =
    walkInIndex >= 0
      ? [
          normalizedCustomers[walkInIndex],
          ...normalizedCustomers.filter((_, index) => index !== walkInIndex),
        ]
      : [...normalizedCustomers];

  orderedCustomers.sort((a, b) => {
    if (a.label.toLowerCase() === "walk-in customer") {
      return -1;
    }
    if (b.label.toLowerCase() === "walk-in customer") {
      return 1;
    }
    return a.label.localeCompare(b.label, undefined, { sensitivity: "base" });
  });

  const customerOptions = orderedCustomers.map((customer, index) => ({
    value: customer.value,
    label: customer.label,
    selected:
      customer.label.toLowerCase() === "walk-in customer" ||
      (walkInIndex < 0 && index === 0),
  }));

  openEntityModal({
    key: "quotation",
    page: "quotations",
    entity: "quotation",
    title: "Create Quotation",
    entityName: "Quotation",
    submitText: "Create Quotation",
    successMessage: "Quotation created successfully!",
    fields: [
      {
        label: "Customer",
        name: "customer_id",
        type: "select",
        required: true,
        searchable: true,
        searchPlaceholder: "Search customer by name...",
        options: customerOptions,
      },
      {
        label: "Amount (Tsh)",
        name: "amount",
        type: "number",
        required: true,
        min: 1,
        step: 0.01,
        placeholder: "Enter amount",
      },
    ],
  });
}

async function openAddPOModal() {
  const suppliers = Array.isArray(APP_CONFIG.suppliers)
    ? APP_CONFIG.suppliers
    : [];
  const fallbackProducts = Array.isArray(APP_CONFIG.poProducts)
    ? APP_CONFIG.poProducts
    : Array.isArray(APP_CONFIG.inventoryProducts)
      ? APP_CONFIG.inventoryProducts
      : [];

  const fetchLatestProducts = async () => {
    try {
      const response = await fetch(
        `products_feed.php?limit=1000&_=${Date.now()}`,
        {
          method: "GET",
          cache: "no-store",
          credentials: "same-origin",
        },
      );
      if (!response.ok) {
        return null;
      }

      const payload = await response.json();
      if (
        !payload ||
        payload.success !== true ||
        !Array.isArray(payload.items)
      ) {
        return null;
      }

      return payload.items;
    } catch (error) {
      return null;
    }
  };

  const latestProducts = await fetchLatestProducts();
  const products = Array.isArray(latestProducts)
    ? latestProducts
    : fallbackProducts;
  const productSourceLabel = Array.isArray(latestProducts) ? "Live" : "Cached";
  const activeSuppliers = suppliers.filter(
    (supplier) =>
      String(supplier.status || "Active").toLowerCase() !== "inactive",
  );

  if (activeSuppliers.length === 0) {
    showToast(
      "warning",
      "Please add an active supplier before creating a purchase order.",
    );
    return;
  }

  if (products.length === 0) {
    showToast(
      "warning",
      "Please add at least one product in inventory before creating a purchase order.",
    );
    return;
  }

  const supplierOptions = activeSuppliers
    .map(
      (supplier) =>
        `<option value="${escapeHtml(String(supplier.id ?? ""))}">${escapeHtml(String(supplier.name || `Supplier #${supplier.id || ""}`))}</option>`,
    )
    .join("");

  const toNumber = (value, fallback = 0) => {
    const parsed = Number.parseFloat(String(value ?? ""));
    return Number.isFinite(parsed) ? parsed : fallback;
  };

  const normalizedProducts = products
    .map((product) => {
      const id = Number.parseInt(String(product.id ?? "0"), 10);
      if (!Number.isFinite(id) || id <= 0) {
        return null;
      }

      const name = String(product.name ?? "Product").trim() || "Product";
      const sku = String(product.sku ?? "").trim();
      const stockQtyRaw = Number.parseInt(String(product.stock_qty ?? "0"), 10);
      const stockQty = Number.isFinite(stockQtyRaw) ? stockQtyRaw : 0;
      const unitPrice = toNumber(product.unit_price, 0);

      return {
        id,
        name,
        sku,
        stockQty,
        unitPrice,
        searchLabel: sku
          ? `${id} - ${name} (${sku}) - Stock: ${stockQty}`
          : `${id} - ${name} - Stock: ${stockQty}`,
      };
    })
    .filter(Boolean);

  const productsById = new Map(
    normalizedProducts.map((product) => [product.id, product]),
  );

  const productSearchOptions = normalizedProducts
    .map(
      (product) =>
        `<option value="${escapeHtml(product.searchLabel)}"></option>`,
    )
    .join("");

  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const content = `
    <form id="purchaseOrderForm" method="POST" action="?page=purchase-orders">
      <input type="hidden" name="action" value="create_entity">
      <input type="hidden" name="entity" value="purchase_order">
      <input type="hidden" name="csrf_token" value="${csrfToken}">
      <input type="hidden" name="items_json" id="poItemsJson" value="[]">

      <div class="form-group">
        <label>Supplier</label>
        <select name="supplier_id" required>
          <option value="">Select supplier</option>
          ${supplierOptions}
        </select>
      </div>

      <div class="form-group">
        <label>Expected Delivery Date</label>
        <input type="date" name="expected_delivery_date">
      </div>

      <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" rows="3" placeholder="What this PO must include, terms, or delivery instructions"></textarea>
      </div>

      <div class="form-group">
        <label style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
          <span>Total Amount (Tsh)</span>
          <button type="button" id="poUseCalculatedBtn" class="btn btn-secondary" style="padding:6px 10px; font-size:12px;">Use Calculated</button>
        </label>
        <input type="number" name="amount" id="poAmount" min="0" step="0.01" value="0">
        <small id="poCalculatedHint" style="display:block; margin-top:6px; color:#6B7280;">Calculated from items: Tsh 0</small>
      </div>

      <div class="form-group">
        <label>Items</label>
        <datalist id="poProductDatalist">
          ${productSearchOptions}
        </datalist>
        <small style="display:block; margin-bottom:8px; color:#6B7280;">
          Search by Product ID, name, or SKU. Stock is visible for each selected product.
        </small>
        <small id="poProductsLoadedNote" style="display:block; margin-bottom:8px; color:#4B5563; font-weight:600;">
          Products loaded: ${normalizedProducts.length.toLocaleString()} (${productSourceLabel})
        </small>
        <div id="poItemsContainer" style="display:grid; gap:8px;"></div>
        <button type="button" class="btn btn-secondary" id="addPOItemBtn" style="margin-top:8px;">
          <i class="fa-solid fa-plus"></i> Add Item
        </button>
      </div>
    </form>
  `;

  openModal("Create Purchase Order", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: "Create PO",
      class: "btn-primary",
      onclick: 'document.getElementById("purchaseOrderForm").requestSubmit()',
    },
  ]);

  const form = document.getElementById("purchaseOrderForm");
  const itemsContainer = document.getElementById("poItemsContainer");
  const addItemBtn = document.getElementById("addPOItemBtn");
  const amountInput = document.getElementById("poAmount");
  const itemsJsonInput = document.getElementById("poItemsJson");
  const calculatedHint = document.getElementById("poCalculatedHint");
  const useCalculatedBtn = document.getElementById("poUseCalculatedBtn");

  if (
    !form ||
    !itemsContainer ||
    !addItemBtn ||
    !amountInput ||
    !itemsJsonInput ||
    !calculatedHint ||
    !useCalculatedBtn
  ) {
    return;
  }

  const formatProductLabel = (product) => {
    if (!product) {
      return "";
    }

    return product.sku
      ? `${product.id} - ${product.name} (${product.sku}) - Stock: ${product.stockQty}`
      : `${product.id} - ${product.name} - Stock: ${product.stockQty}`;
  };

  const findProductBySearch = (rawValue) => {
    const value = String(rawValue || "").trim();
    if (value === "") {
      return null;
    }

    if (/^\d+$/.test(value)) {
      return productsById.get(Number.parseInt(value, 10)) || null;
    }

    const idPrefixMatch = value.match(/^(\d+)\s*-/);
    if (idPrefixMatch) {
      const prefixedId = Number.parseInt(idPrefixMatch[1], 10);
      return productsById.get(prefixedId) || null;
    }

    const needle = value.toLowerCase();
    return (
      normalizedProducts.find((product) => {
        const haystack =
          `${product.id} ${product.name} ${product.sku}`.toLowerCase();
        return haystack.includes(needle);
      }) || null
    );
  };

  const setRowProduct = (row, product, forceUnitCost = true) => {
    if (!row) {
      return;
    }

    const searchInput = row.querySelector(".po-item-product-search");
    const productIdInput = row.querySelector(".po-item-product-id");
    const stockLabel = row.querySelector(".po-item-stock");
    const unitCostInput = row.querySelector(".po-item-unit-cost");

    if (!searchInput || !productIdInput || !stockLabel || !unitCostInput) {
      return;
    }

    if (!product) {
      productIdInput.value = "";
      stockLabel.textContent = "Stock: -";
      return;
    }

    searchInput.value = formatProductLabel(product);
    productIdInput.value = String(product.id);
    stockLabel.textContent = `Stock: ${product.stockQty}`;
    if (forceUnitCost || String(unitCostInput.value || "").trim() === "") {
      unitCostInput.value = String(product.unitPrice);
    }
  };

  const createItemRow = () => {
    const row = document.createElement("div");
    row.className = "po-item-row";
    row.style.display = "grid";
    row.style.gridTemplateColumns = "1fr";
    row.style.gap = "8px";
    row.style.alignItems = "center";
    row.style.padding = "8px";
    row.style.border = "1px solid #E5E7EB";
    row.style.borderRadius = "8px";
    row.style.background = "#FFFFFF";

    row.innerHTML = `
      <div style="display:grid; gap:6px; min-width:0;">
        <input type="search" class="po-item-product-search" list="poProductDatalist" placeholder="Search product by ID, name, or SKU" required>
        <input type="hidden" class="po-item-product-id" value="">
        <small class="po-item-stock" style="color:#6B7280;">Stock: -</small>
      </div>
      <div style="display:grid; grid-template-columns:minmax(70px, 0.8fr) minmax(110px, 1fr) minmax(120px, 1fr) auto; gap:8px; align-items:center;">
        <input type="number" class="po-item-qty" min="1" step="1" value="1" required title="Quantity">
        <input type="number" class="po-item-unit-cost" min="0" step="0.01" value="0" required title="Unit Cost">
        <div class="po-item-line-total" style="padding:10px 12px; border:1px solid #E5E7EB; border-radius:8px; background:#F9FAFB; white-space:nowrap; font-weight:600;">Tsh 0</div>
        <button type="button" class="btn btn-secondary po-item-remove" title="Remove item"><i class="fa-solid fa-minus"></i></button>
      </div>
    `;

    const searchInput = row.querySelector(".po-item-product-search");
    if (searchInput) {
      searchInput.addEventListener("change", () => {
        const product = findProductBySearch(searchInput.value);
        if (!product) {
          showToast("warning", "Select a valid product from the search list.");
          setRowProduct(row, null, false);
          serializeItems();
          return;
        }

        setRowProduct(row, product, true);
        serializeItems();
      });

      searchInput.addEventListener("blur", () => {
        const product = findProductBySearch(searchInput.value);
        if (product) {
          setRowProduct(row, product, false);
          serializeItems();
        }
      });
    }

    const defaultProduct = normalizedProducts[0] || null;
    setRowProduct(row, defaultProduct, true);

    return row;
  };

  const serializeItems = () => {
    const rows = Array.from(itemsContainer.querySelectorAll(".po-item-row"));
    const items = [];
    let total = 0;

    rows.forEach((row) => {
      const productIdInput = row.querySelector(".po-item-product-id");
      const qtyInput = row.querySelector(".po-item-qty");
      const unitCostInput = row.querySelector(".po-item-unit-cost");
      const lineTotalInput = row.querySelector(".po-item-line-total");

      const productId = Number.parseInt(
        String(productIdInput?.value || "0"),
        10,
      );
      const quantity = Number.parseInt(String(qtyInput?.value || "0"), 10);
      const unitCost = Number.parseFloat(String(unitCostInput?.value || "0"));

      if (
        !Number.isFinite(productId) ||
        productId <= 0 ||
        !Number.isFinite(quantity) ||
        quantity <= 0 ||
        !Number.isFinite(unitCost) ||
        unitCost < 0
      ) {
        return;
      }

      const lineTotal = quantity * unitCost;
      if (lineTotalInput) {
        lineTotalInput.textContent = `Tsh ${formatMoney(lineTotal)}`;
      }
      total += lineTotal;
      items.push({
        product_id: productId,
        quantity,
        unit_cost: unitCost,
      });
    });

    itemsJsonInput.value = JSON.stringify(items);
    calculatedHint.textContent = `Calculated from items: Tsh ${formatMoney(total)}`;
    if (amountInput.dataset.manual !== "1") {
      amountInput.value = String(total.toFixed(2));
    }

    return { items, total };
  };

  amountInput.dataset.manual = "0";
  amountInput.addEventListener("input", () => {
    amountInput.dataset.manual = "1";
  });

  useCalculatedBtn.addEventListener("click", () => {
    const { total } = serializeItems();
    amountInput.dataset.manual = "0";
    amountInput.value = String(total.toFixed(2));
  });

  addItemBtn.addEventListener("click", () => {
    itemsContainer.appendChild(createItemRow());
    serializeItems();
  });

  itemsContainer.addEventListener("click", (event) => {
    const removeBtn = event.target.closest(".po-item-remove");
    if (!removeBtn) {
      return;
    }

    const row = removeBtn.closest(".po-item-row");
    if (!row) {
      return;
    }

    if (itemsContainer.querySelectorAll(".po-item-row").length <= 1) {
      showToast("warning", "A purchase order must have at least one item.");
      return;
    }

    row.remove();
    serializeItems();
  });

  itemsContainer.addEventListener("input", () => {
    serializeItems();
  });

  form.addEventListener("submit", (event) => {
    const supplierInput = form.querySelector('select[name="supplier_id"]');
    const supplierId = String(supplierInput?.value || "").trim();
    const payload = serializeItems();
    const enteredAmount = Number.parseFloat(String(amountInput.value || "0"));

    if (supplierId === "") {
      event.preventDefault();
      showToast("warning", "Please select a supplier.");
      return;
    }

    if (payload.items.length === 0) {
      event.preventDefault();
      showToast("warning", "Add at least one valid PO item.");
      return;
    }

    if (!Number.isFinite(enteredAmount) || enteredAmount <= 0) {
      event.preventDefault();
      showToast("warning", "PO amount must be greater than zero.");
      return;
    }

    if (!Number.isFinite(payload.total) || payload.total <= 0) {
      event.preventDefault();
      showToast("warning", "PO items must produce a valid calculated total.");
    }
  });

  itemsContainer.appendChild(createItemRow());
  serializeItems();
}

function openAddReturnModal() {
  const products = Array.isArray(APP_CONFIG.returnProducts)
    ? APP_CONFIG.returnProducts
    : Array.isArray(APP_CONFIG.inventoryProducts)
      ? APP_CONFIG.inventoryProducts
      : [];

  if (products.length === 0) {
    showToast(
      "Please add at least one product in Inventory before recording a return.",
      "warning",
    );
    return;
  }

  const productOptions = products
    .map((product) => {
      const id = String(product.id ?? "");
      const name = String(product.name ?? "Product");
      const sku = String(product.sku ?? "");
      const stock = Number(product.stock_qty ?? 0).toLocaleString();
      return {
        value: id,
        label: sku
          ? `${name} (${sku}) - Stock: ${stock}`
          : `${name} - Stock: ${stock}`,
      };
    })
    .sort((a, b) =>
      a.label.localeCompare(b.label, undefined, { sensitivity: "base" }),
    );

  openEntityModal({
    key: "return",
    page: "returns",
    entity: "return",
    title: "Record Return",
    entityName: "Return",
    submitText: "Save Return",
    successMessage: "Return recorded successfully!",
    fields: [
      {
        label: "Product",
        name: "product_id",
        type: "select",
        required: true,
        searchable: true,
        searchPlaceholder: "Search product by name, SKU, or stock...",
        options: productOptions,
      },
      {
        label: "Quantity",
        name: "quantity",
        type: "number",
        required: true,
        min: 1,
        step: 1,
        placeholder: "Enter quantity",
      },
      {
        label: "Expired Return",
        name: "is_expired",
        type: "checkbox",
        value: "1",
        checkboxLabel: "Expired item (do not add back to stock)",
      },
      {
        label: "Reason",
        name: "reason",
        type: "textarea",
        placeholder: "Optional reason",
        rows: 3,
      },
    ],
  });
}

function openAddAppointmentModal() {
  const customers = Array.isArray(APP_CONFIG.saleCustomers)
    ? APP_CONFIG.saleCustomers
    : [];
  const customerOptions =
    customers.length > 0
      ? customers.map((customer) => ({
          value: String(customer.id ?? ""),
          label: String(customer.name ?? "Customer"),
        }))
      : [{ value: "1", label: "Walk-in Customer" }];

  openEntityModal({
    key: "appointment",
    page: "appointments",
    entity: "appointment",
    title: "Schedule Appointment",
    entityName: "Appointment",
    submitText: "Save Appointment",
    successMessage: "Appointment scheduled successfully!",
    fields: [
      {
        label: "Title",
        name: "title",
        required: true,
        placeholder: "Appointment title",
      },
      {
        label: "Customer",
        name: "customer_id",
        type: "select",
        required: true,
        options: customerOptions,
      },
      {
        label: "Date & Time",
        name: "appointment_date",
        type: "datetime-local",
        required: true,
        placeholder: "",
      },
    ],
  });
}

function openAddLocationModal() {
  openEntityModal({
    key: "location",
    page: "locations",
    entity: "location",
    title: "Add Location",
    entityName: "Location",
    submitText: "Save Location",
    successMessage: "Location added successfully!",
    fields: [
      {
        label: "Location Name",
        name: "name",
        required: true,
        placeholder: "Enter location name",
      },
      {
        label: "Address",
        name: "address",
        type: "textarea",
        required: true,
        placeholder: "Enter address",
        rows: 3,
      },
      { label: "City", name: "city", placeholder: "Enter city" },
      {
        label: "Phone",
        name: "phone",
        type: "tel",
        placeholder: "Enter phone",
      },
    ],
  });
}

function openComposeMessageModal() {
  openEntityModal({
    key: "message",
    page: "messages",
    entity: "message",
    title: "Compose Message",
    entityName: "Message",
    submitText: "Send Message",
    successMessage: "Message sent successfully!",
    fields: [
      {
        label: "Recipient",
        name: "recipient",
        required: true,
        placeholder: "recipient@example.com",
      },
      {
        label: "Subject",
        name: "subject",
        required: true,
        placeholder: "Message subject",
      },
      {
        label: "Message",
        name: "message",
        type: "textarea",
        required: true,
        placeholder: "Type your message",
        rows: 4,
      },
    ],
  });
}

function saveSettings() {
  showToast("success", "Settings saved successfully!");
}

function addLocationRow() {
  const rows = document.getElementById("locationRows");
  if (!rows) {
    return;
  }

  const defaultCityInput = document.getElementById("defaultCity");
  const defaultCity = escapeHtml(defaultCityInput?.value || "Dar es Salaam");

  const row = document.createElement("div");
  row.setAttribute("data-location-row", "");
  row.className = "location-row";

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
  const rows = document.getElementById("locationRows");
  if (!rows) {
    return;
  }

  const rowElements = rows.querySelectorAll("[data-location-row]");
  if (rowElements.length <= 1) {
    showToast("warning", "At least one location row is required.");
    return;
  }

  const row = trigger.closest("[data-location-row]");
  if (row) {
    row.remove();
  }
}

function showEndOfDayReport() {
  const summary = APP_CONFIG.dashboardEodSummary || {};
  const totalSales = Number.parseFloat(summary.totalSales || 0) || 0;
  const transactions = Number.parseInt(summary.transactions || 0, 10) || 0;
  const cash = Number.parseFloat(summary.cash || 0) || 0;
  const mobileMoney = Number.parseFloat(summary.mobileMoney || 0) || 0;

  const now = new Date();
  const dateStr = now.toLocaleDateString("en-US", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
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
                <span class="value">Tsh ${formatMoney(totalSales)}</span>
                </div>
                <div class="eod-stat">
                    <span class="label">Transactions</span>
                <span class="value">${transactions}</span>
                </div>
                <div class="eod-stat">
                    <span class="label">Cash</span>
                <span class="value">Tsh ${formatMoney(cash)}</span>
                </div>
                <div class="eod-stat">
                    <span class="label">Mobile Money</span>
                <span class="value">Tsh ${formatMoney(mobileMoney)}</span>
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
  openModal("End of Day Summary", content, [
    {
      text: "Print Report",
      class: "btn-secondary",
      handler: function () {
        printEndOfDaySummary({
          dateLabel: dateStr,
          totalSales,
          transactions,
          cash,
          mobileMoney,
        });
      },
    },
    {
      text: "Close Day",
      class: "btn-primary",
      handler: function () {
        closeModal();
        showToast("success", "Day closed successfully!");
      },
    },
  ]);
}

function showLogoutConfirm() {
  const content = `
        <div style="text-align: center; padding: 20px 0;">
            <i class="fa-solid fa-right-from-bracket" style="font-size: 48px; color: #EF4444; margin-bottom: 16px;"></i>
            <p style="margin: 0; font-size: 16px;">Are you sure you want to logout?</p>
        </div>
    `;
  openModal("Confirm Logout", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    { text: "Logout", class: "btn-danger", onclick: "logout()" },
  ]);
}

// ============================================
// NOTIFICATIONS
// ============================================

function showNotifications() {
  const panel = document.getElementById("notificationPanel");
  panel.classList.toggle("active");
}

function closeNotifications() {
  const panel = document.getElementById("notificationPanel");
  panel.classList.remove("active");
}

// Close notifications when clicking outside
document.addEventListener("click", function (e) {
  const panel = document.getElementById("notificationPanel");
  const btn = document.querySelector(".notification-btn");
  if (panel && !panel.contains(e.target) && !btn?.contains(e.target)) {
    panel.classList.remove("active");
  }
});

// ============================================
// TOAST NOTIFICATIONS
// ============================================

function showToast(type, message) {
  const container = document.getElementById("toastContainer");
  const toast = document.createElement("div");
  toast.className = `toast ${type}`;
  const localizedMessage =
    getCurrentLanguage() === "sw" ? translateValue(message, "sw") : message;

  const icons = {
    success: "fa-check-circle",
    error: "fa-times-circle",
    warning: "fa-exclamation-triangle",
  };

  // Create elements safely to prevent XSS
  const icon = document.createElement("i");
  icon.className = `fa-solid ${icons[type] || "fa-info-circle"}`;

  const span = document.createElement("span");
  span.textContent = localizedMessage; // Safe: textContent escapes HTML

  toast.appendChild(icon);
  toast.appendChild(span);

  container.appendChild(toast);

  setTimeout(() => {
    toast.style.animation = "slideIn 0.3s ease-out reverse";
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

// ============================================
// TABLE FILTERING
// ============================================

const TABLE_FILTER_STATE = {
  productTable: {
    search: "",
    stock: "all",
    category: "all",
  },
  salesTable: {
    search: "",
    payment: "all",
  },
};

function normalizePaymentGroup(value) {
  const normalized = (value || "")
    .toString()
    .trim()
    .toLowerCase()
    .replace(/[\s-]+/g, "_");

  if (
    normalized === "" ||
    normalized === "all" ||
    normalized === "all_payments"
  ) {
    return "all";
  }

  if (normalized.includes("cash")) {
    return "cash";
  }

  if (normalized.includes("card")) {
    return "card";
  }

  if (normalized.includes("bank")) {
    return "bank_transfer";
  }

  if (
    normalized.includes("mobile") ||
    normalized.includes("mpesa") ||
    normalized.includes("airtel") ||
    normalized.includes("tigo") ||
    normalized.includes("halopesa") ||
    normalized.includes("mixx")
  ) {
    return "mobile";
  }

  return normalized;
}

function applyProductTableFilters() {
  const table = document.getElementById("productTable");
  if (!table) return;

  const rows = table.querySelectorAll("tbody tr");
  const state = TABLE_FILTER_STATE.productTable;
  const searchTerm = (state.search || "").toLowerCase();
  const stockFilter = (state.stock || "all").toLowerCase();
  const categoryFilter = (state.category || "all").toLowerCase();
  const visibleCountEl = document.getElementById("inventoryVisibleCount");
  const noResultEl = document.getElementById("inventoryNoResult");
  let visibleCount = 0;

  rows.forEach((row) => {
    const text = (row.textContent || "").toLowerCase();
    const stockStatus = (row.dataset.stock || "").toLowerCase();
    const category = (row.dataset.category || "").toLowerCase();
    const matchesSearch = searchTerm === "" || text.includes(searchTerm);
    const matchesStock = stockFilter === "all" || stockStatus === stockFilter;
    const matchesCategory =
      categoryFilter === "all" || category === categoryFilter;

    const shouldShow = matchesSearch && matchesStock && matchesCategory;
    row.style.display = shouldShow ? "" : "none";
    if (shouldShow) {
      visibleCount += 1;
    }
  });

  if (visibleCountEl) {
    visibleCountEl.textContent = visibleCount.toLocaleString("en-US");
  }

  if (noResultEl) {
    noResultEl.style.display = visibleCount === 0 ? "flex" : "none";
  }
}

function applySalesTableFilters() {
  const table = document.getElementById("salesTable");
  if (!table) return;

  const rows = table.querySelectorAll("tbody tr");
  const state = TABLE_FILTER_STATE.salesTable;
  const searchTerm = (state.search || "").toLowerCase();
  const paymentFilter = normalizePaymentGroup(state.payment || "all");

  rows.forEach((row) => {
    const text = (row.textContent || "").toLowerCase();
    const rowPayment = normalizePaymentGroup(row.dataset.payment || "");

    const matchesSearch = searchTerm === "" || text.includes(searchTerm);
    const matchesPayment =
      paymentFilter === "all" || rowPayment === paymentFilter;

    row.style.display = matchesSearch && matchesPayment ? "" : "none";
  });
}

function initTableFilters() {
  const productSearch = document.getElementById("productSearch");
  const productStockFilter = document.getElementById("productStockFilter");
  const productCategoryFilter = document.getElementById(
    "productCategoryFilter",
  );
  const customerSearch = document.getElementById("customerSearch");
  const salesSearch = document.getElementById("salesSearch");
  const salesPaymentFilter = document.getElementById("salesPaymentFilter");

  productSearch?.addEventListener("input", () => {
    filterTable("productTable", productSearch.value);
  });

  productStockFilter?.addEventListener("change", () => {
    filterByStock(productStockFilter.value);
  });

  productCategoryFilter?.addEventListener("change", () => {
    filterByCategory(productCategoryFilter.value);
  });

  customerSearch?.addEventListener("input", () => {
    filterTable("customerTable", customerSearch.value);
  });

  salesSearch?.addEventListener("input", () => {
    filterTable("salesTable", salesSearch.value);
  });

  salesPaymentFilter?.addEventListener("change", () => {
    filterByPayment("salesTable", salesPaymentFilter.value);
  });

  if (productStockFilter) {
    TABLE_FILTER_STATE.productTable.stock = (
      productStockFilter.value || "all"
    ).toLowerCase();
  }

  if (productCategoryFilter) {
    TABLE_FILTER_STATE.productTable.category = (
      productCategoryFilter.value || "all"
    ).toLowerCase();
  }

  if (salesPaymentFilter) {
    TABLE_FILTER_STATE.salesTable.payment = normalizePaymentGroup(
      salesPaymentFilter.value || "all",
    );
  }

  applyProductTableFilters();
  applySalesTableFilters();
}

function initCustomerDebtFilters() {
  const filterEl = document.getElementById("customerDebtStatusFilter");
  const table = document.getElementById("customerDebtTable");
  const noResultEl = document.getElementById("customerDebtNoResult");
  if (!filterEl || !table) {
    return;
  }

  const applyFilter = () => {
    const selected = (filterEl.value || "all").toLowerCase();
    const rows = table.querySelectorAll("tbody tr");
    let visibleCount = 0;

    rows.forEach((row) => {
      const status = (
        row.getAttribute("data-credit-status") || ""
      ).toLowerCase();

      if (!status) {
        const show = selected === "all";
        row.style.display = show ? "" : "none";
        if (show) {
          visibleCount += 1;
        }
        return;
      }

      if (selected === "all") {
        row.style.display = "";
        visibleCount += 1;
        return;
      }

      const matchesOpenFilter =
        selected === "open" && (status === "open" || status === "partial");
      const matchesDirect = status === selected;
      const show = matchesOpenFilter || matchesDirect;
      row.style.display = show ? "" : "none";
      if (show) {
        visibleCount += 1;
      }
    });

    if (noResultEl) {
      noResultEl.style.display = visibleCount === 0 ? "flex" : "none";
    }
  };

  filterEl.addEventListener("change", applyFilter);
  filterEl.addEventListener("input", applyFilter);
  applyFilter();
}

function initSecurityLogsFilters() {
  const statusFilterEl = document.getElementById("securityLogsStatusFilter");
  const filterFormEl = document.getElementById("securityLogsFilterForm");
  if (!statusFilterEl || !filterFormEl) {
    return;
  }

  statusFilterEl.addEventListener("change", () => {
    filterFormEl.submit();
  });
}

function initSalesPage() {
  if ((APP_CONFIG.currentPage || "").toLowerCase() !== "sales") {
    return;
  }

  const productsGrid = document.getElementById("salesProductsGrid");
  const searchInput = document.getElementById("salesProductSearch");
  const categoryRow = document.querySelector(".sales-category-row");
  const visibleCountEl = document.getElementById("salesVisibleCount");
  const noResultEl = document.getElementById("salesNoResult");
  const cartItemsEl = document.getElementById("salesCartItems");
  const subtotalEl = document.getElementById("salesSubtotal");
  const taxEl = document.getElementById("salesTax");
  const totalEl = document.getElementById("salesTotal");
  const chargeBtn = document.getElementById("salesChargeBtn");
  const clearBtn = document.getElementById("salesClearAll");

  if (
    !productsGrid ||
    !cartItemsEl ||
    !subtotalEl ||
    !taxEl ||
    !totalEl ||
    !chargeBtn ||
    !clearBtn
  ) {
    return;
  }

  const cart = new Map();
  const customers = Array.isArray(APP_CONFIG.saleCustomers)
    ? APP_CONFIG.saleCustomers
    : [];
  const paymentOptionsRaw = Array.isArray(APP_CONFIG.checkoutPaymentOptions)
    ? APP_CONFIG.checkoutPaymentOptions
    : [];
  const paymentOptions =
    paymentOptionsRaw.length > 0
      ? paymentOptionsRaw
      : [
          { key: "cash", label: "Cash", type: "cash" },
          { key: "card", label: "Card", type: "card" },
          { key: "bank_transfer", label: "Bank Transfer", type: "bank" },
          { key: "mpesa", label: "M-Pesa", type: "mobile", provider: "mpesa" },
          {
            key: "airtel_money",
            label: "Airtel Money",
            type: "mobile",
            provider: "airtel_money",
          },
          {
            key: "tigo_pesa",
            label: "Tigo Pesa",
            type: "mobile",
            provider: "tigo_pesa",
          },
        ];

  if (
    !paymentOptions.some(
      (option) => (option?.key || "").toLowerCase() === "pay_later",
    )
  ) {
    paymentOptions.push({
      key: "pay_later",
      label: "Pay Later",
      type: "credit",
    });
  }

  const toNumber = (value) => {
    const parsed = Number.parseFloat((value || "").toString());
    return Number.isFinite(parsed) ? parsed : 0;
  };

  const formatTsh = (value) => `Tsh ${Math.round(value)}`;

  const formatMoney = (value) => Math.round(value).toLocaleString("en-US");

  const toPaymentMethodValue = (gatewayKey) => {
    switch ((gatewayKey || "").toLowerCase()) {
      case "card":
        return "Card";
      case "bank_transfer":
        return "Bank Transfer";
      case "mpesa":
      case "airtel_money":
      case "tigo_pesa":
        return "Mobile Money";
      case "pay_later":
        return "Cash";
      case "cash":
      default:
        return "Cash";
    }
  };

  const openReceiptModal = (receipt) => {
    if (!receipt || typeof receipt !== "object") {
      return;
    }

    const txNo = escapeHtml(receipt.transaction_no || "N/A");
    const displayReceiptNo = /^TXN-/i.test(receipt.transaction_no || "")
      ? escapeHtml(
          (
            "RCP-" + String(receipt.transaction_no || "").replace(/^TXN-/i, "")
          ).replace(/\s+/g, ""),
        )
      : txNo;
    const customer = escapeHtml(receipt.customer_name || "Walk-in Customer");
    const cashier = escapeHtml(receipt.cashier_name || "Cashier");
    const paymentMethod = escapeHtml(receipt.payment_method || "Cash");
    const createdAtRaw = String(receipt.created_at || "");
    const createdAtDate = createdAtRaw
      ? new Date(createdAtRaw.replace(" ", "T"))
      : null;
    const createdAt =
      createdAtDate && !Number.isNaN(createdAtDate.getTime())
        ? escapeHtml(
            createdAtDate
              .toLocaleString("en-GB", {
                day: "2-digit",
                month: "short",
                year: "numeric",
                hour: "2-digit",
                minute: "2-digit",
                hour12: false,
              })
              .replace(",", " ,"),
          )
        : escapeHtml(createdAtRaw);
    const subtotal = formatMoney(Number(receipt.subtotal || 0));
    const tax = formatMoney(Number(receipt.tax || 0));
    const total = formatMoney(Number(receipt.total || 0));
    const items = Array.isArray(receipt.items) ? receipt.items : [];

    const itemsHtml = items
      .map((item) => {
        const name = escapeHtml(item.name || "Item");
        const qty = Number(item.quantity || 0);
        const lineTotalRaw = Number(item.line_total || 0);
        const unitPriceRaw = Number(
          item.unit_price || (qty > 0 ? lineTotalRaw / qty : 0),
        );
        const eachPrice = formatMoney(unitPriceRaw);
        const lineTotal = formatMoney(Number(item.line_total || 0));
        return `
          <div class="sales-receipt-line">
            <span class="sales-receipt-line-main">
              <strong>${name} x ${qty}</strong>
              <small>Tsh ${eachPrice} each</small>
            </span>
            <strong>Tsh ${lineTotal}</strong>
          </div>
        `;
      })
      .join("");

    const content = `
            <div class="sales-receipt-sheet" id="salesReceiptSheet">
                <div class="sales-receipt-brand">Mchongoma Limited</div>
                <div class="sales-receipt-subtitle">Thank you for your purchase!</div>
                <div class="sales-receipt-time">${createdAt}</div>
                <div class="sales-receipt-meta">
                    <div><span>Receipt #</span><strong>${displayReceiptNo}</strong></div>
                    <div><span>Customer</span><strong>${customer}</strong></div>
                    <div><span>Cashier</span><strong>${cashier}</strong></div>
                </div>
                <div class="sales-receipt-items">${itemsHtml}</div>
                <div class="sales-receipt-totals">
                    <div><span>Subtotal</span><strong>Tsh ${subtotal}</strong></div>
                    <div><span>Tax</span><strong>Tsh ${tax}</strong></div>
                    <div class="sales-receipt-total"><span>TOTAL</span><strong>Tsh ${total}</strong></div>
                    <div><span>Paid via</span><strong>${paymentMethod}</strong></div>
                </div>
                <p class="sales-receipt-thanks">*** Thank you, come again! ***</p>
            </div>
        `;

    const printReceiptFromData = () => {
      const sheet = document.getElementById("salesReceiptSheet");
      if (!sheet) {
        return;
      }

      const printWindow = window.open("", "_blank", "width=420,height=700");
      if (!printWindow) {
        showToast(
          "warning",
          "Pop-up blocked. Enable pop-ups to print receipt.",
        );
        return;
      }

      try {
        printWindow.opener = null;
      } catch (error) {
        // Ignore if browser disallows mutating opener.
      }

      printWindow.document.write(`
                <html>
                    <head>
                        <title>Receipt ${displayReceiptNo}</title>
                        <style>
                  body{font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; padding:16px; color:#111827; background:#f5f7fb;}
                  .sales-receipt-sheet{max-width:360px; margin:0 auto; border:1px solid #cfd8e6; border-radius:10px; padding:12px 11px; background:linear-gradient(180deg, #ffffff 0%, #f8faff 100%);}
                  .sales-receipt-brand{text-align:center; font-size:28px; font-weight:800; margin-bottom:4px; color:#1f2f47;}
                  .sales-receipt-subtitle,.sales-receipt-time{text-align:center; color:#5f6f86; font-size:12px;}
                  .sales-receipt-meta{margin-top:12px; border-top:1px dashed #bcc8db; border-bottom:1px dashed #bcc8db; padding:8px 0;}
                  .sales-receipt-totals{margin-top:12px; border-top:1px dashed #bcc8db; padding-top:8px;}
                  .sales-receipt-meta div,.sales-receipt-line,.sales-receipt-totals div{display:flex; justify-content:space-between; margin-bottom:7px; font-size:12px; align-items:center;}
                  .sales-receipt-items{margin-top:10px;}
                  .sales-receipt-line{align-items:flex-start; border-bottom:1px dashed #c5d0e1; padding:6px 0; margin-bottom:0;}
                  .sales-receipt-line:last-child{border-bottom:none;}
                  .sales-receipt-line-main{display:flex; flex-direction:column; gap:2px;}
                  .sales-receipt-line-main small{color:#68778f; font-size:11px;}
                  .sales-receipt-total{font-weight:800; font-size:16px; color:#3730a3;}
                  .sales-receipt-thanks{text-align:center; margin-top:12px; font-size:12px; color:#5f6f86;}
                        </style>
                    </head>
                    <body>${sheet.outerHTML}</body>
                </html>
            `);
      printWindow.document.close();
      printWindow.focus();
      printWindow.print();
    };

    openModal(
      "Receipt",
      content,
      [
        { text: "New Sale", class: "btn-secondary", handler: closeModal },
        { text: "Print", class: "btn-primary", handler: printReceiptFromData },
      ],
      { modalClass: "modal-receipt" },
    );
  };

  const openCheckoutModal = () => {
    const cartItems = Array.from(cart.values());
    if (cartItems.length === 0) {
      showToast("warning", "Add at least one product to continue.");
      return;
    }

    const normalizedCustomers = customers
      .filter((customer) => customer && typeof customer === "object")
      .map((customer) => ({
        id: String(customer.id ?? "").trim(),
        name: String(customer.name ?? "").trim(),
      }))
      .filter((customer) => customer.name !== "");

    const existingWalkInIndex = normalizedCustomers.findIndex(
      (customer) => customer.name.toLowerCase() === "walk-in customer",
    );
    const walkInCustomer =
      existingWalkInIndex >= 0
        ? normalizedCustomers[existingWalkInIndex]
        : {
            id: "1",
            name: "Walk-in Customer",
          };

    const checkoutCustomers = [
      walkInCustomer,
      ...normalizedCustomers.filter(
        (customer, index) => index !== existingWalkInIndex,
      ),
    ];
    const nonWalkInCustomers = checkoutCustomers.filter(
      (customer) => customer.id !== walkInCustomer.id,
    );

    const customerOptions =
      checkoutCustomers.length > 0
        ? checkoutCustomers
            .map(
              (customer) =>
                `<option value="${escapeHtml(customer.id)}"${customer.id === walkInCustomer.id ? " selected" : ""}>${escapeHtml(customer.name)}</option>`,
            )
            .join("")
        : '<option value="1" selected>Walk-in Customer</option>';

    const paymentIconFor = (option) => {
      const key = (option?.key || "").toLowerCase();
      const type = (option?.type || "").toLowerCase();
      if (type === "mobile") return "fa-mobile-screen-button";
      if (key === "pay_later") return "fa-handshake";
      if (key === "card") return "fa-credit-card";
      if (key === "bank_transfer") return "fa-building-columns";
      return "fa-money-bill-wave";
    };

    const optionTiles = paymentOptions
      .map(
        (option, index) => `
            <button type="button" class="sales-payment-tile ${index === 0 ? "active" : ""}" data-gateway="${escapeHtml(option.key || "")}">
                <i class="fa-solid ${paymentIconFor(option)}"></i>
                <span>${escapeHtml(option.label || "")}</span>
            </button>
        `,
      )
      .join("");

    const cartLines = cartItems
      .map((item) => {
        const lineTotal = item.qty * item.price;
        return `
          <div class="sales-checkout-line">
            <span class="sales-checkout-line-main">
              <strong>${escapeHtml(item.name)} x ${item.qty}</strong>
              <small>Tsh ${escapeHtml(formatMoney(item.price))} each</small>
            </span>
            <strong>Tsh ${formatMoney(lineTotal)}</strong>
          </div>
        `;
      })
      .join("");

    const subtotal = cartItems.reduce(
      (sum, item) => sum + item.qty * item.price,
      0,
    );
    const defaultGateway = (paymentOptions[0]?.key || "cash").toLowerCase();
    const dueDate = new Date();
    dueDate.setDate(dueDate.getDate() + 30);
    const dueDateDefault = `${dueDate.getFullYear()}-${String(dueDate.getMonth() + 1).padStart(2, "0")}-${String(dueDate.getDate()).padStart(2, "0")}`;

    const content = `
            <form id="salesCheckoutForm" method="POST" action="?page=sales" class="sales-checkout-form">
                <input type="hidden" name="action" value="create_entity">
                <input type="hidden" name="entity" value="sale">
                <input type="hidden" name="csrf_token" value="${escapeHtml(APP_CONFIG.csrfToken || "")}">
                <input type="hidden" name="payment_gateway" id="checkoutGateway" value="${escapeHtml(defaultGateway)}">
                <input type="hidden" name="payment_method" id="checkoutPaymentMethod" value="${escapeHtml(toPaymentMethodValue(defaultGateway))}">
                <input type="hidden" name="mobile_money_provider" id="checkoutMobileProvider" value="${["mpesa", "airtel_money", "tigo_pesa"].includes(defaultGateway) ? escapeHtml(defaultGateway) : ""}">
                <input type="hidden" name="is_credit_sale" id="checkoutIsCreditSale" value="${defaultGateway === "pay_later" ? "1" : "0"}">
                <input type="hidden" name="cart_json" id="checkoutCartJson">
                <input type="hidden" name="amount" id="checkoutAmountInput" value="${Math.round(subtotal)}">

                <div class="form-group">
                    <label><i class="fa-regular fa-circle-user"></i> Customer</label>
                  <input type="text" id="checkoutCustomerSearch" placeholder="Search customer..." autocomplete="off">
                    <select id="checkoutCustomerSelect" name="customer_id" required>${customerOptions}</select>
                    <small id="checkoutCreditCustomerHint" style="display:none; color:#b45309; margin-top:6px;">Pay Later requires a named customer (not Walk-in Customer).</small>
                </div>

                <div class="sales-checkout-items" id="checkoutItemsWrap">${cartLines}</div>

                <div class="form-group">
                    <label>Discount (Tsh)</label>
                    <input type="number" name="discount_amount" id="checkoutDiscount" min="0" value="0">
                </div>

                <div class="sales-checkout-summary">
                    <div><span>Subtotal</span><strong id="checkoutSubtotal">Tsh ${formatMoney(subtotal)}</strong></div>
                    <div><span>Tax</span><strong>Tsh 0</strong></div>
                    <div class="total"><span>Total</span><strong id="checkoutTotal">Tsh ${formatMoney(subtotal)}</strong></div>
                </div>

                <h4 class="sales-checkout-payment-title">Payment Method</h4>
                <div class="sales-payment-grid" id="checkoutPaymentGrid">${optionTiles}</div>

                <div class="form-group" id="checkoutMobilePhoneGroup" style="display:${["mpesa", "airtel_money", "tigo_pesa"].includes(defaultGateway) ? "block" : "none"};">
                    <label>Mobile Number</label>
                    <input type="tel" name="mobile_money_phone" id="checkoutMobilePhone" placeholder="07XXXXXXXX or 2557XXXXXXXX">
                </div>

                <div class="form-group" id="checkoutMobileRefGroup" style="display:${["mpesa", "airtel_money", "tigo_pesa"].includes(defaultGateway) ? "block" : "none"};">
                    <label>Payment Reference (Optional)</label>
                    <input type="text" name="mobile_money_reference" placeholder="Invoice number or note">
                </div>

                <div class="form-group" id="checkoutCreditNoteGroup" style="display:${defaultGateway === "pay_later" ? "block" : "none"};">
                  <label>Credit Note (Optional)</label>
                  <input type="text" name="credit_note" placeholder="Customer will pay later">
                </div>

                <div class="form-group" id="checkoutCreditDueDateGroup" style="display:${defaultGateway === "pay_later" ? "block" : "none"};">
                  <label>Due Date</label>
                  <input type="date" name="credit_due_date" id="checkoutCreditDueDate" value="${dueDateDefault}">
                </div>

                <button type="submit" class="sales-checkout-submit" id="checkoutSubmitBtn">Complete Payment</button>
            </form>
        `;

    openModal("Checkout", content, [], { modalClass: "modal-checkout" });

    const form = document.getElementById("salesCheckoutForm");
    const customerSearchInput = document.getElementById(
      "checkoutCustomerSearch",
    );
    const customerSelect = document.getElementById("checkoutCustomerSelect");
    const creditCustomerHint = document.getElementById(
      "checkoutCreditCustomerHint",
    );
    const gatewayInput = document.getElementById("checkoutGateway");
    const methodInput = document.getElementById("checkoutPaymentMethod");
    const providerInput = document.getElementById("checkoutMobileProvider");
    const isCreditSaleInput = document.getElementById("checkoutIsCreditSale");
    const cartJsonInput = document.getElementById("checkoutCartJson");
    const amountInput = document.getElementById("checkoutAmountInput");
    const discountInput = document.getElementById("checkoutDiscount");
    const subtotalElModal = document.getElementById("checkoutSubtotal");
    const totalElModal = document.getElementById("checkoutTotal");
    const paymentGrid = document.getElementById("checkoutPaymentGrid");
    const mobilePhoneGroup = document.getElementById(
      "checkoutMobilePhoneGroup",
    );
    const mobileRefGroup = document.getElementById("checkoutMobileRefGroup");
    const mobilePhoneInput = document.getElementById("checkoutMobilePhone");
    const creditNoteGroup = document.getElementById("checkoutCreditNoteGroup");
    const creditDueDateGroup = document.getElementById(
      "checkoutCreditDueDateGroup",
    );
    const creditDueDateInput = document.getElementById("checkoutCreditDueDate");
    const submitBtn = document.getElementById("checkoutSubmitBtn");

    const toCartPayload = () =>
      cartItems.map((item) => ({
        product_id: Number(item.id),
        quantity: item.qty,
      }));

    const renderCheckoutCustomerOptions = (searchTerm = "") => {
      if (!customerSelect) {
        return;
      }

      const selectedId = (customerSelect.value || walkInCustomer.id || "")
        .toString()
        .trim();
      const term = (searchTerm || "").toLowerCase().trim();
      const filtered = checkoutCustomers.filter((customer) => {
        const name = (customer.name || "").toLowerCase();
        const id = (customer.id || "").toLowerCase();
        return term === "" || name.includes(term) || id.includes(term);
      });

      if (filtered.length === 0) {
        customerSelect.innerHTML =
          '<option value="">No customer found</option>';
        customerSelect.value = "";
        customerSelect.disabled = true;
        return;
      }

      customerSelect.disabled = false;
      customerSelect.innerHTML = filtered
        .map(
          (customer) =>
            `<option value="${escapeHtml(customer.id)}">${escapeHtml(customer.name)}</option>`,
        )
        .join("");

      const hasPreviousSelection = filtered.some(
        (customer) => customer.id === selectedId,
      );
      customerSelect.value = hasPreviousSelection
        ? selectedId
        : filtered[0].id || "";
    };

    const recalcCheckoutTotals = () => {
      const discount = Math.max(
        0,
        Number.parseFloat(discountInput?.value || "0") || 0,
      );
      const safeDiscount = Math.min(discount, subtotal);
      if (discountInput && discount !== safeDiscount) {
        discountInput.value = String(Math.round(safeDiscount));
      }

      const total = Math.max(0, subtotal - safeDiscount);
      if (subtotalElModal)
        subtotalElModal.textContent = `Tsh ${formatMoney(subtotal)}`;
      if (totalElModal) totalElModal.textContent = `Tsh ${formatMoney(total)}`;
      if (amountInput) amountInput.value = String(Math.round(total));
      if (submitBtn) {
        const isCredit =
          (gatewayInput?.value || "").toLowerCase() === "pay_later";
        submitBtn.textContent = isCredit
          ? "Save Credit Sale"
          : "Complete Payment";
      }
    };

    const setGateway = (gateway) => {
      const key = (gateway || "cash").toLowerCase();
      const isMobile = ["mpesa", "airtel_money", "tigo_pesa"].includes(key);
      const isCredit = key === "pay_later";

      if (gatewayInput) gatewayInput.value = key;
      if (methodInput) methodInput.value = toPaymentMethodValue(key);
      if (providerInput) providerInput.value = isMobile ? key : "";
      if (isCreditSaleInput) isCreditSaleInput.value = isCredit ? "1" : "0";
      if (mobilePhoneGroup)
        mobilePhoneGroup.style.display = isMobile ? "block" : "none";
      if (mobileRefGroup)
        mobileRefGroup.style.display = isMobile ? "block" : "none";
      if (creditNoteGroup)
        creditNoteGroup.style.display = isCredit ? "block" : "none";
      if (creditDueDateGroup)
        creditDueDateGroup.style.display = isCredit ? "block" : "none";
      if (creditCustomerHint) {
        creditCustomerHint.style.display = isCredit ? "block" : "none";
      }
      if (creditDueDateInput) creditDueDateInput.required = isCredit;
      if (mobilePhoneInput) mobilePhoneInput.required = isMobile && !isCredit;

      if (isCredit && customerSelect) {
        const selectedCustomerId = (customerSelect.value || "").trim();
        const isWalkInSelected = selectedCustomerId === walkInCustomer.id;
        if (isWalkInSelected && nonWalkInCustomers.length > 0) {
          customerSelect.value = nonWalkInCustomers[0].id;
        }
      }

      paymentGrid?.querySelectorAll(".sales-payment-tile").forEach((tile) => {
        tile.classList.toggle(
          "active",
          (tile.getAttribute("data-gateway") || "").toLowerCase() === key,
        );
      });
    };

    if (cartJsonInput) {
      cartJsonInput.value = JSON.stringify(toCartPayload());
    }

    if (customerSelect) {
      customerSelect.value = walkInCustomer.id;
    }

    customerSearchInput?.addEventListener("input", () => {
      renderCheckoutCustomerOptions(customerSearchInput.value);
    });

    paymentGrid?.addEventListener("click", (event) => {
      const tile = event.target.closest(".sales-payment-tile");
      if (!tile) {
        return;
      }
      setGateway(tile.getAttribute("data-gateway") || "cash");
    });

    discountInput?.addEventListener("input", recalcCheckoutTotals);

    form?.addEventListener("submit", (event) => {
      const isCredit =
        (gatewayInput?.value || "").toLowerCase() === "pay_later";
      const selectedCustomerId = (customerSelect?.value || "").trim();
      if (isCredit && selectedCustomerId === walkInCustomer.id) {
        event.preventDefault();
        showToast(
          "warning",
          "Pay Later requires a named customer. Please select a customer.",
        );
        customerSelect?.focus();
        return;
      }

      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Processing...";
      }
    });

    setGateway(defaultGateway);
    recalcCheckoutTotals();
    renderCheckoutCustomerOptions("");
  };

  const addProduct = (id, name, price) => {
    if (!id || !name) {
      return;
    }

    const existing = cart.get(id);
    if (existing) {
      existing.qty += 1;
      cart.set(id, existing);
    } else {
      cart.set(id, { id, name, price, qty: 1 });
    }
  };

  const getCardModel = (card) => ({
    id: (card.getAttribute("data-product-id") || "").toString(),
    name: (card.getAttribute("data-product-name") || "").toString(),
    price: toNumber(card.getAttribute("data-product-price")),
  });

  const updateVisibleProductMeta = () => {
    const cards = productsGrid.querySelectorAll(".sales-product-card");
    let visibleCount = 0;

    cards.forEach((card) => {
      if (card.style.display !== "none") {
        visibleCount += 1;
      }
    });

    if (visibleCountEl) {
      visibleCountEl.textContent = visibleCount.toLocaleString("en-US");
    }

    if (noResultEl) {
      noResultEl.style.display = visibleCount === 0 ? "block" : "none";
    }
  };

  const applySalesProductFilters = () => {
    const term = (searchInput?.value || "").toLowerCase().trim();
    const activeCategoryChip = categoryRow?.querySelector(".sales-chip.active");
    const activeCategory = (
      activeCategoryChip?.getAttribute("data-category") || "all"
    )
      .toLowerCase()
      .trim();

    productsGrid.querySelectorAll(".sales-product-card").forEach((card) => {
      const blob = (card.getAttribute("data-product-search") || "")
        .toLowerCase()
        .trim();
      const category = (card.getAttribute("data-product-category") || "")
        .toLowerCase()
        .trim();

      const searchMatch = term === "" || blob.includes(term);
      const categoryMatch =
        activeCategory === "all" || category === activeCategory;
      card.style.display = searchMatch && categoryMatch ? "" : "none";
    });

    updateVisibleProductMeta();
  };

  const renderCart = () => {
    if (cart.size === 0) {
      cartItemsEl.innerHTML =
        '<div class="sales-empty-cart">No products selected yet.</div>';
    } else {
      const itemsHtml = Array.from(cart.values())
        .map((item) => {
          const lineTotal = item.price * item.qty;
          return `
                    <div class="sales-cart-item" data-cart-id="${escapeHtml(item.id)}">
                        <div class="sales-cart-item-main">
                            <div>
                                <div class="sales-cart-item-name">${escapeHtml(item.name)}</div>
                                <div class="sales-cart-item-price">${escapeHtml(formatTsh(item.price))} each</div>
                            </div>
                            <div class="sales-cart-controls">
                                <button type="button" data-cart-action="dec" data-cart-id="${escapeHtml(item.id)}">-</button>
                                <span class="sales-cart-qty">${item.qty}</span>
                                <button type="button" data-cart-action="inc" data-cart-id="${escapeHtml(item.id)}">+</button>
                            </div>
                            <strong class="sales-cart-line-total">${escapeHtml(Math.round(lineTotal).toString())}</strong>
                            <button type="button" class="sales-remove-item" data-cart-action="remove" data-cart-id="${escapeHtml(item.id)}" title="Remove">
                                <i class="fa-regular fa-trash-can"></i>
                            </button>
                        </div>
                    </div>
                `;
        })
        .join("");

      cartItemsEl.innerHTML = itemsHtml;
    }

    const subtotal = Array.from(cart.values()).reduce(
      (sum, item) => sum + item.qty * item.price,
      0,
    );
    const tax = 0;
    const total = subtotal + tax;

    subtotalEl.textContent = formatTsh(subtotal);
    taxEl.textContent = formatTsh(tax);
    totalEl.textContent = formatTsh(total);
    chargeBtn.textContent = `Charge ${formatTsh(total)}`;
  };

  productsGrid.addEventListener("click", function (event) {
    const card = event.target.closest(".sales-product-card");
    if (!card) {
      return;
    }

    const product = getCardModel(card);
    addProduct(product.id, product.name, product.price);
    renderCart();
  });

  cartItemsEl.addEventListener("click", function (event) {
    const actionTarget = event.target.closest("[data-cart-action]");
    if (!actionTarget) {
      return;
    }

    const action = (
      actionTarget.getAttribute("data-cart-action") || ""
    ).toLowerCase();
    const id = (actionTarget.getAttribute("data-cart-id") || "").toString();
    const item = cart.get(id);

    if (!item) {
      return;
    }

    if (action === "inc") {
      item.qty += 1;
      cart.set(id, item);
    }

    if (action === "dec") {
      if (item.qty <= 1) {
        cart.delete(id);
      } else {
        item.qty -= 1;
        cart.set(id, item);
      }
    }

    if (action === "remove") {
      cart.delete(id);
    }

    renderCart();
  });

  searchInput?.addEventListener("input", applySalesProductFilters);

  categoryRow?.addEventListener("click", (event) => {
    const chip = event.target.closest(".sales-chip");
    if (!chip) {
      return;
    }

    categoryRow.querySelectorAll(".sales-chip").forEach((node) => {
      node.classList.remove("active");
    });
    chip.classList.add("active");
    applySalesProductFilters();
  });

  clearBtn.addEventListener("click", function () {
    cart.clear();
    renderCart();
  });

  chargeBtn.addEventListener("click", openCheckoutModal);

  renderCart();
  applySalesProductFilters();

  if (APP_CONFIG.flashReceipt && typeof APP_CONFIG.flashReceipt === "object") {
    openReceiptModal(APP_CONFIG.flashReceipt);
  }
}

function filterTable(tableId, searchTerm) {
  if (tableId === "productTable") {
    TABLE_FILTER_STATE.productTable.search = (searchTerm || "").toString();
    applyProductTableFilters();
    return;
  }

  if (tableId === "salesTable") {
    TABLE_FILTER_STATE.salesTable.search = (searchTerm || "").toString();
    applySalesTableFilters();
    return;
  }

  const table = document.getElementById(tableId);
  if (!table) return;

  const rows = table.querySelectorAll("tbody tr");
  const term = (searchTerm || "").toString().toLowerCase();

  rows.forEach((row) => {
    const text = (row.textContent || "").toLowerCase();
    row.style.display = text.includes(term) ? "" : "none";
  });
}

function filterByStock(value) {
  TABLE_FILTER_STATE.productTable.stock = (value || "all")
    .toString()
    .toLowerCase();
  applyProductTableFilters();
}

function filterByCategory(value) {
  TABLE_FILTER_STATE.productTable.category = (value || "all")
    .toString()
    .toLowerCase();
  applyProductTableFilters();
}

window.filterTable = filterTable;
window.filterByStock = filterByStock;
window.filterByCategory = filterByCategory;
window.filterByPayment = filterByPayment;

function filterByPayment(tableId, value) {
  if (tableId !== "salesTable") {
    return;
  }

  TABLE_FILTER_STATE.salesTable.payment = normalizePaymentGroup(value || "all");
  applySalesTableFilters();
}

// ============================================
// CRUD OPERATIONS (Demo - shows toast)
// ============================================

function createSale(event) {
  event.preventDefault();
  closeModal();
  showToast("success", "Sale created successfully!");
  setTimeout(() => window.location.reload(), 1500);
}

function createProduct(event) {
  event.preventDefault();
  closeModal();
  showToast("success", "Product added successfully!");
  setTimeout(() => window.location.reload(), 1500);
}

function createCustomer(event) {
  event.preventDefault();
  closeModal();
  showToast("success", "Customer added successfully!");
  setTimeout(() => window.location.reload(), 1500);
}

function editProduct(id) {
  if (!APP_CONFIG.canManageProducts) {
    showToast("error", "Only administrators can edit products.");
    return;
  }

  const productId = Number.parseInt(id, 10);
  if (!Number.isFinite(productId) || productId <= 0) {
    showToast("error", "Invalid product ID");
    return;
  }

  const products = Array.isArray(APP_CONFIG.inventoryProducts)
    ? APP_CONFIG.inventoryProducts
    : [];
  const product = products.find(
    (item) => Number.parseInt(item.id, 10) === productId,
  );

  if (!product) {
    showToast("error", "Product not found");
    return;
  }

  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const content = `
        <form id="editProductForm" method="POST" action="?page=inventory">
            <input type="hidden" name="action" value="update_entity">
            <input type="hidden" name="entity" value="product">
            <input type="hidden" name="id" value="${productId}">
            <input type="hidden" name="csrf_token" value="${csrfToken}">
            <div class="form-group">
                <label>Product Name</label>
                <input type="text" name="name" required value="${escapeHtml(product.name || "")}">
            </div>
            <div class="form-group">
                <label>SKU</label>
                <input type="text" name="sku" required value="${escapeHtml(product.sku || "")}">
            </div>
            <div class="form-group">
                <label>Product Category</label>
                <input type="text" name="category" value="${escapeHtml(product.category || "")}">
            </div>
            <div class="form-group">
                <label>Unit Price (Tsh)</label>
                <input type="number" name="unit_price" min="0" step="0.01" required value="${escapeHtml(product.unit_price || 0)}">
            </div>
            <div class="form-group">
                <label>Stock Quantity</label>
                <input type="number" name="stock_qty" min="0" required value="${escapeHtml(product.stock_qty || 0)}">
            </div>
            <div class="form-group">
                <label>Reorder Level</label>
                <input type="number" name="reorder_level" min="0" required value="${escapeHtml(product.reorder_level || 5)}">
            </div>
        </form>
    `;

  openModal("Edit Product", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: "Save Changes",
      class: "btn-primary",
      onclick: 'document.getElementById("editProductForm").requestSubmit()',
    },
  ]);
}

function deleteProduct(id) {
  if (!APP_CONFIG.canManageProducts) {
    showToast("error", "Only administrators can delete products.");
    return;
  }

  const productId = Number.parseInt(id, 10);
  if (!Number.isFinite(productId) || productId <= 0) {
    showToast("error", "Invalid product ID");
    return;
  }

  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const content = `
        <form id="deleteProductForm" method="POST" action="?page=inventory">
            <input type="hidden" name="action" value="update_entity">
            <input type="hidden" name="entity" value="product_delete">
            <input type="hidden" name="id" value="${productId}">
            <input type="hidden" name="csrf_token" value="${csrfToken}">
            <div style="text-align: center; padding: 20px 0;">
                <i class="fa-solid fa-trash" style="font-size: 48px; color: #EF4444; margin-bottom: 16px;"></i>
                <p style="margin: 0; font-size: 16px;">Are you sure you want to delete this product?</p>
                <p style="margin: 8px 0 0 0; font-size: 13px; color: #6B7280;">This action cannot be undone.</p>
            </div>
        </form>
    `;
  openModal("Delete Product", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: "Delete",
      class: "btn-danger",
      onclick: 'document.getElementById("deleteProductForm").requestSubmit()',
    },
  ]);
}

function viewReceiving(id) {
  const receivingId = Number.parseInt(id, 10);
  if (!Number.isFinite(receivingId) || receivingId <= 0) {
    showToast("error", "Invalid receiving ID");
    return;
  }

  const records = Array.isArray(APP_CONFIG.receivingRecords)
    ? APP_CONFIG.receivingRecords
    : [];
  const record = records.find(
    (item) => Number.parseInt(String(item?.id || "0"), 10) === receivingId,
  );

  if (!record) {
    showToast("error", "Receiving record not found");
    return;
  }

  const items = Array.isArray(record.items) ? record.items : [];
  const itemRows =
    items.length === 0
      ? '<tr><td colspan="5" style="text-align:center; color:#6b7280;">No receiving items saved.</td></tr>'
      : items
          .map((item) => {
            const product = escapeHtml(String(item.product_name || "Product"));
            const qtyReceived = Number.parseInt(
              String(item.quantity_received || "0"),
              10,
            );
            const qtyRejected = Number.parseInt(
              String(item.quantity_rejected || "0"),
              10,
            );
            const unitCost = Number.parseFloat(String(item.unit_cost || "0"));
            const lineTotal = Number.parseFloat(String(item.line_total || "0"));
            return `
              <tr>
                <td>${product}</td>
                <td>${escapeHtml(String(Number.isFinite(qtyReceived) ? qtyReceived : 0))}</td>
                <td>${escapeHtml(String(Number.isFinite(qtyRejected) ? qtyRejected : 0))}</td>
                <td>Tsh ${escapeHtml(formatMoney(Number.isFinite(unitCost) ? unitCost : 0))}</td>
                <td><strong>Tsh ${escapeHtml(formatMoney(Number.isFinite(lineTotal) ? lineTotal : 0))}</strong></td>
              </tr>
            `;
          })
          .join("");

  const receivingNo = escapeHtml(String(record.receiving_no || "-"));
  const supplierName = escapeHtml(String(record.supplier_name || "-"));
  const purchaseOrderId = Number.parseInt(
    String(record.purchase_order_id || "0"),
    10,
  );
  const purchaseOrderNo = String(record.purchase_order_no || "").trim();
  const linkedPoLabel =
    Number.isFinite(purchaseOrderId) && purchaseOrderId > 0
      ? escapeHtml(
          purchaseOrderNo !== "" ? purchaseOrderNo : `PO #${purchaseOrderId}`,
        )
      : "-";
  const status = escapeHtml(String(record.status || "Pending"));
  const createdAtRaw = String(record.created_at || "");
  const createdAt = createdAtRaw
    ? new Date(createdAtRaw.replace(" ", "T"))
    : null;
  const createdLabel =
    createdAt instanceof Date && !Number.isNaN(createdAt.getTime())
      ? escapeHtml(createdAt.toLocaleString())
      : escapeHtml(createdAtRaw || "-");

  const amount = Number.parseFloat(String(record.amount || "0"));
  const amountLabel = escapeHtml(
    formatMoney(Number.isFinite(amount) ? amount : 0),
  );

  const content = `
    <div style="display:grid; gap:10px;">
      <div><strong>Receiving No:</strong> ${receivingNo}</div>
      <div><strong>Supplier:</strong> ${supplierName}</div>
      <div><strong>Linked PO:</strong> ${linkedPoLabel}</div>
      <div><strong>Status:</strong> ${status}</div>
      <div><strong>Date:</strong> ${createdLabel}</div>
      <div><strong>Total Amount:</strong> Tsh ${amountLabel}</div>
      <div style="overflow:auto; margin-top:4px;">
        <table class="data-table" style="min-width:560px;">
          <thead>
            <tr>
              <th>Product</th>
              <th>Received</th>
              <th>Rejected</th>
              <th>Unit Cost</th>
              <th>Line Total</th>
            </tr>
          </thead>
          <tbody>
            ${itemRows}
          </tbody>
        </table>
      </div>
    </div>
  `;

  openModal("Receiving Details", content, [
    { text: "Close", class: "btn-primary", onclick: "closeModal()" },
  ]);
}

function viewDelivery(target) {
  if (!target) {
    return;
  }

  const id = Number.parseInt(
    target.getAttribute("data-delivery-id") || "0",
    10,
  );
  const deliveryNo = target.getAttribute("data-delivery-no") || "-";
  const customer = target.getAttribute("data-customer") || "-";
  const amount = target.getAttribute("data-amount") || "0";
  const status = target.getAttribute("data-status") || "Pending";
  const date = target.getAttribute("data-date") || "-";

  const content = `
    <div style="display:grid; gap:10px;">
      <div><strong>Delivery ID:</strong> ${escapeHtml(String(Number.isFinite(id) ? id : 0))}</div>
      <div><strong>Delivery No:</strong> ${escapeHtml(deliveryNo)}</div>
      <div><strong>Customer:</strong> ${escapeHtml(customer)}</div>
      <div><strong>Amount:</strong> Tsh ${escapeHtml(amount)}</div>
      <div><strong>Status:</strong> ${escapeHtml(status)}</div>
      <div><strong>Date:</strong> ${escapeHtml(date)}</div>
    </div>
  `;

  openModal("Delivery Details", content, [
    { text: "Close", class: "btn-primary", onclick: "closeModal()" },
  ]);
}

function updateDeliveryStatus(id, status) {
  const deliveryId = Number.parseInt(id, 10);
  const normalizedStatus = String(status || "").trim();
  if (!Number.isFinite(deliveryId) || deliveryId <= 0) {
    showToast("error", "Invalid delivery ID");
    return;
  }

  if (!["In Transit", "Delivered", "Cancelled"].includes(normalizedStatus)) {
    showToast("error", "Invalid delivery status");
    return;
  }

  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const iconClass =
    normalizedStatus === "Delivered"
      ? "fa-circle-check"
      : normalizedStatus === "Cancelled"
        ? "fa-ban"
        : "fa-truck-fast";
  const buttonClass =
    normalizedStatus === "Delivered"
      ? "btn-primary"
      : normalizedStatus === "Cancelled"
        ? "btn-danger"
        : "btn-secondary";

  const content = `
    <form id="deliveryStatusForm" method="POST" action="?page=deliveries">
      <input type="hidden" name="action" value="update_entity">
      <input type="hidden" name="entity" value="delivery_status">
      <input type="hidden" name="id" value="${deliveryId}">
      <input type="hidden" name="status" value="${escapeHtml(normalizedStatus)}">
      <input type="hidden" name="csrf_token" value="${csrfToken}">
      <div style="text-align: center; padding: 20px 0;">
        <i class="fa-solid ${iconClass}" style="font-size: 48px; color: #4F46E5; margin-bottom: 16px;"></i>
        <p style="margin: 0; font-size: 16px;">Set delivery status to ${escapeHtml(normalizedStatus)}?</p>
      </div>
    </form>
  `;

  openModal("Update Delivery", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: normalizedStatus,
      class: buttonClass,
      onclick: 'document.getElementById("deliveryStatusForm").requestSubmit()',
    },
  ]);
}

function updateReceivingStatus(id, status) {
  const receivingId = Number.parseInt(id, 10);
  const normalizedStatus = String(status || "").trim();
  if (!Number.isFinite(receivingId) || receivingId <= 0) {
    showToast("error", "Invalid receiving ID");
    return;
  }

  if (!["Pending", "Received", "Completed"].includes(normalizedStatus)) {
    showToast("error", "Invalid receiving status");
    return;
  }

  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const iconClass =
    normalizedStatus === "Completed" ? "fa-circle-check" : "fa-truck-ramp-box";
  const buttonClass =
    normalizedStatus === "Completed" ? "btn-primary" : "btn-secondary";
  const note =
    normalizedStatus === "Completed"
      ? '<p style="margin: 8px 0 0 0; font-size: 13px; color: #6B7280;">Stock quantities will be posted if not already applied.</p>'
      : "";

  const content = `
    <form id="receivingStatusForm" method="POST" action="?page=receiving">
      <input type="hidden" name="action" value="update_entity">
      <input type="hidden" name="entity" value="receiving_status">
      <input type="hidden" name="id" value="${receivingId}">
      <input type="hidden" name="status" value="${escapeHtml(normalizedStatus)}">
      <input type="hidden" name="csrf_token" value="${csrfToken}">
      <div style="text-align: center; padding: 20px 0;">
        <i class="fa-solid ${iconClass}" style="font-size: 48px; color: #4F46E5; margin-bottom: 16px;"></i>
        <p style="margin: 0; font-size: 16px;">Set receiving status to ${escapeHtml(normalizedStatus)}?</p>
        ${note}
      </div>
    </form>
  `;

  openModal("Update Receiving", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: normalizedStatus,
      class: buttonClass,
      onclick: 'document.getElementById("receivingStatusForm").requestSubmit()',
    },
  ]);
}

function updateInvoiceStatus(id, status) {
  const invoiceId = Number.parseInt(id, 10);
  const normalizedStatus = String(status || "").trim();
  if (!Number.isFinite(invoiceId) || invoiceId <= 0) {
    showToast("error", "Invalid invoice ID");
    return;
  }

  if (!["Pending", "Paid", "Cancelled"].includes(normalizedStatus)) {
    showToast("error", "Invalid invoice status");
    return;
  }

  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const iconClass =
    normalizedStatus === "Paid"
      ? "fa-circle-check"
      : normalizedStatus === "Cancelled"
        ? "fa-ban"
        : "fa-hourglass-half";
  const buttonClass =
    normalizedStatus === "Paid"
      ? "btn-primary"
      : normalizedStatus === "Cancelled"
        ? "btn-danger"
        : "btn-secondary";

  const content = `
    <form id="invoiceStatusForm" method="POST" action="?page=invoices">
      <input type="hidden" name="action" value="update_entity">
      <input type="hidden" name="entity" value="invoice_status">
      <input type="hidden" name="id" value="${invoiceId}">
      <input type="hidden" name="status" value="${escapeHtml(normalizedStatus)}">
      <input type="hidden" name="csrf_token" value="${csrfToken}">
      <div style="text-align: center; padding: 20px 0;">
        <i class="fa-solid ${iconClass}" style="font-size: 48px; color: #4F46E5; margin-bottom: 16px;"></i>
        <p style="margin: 0; font-size: 16px;">Set invoice status to ${escapeHtml(normalizedStatus)}?</p>
      </div>
    </form>
  `;

  openModal("Update Invoice", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: normalizedStatus,
      class: buttonClass,
      onclick: 'document.getElementById("invoiceStatusForm").requestSubmit()',
    },
  ]);
}

function viewEmployee(target) {
  if (!target) {
    return;
  }

  const id = Number.parseInt(target.getAttribute("data-value") || "0", 10);
  const name = target.getAttribute("data-name") || "-";
  const position = target.getAttribute("data-position") || "N/A";
  const phone = target.getAttribute("data-phone") || "N/A";
  const email = target.getAttribute("data-email") || "N/A";
  const salary =
    Number.parseFloat(target.getAttribute("data-salary") || "0") || 0;
  const status = target.getAttribute("data-status") || "Active";

  const content = `
    <div style="display:grid; gap:10px;">
      <div><strong>ID:</strong> ${escapeHtml(String(Number.isFinite(id) ? id : 0))}</div>
      <div><strong>Name:</strong> ${escapeHtml(name)}</div>
      <div><strong>Position:</strong> ${escapeHtml(position)}</div>
      <div><strong>Phone:</strong> ${escapeHtml(phone)}</div>
      <div><strong>Email:</strong> ${escapeHtml(email)}</div>
      <div><strong>Salary:</strong> Tsh ${escapeHtml(formatMoney(salary))}</div>
      <div><strong>Status:</strong> ${escapeHtml(status)}</div>
    </div>
  `;

  openModal("Employee Details", content, [
    { text: "Close", class: "btn-primary", onclick: "closeModal()" },
  ]);
}

function viewSupplier(target) {
  if (!target) {
    return;
  }

  const id = Number.parseInt(target.getAttribute("data-value") || "0", 10);
  const name = target.getAttribute("data-name") || "-";
  const contact = target.getAttribute("data-contact") || "N/A";
  const phone = target.getAttribute("data-phone") || "N/A";
  const email = target.getAttribute("data-email") || "N/A";
  const address = target.getAttribute("data-address") || "N/A";
  const status = target.getAttribute("data-status") || "Active";

  const content = `
    <div style="display:grid; gap:10px;">
      <div><strong>ID:</strong> ${escapeHtml(String(Number.isFinite(id) ? id : 0))}</div>
      <div><strong>Name:</strong> ${escapeHtml(name)}</div>
      <div><strong>Contact Person:</strong> ${escapeHtml(contact)}</div>
      <div><strong>Phone:</strong> ${escapeHtml(phone)}</div>
      <div><strong>Email:</strong> ${escapeHtml(email)}</div>
      <div><strong>Address:</strong> ${escapeHtml(address)}</div>
      <div><strong>Status:</strong> ${escapeHtml(status)}</div>
    </div>
  `;

  openModal("Supplier Details", content, [
    { text: "Close", class: "btn-primary", onclick: "closeModal()" },
  ]);
}

function editSupplier(target) {
  if (!target) {
    return;
  }

  const supplierId = Number.parseInt(
    target.getAttribute("data-value") || "0",
    10,
  );
  if (!Number.isFinite(supplierId) || supplierId <= 0) {
    showToast("error", "Invalid supplier ID");
    return;
  }

  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const name = target.getAttribute("data-name") || "";
  const contact = target.getAttribute("data-contact") || "";
  const phone = target.getAttribute("data-phone") || "";
  const email = target.getAttribute("data-email") || "";
  const address = target.getAttribute("data-address") || "";
  const status = target.getAttribute("data-status") || "Active";

  const content = `
    <form id="editSupplierForm" method="POST" action="?page=suppliers">
      <input type="hidden" name="action" value="update_entity">
      <input type="hidden" name="entity" value="supplier">
      <input type="hidden" name="id" value="${supplierId}">
      <input type="hidden" name="csrf_token" value="${csrfToken}">
      <div class="form-group">
        <label>Supplier Name</label>
        <input type="text" name="name" required value="${escapeHtml(name)}">
      </div>
      <div class="form-group">
        <label>Contact Person</label>
        <input type="text" name="contact_person" value="${escapeHtml(contact)}">
      </div>
      <div class="form-group">
        <label>Phone</label>
        <input type="text" name="phone" value="${escapeHtml(phone)}">
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" value="${escapeHtml(email)}">
      </div>
      <div class="form-group">
        <label>Address</label>
        <textarea name="address" rows="3">${escapeHtml(address)}</textarea>
      </div>
      <div class="form-group">
        <label>Status</label>
        <select name="status" required>
          <option value="Active"${status === "Active" ? " selected" : ""}>Active</option>
          <option value="Inactive"${status === "Inactive" ? " selected" : ""}>Inactive</option>
        </select>
      </div>
    </form>
  `;

  openModal("Edit Supplier", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: "Save Changes",
      class: "btn-primary",
      onclick: 'document.getElementById("editSupplierForm").requestSubmit()',
    },
  ]);
}

function deleteSupplier(id) {
  const supplierId = Number.parseInt(id, 10);
  if (!Number.isFinite(supplierId) || supplierId <= 0) {
    showToast("error", "Invalid supplier ID");
    return;
  }

  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const content = `
    <form id="deleteSupplierForm" method="POST" action="?page=suppliers">
      <input type="hidden" name="action" value="update_entity">
      <input type="hidden" name="entity" value="supplier_delete">
      <input type="hidden" name="id" value="${supplierId}">
      <input type="hidden" name="csrf_token" value="${csrfToken}">
      <div style="text-align: center; padding: 20px 0;">
        <i class="fa-solid fa-trash" style="font-size: 48px; color: #EF4444; margin-bottom: 16px;"></i>
        <p style="margin: 0; font-size: 16px;">Are you sure you want to delete this supplier?</p>
        <p style="margin: 8px 0 0 0; font-size: 13px; color: #6B7280;">This action cannot be undone.</p>
      </div>
    </form>
  `;

  openModal("Confirm Delete", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: "Delete",
      class: "btn-danger",
      onclick: 'document.getElementById("deleteSupplierForm").requestSubmit()',
    },
  ]);
}

function viewCustomer(id) {
  const customerId = Number.parseInt(id, 10);
  if (!Number.isFinite(customerId) || customerId <= 0) {
    showToast("error", "Invalid customer ID");
    return;
  }

  const customers = Array.isArray(APP_CONFIG.customers)
    ? APP_CONFIG.customers
    : [];
  const customer = customers.find(
    (item) => Number.parseInt(item.id, 10) === customerId,
  );

  if (!customer) {
    showToast("error", "Customer not found");
    return;
  }

  const allCredits = Array.isArray(APP_CONFIG.customerCredits)
    ? APP_CONFIG.customerCredits
    : [];

  const customerCredits = allCredits
    .filter((item) => Number.parseInt(item.customer_id, 10) === customerId)
    .sort((a, b) => {
      const aTime = new Date(a.created_at || 0).getTime();
      const bTime = new Date(b.created_at || 0).getTime();
      return bTime - aTime;
    });

  const totals = customerCredits.reduce(
    (acc, credit) => {
      acc.total += Number.parseFloat(credit.total_amount || 0) || 0;
      acc.paid += Number.parseFloat(credit.paid_amount || 0) || 0;
      acc.outstanding += Number.parseFloat(credit.outstanding_amount || 0) || 0;
      return acc;
    },
    { total: 0, paid: 0, outstanding: 0 },
  );

  const recentCredits = customerCredits.slice(0, 8);
  const creditsHtml =
    recentCredits.length === 0
      ? '<div class="sales-empty-cart" style="margin-top:8px;">No credit records for this customer.</div>'
      : recentCredits
          .map((credit) => {
            const saleRef =
              credit.transaction_no || `SALE-${credit.sale_id || credit.id}`;
            const dueDate = credit.due_date
              ? new Date(credit.due_date).toLocaleDateString("en-GB")
              : "N/A";
            const outstanding =
              Number.parseFloat(credit.outstanding_amount || 0) || 0;
            const isOverdue =
              credit.due_date &&
              new Date(credit.due_date).getTime() <
                new Date().setHours(0, 0, 0, 0) &&
              outstanding > 0;
            const statusText = isOverdue ? "Overdue" : credit.status || "Open";
            const statusColor =
              statusText === "Paid"
                ? "#16a34a"
                : statusText === "Overdue"
                  ? "#dc2626"
                  : "#d97706";

            return `
              <div style="border:1px solid #e5e7eb; border-radius:8px; padding:10px; margin-bottom:8px; background:#fff;">
                <div style="display:flex; justify-content:space-between; gap:8px; margin-bottom:4px;">
                  <strong>${escapeHtml(saleRef)}</strong>
                  <span style="font-weight:700; color:${statusColor};">${escapeHtml(statusText)}</span>
                </div>
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:6px; font-size:13px; color:#4b5563;">
                  <span>Total: Tsh ${escapeHtml(formatMoney(Number.parseFloat(credit.total_amount || 0) || 0))}</span>
                  <span>Paid: Tsh ${escapeHtml(formatMoney(Number.parseFloat(credit.paid_amount || 0) || 0))}</span>
                  <span style="font-weight:600; color:${outstanding > 0 ? "#dc2626" : "#16a34a"};">Outstanding: Tsh ${escapeHtml(formatMoney(outstanding))}</span>
                  <span>Due: ${escapeHtml(dueDate)}</span>
                </div>
              </div>
            `;
          })
          .join("");

  const content = `
    <div>
      <div style="display:grid; gap:8px; margin-bottom:12px;">
        <div><strong>Name:</strong> ${escapeHtml(customer.name || "-")}</div>
        <div><strong>Phone:</strong> ${escapeHtml(customer.phone || "N/A")}</div>
      </div>

      <div style="display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; margin-bottom:12px;">
        <div style="border:1px solid #e5e7eb; border-radius:8px; padding:8px; background:#f9fafb;">
          <small style="color:#6b7280;">Total Credit</small>
          <div style="font-weight:700;">Tsh ${escapeHtml(formatMoney(totals.total))}</div>
        </div>
        <div style="border:1px solid #e5e7eb; border-radius:8px; padding:8px; background:#f9fafb;">
          <small style="color:#6b7280;">Total Paid</small>
          <div style="font-weight:700; color:#16a34a;">Tsh ${escapeHtml(formatMoney(totals.paid))}</div>
        </div>
        <div style="border:1px solid #e5e7eb; border-radius:8px; padding:8px; background:#f9fafb;">
          <small style="color:#6b7280;">Outstanding</small>
          <div style="font-weight:700; color:${totals.outstanding > 0 ? "#dc2626" : "#16a34a"};">Tsh ${escapeHtml(formatMoney(totals.outstanding))}</div>
        </div>
      </div>

      <h4 style="margin:0 0 8px;">Recent Credit Activity</h4>
      <div style="max-height:320px; overflow:auto;">${creditsHtml}</div>
    </div>
  `;

  openModal("Customer Details", content, [
    { text: "Close", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: "Record Payment",
      class: "btn-primary",
      handler: () => {
        closeModal();
        receiveCustomerPayment(customerId);
      },
    },
  ]);
}

function updatePurchaseOrderStatus(id, status) {
  const purchaseOrderId = Number.parseInt(id, 10);
  const normalizedStatus = String(status || "").trim();
  if (!Number.isFinite(purchaseOrderId) || purchaseOrderId <= 0) {
    showToast("error", "Invalid purchase order ID");
    return;
  }

  if (!["Approved", "Received", "Cancelled"].includes(normalizedStatus)) {
    showToast("error", "Invalid purchase order status");
    return;
  }

  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const iconClass =
    normalizedStatus === "Received"
      ? "fa-box-open"
      : normalizedStatus === "Cancelled"
        ? "fa-ban"
        : "fa-circle-check";
  const buttonClass =
    normalizedStatus === "Cancelled" ? "btn-danger" : "btn-primary";

  const content = `
    <form id="purchaseOrderStatusForm" method="POST" action="?page=purchase-orders">
      <input type="hidden" name="action" value="update_entity">
      <input type="hidden" name="entity" value="purchase_order_status">
      <input type="hidden" name="id" value="${purchaseOrderId}">
      <input type="hidden" name="status" value="${escapeHtml(normalizedStatus)}">
      <input type="hidden" name="csrf_token" value="${csrfToken}">
      <div style="text-align: center; padding: 20px 0;">
        <i class="fa-solid ${iconClass}" style="font-size: 48px; color: #4F46E5; margin-bottom: 16px;"></i>
        <p style="margin: 0; font-size: 16px;">Set purchase order status to ${escapeHtml(normalizedStatus)}?</p>
      </div>
    </form>
  `;

  openModal("Update Purchase Order", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: normalizedStatus,
      class: buttonClass,
      onclick:
        'document.getElementById("purchaseOrderStatusForm").requestSubmit()',
    },
  ]);
}

function editCustomer(id) {
  const customerId = Number.parseInt(id, 10);
  if (!Number.isFinite(customerId) || customerId <= 0) {
    showToast("error", "Invalid customer ID");
    return;
  }

  const customers = Array.isArray(APP_CONFIG.customers)
    ? APP_CONFIG.customers
    : [];
  const customer = customers.find(
    (item) => Number.parseInt(item.id, 10) === customerId,
  );

  if (!customer) {
    showToast("error", "Customer not found");
    return;
  }

  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const content = `
        <form id="editCustomerForm" method="POST" action="?page=customers">
            <input type="hidden" name="action" value="update_entity">
            <input type="hidden" name="entity" value="customer">
            <input type="hidden" name="id" value="${customerId}">
            <input type="hidden" name="csrf_token" value="${csrfToken}">
            <div class="form-group">
                <label>Customer Name</label>
                <input type="text" name="name" required value="${escapeHtml(customer.name || "")}">
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" name="phone" value="${escapeHtml(customer.phone || "")}">
            </div>
        </form>
    `;

  openModal("Edit Customer", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: "Save Changes",
      class: "btn-primary",
      onclick: 'document.getElementById("editCustomerForm").requestSubmit()',
    },
  ]);
}

function receiveCustomerPayment(id) {
  const customerId = Number.parseInt(id, 10);
  if (!Number.isFinite(customerId) || customerId <= 0) {
    showToast("error", "Invalid customer ID");
    return;
  }

  const customers = Array.isArray(APP_CONFIG.customers)
    ? APP_CONFIG.customers
    : [];
  const customer = customers.find(
    (item) => Number.parseInt(item.id, 10) === customerId,
  );

  if (!customer) {
    showToast("error", "Customer not found");
    return;
  }

  const allCredits = Array.isArray(APP_CONFIG.customerCredits)
    ? APP_CONFIG.customerCredits
    : [];

  const openCredits = allCredits.filter(
    (item) =>
      Number.parseInt(item.customer_id, 10) === customerId &&
      Number.parseFloat(item.outstanding_amount || 0) > 0,
  );

  if (openCredits.length === 0) {
    showToast("warning", "This customer has no outstanding debt.");
    return;
  }

  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const creditOptions = openCredits
    .map((credit) => {
      const transactionNo =
        credit.transaction_no || `SALE-${credit.sale_id || credit.id}`;
      const outstanding = Number.parseFloat(credit.outstanding_amount || 0);
      return `<option value="${escapeHtml(credit.id)}" data-outstanding="${escapeHtml(outstanding)}">${escapeHtml(transactionNo)} - Outstanding Tsh ${escapeHtml(formatMoney(outstanding))}</option>`;
    })
    .join("");

  const content = `
    <form id="customerPaymentForm" method="POST" action="?page=customers">
      <input type="hidden" name="action" value="create_entity">
      <input type="hidden" name="entity" value="customer_payment">
      <input type="hidden" name="csrf_token" value="${csrfToken}">
      <div class="form-group">
        <label>Customer</label>
        <input type="text" value="${escapeHtml(customer.name || "")}" readonly>
      </div>
      <div class="form-group">
        <label>Credit Sale</label>
        <select name="credit_id" id="customerPaymentCreditId" required>${creditOptions}</select>
      </div>
      <div class="form-group">
        <label>Payment Amount (Tsh)</label>
        <input type="number" id="customerPaymentAmount" name="payment_amount" min="1" step="0.01" required>
        <small id="customerPaymentHint" style="color:#6b7280; display:block; margin-top:4px;"></small>
      </div>
      <div class="form-group">
        <label>Payment Method</label>
        <select name="payment_method" required>
          <option value="Cash">Cash</option>
          <option value="Mobile Money">Mobile Money</option>
          <option value="Card">Card</option>
          <option value="Bank Transfer">Bank Transfer</option>
        </select>
      </div>
      <div class="form-group">
        <label>Reference (Optional)</label>
        <input type="text" name="payment_reference" placeholder="Receipt number or note">
      </div>
    </form>
  `;

  openModal("Receive Debt Payment", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: "Save Payment",
      class: "btn-primary",
      onclick: 'document.getElementById("customerPaymentForm").requestSubmit()',
    },
  ]);

  const creditSelect = document.getElementById("customerPaymentCreditId");
  const amountInput = document.getElementById("customerPaymentAmount");
  const hint = document.getElementById("customerPaymentHint");

  const syncOutstanding = () => {
    const selected = creditSelect?.selectedOptions?.[0];
    if (!selected || !amountInput) {
      return;
    }

    const outstanding = Math.max(
      0,
      Number.parseFloat(selected.getAttribute("data-outstanding") || "0") || 0,
    );

    amountInput.max = String(outstanding);
    if (
      !amountInput.value ||
      Number.parseFloat(amountInput.value) > outstanding
    ) {
      amountInput.value = String(Math.round(outstanding));
    }

    if (hint) {
      hint.textContent = `Outstanding: Tsh ${formatMoney(outstanding)}`;
    }
  };

  creditSelect?.addEventListener("change", syncOutstanding);
  syncOutstanding();
}

function viewCustomerDebtPayments(id) {
  const creditId = Number.parseInt(id, 10);
  if (!Number.isFinite(creditId) || creditId <= 0) {
    showToast("error", "Invalid credit ID");
    return;
  }

  const credits = Array.isArray(APP_CONFIG.customerCredits)
    ? APP_CONFIG.customerCredits
    : [];
  const payments = Array.isArray(APP_CONFIG.customerCreditPayments)
    ? APP_CONFIG.customerCreditPayments
    : [];

  const credit = credits.find(
    (item) => Number.parseInt(String(item.id || "0"), 10) === creditId,
  );
  if (!credit) {
    showToast("error", "Credit record not found");
    return;
  }

  const creditPayments = payments
    .filter(
      (item) => Number.parseInt(String(item.credit_id || "0"), 10) === creditId,
    )
    .sort((a, b) => {
      const aTs = Date.parse(String(a.created_at || "")) || 0;
      const bTs = Date.parse(String(b.created_at || "")) || 0;
      return bTs - aTs;
    });

  const transactionNo = escapeHtml(
    String(credit.transaction_no || `SALE-${credit.sale_id || credit.id}`),
  );
  const customerName = escapeHtml(String(credit.customer_name || "Customer"));
  const total = formatMoney(
    Number.parseFloat(String(credit.total_amount || 0)),
  );
  const paid = formatMoney(Number.parseFloat(String(credit.paid_amount || 0)));
  const outstanding = formatMoney(
    Number.parseFloat(String(credit.outstanding_amount || 0)),
  );

  const rowsHtml =
    creditPayments.length === 0
      ? '<tr><td colspan="4" style="text-align:center; color:#6b7280;">No payment records yet for this debt.</td></tr>'
      : creditPayments
          .map((payment) => {
            const amount = formatMoney(
              Number.parseFloat(String(payment.amount || 0)),
            );
            const method = escapeHtml(String(payment.payment_method || "Cash"));
            const reference = escapeHtml(String(payment.reference || "-"));
            const createdAtRaw = String(payment.created_at || "");
            const createdAtDate = createdAtRaw
              ? new Date(createdAtRaw.replace(" ", "T"))
              : null;
            const createdAt =
              createdAtDate && !Number.isNaN(createdAtDate.getTime())
                ? escapeHtml(createdAtDate.toLocaleString())
                : escapeHtml(createdAtRaw || "-");

            return `
              <tr>
                <td><strong>Tsh ${escapeHtml(amount)}</strong></td>
                <td>${method}</td>
                <td>${reference}</td>
                <td>${createdAt}</td>
              </tr>
            `;
          })
          .join("");

  const content = `
    <div style="display:grid; gap:10px;">
      <div><strong>Customer:</strong> ${customerName}</div>
      <div><strong>Sale Ref:</strong> <code>${transactionNo}</code></div>
      <div><strong>Total:</strong> Tsh ${escapeHtml(total)} | <strong>Paid:</strong> Tsh ${escapeHtml(paid)} | <strong>Outstanding:</strong> Tsh ${escapeHtml(outstanding)}</div>
      <div style="overflow:auto; margin-top:4px;">
        <table class="data-table" style="min-width:620px;">
          <thead>
            <tr>
              <th>Amount</th>
              <th>Method</th>
              <th>Reference</th>
              <th>Date</th>
            </tr>
          </thead>
          <tbody>
            ${rowsHtml}
          </tbody>
        </table>
      </div>
    </div>
  `;

  openModal("Debt Payment History", content, [
    { text: "Close", class: "btn-primary", onclick: "closeModal()" },
  ]);
}

function deleteCustomer(id) {
  const customerId = Number.parseInt(id, 10);
  if (!Number.isFinite(customerId) || customerId <= 0) {
    showToast("error", "Invalid customer ID");
    return;
  }

  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const content = `
        <form id="deleteCustomerForm" method="POST" action="?page=customers">
            <input type="hidden" name="action" value="update_entity">
            <input type="hidden" name="entity" value="customer_delete">
            <input type="hidden" name="id" value="${customerId}">
            <input type="hidden" name="csrf_token" value="${csrfToken}">
        <div style="text-align: center; padding: 20px 0;">
            <i class="fa-solid fa-user-minus" style="font-size: 48px; color: #EF4444; margin-bottom: 16px;"></i>
            <p style="margin: 0; font-size: 16px;">Are you sure you want to delete this customer?</p>
            <p style="margin: 8px 0 0 0; font-size: 13px; color: #6B7280;">This action cannot be undone.</p>
        </div>
        </form>
    `;
  openModal("Confirm Delete", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: "Delete",
      class: "btn-danger",
      onclick: 'document.getElementById("deleteCustomerForm").requestSubmit()',
    },
  ]);
}

function deleteQuotation(id) {
  const quotationId = Number.parseInt(id, 10);
  if (!Number.isFinite(quotationId) || quotationId <= 0) {
    showToast("error", "Invalid quotation ID");
    return;
  }

  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const content = `
        <form id="deleteQuotationForm" method="POST" action="?page=quotations">
            <input type="hidden" name="action" value="update_entity">
            <input type="hidden" name="entity" value="quotation_delete">
            <input type="hidden" name="id" value="${quotationId}">
            <input type="hidden" name="csrf_token" value="${csrfToken}">
        <div style="text-align: center; padding: 20px 0;">
            <i class="fa-solid fa-trash" style="font-size: 48px; color: #EF4444; margin-bottom: 16px;"></i>
            <p style="margin: 0; font-size: 16px;">Are you sure you want to delete this quotation?</p>
            <p style="margin: 8px 0 0 0; font-size: 13px; color: #6B7280;">This action cannot be undone.</p>
        </div>
        </form>
    `;

  openModal("Confirm Delete", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: "Delete",
      class: "btn-danger",
      onclick: 'document.getElementById("deleteQuotationForm").requestSubmit()',
    },
  ]);
}

function deleteAppointment(id) {
  const appointmentId = Number.parseInt(id, 10);
  if (!Number.isFinite(appointmentId) || appointmentId <= 0) {
    showToast("error", "Invalid appointment ID");
    return;
  }

  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const content = `
        <form id="deleteAppointmentForm" method="POST" action="?page=appointments">
            <input type="hidden" name="action" value="update_entity">
            <input type="hidden" name="entity" value="appointment_delete">
            <input type="hidden" name="id" value="${appointmentId}">
            <input type="hidden" name="csrf_token" value="${csrfToken}">
            <div style="text-align: center; padding: 20px 0;">
                <i class="fa-solid fa-calendar-xmark" style="font-size: 48px; color: #EF4444; margin-bottom: 16px;"></i>
                <p style="margin: 0; font-size: 16px;">Are you sure you want to delete this appointment?</p>
                <p style="margin: 8px 0 0 0; font-size: 13px; color: #6B7280;">This action cannot be undone.</p>
            </div>
        </form>
    `;

  openModal("Confirm Delete", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: "Delete",
      class: "btn-danger",
      onclick:
        'document.getElementById("deleteAppointmentForm").requestSubmit()',
    },
  ]);
}

function viewAppointment(target) {
  if (!target) {
    return;
  }

  const id = Number.parseInt(target.getAttribute("data-value") || "0", 10);
  const title = target.getAttribute("data-title") || "-";
  const customer = target.getAttribute("data-customer") || "-";
  const date = target.getAttribute("data-date") || "-";
  const status = target.getAttribute("data-status") || "Scheduled";

  const content = `
    <div style="display:grid; gap:10px;">
      <div><strong>ID:</strong> ${escapeHtml(String(Number.isFinite(id) ? id : 0))}</div>
      <div><strong>Title:</strong> ${escapeHtml(title)}</div>
      <div><strong>Customer:</strong> ${escapeHtml(customer)}</div>
      <div><strong>Date:</strong> ${escapeHtml(date)}</div>
      <div><strong>Status:</strong> ${escapeHtml(status)}</div>
    </div>
  `;

  openModal("Appointment Details", content, [
    { text: "Close", class: "btn-primary", onclick: "closeModal()" },
  ]);
}

function updateAppointmentStatus(id, status) {
  const appointmentId = Number.parseInt(id, 10);
  const normalizedStatus = String(status || "").trim();
  if (!Number.isFinite(appointmentId) || appointmentId <= 0) {
    showToast("error", "Invalid appointment ID");
    return;
  }
  if (!["Completed", "Cancelled"].includes(normalizedStatus)) {
    showToast("error", "Invalid appointment status");
    return;
  }

  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const iconClass =
    normalizedStatus === "Completed" ? "fa-circle-check" : "fa-ban";
  const buttonClass =
    normalizedStatus === "Completed" ? "btn-primary" : "btn-danger";
  const content = `
        <form id="appointmentStatusForm" method="POST" action="?page=appointments">
            <input type="hidden" name="action" value="update_entity">
            <input type="hidden" name="entity" value="appointment_status">
            <input type="hidden" name="id" value="${appointmentId}">
            <input type="hidden" name="status" value="${escapeHtml(normalizedStatus)}">
            <input type="hidden" name="csrf_token" value="${csrfToken}">
            <div style="text-align: center; padding: 20px 0;">
                <i class="fa-solid ${iconClass}" style="font-size: 48px; color: #4F46E5; margin-bottom: 16px;"></i>
                <p style="margin: 0; font-size: 16px;">Mark this appointment as ${escapeHtml(normalizedStatus)}?</p>
            </div>
        </form>
    `;

  openModal("Update Appointment", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: normalizedStatus,
      class: buttonClass,
      onclick:
        'document.getElementById("appointmentStatusForm").requestSubmit()',
    },
  ]);
}

function updateReturnStatus(id, status, reasonText = "", isExpiredFlag = "0") {
  const returnId = Number.parseInt(id, 10);
  const normalizedStatus = String(status || "").trim();
  if (!Number.isFinite(returnId) || returnId <= 0) {
    showToast("error", "Invalid return ID");
    return;
  }

  if (!["Approved", "Rejected"].includes(normalizedStatus)) {
    showToast("error", "Invalid return status");
    return;
  }

  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const normalizedReason = String(reasonText || "").toLowerCase();
  const expiredKeywords = [
    "expired",
    "expire",
    "expiry",
    "imeisha",
    "imekwisha",
    "bad",
  ];
  const isExpiredByFlag = ["1", "true", "yes", "on"].includes(
    String(isExpiredFlag || "0").toLowerCase(),
  );
  const isExpiredReason =
    isExpiredByFlag ||
    expiredKeywords.some((keyword) => normalizedReason.includes(keyword));

  let note =
    '<p style="margin: 8px 0 0 0; font-size: 13px; color: #6B7280;">Stock will not be changed.</p>';
  if (normalizedStatus === "Approved") {
    note = isExpiredReason
      ? '<p style="margin: 8px 0 0 0; font-size: 13px; color: #B45309;">Reason indicates expired item, so stock will NOT be added.</p>'
      : '<p style="margin: 8px 0 0 0; font-size: 13px; color: #166534;">Stock will be added to inventory after approval.</p>';
  }

  const iconClass =
    normalizedStatus === "Approved" ? "fa-circle-check" : "fa-ban";
  const buttonClass =
    normalizedStatus === "Approved" ? "btn-primary" : "btn-danger";

  const content = `
    <form id="returnStatusForm" method="POST" action="?page=returns">
      <input type="hidden" name="action" value="update_entity">
      <input type="hidden" name="entity" value="return_status">
      <input type="hidden" name="id" value="${returnId}">
      <input type="hidden" name="status" value="${escapeHtml(normalizedStatus)}">
      <input type="hidden" name="csrf_token" value="${csrfToken}">
      <div style="text-align: center; padding: 20px 0;">
        <i class="fa-solid ${iconClass}" style="font-size: 48px; color: #4F46E5; margin-bottom: 16px;"></i>
        <p style="margin: 0; font-size: 16px;">Set return as ${escapeHtml(normalizedStatus)}?</p>
        ${note}
      </div>
    </form>
  `;

  openModal("Update Return", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: normalizedStatus,
      class: buttonClass,
      onclick: 'document.getElementById("returnStatusForm").requestSubmit()',
    },
  ]);
}

function viewReturn(target) {
  if (!target) {
    return;
  }

  const returnNo = target.getAttribute("data-return-no") || "-";
  const product = target.getAttribute("data-product") || "-";
  const quantity = target.getAttribute("data-quantity") || "0";
  const reason = target.getAttribute("data-reason") || "-";
  const isExpiredFlag = target.getAttribute("data-is-expired") || "0";
  const status = target.getAttribute("data-status") || "Pending";
  const date = target.getAttribute("data-date") || "-";

  const normalizedStatus = String(status).toLowerCase();
  const normalizedReason = String(reason).toLowerCase();
  const isExpiredByFlag = ["1", "true", "yes", "on"].includes(
    String(isExpiredFlag || "0").toLowerCase(),
  );
  const isExpiredReason =
    isExpiredByFlag ||
    ["expired", "expire", "expiry", "imeisha", "imekwisha", "bad"].some(
      (keyword) => normalizedReason.includes(keyword),
    );

  let stockImpact = "Stock will not change.";
  if (normalizedStatus === "approved") {
    stockImpact = isExpiredReason
      ? "Stock was not added because item is expired."
      : "Stock was added to inventory.";
  } else if (normalizedStatus === "pending") {
    stockImpact = isExpiredReason
      ? "If approved, stock will still not be added because reason indicates expired item."
      : "If approved, stock will be added to inventory.";
  }

  const content = `
    <div style="display:grid; gap:10px;">
      <div><strong>Return No:</strong> ${escapeHtml(returnNo)}</div>
      <div><strong>Product:</strong> ${escapeHtml(product)}</div>
      <div><strong>Quantity:</strong> ${escapeHtml(quantity)}</div>
      <div><strong>Reason:</strong> ${escapeHtml(reason)}</div>
      <div><strong>Status:</strong> ${escapeHtml(status)}</div>
      <div><strong>Date:</strong> ${escapeHtml(date)}</div>
      <div style="margin-top:6px; padding:10px 12px; border-radius:8px; background:#F9FAFB; color:#374151; font-size:13px;">
        <strong>Stock Impact:</strong> ${escapeHtml(stockImpact)}
      </div>
    </div>
  `;

  openModal("Return Details", content, [
    { text: "Close", class: "btn-primary", onclick: "closeModal()" },
  ]);
}

function viewPurchaseOrder(target) {
  if (!target) {
    return;
  }

  const poId = Number.parseInt(target.getAttribute("data-po-id") || "0", 10);
  const poNo = target.getAttribute("data-po-no") || "-";
  const supplier = target.getAttribute("data-supplier") || "-";
  const amount = target.getAttribute("data-amount") || "0";
  const status = target.getAttribute("data-status") || "Pending";
  const expectedDelivery = target.getAttribute("data-expected-delivery") || "-";
  const notes = target.getAttribute("data-notes") || "-";
  const rawItems = target.getAttribute("data-items") || "[]";
  const date = target.getAttribute("data-date") || "-";

  let items = [];
  try {
    const parsed = JSON.parse(rawItems);
    if (Array.isArray(parsed)) {
      items = parsed;
    }
  } catch (error) {
    items = [];
  }

  const itemRows =
    items.length > 0
      ? items
          .map((item, index) => {
            const productName = String(item.product_name || "").trim();
            const productId = Number.parseInt(
              String(item.product_id || "0"),
              10,
            );
            const quantity = Number.parseInt(String(item.quantity || "0"), 10);
            const unitCost = Number.parseFloat(String(item.unit_cost || "0"));
            const lineTotal = Number.parseFloat(
              String(item.line_total || quantity * unitCost || 0),
            );

            const label =
              productName !== ""
                ? productName
                : Number.isFinite(productId) && productId > 0
                  ? `Product #${productId}`
                  : `Item ${index + 1}`;

            return `<tr>
              <td style="padding:8px; border-bottom:1px solid #E5E7EB;">${escapeHtml(label)}</td>
              <td style="padding:8px; border-bottom:1px solid #E5E7EB;">${Number.isFinite(quantity) ? quantity : 0}</td>
              <td style="padding:8px; border-bottom:1px solid #E5E7EB;">Tsh ${escapeHtml(formatMoney(Number.isFinite(unitCost) ? unitCost : 0))}</td>
              <td style="padding:8px; border-bottom:1px solid #E5E7EB;">Tsh ${escapeHtml(formatMoney(Number.isFinite(lineTotal) ? lineTotal : 0))}</td>
            </tr>`;
          })
          .join("")
      : '<tr><td colspan="4" style="padding:8px; text-align:center; color:#6B7280;">No PO items recorded.</td></tr>';

  const content = `
    <div style="display:grid; gap:10px;">
      <div><strong>PO No:</strong> ${escapeHtml(poNo)}</div>
      <div><strong>Supplier:</strong> ${escapeHtml(supplier)}</div>
      <div><strong>Amount:</strong> Tsh ${escapeHtml(amount)}</div>
      <div><strong>Status:</strong> ${escapeHtml(status)}</div>
      <div><strong>Expected Delivery:</strong> ${escapeHtml(expectedDelivery)}</div>
      <div><strong>Notes:</strong> ${escapeHtml(notes)}</div>
      <div><strong>Date:</strong> ${escapeHtml(date)}</div>
      <div style="margin-top:8px;">
        <strong>Items</strong>
        <div style="margin-top:6px; border:1px solid #E5E7EB; border-radius:8px; overflow:auto;">
          <table style="width:100%; border-collapse:collapse; min-width:480px;">
            <thead>
              <tr style="background:#F9FAFB; text-align:left;">
                <th style="padding:8px; border-bottom:1px solid #E5E7EB;">Product</th>
                <th style="padding:8px; border-bottom:1px solid #E5E7EB;">Qty</th>
                <th style="padding:8px; border-bottom:1px solid #E5E7EB;">Unit Cost</th>
                <th style="padding:8px; border-bottom:1px solid #E5E7EB;">Line Total</th>
              </tr>
            </thead>
            <tbody>
              ${itemRows}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  `;

  const poDetails = {
    poId: Number.isFinite(poId) ? poId : 0,
    poNo,
    supplier,
    amount,
    status,
    expectedDelivery,
    notes,
    date,
    items,
  };

  openModal("Purchase Order Details", content, [
    {
      text: "Print PO",
      class: "btn-secondary",
      handler: () => printPurchaseOrderReceipt(poDetails),
    },
    { text: "Close", class: "btn-primary", onclick: "closeModal()" },
  ]);
}

function printPurchaseOrderReceipt(details) {
  if (!details || typeof details !== "object") {
    showToast("error", "Purchase order data is unavailable for printing.");
    return;
  }

  const poNo = String(details.poNo || "PO").trim();
  const supplier = String(details.supplier || "Supplier").trim();
  const status = String(details.status || "Pending").trim();
  const expectedDelivery = String(details.expectedDelivery || "-").trim();
  const notes = String(details.notes || "-").trim();
  const dateLabel = String(details.date || "-").trim();
  const items = Array.isArray(details.items) ? details.items : [];

  const normalizeMoney = (raw) => {
    const numeric = Number.parseFloat(
      String(raw ?? "0").replace(/[^0-9.\-]/g, ""),
    );
    return Number.isFinite(numeric) ? numeric : 0;
  };

  const parsedAmount = normalizeMoney(details.amount);
  let computedTotal = 0;

  const itemLinesHtml =
    items.length > 0
      ? items
          .map((item, index) => {
            const productName = String(item.product_name || "").trim();
            const productId = Number.parseInt(
              String(item.product_id || "0"),
              10,
            );
            const qty = Number.parseInt(String(item.quantity || "0"), 10);
            const unitCost = normalizeMoney(item.unit_cost);
            const lineTotalRaw = normalizeMoney(
              item.line_total ?? (Number.isFinite(qty) ? qty : 0) * unitCost,
            );
            computedTotal += lineTotalRaw;

            const label =
              productName !== ""
                ? productName
                : Number.isFinite(productId) && productId > 0
                  ? `Product #${productId}`
                  : `Item ${index + 1}`;

            return `
              <div class="sales-receipt-line">
                <span class="sales-receipt-line-main">
                  <strong>${escapeHtml(label)} x ${Number.isFinite(qty) ? qty : 0}</strong>
                  <small>Tsh ${escapeHtml(formatMoney(unitCost))} each</small>
                </span>
                <strong>Tsh ${escapeHtml(formatMoney(lineTotalRaw))}</strong>
              </div>
            `;
          })
          .join("")
      : '<div class="sales-receipt-line"><span class="sales-receipt-line-main"><strong>No PO items</strong></span><strong>-</strong></div>';

  const finalTotal = parsedAmount > 0 ? parsedAmount : computedTotal;

  const sheetHtml = `
    <div class="sales-receipt-sheet" id="purchaseOrderReceiptSheet">
      <div class="sales-receipt-brand">Mchongoma Limited</div>
      <div class="sales-receipt-subtitle">Purchase Order</div>
      <div class="sales-receipt-time">${escapeHtml(dateLabel)}</div>
      <div class="sales-receipt-meta">
        <div><span>PO No</span><strong>${escapeHtml(poNo)}</strong></div>
        <div><span>Supplier</span><strong>${escapeHtml(supplier)}</strong></div>
        <div><span>Status</span><strong>${escapeHtml(status)}</strong></div>
      </div>
      <div class="sales-receipt-items">${itemLinesHtml}</div>
      <div class="sales-receipt-totals">
        <div><span>Expected Delivery</span><strong>${escapeHtml(expectedDelivery)}</strong></div>
        <div><span>Notes</span><strong>${escapeHtml(notes)}</strong></div>
        <div class="sales-receipt-total"><span>TOTAL</span><strong>Tsh ${escapeHtml(formatMoney(finalTotal))}</strong></div>
      </div>
      <p class="sales-receipt-thanks">*** Generated by Mchongoma POS ***</p>
    </div>
  `;

  const printWindow = window.open("", "_blank", "width=420,height=760");
  if (!printWindow) {
    showToast("warning", "Pop-up blocked. Enable pop-ups to print PO.");
    return;
  }

  try {
    printWindow.opener = null;
  } catch (error) {
    // Ignore if browser disallows mutating opener.
  }

  printWindow.document.open();
  printWindow.document.write(`
    <html>
      <head>
        <title>Purchase Order ${escapeHtml(poNo)}</title>
        <style>
          body{font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; padding:16px; color:#111827; background:#f5f7fb;}
          .sales-receipt-sheet{max-width:360px; margin:0 auto; border:1px solid #cfd8e6; border-radius:10px; padding:12px 11px; background:linear-gradient(180deg, #ffffff 0%, #f8faff 100%);}
          .sales-receipt-brand{text-align:center; font-size:28px; font-weight:800; margin-bottom:4px; color:#1f2f47;}
          .sales-receipt-subtitle,.sales-receipt-time{text-align:center; color:#5f6f86; font-size:12px;}
          .sales-receipt-meta{margin-top:12px; border-top:1px dashed #bcc8db; border-bottom:1px dashed #bcc8db; padding:8px 0;}
          .sales-receipt-totals{margin-top:12px; border-top:1px dashed #bcc8db; padding-top:8px;}
          .sales-receipt-meta div,.sales-receipt-line,.sales-receipt-totals div{display:flex; justify-content:space-between; margin-bottom:7px; font-size:12px; align-items:center; gap:8px;}
          .sales-receipt-items{margin-top:10px;}
          .sales-receipt-line{align-items:flex-start; border-bottom:1px dashed #c5d0e1; padding:6px 0; margin-bottom:0;}
          .sales-receipt-line:last-child{border-bottom:none;}
          .sales-receipt-line-main{display:flex; flex-direction:column; gap:2px;}
          .sales-receipt-line-main small{color:#68778f; font-size:11px;}
          .sales-receipt-total{font-weight:800; font-size:16px; color:#3730a3;}
          .sales-receipt-thanks{text-align:center; margin-top:12px; font-size:12px; color:#5f6f86;}
        </style>
      </head>
      <body>${sheetHtml}</body>
    </html>
  `);
  printWindow.document.close();

  let didPrint = false;
  const triggerPrint = function () {
    if (didPrint || printWindow.closed) {
      return;
    }
    didPrint = true;
    printWindow.focus();
    printWindow.print();
  };

  printWindow.addEventListener("load", function () {
    setTimeout(triggerPrint, 120);
  });

  setTimeout(triggerPrint, 420);
}

function viewQuotation(target) {
  if (!target) {
    return;
  }

  const quotationId = Number.parseInt(
    target.getAttribute("data-quotation-id") || "0",
    10,
  );
  const quotationNo = target.getAttribute("data-quotation-no") || "-";
  const customer = target.getAttribute("data-customer") || "-";
  const amount = target.getAttribute("data-amount") || "0";
  const status = target.getAttribute("data-status") || "Pending";
  const date = target.getAttribute("data-date") || "-";

  const quotationDetails = {
    quotationId: Number.isFinite(quotationId) ? quotationId : 0,
    quotationNo,
    customer,
    amount,
    status,
    date,
  };

  const content = `
    <div style="display:grid; gap:10px;">
      <div><strong>Quotation No:</strong> ${escapeHtml(quotationNo)}</div>
      <div><strong>Customer:</strong> ${escapeHtml(customer)}</div>
      <div><strong>Amount:</strong> Tsh ${escapeHtml(amount)}</div>
      <div><strong>Status:</strong> ${escapeHtml(status)}</div>
      <div><strong>Date:</strong> ${escapeHtml(date)}</div>
    </div>
  `;

  openModal("Quotation Details", content, [
    {
      text: "Print Quotation",
      class: "btn-secondary",
      handler: () => printQuotationReceipt(quotationDetails),
    },
    { text: "Close", class: "btn-primary", onclick: "closeModal()" },
  ]);
}

function editQuotation(target) {
  if (!target) {
    return;
  }

  const quotationId = Number.parseInt(
    target.getAttribute("data-value") || "0",
    10,
  );
  if (!Number.isFinite(quotationId) || quotationId <= 0) {
    showToast("error", "Invalid quotation ID");
    return;
  }

  const currentCustomerId = String(
    target.getAttribute("data-customer-id") || "",
  ).trim();
  const currentCustomerName = String(
    target.getAttribute("data-customer") || "",
  ).trim();
  const currentAmount = Number.parseFloat(
    String(target.getAttribute("data-amount-raw") || "0"),
  );

  const customers = Array.isArray(APP_CONFIG.saleCustomers)
    ? APP_CONFIG.saleCustomers
    : [];
  if (customers.length === 0) {
    showToast("warning", "Please add at least one customer first.");
    return;
  }

  const customerOptions = customers
    .map((customer) => {
      const customerId = String(customer.id ?? "").trim();
      const customerLabel = String(
        customer.name ?? `Customer #${customer.id || ""}`,
      ).trim();
      if (customerId === "" || customerLabel === "") {
        return "";
      }

      const selected =
        customerId === currentCustomerId ||
        (currentCustomerId === "" && customerLabel === currentCustomerName)
          ? " selected"
          : "";
      return `<option value="${escapeHtml(customerId)}"${selected}>${escapeHtml(customerLabel)}</option>`;
    })
    .filter((entry) => entry !== "")
    .join("");

  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const content = `
    <form id="editQuotationForm" method="POST" action="?page=quotations">
      <input type="hidden" name="action" value="update_entity">
      <input type="hidden" name="entity" value="quotation">
      <input type="hidden" name="id" value="${quotationId}">
      <input type="hidden" name="csrf_token" value="${csrfToken}">

      <div class="form-group">
        <label>Customer</label>
        <select name="customer_id" required>
          ${customerOptions}
        </select>
      </div>

      <div class="form-group">
        <label>Amount (Tsh)</label>
        <input type="number" name="amount" min="1" step="0.01" required value="${escapeHtml(String(Number.isFinite(currentAmount) ? currentAmount : 0))}">
      </div>
    </form>
  `;

  openModal("Edit Quotation", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: "Save Changes",
      class: "btn-primary",
      onclick: 'document.getElementById("editQuotationForm").requestSubmit()',
    },
  ]);
}

function updateQuotationStatus(id, status) {
  const quotationId = Number.parseInt(id, 10);
  const normalizedStatus = String(status || "").trim();
  if (!Number.isFinite(quotationId) || quotationId <= 0) {
    showToast("error", "Invalid quotation ID");
    return;
  }

  if (!["Approved", "Rejected", "Expired"].includes(normalizedStatus)) {
    showToast("error", "Invalid quotation status");
    return;
  }

  const csrfToken = escapeHtml(APP_CONFIG.csrfToken || "");
  const iconClass =
    normalizedStatus === "Approved"
      ? "fa-circle-check"
      : normalizedStatus === "Rejected"
        ? "fa-ban"
        : "fa-hourglass-end";
  const buttonClass =
    normalizedStatus === "Approved"
      ? "btn-primary"
      : normalizedStatus === "Rejected"
        ? "btn-danger"
        : "btn-secondary";

  const content = `
    <form id="quotationStatusForm" method="POST" action="?page=quotations">
      <input type="hidden" name="action" value="update_entity">
      <input type="hidden" name="entity" value="quotation_status">
      <input type="hidden" name="id" value="${quotationId}">
      <input type="hidden" name="status" value="${escapeHtml(normalizedStatus)}">
      <input type="hidden" name="csrf_token" value="${csrfToken}">
      <div style="text-align: center; padding: 20px 0;">
        <i class="fa-solid ${iconClass}" style="font-size: 48px; color: #4F46E5; margin-bottom: 16px;"></i>
        <p style="margin: 0; font-size: 16px;">Set quotation status to ${escapeHtml(normalizedStatus)}?</p>
      </div>
    </form>
  `;

  openModal("Update Quotation", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: normalizedStatus,
      class: buttonClass,
      onclick: 'document.getElementById("quotationStatusForm").requestSubmit()',
    },
  ]);
}

function viewInvoice(target) {
  if (!target) {
    return;
  }

  const invoiceId = Number.parseInt(
    target.getAttribute("data-invoice-id") || "0",
    10,
  );
  const invoiceNo = target.getAttribute("data-invoice-no") || "-";
  const customer = target.getAttribute("data-customer") || "-";
  const amount = target.getAttribute("data-amount") || "0";
  const status = target.getAttribute("data-status") || "Pending";
  const date = target.getAttribute("data-date") || "-";

  const invoiceDetails = {
    invoiceId: Number.isFinite(invoiceId) ? invoiceId : 0,
    invoiceNo,
    customer,
    amount,
    status,
    date,
  };

  const content = `
    <div style="display:grid; gap:10px;">
      <div><strong>Invoice No:</strong> ${escapeHtml(invoiceNo)}</div>
      <div><strong>Customer:</strong> ${escapeHtml(customer)}</div>
      <div><strong>Amount:</strong> Tsh ${escapeHtml(amount)}</div>
      <div><strong>Status:</strong> ${escapeHtml(status)}</div>
      <div><strong>Date:</strong> ${escapeHtml(date)}</div>
    </div>
  `;

  openModal("Invoice Details", content, [
    {
      text: "Print Invoice",
      class: "btn-secondary",
      handler: () => printInvoiceReceipt(invoiceDetails),
    },
    { text: "Close", class: "btn-primary", onclick: "closeModal()" },
  ]);
}

function printQuotationReceipt(details) {
  if (!details || typeof details !== "object") {
    showToast("error", "Quotation data is unavailable for printing.");
    return;
  }

  const quotationNo = String(details.quotationNo || "Quotation").trim();
  const customer = String(details.customer || "Customer").trim();
  const status = String(details.status || "Pending").trim();
  const date = String(details.date || "-").trim();
  const amountText = String(details.amount || "0").trim();
  const amountNumber = Number.parseFloat(amountText.replace(/[^0-9.\-]/g, ""));
  const amountLabel = Number.isFinite(amountNumber)
    ? formatMoney(amountNumber)
    : amountText;

  const sheetHtml = `
    <div class="sales-receipt-sheet">
      <div class="sales-receipt-brand">Mchongoma Limited</div>
      <div class="sales-receipt-subtitle">Quotation</div>
      <div class="sales-receipt-time">${escapeHtml(date)}</div>
      <div class="sales-receipt-meta">
        <div><span>Quotation No</span><strong>${escapeHtml(quotationNo)}</strong></div>
        <div><span>Customer</span><strong>${escapeHtml(customer)}</strong></div>
        <div><span>Status</span><strong>${escapeHtml(status)}</strong></div>
      </div>
      <div class="sales-receipt-totals">
        <div class="sales-receipt-total"><span>TOTAL</span><strong>Tsh ${escapeHtml(amountLabel)}</strong></div>
      </div>
      <p class="sales-receipt-thanks">*** Quotation generated by Mchongoma POS ***</p>
    </div>
  `;

  openPrintWindow(`Quotation ${quotationNo}`, sheetHtml);
}

function printInvoiceReceipt(details) {
  if (!details || typeof details !== "object") {
    showToast("error", "Invoice data is unavailable for printing.");
    return;
  }

  const invoiceNo = String(details.invoiceNo || "Invoice").trim();
  const customer = String(details.customer || "Customer").trim();
  const status = String(details.status || "Pending").trim();
  const date = String(details.date || "-").trim();
  const amountText = String(details.amount || "0").trim();
  const amountNumber = Number.parseFloat(amountText.replace(/[^0-9.\-]/g, ""));
  const amountLabel = Number.isFinite(amountNumber)
    ? formatMoney(amountNumber)
    : amountText;

  const rowDate = escapeHtml(
    date !== "-" ? date : new Date().toLocaleDateString(),
  );
  const rowDescription = escapeHtml(`Invoice ${invoiceNo} - ${customer}`);
  const rowPrice = escapeHtml(amountLabel);
  const rowQty = "1";
  const rowTotal = escapeHtml(amountLabel);

  const sheetHtml = `
    <div class="invoice-template">
      <header class="invoice-template-header">
        <h1>Invoice</h1>
      </header>
      <div class="invoice-template-accent"></div>

      <section class="invoice-template-body">
        <div class="invoice-template-top-grid">
          <div class="invoice-template-left-meta">
            <div><span>Date:</span><strong>${escapeHtml(date)}</strong></div>
            <div><span>No. Invoice:</span><strong>${escapeHtml(invoiceNo)}</strong></div>
            <div><span>Bill to:</span><strong>${escapeHtml(customer)}</strong></div>
          </div>
          <div class="invoice-template-right-meta">
            <div><span>Payment Method:</span><strong>${escapeHtml(status)}</strong></div>
            <div><span>Account Number:</span><strong>N/A</strong></div>
          </div>
        </div>

        <table class="invoice-template-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Item Description</th>
              <th>Price</th>
              <th>Qty</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>${rowDate}</td>
              <td>${rowDescription}</td>
              <td>Tsh ${rowPrice}</td>
              <td>${rowQty}</td>
              <td>Tsh ${rowTotal}</td>
            </tr>
            <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>
            <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>
            <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>
            <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td></tr>
          </tbody>
        </table>

        <div class="invoice-template-bottom">
          <div class="invoice-template-thanks">Thank you!</div>
          <div class="invoice-template-total-box">
            <span>Total:</span>
            <strong>Tsh ${escapeHtml(amountLabel)}</strong>
          </div>
        </div>

        <footer class="invoice-template-footer">
          <div>Mchongoma Limited, Dar es Salaam</div>
          <div>+255-700-000-111</div>
          <div>info@mchongoma.com</div>
        </footer>
      </section>
    </div>
  `;

  openPrintWindow(`Invoice ${invoiceNo}`, sheetHtml, {
    width: 960,
    height: 820,
    bodyClass: "invoice-print-body",
  });
}

function printPurchaseOrder(id) {
  const purchaseOrderId = Number.parseInt(id, 10);
  if (!Number.isFinite(purchaseOrderId) || purchaseOrderId <= 0) {
    showToast("error", "Invalid purchase order ID");
    return;
  }

  window.open(
    `export_purchase_order_pdf.php?po_id=${encodeURIComponent(purchaseOrderId)}`,
    "_blank",
    "noopener",
  );
}

function printQuotation(targetOrId) {
  if (targetOrId && typeof targetOrId === "object") {
    const quotationId = Number.parseInt(
      targetOrId.getAttribute("data-value") || "0",
      10,
    );
    const quotationNo =
      targetOrId
        .closest("tr")
        ?.querySelector("td:nth-child(1) strong")
        ?.textContent?.trim() || `QUO-${quotationId}`;
    const customer =
      targetOrId
        .closest("tr")
        ?.querySelector("td:nth-child(2)")
        ?.textContent?.trim() || "Customer";
    const amountRaw =
      targetOrId
        .closest("tr")
        ?.querySelector("td:nth-child(3)")
        ?.textContent?.trim() || "Tsh 0";
    const status =
      targetOrId
        .closest("tr")
        ?.querySelector("td:nth-child(4) .status-badge")
        ?.textContent?.trim() || "Draft";
    const date =
      targetOrId
        .closest("tr")
        ?.querySelector("td:nth-child(5)")
        ?.textContent?.trim() || "-";

    printQuotationReceipt({
      quotationId,
      quotationNo,
      customer,
      amount: amountRaw,
      status,
      date,
    });
    return;
  }

  const quotationId = Number.parseInt(String(targetOrId || "0"), 10);
  if (!Number.isFinite(quotationId) || quotationId <= 0) {
    showToast("error", "Invalid quotation ID");
    return;
  }

  window.open(
    `export_quotation_pdf.php?quotation_id=${encodeURIComponent(quotationId)}`,
    "_blank",
    "noopener",
  );
}

function printInvoice(targetOrId) {
  if (targetOrId && typeof targetOrId === "object") {
    const invoiceId = Number.parseInt(
      targetOrId.getAttribute("data-invoice-id") || "0",
      10,
    );
    const invoiceNo =
      targetOrId.getAttribute("data-invoice-no") || `INV-${invoiceId}`;
    const customer = targetOrId.getAttribute("data-customer") || "Customer";
    const amount = targetOrId.getAttribute("data-amount") || "0";
    const status = targetOrId.getAttribute("data-status") || "Pending";
    const date = targetOrId.getAttribute("data-date") || "-";

    printInvoiceReceipt({
      invoiceId,
      invoiceNo,
      customer,
      amount,
      status,
      date,
    });
    return;
  }

  const invoiceId = Number.parseInt(String(targetOrId || "0"), 10);
  if (!Number.isFinite(invoiceId) || invoiceId <= 0) {
    showToast("error", "Invalid invoice ID");
    return;
  }

  window.open(
    `export_invoice_pdf.php?invoice_id=${encodeURIComponent(invoiceId)}`,
    "_blank",
    "noopener",
  );
}

function printCustomerStatement(targetOrId) {
  if (targetOrId && typeof targetOrId === "object") {
    const customerId = Number.parseInt(
      targetOrId.getAttribute("data-value") || "0",
      10,
    );
    const customerName =
      targetOrId
        .closest("tr")
        ?.querySelector("td:nth-child(1) strong")
        ?.textContent?.trim() || `Customer #${customerId}`;
    const phone =
      targetOrId
        .closest("tr")
        ?.querySelector("td:nth-child(2)")
        ?.textContent?.trim() || "N/A";
    const totalOrders =
      targetOrId
        .closest("tr")
        ?.querySelector("td:nth-child(3)")
        ?.textContent?.trim() || "0";
    const totalSpent =
      targetOrId
        .closest("tr")
        ?.querySelector("td:nth-child(4)")
        ?.textContent?.trim() || "Tsh 0";
    const outstandingDebt =
      targetOrId
        .closest("tr")
        ?.querySelector("td:nth-child(5)")
        ?.textContent?.trim() || "Tsh 0";

    const sheetHtml = `
      <div class="sales-receipt-sheet">
        <div class="sales-receipt-brand">Mchongoma Limited</div>
        <div class="sales-receipt-subtitle">Customer Statement</div>
        <div class="sales-receipt-time">${escapeHtml(new Date().toLocaleString())}</div>
        <div class="sales-receipt-meta">
          <div><span>Customer</span><strong>${escapeHtml(customerName)}</strong></div>
          <div><span>Phone</span><strong>${escapeHtml(phone)}</strong></div>
          <div><span>Total Orders</span><strong>${escapeHtml(totalOrders)}</strong></div>
        </div>
        <div class="sales-receipt-totals">
          <div><span>Total Spent</span><strong>${escapeHtml(totalSpent)}</strong></div>
          <div class="sales-receipt-total"><span>Outstanding</span><strong>${escapeHtml(outstandingDebt)}</strong></div>
        </div>
        <p class="sales-receipt-thanks">*** Statement generated by Mchongoma POS ***</p>
      </div>
    `;

    openPrintWindow(`Statement ${customerName}`, sheetHtml);
    return;
  }

  const customerId = Number.parseInt(String(targetOrId || "0"), 10);
  if (!Number.isFinite(customerId) || customerId <= 0) {
    showToast("error", "Invalid customer ID");
    return;
  }

  window.open(
    `customer_statement_pdf.php?customer_id=${encodeURIComponent(customerId)}`,
    "_blank",
    "noopener",
  );
}

function openPrintWindow(title, sheetHtml, options = {}) {
  const width = Number.parseInt(String(options.width || 420), 10) || 420;
  const height = Number.parseInt(String(options.height || 760), 10) || 760;
  const bodyClass = String(options.bodyClass || "").trim();

  const printWindow = window.open(
    "",
    "_blank",
    `width=${width},height=${height}`,
  );
  if (!printWindow) {
    showToast("warning", "Pop-up blocked. Enable pop-ups to print.");
    return;
  }

  try {
    printWindow.opener = null;
  } catch (error) {
    // Ignore if browser disallows mutating opener.
  }

  printWindow.document.open();
  printWindow.document.write(`
    <html>
      <head>
        <title>${escapeHtml(String(title || "Print"))}</title>
        <style>
          body{font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; padding:16px; color:#111827; background:#f5f7fb;}
          .sales-receipt-sheet{max-width:360px; margin:0 auto; border:1px solid #cfd8e6; border-radius:10px; padding:12px 11px; background:linear-gradient(180deg, #ffffff 0%, #f8faff 100%);}
          .sales-receipt-brand{text-align:center; font-size:28px; font-weight:800; margin-bottom:4px; color:#1f2f47;}
          .sales-receipt-subtitle,.sales-receipt-time{text-align:center; color:#5f6f86; font-size:12px;}
          .sales-receipt-meta{margin-top:12px; border-top:1px dashed #bcc8db; border-bottom:1px dashed #bcc8db; padding:8px 0;}
          .sales-receipt-totals{margin-top:12px; border-top:1px dashed #bcc8db; padding-top:8px;}
          .sales-receipt-meta div,.sales-receipt-line,.sales-receipt-totals div{display:flex; justify-content:space-between; margin-bottom:7px; font-size:12px; align-items:center; gap:8px;}
          .sales-receipt-items{margin-top:10px;}
          .sales-receipt-line{align-items:flex-start; border-bottom:1px dashed #c5d0e1; padding:6px 0; margin-bottom:0;}
          .sales-receipt-line:last-child{border-bottom:none;}
          .sales-receipt-line-main{display:flex; flex-direction:column; gap:2px;}
          .sales-receipt-line-main small{color:#68778f; font-size:11px;}
          .sales-receipt-total{font-weight:800; font-size:16px; color:#3730a3;}
          .sales-receipt-thanks{text-align:center; margin-top:12px; font-size:12px; color:#5f6f86;}

          body.invoice-print-body{font-family:"Poppins", "Segoe UI", sans-serif; background:#eef2f6; padding:10px;}
          .invoice-template{max-width:820px; margin:0 auto; background:#ffffff; border:1px solid #d6dee6; box-shadow:0 10px 24px rgba(15, 23, 42, 0.14);}
          .invoice-template-header{background:linear-gradient(140deg, #2a2430 0%, #1f1a28 100%); color:#f3f4f6; padding:28px 36px 20px;}
          .invoice-template-header h1{margin:0; font-size:52px; line-height:1; letter-spacing:.3px;}
          .invoice-template-accent{height:8px; background:#67b64a;}
          .invoice-template-body{padding:24px 34px 30px; color:#111827;}
          .invoice-template-top-grid{display:grid; grid-template-columns:1fr 1fr; gap:32px; margin-bottom:22px;}
          .invoice-template-left-meta div,.invoice-template-right-meta div{display:flex; justify-content:space-between; gap:12px; border-bottom:1px solid #9aa3ad; padding:8px 0; font-size:13px;}
          .invoice-template-left-meta span,.invoice-template-right-meta span{font-weight:700; color:#253246;}
          .invoice-template-left-meta strong,.invoice-template-right-meta strong{font-weight:600; color:#111827; text-align:right;}
          .invoice-template-table{width:100%; border-collapse:collapse; margin-top:14px; font-size:12px;}
          .invoice-template-table th{background:#67b64a; color:#0b1f0c; border:1px solid #4a9c31; padding:8px 6px; text-align:center; font-weight:700;}
          .invoice-template-table td{border:1px solid #9ca3af; padding:7px 6px; min-height:30px; text-align:center;}
          .invoice-template-table td:nth-child(2){text-align:left;}
          .invoice-template-bottom{display:flex; justify-content:space-between; align-items:flex-end; margin-top:14px; gap:20px;}
          .invoice-template-thanks{font-size:34px; font-weight:800; color:#111827; letter-spacing:.2px;}
          .invoice-template-total-box{border:1px solid #9ca3af; min-width:230px; padding:8px 10px; display:flex; justify-content:space-between; gap:12px;}
          .invoice-template-total-box span{color:#4b5563; font-size:13px;}
          .invoice-template-total-box strong{font-size:15px; color:#111827;}
          .invoice-template-footer{margin-top:26px; border-top:2px solid #9ca3af; padding-top:10px; color:#1f2937; font-size:12px; display:grid; gap:4px;}

          @media print {
            body{background:#fff; padding:0; margin:0;}
            .invoice-template{box-shadow:none; border:0; max-width:100%;}
          }
        </style>
      </head>
      <body class="${escapeHtml(bodyClass)}">${sheetHtml}</body>
    </html>
  `);
  printWindow.document.close();

  let didPrint = false;
  const triggerPrint = function () {
    if (didPrint || printWindow.closed) {
      return;
    }
    didPrint = true;
    printWindow.focus();
    printWindow.print();
  };

  printWindow.addEventListener("load", function () {
    setTimeout(triggerPrint, 120);
  });

  setTimeout(triggerPrint, 420);
}

function viewReceipt(transactionNo) {
  // Validate transaction number format to prevent XSS
  if (!isValidIdentifier(transactionNo)) {
    showToast("error", "Invalid transaction number");
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
  openModal("Receipt", content, [
    {
      text: "Print",
      class: "btn-secondary",
      onclick: `printReceipt("${safeTransactionNo}")`,
    },
    { text: "Close", class: "btn-primary", onclick: "closeModal()" },
  ]);
}

function printReceipt(transactionNo) {
  showToast("success", "Printing receipt...");
}

function printEndOfDaySummary(summary) {
  const dateLabel = String(summary?.dateLabel || "");
  const totalSales = Number.parseFloat(summary?.totalSales || 0) || 0;
  const transactions = Number.parseInt(summary?.transactions || 0, 10) || 0;
  const cash = Number.parseFloat(summary?.cash || 0) || 0;
  const mobileMoney = Number.parseFloat(summary?.mobileMoney || 0) || 0;

  const printWindow = window.open("", "_blank", "width=900,height=700");
  if (!printWindow) {
    showToast("error", "Popup blocked. Please allow popups and try again.");
    return;
  }

  try {
    printWindow.opener = null;
  } catch (error) {
    // Ignore if browser blocks mutating opener.
  }

  const printableHtml = `
    <!doctype html>
    <html lang="en">
      <head>
        <meta charset="utf-8">
        <title>End of Day Report</title>
        <style>
          body { font-family: Arial, sans-serif; color: #111827; margin: 24px; }
          h1 { margin: 0 0 6px 0; font-size: 22px; }
          .date { margin: 0 0 18px 0; color: #6B7280; }
          .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
          .card { border: 1px solid #D1D5DB; border-radius: 8px; padding: 12px; }
          .label { color: #6B7280; font-size: 12px; margin: 0 0 6px 0; }
          .value { font-size: 22px; font-weight: 700; margin: 0; }
          @media print { body { margin: 12mm; } }
        </style>
      </head>
      <body>
        <h1>End of Day Report</h1>
        <p class="date">${escapeHtml(dateLabel)}</p>
        <div class="grid">
          <div class="card">
            <p class="label">Total Sales</p>
            <p class="value">Tsh ${formatMoney(totalSales)}</p>
          </div>
          <div class="card">
            <p class="label">Transactions</p>
            <p class="value">${transactions}</p>
          </div>
          <div class="card">
            <p class="label">Cash</p>
            <p class="value">Tsh ${formatMoney(cash)}</p>
          </div>
          <div class="card">
            <p class="label">Mobile Money</p>
            <p class="value">Tsh ${formatMoney(mobileMoney)}</p>
          </div>
        </div>
      </body>
    </html>
  `;

  printWindow.document.open();
  printWindow.document.write(printableHtml);
  printWindow.document.close();

  let didPrint = false;
  const triggerPrint = function () {
    if (didPrint || printWindow.closed) {
      return;
    }
    didPrint = true;
    printWindow.focus();
    printWindow.print();
  };

  printWindow.addEventListener("load", function () {
    setTimeout(triggerPrint, 100);
  });

  // Fallback for browsers that do not fire load reliably after document.write.
  setTimeout(triggerPrint, 400);

  showToast("success", "Printing report...");
}

function printReport() {
  const rawRange =
    new URLSearchParams(window.location.search).get("range") || "month";
  const rangeAlias = {
    today: "day",
    day: "day",
    week: "week",
    month: "month",
    year: "year",
    all: "all",
  };
  const range = rangeAlias[rawRange] || "month";
  const url = `export_report_pdf.php?range=${encodeURIComponent(range)}&disposition=inline`;
  window.open(url, "_blank", "noopener");
  showToast("success", "Opening report for print...");
}

function generateReport(value) {
  const typeNames = {
    daily: "Daily Sales",
    weekly: "Weekly Sales",
    monthly: "Monthly Sales",
    inventory: "Inventory",
    customers: "Customer",
    profit: "Profit & Loss",
  };
  const rangeNames = {
    day: "Today",
    today: "Today",
    week: "This Week",
    month: "This Month",
    year: "This Year",
    all: "All Time",
  };

  if (rangeNames[value]) {
    const rangeAlias = {
      today: "day",
      day: "day",
      week: "week",
      month: "month",
      year: "year",
      all: "all",
    };
    const exportRange = rangeAlias[value] || "month";
    window.open(
      `export_report_pdf.php?range=${encodeURIComponent(exportRange)}`,
      "_blank",
      "noopener",
    );
    if (getCurrentLanguage() === "sw") {
      showToast(
        "success",
        "Inahamisha ripoti kulingana na kipindi kilichochaguliwa...",
      );
    } else {
      showToast("success", `Exporting ${rangeNames[value]} report PDF...`);
    }
    return;
  }

  if (!typeNames[value]) {
    showToast("error", "Invalid report type");
    return;
  }

  window.open(
    `export_report_pdf.php?type=${encodeURIComponent(value)}`,
    "_blank",
    "noopener",
  );
  if (getCurrentLanguage() === "sw") {
    const localized = translateValue(typeNames[value], "sw");
    showToast("success", `Inahamisha PDF ya Ripoti ya ${localized}...`);
  } else {
    showToast("success", `Exporting ${typeNames[value]} Report PDF...`);
  }
}

function logout() {
  closeModal();
  const logoutForm = document.getElementById("logoutForm");
  if (logoutForm) {
    logoutForm.requestSubmit();
    return;
  }

  window.location.href = "login.php";
}

// ============================================
// KEYBOARD SHORTCUTS
// ============================================

function initKeyboardShortcuts() {
  document.addEventListener("keydown", function (e) {
    // Escape to close modal
    if (e.key === "Escape") {
      closeModal();
      closeNotifications();
      closeSidebar();
    }

    // Ctrl+K for quick search
    if (e.ctrlKey && e.key === "k") {
      e.preventDefault();
      const searchInput = document.querySelector(".search-box input");
      if (searchInput) {
        searchInput.focus();
      }
    }

    // Ctrl+N for new sale
    if (e.ctrlKey && e.key === "n" && !e.shiftKey) {
      e.preventDefault();
      showNewSaleModal();
    }
  });
}

// ============================================
// UTILITY FUNCTIONS
// ============================================

function formatMoney(amount) {
  return new Intl.NumberFormat("en-TZ").format(amount);
}
