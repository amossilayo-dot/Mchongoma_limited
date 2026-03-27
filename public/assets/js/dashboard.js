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
  applyLanguage(getCurrentLanguage());
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
    const ampm = hours >= 12 ? "pm" : "am";
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

    if (action === "printCustomerStatement") {
      printCustomerStatement(parseInt(value, 10));
      return;
    }

    if (action === "receiveCustomerPayment") {
      receiveCustomerPayment(parseInt(value, 10));
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
        } else if (btn.onclick.startsWith("confirmDeleteProduct(")) {
          const match = btn.onclick.match(
            /confirmDeleteProduct\s*\(\s*(\d+)\s*\)/,
          );
          if (match) confirmDeleteProduct(parseInt(match[1], 10));
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

  const customerOptions =
    customers.length > 0
      ? customers
          .map(
            (c) =>
              `<option value="${escapeHtml(c.id)}">${escapeHtml(c.name || "")}</option>`,
          )
          .join("")
      : '<option value="1">Walk-in Customer</option>';

  const content = `
        <form id="newSaleForm" method="POST" action="?page=sales">
            <input type="hidden" name="action" value="create_entity">
            <input type="hidden" name="entity" value="sale">
            <input type="hidden" name="csrf_token" value="${csrfToken}">
            <div class="form-group">
                <label>Customer</label>
                <select name="customer_id" required>
                    ${customerOptions}
                </select>
            </div>
            <div class="form-group">
                <label>Product</label>
                <input type="text" id="saleProductSearch" placeholder="Search products..." class="form-control" style="margin-bottom:8px;">
                <select id="saleProductId" name="product_id" ${products.length > 0 ? "required" : ""}>
                    <option value="">Select product</option>
                </select>
            </div>
            <div class="form-group">
                <label>Quantity</label>
                <input id="saleQuantity" type="number" name="quantity" placeholder="Enter quantity" required min="1" value="1">
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
  const productEl = document.getElementById("saleProductId");
  const quantityEl = document.getElementById("saleQuantity");
  const amountEl = document.getElementById("saleAmount");
  const mobileMoneyFields = document.getElementById("mobileMoneyFields");
  const mobileMoneyProvider = document.getElementById("mobileMoneyProvider");
  const mobileMoneyPhone = document.getElementById("mobileMoneyPhone");
  let productSearchTimer = null;
  let productSearchRequestId = 0;

  const renderProductOptions = (searchTerm = "") => {
    if (!productEl) {
      return;
    }

    const previous = productEl.value;
    const term = (searchTerm || "").toLowerCase().trim();
    const source = loadedProducts.filter((p) => {
      if (term === "") {
        return true;
      }
      const name = String(p.name || "").toLowerCase();
      const category = String(p.category || "").toLowerCase();
      return name.includes(term) || category.includes(term);
    });

    productEl.innerHTML = "";

    const first = document.createElement("option");
    first.value = "";
    first.textContent = "Select product";
    productEl.appendChild(first);

    if (source.length === 0) {
      const empty = document.createElement("option");
      empty.value = "";
      empty.textContent = "No products available";
      productEl.appendChild(empty);
      productEl.required = false;
      return;
    }

    source.forEach((p) => {
      const option = document.createElement("option");
      const category = String(p.category || "").trim();
      const stock = Number.isFinite(Number(p.stock_qty))
        ? Number(p.stock_qty)
        : 0;
      const price = Number.isFinite(Number(p.unit_price))
        ? Number(p.unit_price)
        : 0;
      option.value = String(p.id || "");
      option.setAttribute("data-price", String(price));
      option.textContent = `${p.name || ""}${category ? ` - ${category}` : ""} | Stock: ${stock} | Tsh ${price.toLocaleString()}`;
      productEl.appendChild(option);
    });

    productEl.required = true;
    if (previous && source.some((p) => String(p.id) === previous)) {
      productEl.value = previous;
    }
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
      renderProductOptions(productSearchEl?.value || "");
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
    if (!productEl || !amountEl || !quantityEl) {
      return;
    }
    const selectedOption = productEl.options[productEl.selectedIndex];
    const unitPrice = parseFloat(
      selectedOption?.getAttribute("data-price") || "0",
    );
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

  paymentMethodEl?.addEventListener("change", toggleMobileMoneyFields);
  productSearchEl?.addEventListener("input", () => {
    const term = productSearchEl.value || "";
    renderProductOptions(term);

    if (productSearchTimer) {
      clearTimeout(productSearchTimer);
    }

    if (term.trim().length < 2) {
      return;
    }

    productSearchTimer = setTimeout(() => {
      runRemoteProductSearch(term.trim());
    }, 220);
  });
  productEl?.addEventListener("change", syncAmount);
  quantityEl?.addEventListener("input", syncAmount);
  renderProductOptions("");
  toggleMobileMoneyFields();
  syncAmount();
  loadProductsFallback();
}

function showAddProductModal() {
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
        const optionsMarkup = (field.options || [])
          .map((option) => {
            const optionValue = escapeHtml(option.value || "");
            const optionLabel = escapeHtml(option.label || option.value || "");
            return `<option value="${optionValue}">${optionLabel}</option>`;
          })
          .join("");

        return `
                <div class="form-group">
                    <label>${field.label}</label>
                    <select name="${field.name}" ${field.required ? "required" : ""}>
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

      return `
            <div class="form-group">
                <label>${field.label}</label>
                <input type="${field.type || "text"}" name="${field.name}" placeholder="${field.placeholder || ""}" ${field.required ? "required" : ""}>
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

  const pageOptions = pages
    .map((page) => {
      const key = escapeHtml(page.key || "");
      const title = escapeHtml(page.title || page.key || "");
      const currentValue = Object.prototype.hasOwnProperty.call(
        userOverrides,
        page.key,
      )
        ? userOverrides[page.key]
          ? "allow"
          : "deny"
        : "default";

      return `
        <div class="form-group" style="margin-bottom:10px;">
            <label style="display:flex; justify-content:space-between; gap:8px; align-items:center;">
                <span>${title}</span>
                <select name="permission_mode[${key}]" style="max-width:160px;">
                    <option value="default" ${currentValue === "default" ? "selected" : ""}>Role Default</option>
                    <option value="allow" ${currentValue === "allow" ? "selected" : ""}>Allow</option>
                    <option value="deny" ${currentValue === "deny" ? "selected" : ""}>Deny</option>
                </select>
            </label>
            <input type="hidden" name="page_keys[]" value="${key}">
        </div>
      `;
    })
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
      <p style="margin:0 0 10px 0; color:#6B7280;">Set page access overrides for this user. Role Default keeps standard role permissions.</p>
      <div style="max-height:320px; overflow:auto; padding-right:6px;">
        ${pageOptions}
      </div>
    </form>
  `;

  openModal("Custom Permissions", content, [
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

function openAddInvoiceModal() {
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
        label: "Customer ID",
        name: "customer_id",
        type: "number",
        required: true,
        placeholder: "Enter customer ID",
      },
      {
        label: "Amount (Tsh)",
        name: "amount",
        type: "number",
        required: true,
        placeholder: "Enter amount",
      },
    ],
  });
}

function openAddDeliveryModal() {
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
        label: "Customer ID",
        name: "customer_id",
        type: "number",
        required: true,
        placeholder: "Enter customer ID",
      },
      {
        label: "Amount (Tsh)",
        name: "amount",
        type: "number",
        placeholder: "Enter amount",
      },
    ],
  });
}

function openAddReceivingModal() {
  openEntityModal({
    key: "receiving",
    page: "receiving",
    entity: "receiving",
    title: "Record Receiving",
    entityName: "Receiving",
    submitText: "Save Receiving",
    successMessage: "Receiving record saved successfully!",
    fields: [
      {
        label: "Supplier ID",
        name: "supplier_id",
        type: "number",
        required: true,
        placeholder: "Enter supplier ID",
      },
      {
        label: "Amount (Tsh)",
        name: "amount",
        type: "number",
        placeholder: "Enter amount",
      },
    ],
  });
}

function openAddQuotationModal() {
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
        label: "Customer ID",
        name: "customer_id",
        type: "number",
        required: true,
        placeholder: "Enter customer ID",
      },
      {
        label: "Amount (Tsh)",
        name: "amount",
        type: "number",
        required: true,
        placeholder: "Enter amount",
      },
    ],
  });
}

function openAddPOModal() {
  openEntityModal({
    key: "purchaseOrder",
    page: "purchase-orders",
    entity: "purchase_order",
    title: "Create Purchase Order",
    entityName: "Purchase Order",
    submitText: "Create PO",
    successMessage: "Purchase order created successfully!",
    fields: [
      {
        label: "Supplier ID",
        name: "supplier_id",
        type: "number",
        required: true,
        placeholder: "Enter supplier ID",
      },
      {
        label: "Amount (Tsh)",
        name: "amount",
        type: "number",
        required: true,
        placeholder: "Enter amount",
      },
    ],
  });
}

function openAddReturnModal() {
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
        label: "Product ID",
        name: "product_id",
        type: "number",
        required: true,
        placeholder: "Enter product ID",
      },
      {
        label: "Quantity",
        name: "quantity",
        type: "number",
        required: true,
        placeholder: "Enter quantity",
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
        label: "Customer ID",
        name: "customer_id",
        type: "number",
        required: true,
        placeholder: "Enter customer ID",
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
    { text: "Print Report", class: "btn-secondary", onclick: "printReport()" },
    {
      text: "Close Day",
      class: "btn-primary",
      onclick: 'closeModal(); showToast("success", "Day closed successfully!")',
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

  rows.forEach((row) => {
    const text = (row.textContent || "").toLowerCase();
    const stockStatus = (row.dataset.stock || "").toLowerCase();
    const matchesSearch = searchTerm === "" || text.includes(searchTerm);
    const matchesStock = stockFilter === "all" || stockStatus === stockFilter;

    row.style.display = matchesSearch && matchesStock ? "" : "none";
  });
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
  productSearch?.addEventListener("input", () => {
    filterTable("productTable", productSearch.value);
  });
}

function initCustomerDebtFilters() {
  const filterEl = document.getElementById("customerDebtStatusFilter");
  const table = document.getElementById("customerDebtTable");
  if (!filterEl || !table) {
    return;
  }

  const applyFilter = () => {
    const selected = (filterEl.value || "all").toLowerCase();
    const rows = table.querySelectorAll("tbody tr");

    rows.forEach((row) => {
      const status = (
        row.getAttribute("data-credit-status") || ""
      ).toLowerCase();

      if (!status) {
        row.style.display = selected === "all" ? "" : "none";
        return;
      }

      if (selected === "all") {
        row.style.display = "";
        return;
      }

      row.style.display = status === selected ? "" : "none";
    });
  };

  filterEl.addEventListener("change", applyFilter);
  applyFilter();
}

function initSalesPage() {
  if ((APP_CONFIG.currentPage || "").toLowerCase() !== "sales") {
    return;
  }

  const productsGrid = document.getElementById("salesProductsGrid");
  const searchInput = document.getElementById("salesProductSearch");
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
                            body{font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; padding:16px; color:#111;}
                            .sales-receipt-sheet{max-width:360px; margin:0 auto;}
                            .sales-receipt-brand{text-align:center; font-size:32px; font-weight:800; margin-bottom:4px;}
                            .sales-receipt-subtitle,.sales-receipt-time{text-align:center; color:#555; font-size:12px;}
                            .sales-receipt-meta{margin-top:14px; border-top:1px dashed #bbb; border-bottom:1px dashed #bbb; padding:10px 0;}
                            .sales-receipt-totals{margin-top:14px; border-top:1px dashed #bbb; padding-top:10px;}
                            .sales-receipt-meta div,.sales-receipt-line,.sales-receipt-totals div{display:flex; justify-content:space-between; margin-bottom:8px; font-size:13px;}
                            .sales-receipt-items{margin-top:10px;}
                            .sales-receipt-line{align-items:flex-start; border-bottom:1px dashed #ddd; padding:6px 0; margin-bottom:0;}
                            .sales-receipt-line:last-child{border-bottom:none;}
                            .sales-receipt-line-main{display:flex; flex-direction:column; gap:2px;}
                            .sales-receipt-line-main small{color:#666; font-size:11px;}
                            .sales-receipt-total{font-weight:800; font-size:18px;}
                            .sales-receipt-thanks{text-align:center; margin-top:14px; font-size:12px;}
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
                    <select id="checkoutCustomerSelect" name="customer_id" required>${customerOptions}</select>
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
    const customerSelect = document.getElementById("checkoutCustomerSelect");
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
      if (creditDueDateInput) creditDueDateInput.required = isCredit;
      if (mobilePhoneInput) mobilePhoneInput.required = isMobile && !isCredit;

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

    paymentGrid?.addEventListener("click", (event) => {
      const tile = event.target.closest(".sales-payment-tile");
      if (!tile) {
        return;
      }
      setGateway(tile.getAttribute("data-gateway") || "cash");
    });

    discountInput?.addEventListener("input", recalcCheckoutTotals);

    form?.addEventListener("submit", () => {
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = "Processing...";
      }
    });

    setGateway(defaultGateway);
    recalcCheckoutTotals();
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

  searchInput?.addEventListener("input", function () {
    const term = (searchInput.value || "").toLowerCase().trim();
    const cards = productsGrid.querySelectorAll(".sales-product-card");

    cards.forEach((card) => {
      const blob = (
        card.getAttribute("data-product-search") || ""
      ).toLowerCase();
      card.style.display = term === "" || blob.includes(term) ? "" : "none";
    });
  });

  clearBtn.addEventListener("click", function () {
    cart.clear();
    renderCart();
  });

  chargeBtn.addEventListener("click", openCheckoutModal);

  renderCart();

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

window.filterTable = filterTable;
window.filterByStock = filterByStock;
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
  const content = `
        <div style="text-align: center; padding: 20px 0;">
            <i class="fa-solid fa-trash" style="font-size: 48px; color: #EF4444; margin-bottom: 16px;"></i>
            <p style="margin: 0; font-size: 16px;">Are you sure you want to delete this product?</p>
            <p style="margin: 8px 0 0 0; font-size: 13px; color: #6B7280;">This action cannot be undone.</p>
        </div>
    `;
  openModal("Confirm Delete", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: "Delete",
      class: "btn-danger",
      onclick: "confirmDeleteProduct(" + id + ")",
    },
  ]);
}

function confirmDeleteProduct(id) {
  closeModal();
  showToast("success", "Product deleted successfully!");
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

function deleteCustomer(id) {
  const content = `
        <div style="text-align: center; padding: 20px 0;">
            <i class="fa-solid fa-user-minus" style="font-size: 48px; color: #EF4444; margin-bottom: 16px;"></i>
            <p style="margin: 0; font-size: 16px;">Are you sure you want to delete this customer?</p>
        </div>
    `;
  openModal("Confirm Delete", content, [
    { text: "Cancel", class: "btn-secondary", onclick: "closeModal()" },
    {
      text: "Delete",
      class: "btn-danger",
      onclick: 'closeModal(); showToast("success", "Customer deleted!")',
    },
  ]);
}

function printCustomerStatement(id) {
  const customerId = Number.parseInt(id, 10);
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

function printReport() {
  window.open("export_report_pdf.php?type=daily", "_blank", "noopener");
  showToast("success", "Exporting Daily Report PDF...");
}

function generateReport(type) {
  const names = {
    daily: "Daily Sales",
    weekly: "Weekly Sales",
    monthly: "Monthly Sales",
    inventory: "Inventory",
    customers: "Customer",
    profit: "Profit & Loss",
  };
  if (!names[type]) {
    showToast("error", "Invalid report type");
    return;
  }

  window.open(
    `export_report_pdf.php?type=${encodeURIComponent(type)}`,
    "_blank",
    "noopener",
  );
  if (getCurrentLanguage() === "sw") {
    const localized = translateValue(names[type], "sw");
    showToast("success", `Inahamisha PDF ya Ripoti ya ${localized}...`);
  } else {
    showToast("success", `Exporting ${names[type]} Report PDF...`);
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
