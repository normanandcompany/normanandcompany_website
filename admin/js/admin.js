// =========================================
// DYNAMIC PAGE LOADER UX
// =========================================
async function loadPage(pageName) {

    try {
        const response = await fetch(
            `/admin/pages/${pageName}.php`,
            {
                cache: 'no-store'
            }
        );
        const content = await response.text();

        document.getElementById('content').innerHTML = content;

        /* =========================================
           PAGE-SPECIFIC LOADERS
        ========================================= */

        switch (pageName) {

            // Page loader for home/dashboard data
            case 'home':
                loadDashboard();
                break; 

            // Page loader for product data
            case 'products':
                loadProducts();
                break;

            // Page loader for user data
            case 'users':
                loadUsers();
                break;

            // Page loader for the complimentary book customer chooser
            case 'bookchooser':
                loadBookChooser();
                break;

            // Page loader for task data
            case 'tasks':
                loadTasks();
                break;

            // Page loader for transaction data
            case 'transactions':
                loadTransactions();
                break;

            // Page loader for blog post data
            case 'blogposts':
                loadBlogPosts();
                break;

            case 'news':
                initNewsAdmin();
                break;

            case 'emailtools':
                initEmailTools();
                break;

            case 'downloads':
                initDownloadManager();
                break;

            // Page loader for resorts data
            case 'resorts':
                loadResorts();
                break;

            // Page loader for destination data
            case 'destinations':
                loadDestinations();
                break;

            // Page loaders for future pages
            // case 'travelstore':
            //     loadProducts();
            //     break;

            default:
                break;
        }

        // Update document title
        updatePageTitle();

        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });

    } catch (error) {
        console.error('Error loading page:', error);
    }
}

// =========================================
// FORM VALIDATION UX
// =========================================

function validateEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

// =========================================
// INITIAL PAGE LOADER UX
// =========================================

window.addEventListener('DOMContentLoaded', () => {
    loadPage('home');
});

// Update title based on metadata
function updatePageTitle() {
    const meta = document.getElementById('page-title-meta');
    if (meta && meta.dataset.title) {
        document.title = meta.dataset.title;
    } else {
        // Fallback
        document.title = "Norman and Company | Caribbean Travel";
    }
}

// =========================================
// BOOK CHOOSER UX
// =========================================

function loadBookChooser() {
    const chooseButton = document.getElementById('chooseBookRecipientBtn');

    if (!chooseButton) return;

    chooseButton.addEventListener('click', chooseRandomBookCustomer);
}

async function chooseRandomBookCustomer() {
    const chooseButton = document.getElementById('chooseBookRecipientBtn');
    const alert = document.getElementById('bookChooserAlert');
    const emptyState = document.getElementById('bookChooserEmpty');
    const recipientCard = document.getElementById('bookRecipientCard');

    if (!chooseButton || !alert || !emptyState || !recipientCard) return;

    chooseButton.disabled = true;
    chooseButton.textContent = 'Choosing...';
    alert.hidden = true;

    try {
        const response = await fetch('/admin/api/getRandomBookCustomer.php', {
            method: 'POST',
            cache: 'no-store',
            headers: {
                'Accept': 'application/json'
            }
        });
        const result = await response.json();

        if (!response.ok || !result.success || !result.customer) {
            throw new Error(result.message || 'Unable to choose a customer.');
        }

        displayBookRecipient(result.customer);
        emptyState.hidden = true;
        recipientCard.hidden = false;
        chooseButton.textContent = 'Choose Again';
        alert.textContent = result.message || 'Winner selected and drawing recorded.';
        alert.classList.remove('is-error');
        alert.classList.add('is-success');
        alert.hidden = false;
    } catch (error) {
        alert.textContent = error.message || 'Unable to choose a customer. Please try again.';
        alert.classList.remove('is-success');
        alert.classList.add('is-error');
        alert.hidden = false;
        chooseButton.textContent = recipientCard.hidden ? 'Choose a Customer' : 'Choose Again';
    } finally {
        chooseButton.disabled = false;
    }
}

function displayBookRecipient(customer) {
    const fullName = String(customer.full_name || '').trim() || 'Customer';
    const email = String(customer.email_address || '').trim();
    const phone = String(customer.phone || '').trim();
    const addressLines = [
        customer.address_1,
        customer.address_2,
        [customer.city, customer.state_province, customer.postal_code]
            .map(value => String(value || '').trim())
            .filter(Boolean)
            .join(', ')
            .replace(/,\s(?=[^,]+$)/, ' '),
        customer.country
    ]
        .map(value => String(value || '').trim())
        .filter(Boolean);

    document.getElementById('bookRecipientName').textContent = fullName;
    document.getElementById('bookRecipientId').textContent = String(customer.id || 'N/A');
    document.getElementById('bookRecipientSince').textContent = formatBookChooserDate(customer.created_at);
    document.getElementById('bookRecipientAge').textContent = Number.isFinite(Number(customer.age))
        ? `${Number(customer.age)} years old`
        : 'N/A';
    document.getElementById('bookRecipientDrawingDate').textContent = formatBookChooserDate(
        customer.sweepstakes_won_date
    );

    const emailLink = document.getElementById('bookRecipientEmail');
    emailLink.textContent = email || 'Not provided';
    emailLink.removeAttribute('target');
    if (email) {
        emailLink.href = `mailto:${email}`;
    } else {
        emailLink.removeAttribute('href');
    }

    const phoneLink = document.getElementById('bookRecipientPhone');
    phoneLink.textContent = phone || 'Not provided';
    if (phone) {
        phoneLink.href = `tel:${phone.replace(/[^\d+]/g, '')}`;
    } else {
        phoneLink.removeAttribute('href');
    }

    const address = document.getElementById('bookRecipientAddress');
    address.replaceChildren();

    if (addressLines.length === 0) {
        address.textContent = 'No shipping address on file.';
        return;
    }

    addressLines.forEach((line, index) => {
        if (index > 0) {
            address.appendChild(document.createElement('br'));
        }

        address.appendChild(document.createTextNode(line));
    });
}

function formatBookChooserDate(value) {
    if (!value) return 'N/A';

    const parsed = new Date(String(value).replace(' ', 'T'));

    if (Number.isNaN(parsed.getTime())) return String(value);

    return new Intl.DateTimeFormat('en-US', {
        month: 'long',
        day: 'numeric',
        year: 'numeric'
    }).format(parsed);
}

// =========================================
// CRUISELINES CARD UX
// =========================================

async function loadCruiseLines() {
    // Get the HTML container where cards will be inserted
    const container = document.getElementById('cruiseLinesContainer');

    // Stop if container does not exist
    if (!container) return;

    try {

        // Fetch the JSON file
        const response = await fetch('/cruiselines/cruiselines.json');

        // Check for errors
        if (!response.ok) {
            throw new Error(`HTTP Error: ${response.status}`);
        }

        // Convert response to JSON
        const cruiseLines = await response.json();

        // Clear loading message
        container.innerHTML = '';

        // Loop through each cruise line
        cruiseLines.forEach(cruise => { 

            // Create card element
            const card = document.createElement('div');
            card.classList.add('card');

            // Build card HTML
            card.innerHTML = `
                <h3>${cruise.title}</h3>
                <p>${cruise.description}</p>
                `;

                // Add card to container
                container.appendChild(card);
    
            });

        } catch (error) {

            console.error('Error loading cruise lines:', error);

            // Show user-friendly error
            container.innerHTML = `
                <div class="card">
                    <h3>Error</h3>
                    <p>Unable to load cruise line data.</p>
                </div>
            `;
        }  

    }
    
// =========================================
// RESORTS CARD UX
// =========================================

async function loadResorts() {
    // Get the HTML container where cards will be inserted
    const container = document.getElementById('resortsContainer');

    // Stop if container does not exist
    if (!container) return;

    try {

        // Fetch the JSON file
        const response = await fetch('/resorts/resorts.json');

        // Check for errors
        if (!response.ok) {
            throw new Error(`HTTP Error: ${response.status}`);
        }

        // Convert response to JSON
        const resorts = await response.json();

        // Clear loading message
        container.innerHTML = '';

        // Loop through each resort
        resorts.forEach(resort => { 

            // Create card element
            const card = document.createElement('div');
            card.classList.add('card');

            // Build card HTML
            card.innerHTML = `
                <h3>${resort.title}</h3>
                <p><strong class="highlight-strong">${resort.location}</strong></p>
                <p>${resort.description}</p>
                `;

                // Add card to container
                container.appendChild(card);
    
            });

        } catch (error) {

            console.error('Error loading resorts:', error);

            // Show user-friendly error
            container.innerHTML = `
                <div class="card">
                    <h3>Error</h3>
                    <p>Unable to load resort data.</p>
                </div>
            `;
        }  

    }

// =========================================
// DESTINATIONS CARD UX
// =========================================

async function loadDestinations() {
    // Get the HTML container where cards will be inserted
    const container = document.getElementById('destinationsContainer');

    // Stop if container does not exist
    if (!container) return;

    try {

        // Fetch the JSON file
        const response = await fetch('/destinations/destinations.json');

        // Check for errors
        if (!response.ok) {
            throw new Error(`HTTP Error: ${response.status}`);
        }

        // Convert response to JSON
        const destinations = await response.json();

        // Clear loading message
        container.innerHTML = '';

        // Loop through each destination
        destinations.forEach(destination => { 

            // Create card element
            const card = document.createElement('div');
            card.classList.add('card');

            // Build card HTML
            card.innerHTML = `
                <h3>${destination.location}</h3>
                <p><strong class="highlight-strong">${destination.country}</strong></p>
                <p>${destination.description}</p>
                `;

                // Add card to container
                container.appendChild(card);
    
            });

        } catch (error) {

            console.error('Error loading destinations:', error);

            // Show user-friendly error
            container.innerHTML = `
                <div class="card">
                    <h3>Error</h3>
                    <p>Unable to load destination data.</p>
                </div>
            `;
        }  

    }

// =========================================
// DASHBOARD UX
// =========================================

async function loadDashboard() {

    initDashboardTabs();

    try {

        const response = await fetch('/admin/api/getDashboard.php');
        const data = await response.json();
        const dashboard = Array.isArray(data) ? data[0] : null;

        if (!response.ok || !dashboard) {
            throw new Error(data?.message || 'Unable to load dashboard data.');
        }

        renderDashboardFields(dashboard);
        renderDashboardProductInfoLists(dashboard);
        renderDashboardProductViewLists(dashboard);
        renderDashboardReviewProductList(dashboard);
        renderDashboardReviewTable(dashboard);
        renderDashboardContacts(dashboard);

    } catch (err) {

        console.error(err);

    }

    initCloudflareAnalyticsDashboard();
}

function renderDashboardFields(dashboard) {
    document
        .querySelectorAll('[data-field]')
        .forEach(element => {

            const field = element.dataset.field;

            if (Object.prototype.hasOwnProperty.call(dashboard, field)) {
                element.textContent = formatDashboardFieldValue(dashboard[field], element.dataset.format);
            }

        });
}

function formatDashboardFieldValue(value, format) {
    if (format === 'rating') {
        return `${formatDashboardRatingNumber(value)} / 5`;
    }

    if (format === 'currency') {
        return formatDashboardCurrency(value);
    }

    if (format === 'percent') {
        return `${formatDashboardPercentNumber(value)}%`;
    }

    if (format === 'integer') {
        return formatDashboardInteger(value);
    }

    return value ?? '0';
}

function formatDashboardRatingNumber(value) {
    const rating = Number.parseFloat(value);

    if (!Number.isFinite(rating)) {
        return '0.0';
    }

    return rating.toFixed(1);
}

function formatDashboardCurrency(value) {
    const amount = Number.parseFloat(value);

    if (!Number.isFinite(amount)) {
        return '$0.00';
    }

    return amount.toLocaleString('en-US', {
        style: 'currency',
        currency: 'USD'
    });
}

function formatDashboardPercentNumber(value) {
    const percent = Number.parseFloat(value);

    if (!Number.isFinite(percent)) {
        return '0.0';
    }

    return percent.toLocaleString('en-US', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1
    });
}

function formatDashboardInteger(value) {
    const number = Number.parseInt(value ?? 0, 10);

    if (!Number.isFinite(number)) {
        return '0';
    }

    return number.toLocaleString('en-US');
}

function renderDashboardProductInfoLists(dashboard) {
    document
        .querySelectorAll('[data-product-info-list-field]')
        .forEach((list) => {
            const field = list.dataset.productInfoListField;
            const rows = Array.isArray(dashboard?.[field]) ? dashboard[field] : [];
            const emptyMessage = list.dataset.emptyMessage || 'No product data recorded.';

            if (rows.length === 0) {
                list.innerHTML = `<li>${adminEscapeHtml(emptyMessage)}</li>`;
                return;
            }

            list.innerHTML = rows.map((row) => {
                if (field === 'low_inventory_products') {
                    return renderDashboardInventoryListItem(row);
                }

                return renderDashboardSalesListItem(row);
            }).join('');
        });
}

function renderDashboardSalesListItem(row) {
    const productName = adminEscapeHtml(row.product_name || 'Unnamed product');
    const soldQuantity = Number.parseInt(row.sold_quantity ?? 0, 10);
    const soldLabel = soldQuantity === 1 ? 'unit sold' : 'units sold';

    return `<li>${productName} (${formatDashboardInteger(soldQuantity)} ${soldLabel})</li>`;
}

function renderDashboardInventoryListItem(row) {
    const productName = adminEscapeHtml(row.product_name || 'Unnamed product');
    const inventoryCount = Number.parseInt(row.inventory_count ?? 0, 10);
    const stockLabel = inventoryCount === 1 ? 'in stock' : 'in stock';
    const sku = String(row.sku || '').trim();
    const skuText = sku ? ` | SKU ${adminEscapeHtml(sku)}` : '';

    return `<li>${productName} (${formatDashboardInteger(inventoryCount)} ${stockLabel}${skuText})</li>`;
}

function renderDashboardProductViewLists(dashboard) {
    document
        .querySelectorAll('[data-list-field]')
        .forEach((list) => {
            const field = list.dataset.listField;
            const rows = Array.isArray(dashboard?.[field]) ? dashboard[field] : [];

            if (rows.length === 0) {
                list.innerHTML = '<li>No product views recorded.</li>';
                return;
            }

            list.innerHTML = rows.map((row) => {
                const productName = adminEscapeHtml(row.product_name || 'Unnamed product');
                const viewCount = Number.parseInt(row.view_count ?? 0, 10);
                const suffix = viewCount === 1 ? 'view' : 'views';

                return `<li>${productName} (${viewCount} ${suffix})</li>`;
            }).join('');
        });
}

function renderDashboardReviewProductList(dashboard) {
    const list = document.querySelector('[data-review-list-field="top_reviewed_products"]');

    if (!list) return;

    const rows = Array.isArray(dashboard?.top_reviewed_products)
        ? dashboard.top_reviewed_products
        : [];

    if (rows.length === 0) {
        list.innerHTML = '<li class="dashboard-review-empty">No product reviews recorded.</li>';
        return;
    }

    list.innerHTML = rows.map((row) => {
        const productName = adminEscapeHtml(row.product_name || 'Unnamed product');
        const reviewCount = Number.parseInt(row.review_count ?? 0, 10);
        const averageRating = formatDashboardRatingNumber(row.average_rating);
        const latestDate = formatDate(row.latest_reviewed_at);
        const countLabel = reviewCount === 1 ? 'review' : 'reviews';

        return `
            <li>
                <div>
                    <strong>${productName}</strong>
                    <small>Last reviewed ${latestDate}</small>
                </div>
                <span>${reviewCount} ${countLabel}</span>
                <small>${averageRating} / 5 avg</small>
            </li>
        `;
    }).join('');
}

function renderDashboardReviewTable(dashboard) {
    const tbody = document.getElementById('dashboardProductReviewsBody');

    if (!tbody) return;

    const rows = Array.isArray(dashboard?.recent_product_reviews)
        ? dashboard.recent_product_reviews
        : [];

    if (rows.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="product-empty-state">
                    No product reviews recorded.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = rows.map((review) => {
        const title = String(review.review_title || '').trim() || 'Untitled review';
        const content = dashboardReviewExcerpt(review.review_content);
        const productName = review.product_name || `Product #${review.product_id || 'N/A'}`;
        const customerName = String(review.customer_name || '').trim();
        const customerEmail = String(review.customer_email || '').trim();
        const fallbackCustomer = review.user_id ? `User #${review.user_id}` : 'N/A';
        const displayCustomer = customerName || customerEmail || fallbackCustomer;
        const customerDetail = customerName && customerEmail ? customerEmail : '';
        const approvalBadge = Number(review.is_approved) === 1
            ? '<span class="status-badge active">Approved</span>'
            : '<span class="status-badge warning">Pending</span>';
        const visibilityBadge = Number(review.visible) === 1
            ? '<span class="status-badge active">Visible</span>'
            : '<span class="status-badge muted">Hidden</span>';

        return `
            <tr>
                <td>
                    <div class="dashboard-review-title-cell">
                        <strong>${adminEscapeHtml(title)}</strong>
                        ${content ? `<small>${adminEscapeHtml(content)}</small>` : ''}
                    </div>
                </td>
                <td>${adminEscapeHtml(productName)}</td>
                <td>
                    <div class="dashboard-review-customer-cell">
                        <strong>${adminEscapeHtml(displayCustomer)}</strong>
                        ${customerDetail ? `<small>${adminEscapeHtml(customerDetail)}</small>` : ''}
                    </div>
                </td>
                <td>${renderDashboardRatingPill(review.rating)}</td>
                <td>
                    <div class="status-stack">
                        ${approvalBadge}
                        ${visibilityBadge}
                    </div>
                </td>
                <td>${formatDate(review.created_at)}</td>
            </tr>
        `;
    }).join('');
}

function dashboardReviewExcerpt(value) {
    const text = String(value || '').trim().replace(/\s+/g, ' ');

    if (text.length <= 120) {
        return text;
    }

    return `${text.slice(0, 117)}...`;
}

function renderDashboardRatingPill(value) {
    const rating = Number.parseInt(value ?? '', 10);

    if (!Number.isFinite(rating) || rating <= 0) {
        return '<span class="dashboard-review-rating muted">N/A</span>';
    }

    return `<span class="dashboard-review-rating">${rating}/5</span>`;
}

function renderDashboardContacts(dashboard) {
    const tbody = document.getElementById('dashboardContactsBody');

    if (!tbody) return;

    const rows = Array.isArray(dashboard?.contacts) ? dashboard.contacts : [];

    if (rows.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="4" class="product-empty-state">
                    No contact form submissions recorded.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = rows.map((contact) => {
        const fullName = String(contact.full_name || '').trim() || 'Name not provided';
        const subject = String(contact.subject || '').trim() || 'No subject';
        const message = String(contact.message || '').trim() || 'No message provided.';
        const email = String(contact.email_address || '').trim();
        const phone = String(contact.phone_number || '').trim();
        const emailLink = email
            ? `<a href="mailto:${encodeURIComponent(email)}">${adminEscapeHtml(email)}</a>`
            : '<span class="dashboard-contact-unavailable">No email provided</span>';
        const phoneLink = phone
            ? `<a href="tel:${encodeURIComponent(phone)}">${adminEscapeHtml(phone)}</a>`
            : '<span class="dashboard-contact-unavailable">No phone provided</span>';

        return `
            <tr>
                <td>
                    <strong class="dashboard-contact-name">${adminEscapeHtml(fullName)}</strong>
                </td>
                <td>
                    <div class="dashboard-contact-message">
                        <strong>${adminEscapeHtml(subject)}</strong>
                        <p>${adminEscapeHtml(message)}</p>
                    </div>
                </td>
                <td>
                    <div class="dashboard-contact-details">
                        ${emailLink}
                        ${phoneLink}
                    </div>
                </td>
                <td>${formatDashboardDateTime(contact.created_at)}</td>
            </tr>
        `;
    }).join('');
}

function formatDashboardDateTime(value) {
    if (!value) return 'N/A';

    const normalizedValue = String(value).includes('T')
        ? String(value)
        : String(value).replace(' ', 'T');
    const date = new Date(normalizedValue);

    if (Number.isNaN(date.getTime())) return 'N/A';

    return date.toLocaleString('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short'
    });
}

function initDashboardTabs() {
    const tabList = document.querySelector('.dashboard-tabs');

    if (!tabList || tabList.dataset.bound === 'true') return;

    tabList.dataset.bound = 'true';

    tabList.addEventListener('click', (event) => {
        const button = event.target.closest('[data-dashboard-tab]');

        if (!button) return;

        activateDashboardTab(button.dataset.dashboardTab);
    });

    tabList.addEventListener('keydown', (event) => {
        if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;

        const tabs = Array.from(tabList.querySelectorAll('[data-dashboard-tab]'));
        const currentIndex = tabs.findIndex((tab) => tab.getAttribute('aria-selected') === 'true');
        let nextIndex = currentIndex;

        if (event.key === 'ArrowRight') {
            nextIndex = currentIndex >= tabs.length - 1 ? 0 : currentIndex + 1;
        } else if (event.key === 'ArrowLeft') {
            nextIndex = currentIndex <= 0 ? tabs.length - 1 : currentIndex - 1;
        } else if (event.key === 'Home') {
            nextIndex = 0;
        } else if (event.key === 'End') {
            nextIndex = tabs.length - 1;
        }

        const nextTab = tabs[nextIndex];

        if (!nextTab) return;

        event.preventDefault();
        nextTab.focus();
        activateDashboardTab(nextTab.dataset.dashboardTab);
    });
}

function activateDashboardTab(tabName) {
    if (!tabName) return;

    document.querySelectorAll('[data-dashboard-tab]').forEach((button) => {
        const isActive = button.dataset.dashboardTab === tabName;
        button.classList.toggle('active', isActive);
        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    document.querySelectorAll('[data-dashboard-panel]').forEach((panel) => {
        const isActive = panel.dataset.dashboardPanel === tabName;
        panel.classList.toggle('active', isActive);
        panel.hidden = !isActive;
    });

    if (tabName === 'site-info') {
        Object.values(cloudflareAnalyticsState.charts).forEach((chart) => {
            if (chart && typeof chart.resize === 'function') {
                chart.resize();
            }
        });
    }
}

const productImageFields = [
    'image_url',
    'image_url2',
    'image_url3',
    'image_url4',
    'image_url5',
    'image_url6',
    'image_url7',
    'image_url8',
    'image_url9',
    'image_url10'
];

const productManagerState = {
    products: [],
    filteredProducts: [],
    categories: [],
    bookFormats: [],
    currentPage: 1,
    perPage: 10
};

const userManagerState = {
    users: [],
    filteredUsers: [],
    roles: [],
    states: [],
    currentPage: 1,
    perPage: 10
};

const downloadManagerState = {
    downloads: [],
    filteredDownloads: [],
    metrics: {}
};

const taskManagerState = {
    tasks: [],
    filteredTasks: [],
    users: [],
    currentPage: 1,
    perPage: 10
};

const transactionManagerState = {
    transactions: [],
    filteredTransactions: [],
    statuses: [],
    types: [],
    currentPage: 1,
    perPage: 10
};

const blogImageFields = [
    'featured_image_url',
    'image2_url',
    'image3_url',
    'image4_url',
    'image5_url'
];

const blogManagerState = {
    posts: [],
    filteredPosts: [],
    categories: [],
    currentPage: 1,
    perPage: 10,
    editorMode: 'visual'
};

const cloudflareAnalyticsState = {
    charts: {}
};

function adminEscapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function formatCurrency(value) {
    const number = Number.parseFloat(value);

    if (!Number.isFinite(number)) {
        return '$0.00';
    }

    return `$${number.toFixed(2)}`;
}

function calculateMarginFromValues(costValue, priceValue) {
    const price = Number.parseFloat(priceValue);
    const cost = Number.parseFloat(costValue);

    if (!Number.isFinite(price) || !Number.isFinite(cost) || price <= 0) {
        return 'N/A';
    }

    return `${(((price - cost) / price) * 100).toFixed(1)}%`;
}

function calculateMargin(product) {
    return calculateMarginFromValues(product?.cost, product?.price);
}

function formatDate(dateString) {
    if (!dateString) {
        return 'N/A';
    }

    const date = new Date(dateString);

    if (Number.isNaN(date.getTime())) {
        return 'N/A';
    }

    return date.toLocaleDateString('en-US');
}

async function fetchAdminJson(url, options = {}) {
    const response = await fetch(url, {
        cache: 'no-store',
        ...options
    });
    const data = await response.json().catch(() => null);

    if (data === null) {
        throw new Error('Unexpected response from server.');
    }

    if (!response.ok || data?.success === false) {
        throw new Error(data?.message || `HTTP Error: ${response.status}`);
    }

    return data;
}

function initCloudflareAnalyticsDashboard() {
    const dashboard = document.getElementById('cloudflareAnalyticsDashboard');

    if (!dashboard) return;

    const form = document.getElementById('cloudflareAnalyticsForm');
    const range = document.getElementById('cloudflareAnalyticsRange');
    const customRange = document.getElementById('cloudflareCustomRange');
    const startDate = document.getElementById('cloudflareStartDate');
    const endDate = document.getElementById('cloudflareEndDate');

    if (!form || !range) return;

    const today = new Date();
    const priorWeek = new Date(today);
    priorWeek.setDate(today.getDate() - 6);

    if (startDate && !startDate.value) {
        startDate.value = cloudflareDateInputValue(priorWeek);
    }

    if (endDate && !endDate.value) {
        endDate.value = cloudflareDateInputValue(today);
    }

    const toggleCustomRange = () => {
        if (customRange) {
            customRange.hidden = range.value !== 'custom';
        }
    };

    toggleCustomRange();

    if (dashboard.dataset.bound !== 'true') {
        dashboard.dataset.bound = 'true';

        range.addEventListener('change', () => {
            toggleCustomRange();
            loadCloudflareAnalyticsDashboard(false);
        });

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            loadCloudflareAnalyticsDashboard(document.getElementById('cloudflareForceRefresh')?.checked === true);
        });
    }

    loadCloudflareAnalyticsDashboard(false);
}

async function loadCloudflareAnalyticsDashboard(forceRefresh = false) {
    const dashboard = document.getElementById('cloudflareAnalyticsDashboard');

    if (!dashboard) return;

    const endpoint = dashboard.dataset.endpoint || '/admin/api/analytics/cloudflare-dashboard.php';
    const params = cloudflareAnalyticsParams(forceRefresh);
    const status = document.getElementById('cloudflareAnalyticsStatus');
    const submitButton = document.querySelector('#cloudflareAnalyticsForm button[type="submit"]');

    if (status) {
        status.textContent = 'Loading Cloudflare analytics...';
        status.dataset.type = 'loading';
    }

    if (submitButton) {
        submitButton.disabled = true;
    }

    try {
        const response = await fetch(`${endpoint}?${params.toString()}`, {
            cache: 'no-store',
            credentials: 'same-origin'
        });
        const payload = await response.json().catch(() => null);

        if (payload === null) {
            throw new Error('Unexpected response from analytics endpoint.');
        }

        if (!response.ok || payload.success === false) {
            throw new Error(payload.error || payload.message || `HTTP Error: ${response.status}`);
        }

        renderCloudflareAnalyticsDashboard(payload);
    } catch (error) {
        renderCloudflareAnalyticsError(error);
    } finally {
        if (submitButton) {
            submitButton.disabled = false;
        }
    }
}

function cloudflareAnalyticsParams(forceRefresh = false) {
    const form = document.getElementById('cloudflareAnalyticsForm');
    const params = new URLSearchParams();

    if (!form) {
        params.set('range', 'yesterday');
        return params;
    }

    const data = new FormData(form);
    const range = String(data.get('range') || 'yesterday');

    params.set('range', range);

    if (range === 'custom') {
        params.set('start_date', String(data.get('start_date') || ''));
        params.set('end_date', String(data.get('end_date') || ''));
    }

    if (forceRefresh) {
        params.set('force_refresh', '1');
    }

    return params;
}

function renderCloudflareAnalyticsDashboard(payload) {
    const data = payload?.data || {};
    const meta = payload?.meta || {};
    const warnings = Array.isArray(payload?.warnings) ? payload.warnings : [];

    renderCloudflareStatus(meta, data.status);
    renderCloudflareWarnings(warnings);
    renderCloudflareSummary(data.summary || {});
    renderCloudflareCharts(data.timeseries || {});
    renderCloudflareBreakdowns(data.breakdowns || {});
}

function renderCloudflareStatus(meta, dataStatus) {
    const status = document.getElementById('cloudflareAnalyticsStatus');

    if (!status) return;

    const parts = [];

    if (meta.start_date && meta.end_date) {
        parts.push(`${meta.start_date} to ${meta.end_date}`);
    }

    parts.push('Source: Cloudflare');

    if (meta.cached === true) {
        parts.push(meta.stale === true ? 'showing stale cache' : 'from cache');
    } else if (meta.last_refreshed) {
        parts.push(`refreshed ${formatCloudflareTimestamp(meta.last_refreshed)}`);
    }

    if (meta.grouping_interval) {
        parts.push(`${meta.grouping_interval} grouping`);
    }

    if (dataStatus === 'not_configured') {
        parts.push('not configured');
    }

    status.textContent = parts.join(' | ');
    status.dataset.type = dataStatus === 'not_configured' ? 'warning' : 'ready';
}

function renderCloudflareWarnings(warnings) {
    const container = document.getElementById('cloudflareAnalyticsWarnings');

    if (!container) return;

    clearElement(container);

    if (!warnings.length) {
        container.hidden = true;
        return;
    }

    const list = document.createElement('ul');

    warnings.forEach((warning) => {
        const item = document.createElement('li');
        item.textContent = String(warning);
        list.appendChild(item);
    });

    container.appendChild(list);
    container.hidden = false;
}

function renderCloudflareSummary(summary) {
    const grid = document.getElementById('cloudflareSummaryGrid');

    if (!grid) return;

    clearElement(grid);

    const cards = [
        {
            key: 'requests',
            title: 'Cloudflare Requests',
            format: 'number',
            info: 'HTTP requests handled by Cloudflare. This is not page views or people.'
        },
        {
            key: 'visits',
            title: 'Cloudflare Visits',
            format: 'number',
            info: 'Cloudflare visits are based on Cloudflare HTTP analytics and are not exact unique visitors.'
        },
        {
            key: 'unique_ips',
            title: 'Estimated Unique IPs',
            format: 'number',
            info: 'Unavailable unless Cloudflare exposes a supported unique IP estimate for this dataset.'
        },
        {
            key: 'bandwidth_bytes',
            title: 'Bandwidth',
            format: 'bytes',
            info: 'Bytes transferred at Cloudflare edge.'
        },
        {
            key: 'cache_hit_percentage',
            title: 'Cache Hit',
            format: 'percent',
            info: 'Cached requests divided by cache-status requests returned by Cloudflare.'
        },
        {
            key: 'cached_requests',
            title: 'Cached Requests',
            format: 'number',
            info: 'Requests with cache statuses treated as served from cache.'
        },
        {
            key: 'uncached_requests',
            title: 'Uncached Requests',
            format: 'number',
            info: 'Requests with cache statuses not treated as cache hits.'
        },
        {
            key: 'origin_requests',
            title: 'Origin Requests',
            format: 'number',
            info: 'Only shown when Cloudflare exposes a supported origin request metric.'
        },
        {
            key: 'security_events',
            title: 'Security Events',
            format: 'number',
            info: 'Firewall or security events returned by Cloudflare for the selected range.'
        },
        {
            key: 'blocked_requests',
            title: 'Blocked Actions',
            format: 'number',
            info: 'Cloudflare security events with block actions.'
        },
        {
            key: 'challenged_requests',
            title: 'Challenged Actions',
            format: 'number',
            info: 'Cloudflare security events with challenge actions.'
        }
    ];

    cards.forEach((card) => {
        grid.appendChild(createCloudflareSummaryCard(card, summary[card.key]));
    });
}

function createCloudflareSummaryCard(card, metric) {
    const element = document.createElement('div');
    element.className = 'cloudflare-summary-card';

    const title = document.createElement('h3');
    title.textContent = card.title;
    title.title = card.info;

    const value = document.createElement('span');
    value.className = 'cloudflare-summary-value';

    const note = document.createElement('small');

    if (metric?.status === 'available') {
        value.textContent = formatCloudflareMetric(metric.value, card.format);
        note.textContent = card.info;
    } else {
        value.textContent = 'Not available';
        value.dataset.state = 'not-available';
        note.textContent = metric?.reason || 'Not supported by this Cloudflare plan or dataset.';
    }

    element.appendChild(title);
    element.appendChild(value);
    element.appendChild(note);

    return element;
}

function renderCloudflareCharts(timeseries) {
    const points = Array.isArray(timeseries?.points) ? timeseries.points : [];
    const meta = document.getElementById('cloudflareTimeseriesMeta');

    if (meta) {
        meta.textContent = timeseries?.interval ? `${timeseries.interval} view` : '';
    }

    if (timeseries?.status !== 'available' || !points.length) {
        destroyCloudflareChart('requests');
        destroyCloudflareChart('cache');
        setCloudflareCanvasHidden('cloudflareRequestsChart', true);
        setCloudflareCanvasHidden('cloudflareCacheChart', true);
        setCloudflareFallback('cloudflareRequestsChartFallback', timeseries?.reason || 'No Cloudflare request data is available for this range.');
        setCloudflareFallback('cloudflareCacheChartFallback', 'Cache time-series data is not available for this range.');
        return;
    }

    hideCloudflareFallback('cloudflareRequestsChartFallback');
    renderCloudflareRequestsChart(points);

    const hasCacheSeries = points.some((point) => point.cached_requests !== null || point.uncached_requests !== null);

    if (hasCacheSeries) {
        hideCloudflareFallback('cloudflareCacheChartFallback');
        renderCloudflareCacheChart(points);
    } else {
        destroyCloudflareChart('cache');
        setCloudflareCanvasHidden('cloudflareCacheChart', true);
        setCloudflareFallback('cloudflareCacheChartFallback', 'Cache time-series data is not available for this Cloudflare plan or dataset.');
    }
}

function renderCloudflareRequestsChart(points) {
    if (typeof Chart === 'undefined') {
        destroyCloudflareChart('requests');
        setCloudflareCanvasHidden('cloudflareRequestsChart', true);
        setCloudflareFallback('cloudflareRequestsChartFallback', 'Chart.js is unavailable. Use the tables below for Cloudflare analytics.');
        return;
    }

    const canvas = document.getElementById('cloudflareRequestsChart');

    if (!canvas) return;

    setCloudflareCanvasHidden('cloudflareRequestsChart', false);
    destroyCloudflareChart('requests');

    cloudflareAnalyticsState.charts.requests = new Chart(canvas, {
        type: 'line',
        data: {
            labels: points.map((point) => point.label),
            datasets: [
                {
                    label: 'Requests',
                    data: points.map((point) => Number(point.requests || 0)),
                    borderColor: '#1687C9',
                    backgroundColor: 'rgba(22, 135, 201, 0.12)',
                    tension: 0.25,
                    fill: true,
                    yAxisID: 'requests'
                },
                {
                    label: 'Bandwidth MB',
                    data: points.map((point) => Number(point.bandwidth_bytes || 0) / (1024 * 1024)),
                    borderColor: '#D84B3D',
                    backgroundColor: 'rgba(216, 75, 61, 0.12)',
                    tension: 0.25,
                    fill: false,
                    yAxisID: 'bandwidth'
                }
            ]
        },
        options: cloudflareChartOptions('Requests', 'MB')
    });
}

function renderCloudflareCacheChart(points) {
    if (typeof Chart === 'undefined') {
        destroyCloudflareChart('cache');
        setCloudflareCanvasHidden('cloudflareCacheChart', true);
        setCloudflareFallback('cloudflareCacheChartFallback', 'Chart.js is unavailable. Cache totals remain available in the summary cards.');
        return;
    }

    const canvas = document.getElementById('cloudflareCacheChart');

    if (!canvas) return;

    setCloudflareCanvasHidden('cloudflareCacheChart', false);
    destroyCloudflareChart('cache');

    cloudflareAnalyticsState.charts.cache = new Chart(canvas, {
        type: 'bar',
        data: {
            labels: points.map((point) => point.label),
            datasets: [
                {
                    label: 'Cached',
                    data: points.map((point) => Number(point.cached_requests || 0)),
                    backgroundColor: 'rgba(42, 157, 143, 0.72)'
                },
                {
                    label: 'Uncached',
                    data: points.map((point) => Number(point.uncached_requests || 0)),
                    backgroundColor: 'rgba(244, 162, 97, 0.78)'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            },
            scales: {
                x: {
                    stacked: true,
                    ticks: {
                        maxRotation: 0,
                        autoSkip: true,
                        maxTicksLimit: 8
                    }
                },
                y: {
                    stacked: true,
                    beginAtZero: true
                }
            }
        }
    });
}

function cloudflareChartOptions(leftTitle, rightTitle) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false
        },
        plugins: {
            legend: {
                position: 'bottom'
            }
        },
        scales: {
            x: {
                ticks: {
                    maxRotation: 0,
                    autoSkip: true,
                    maxTicksLimit: 8
                }
            },
            requests: {
                type: 'linear',
                beginAtZero: true,
                position: 'left',
                title: {
                    display: true,
                    text: leftTitle
                }
            },
            bandwidth: {
                type: 'linear',
                beginAtZero: true,
                position: 'right',
                grid: {
                    drawOnChartArea: false
                },
                title: {
                    display: true,
                    text: rightTitle
                }
            }
        }
    };
}

function renderCloudflareBreakdowns(breakdowns) {
    const configs = {
        countries: { label: 'Country', value: 'requests' },
        status_codes: { label: 'Status', value: 'requests' },
        hostnames: { label: 'Hostname', value: 'requests' },
        paths: { label: 'Path', value: 'requests' },
        browsers: { label: 'Browser', value: 'requests' },
        devices: { label: 'Device', value: 'requests' },
        operating_systems: { label: 'Operating System', value: 'requests' },
        security_actions: { label: 'Action', value: 'events' }
    };

    Object.entries(configs).forEach(([key, config]) => {
        renderCloudflareBreakdownTable(key, breakdowns[key], config);
    });
}

function renderCloudflareBreakdownTable(key, dataset, config) {
    const container = document.querySelector(`[data-breakdown-table="${key}"]`);

    if (!container) return;

    clearElement(container);

    if (dataset?.status !== 'available') {
        const empty = document.createElement('div');
        empty.className = 'cloudflare-empty-state';
        empty.textContent = dataset?.reason || 'Not supported by this Cloudflare plan or dataset.';
        container.appendChild(empty);
        return;
    }

    const items = Array.isArray(dataset.items) ? dataset.items : [];

    if (!items.length) {
        const empty = document.createElement('div');
        empty.className = 'cloudflare-empty-state';
        empty.textContent = 'No Cloudflare data returned for this range.';
        container.appendChild(empty);
        return;
    }

    const table = document.createElement('table');
    table.className = 'cloudflare-breakdown-table';

    const thead = document.createElement('thead');
    const headerRow = document.createElement('tr');
    [config.label, config.value === 'events' ? 'Events' : 'Requests', 'Bandwidth'].forEach((heading) => {
        const th = document.createElement('th');
        th.textContent = heading;
        headerRow.appendChild(th);
    });
    thead.appendChild(headerRow);

    const tbody = document.createElement('tbody');

    items.forEach((item) => {
        const row = document.createElement('tr');
        const label = document.createElement('td');
        const count = document.createElement('td');
        const bandwidth = document.createElement('td');

        label.textContent = String(item.label ?? 'Unknown');
        count.textContent = formatCloudflareMetric(item[config.value] ?? 0, 'number');
        bandwidth.textContent = item.bandwidth_bytes === undefined ? 'N/A' : formatCloudflareMetric(item.bandwidth_bytes, 'bytes');

        row.appendChild(label);
        row.appendChild(count);
        row.appendChild(bandwidth);
        tbody.appendChild(row);
    });

    table.appendChild(thead);
    table.appendChild(tbody);
    container.appendChild(table);
}

function renderCloudflareAnalyticsError(error) {
    const status = document.getElementById('cloudflareAnalyticsStatus');

    if (status) {
        status.textContent = error?.message || 'Cloudflare analytics could not be loaded right now.';
        status.dataset.type = 'error';
    }

    renderCloudflareWarnings([]);
    destroyCloudflareChart('requests');
    destroyCloudflareChart('cache');
}

function destroyCloudflareChart(key) {
    if (cloudflareAnalyticsState.charts[key]) {
        cloudflareAnalyticsState.charts[key].destroy();
        delete cloudflareAnalyticsState.charts[key];
    }
}

function setCloudflareFallback(id, message) {
    const fallback = document.getElementById(id);

    if (!fallback) return;

    fallback.textContent = message;
    fallback.hidden = false;
}

function hideCloudflareFallback(id) {
    const fallback = document.getElementById(id);

    if (!fallback) return;

    fallback.hidden = true;
    fallback.textContent = '';
}

function setCloudflareCanvasHidden(id, hidden) {
    const canvas = document.getElementById(id);

    if (!canvas) return;

    canvas.hidden = hidden;
}

function clearElement(element) {
    while (element.firstChild) {
        element.removeChild(element.firstChild);
    }
}

function formatCloudflareMetric(value, type) {
    const number = Number(value);

    if (!Number.isFinite(number)) {
        return 'N/A';
    }

    if (type === 'bytes') {
        return formatCloudflareBytes(number);
    }

    if (type === 'percent') {
        return `${number.toLocaleString('en-US', { maximumFractionDigits: 1 })}%`;
    }

    return number.toLocaleString('en-US', { maximumFractionDigits: 0 });
}

function formatCloudflareBytes(bytes) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let value = Math.max(0, Number(bytes) || 0);
    let unitIndex = 0;

    while (value >= 1024 && unitIndex < units.length - 1) {
        value /= 1024;
        unitIndex += 1;
    }

    return `${value.toLocaleString('en-US', { maximumFractionDigits: value >= 10 ? 1 : 2 })} ${units[unitIndex]}`;
}

function formatCloudflareTimestamp(value) {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'recently';
    }

    return date.toLocaleString('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short'
    });
}

function cloudflareDateInputValue(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function adminProductImageSrc(imageName) {
    const image = String(imageName ?? '').trim();

    if (!image) return '';

    if (/^(https?:)?\/\//i.test(image) || image.startsWith('/') || image.startsWith('data:')) {
        return image;
    }

    return `/images/products/${encodeURIComponent(image)}`;
}

function setProductTableLoading(message = 'Loading products...') {
    const tbody = document.getElementById('productsTableBody');

    if (!tbody) return;

    tbody.innerHTML = `
        <tr>
            <td colspan="10" class="product-empty-state">
                ${adminEscapeHtml(message)}
            </td>
        </tr>
    `;
}

function showProductAlert(message, type = 'success') {
    const alert = document.getElementById('productAlert');

    if (!alert) return;

    alert.textContent = message;
    alert.dataset.type = type;
    alert.hidden = false;

    window.setTimeout(() => {
        if (alert.textContent === message) {
            alert.hidden = true;
        }
    }, 5000);
}

async function loadProducts() {
    const tbody = document.getElementById('productsTableBody');

    if (!tbody) return;

    bindProductManagerEvents();
    setProductTableLoading();

    try {
        const products = await fetchAdminJson('/admin/api/getProducts.php');
        let categories = [];
        let bookFormats = [];

        try {
            categories = await fetchAdminJson('/admin/api/getProductCategories.php');
        } catch (categoryError) {
            console.warn('Unable to load product categories endpoint; deriving from products.', categoryError);
        }

        try {
            bookFormats = await fetchAdminJson('/admin/api/getBookFormats.php');
        } catch (formatError) {
            console.warn('Unable to load book formats endpoint.', formatError);
        }

        productManagerState.products = Array.isArray(products) ? products : [];
        productManagerState.categories = Array.isArray(categories) && categories.length > 0
            ? categories
            : getCategoriesFromProducts(productManagerState.products);
        productManagerState.bookFormats = Array.isArray(bookFormats) ? bookFormats : [];
        productManagerState.currentPage = 1;

        populateProductCategoryControls();
        populateProductBookFormatControl();
        renderProductImageSlots();
        applyProductFilters();
    } catch (error) {
        console.error('Error loading products:', error);
        setProductTableLoading('Unable to load products.');
        showProductAlert(error.message || 'Unable to load products.', 'error');
    }
}

function getCategoriesFromProducts(products) {
    const categories = new Map();

    products.forEach((product) => {
        const id = String(product.product_category_id ?? '').trim();
        const name = String(product.category_name ?? '').trim();

        if (!id || !name || categories.has(id)) {
            return;
        }

        categories.set(id, {
            id,
            category_name: name
        });
    });

    return Array.from(categories.values())
        .sort((a, b) => a.category_name.localeCompare(b.category_name));
}

function bindProductManagerEvents() {
    const addButton = document.getElementById('addProductBtn');
    const searchInput = document.getElementById('productSearchInput');
    const categoryFilter = document.getElementById('productCategoryFilter');
    const resetButton = document.getElementById('resetProductFiltersBtn');
    const prevButton = document.getElementById('productPrevPageBtn');
    const nextButton = document.getElementById('productNextPageBtn');
    const tbody = document.getElementById('productsTableBody');
    const form = document.getElementById('productForm');
    const formCategory = document.getElementById('productCategoryId');
    const costInput = document.getElementById('productCost');
    const priceInput = document.getElementById('productPrice');
    const cancelButton = document.getElementById('cancelProductBtn');
    const closeButton = document.getElementById('closeProductDialogBtn');
    const dialog = document.getElementById('productFormDialog');

    addButton?.addEventListener('click', () => openProductForm());
    searchInput?.addEventListener('input', () => {
        productManagerState.currentPage = 1;
        applyProductFilters();
    });
    categoryFilter?.addEventListener('change', () => {
        productManagerState.currentPage = 1;
        applyProductFilters();
    });
    resetButton?.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        if (categoryFilter) categoryFilter.value = 'all';
        productManagerState.currentPage = 1;
        applyProductFilters();
    });
    prevButton?.addEventListener('click', () => {
        if (productManagerState.currentPage > 1) {
            productManagerState.currentPage--;
            renderProducts();
        }
    });
    nextButton?.addEventListener('click', () => {
        const totalPages = getProductTotalPages();

        if (productManagerState.currentPage < totalPages) {
            productManagerState.currentPage++;
            renderProducts();
        }
    });
    tbody?.addEventListener('click', (event) => {
        const button = event.target?.closest?.('[data-product-action]');

        if (!button) return;

        const id = Number.parseInt(button.dataset.productId || '0', 10);

        if (!id) return;

        if (button.dataset.productAction === 'edit') {
            editProduct(id);
        }

        if (button.dataset.productAction === 'delete') {
            deleteProduct(id);
        }
    });
    form?.addEventListener('submit', handleProductFormSubmit);
    formCategory?.addEventListener('change', updateProductBookFormatVisibility);
    costInput?.addEventListener('input', updateProductMarginField);
    priceInput?.addEventListener('input', updateProductMarginField);
    cancelButton?.addEventListener('click', closeProductDialog);
    closeButton?.addEventListener('click', closeProductDialog);
    dialog?.addEventListener('click', (event) => {
        if (event.target === dialog) {
            closeProductDialog();
        }
    });
}

function populateProductCategoryControls() {
    const filter = document.getElementById('productCategoryFilter');
    const formSelect = document.getElementById('productCategoryId');
    const categoryOptions = productManagerState.categories.map((category) => `
        <option value="${adminEscapeHtml(category.id)}">
            ${adminEscapeHtml(category.category_name)}
        </option>
    `).join('');

    if (filter) {
        const selectedValue = filter.value || 'all';
        filter.innerHTML = `
            <option value="all">All Categories</option>
            ${categoryOptions}
        `;
        filter.value = selectedValue;

        if (filter.value !== selectedValue) {
            filter.value = 'all';
        }
    }

    if (formSelect) {
        formSelect.innerHTML = `
            <option value="">Select category</option>
            ${categoryOptions}
        `;
    }
}

function populateProductBookFormatControl() {
    const formSelect = document.getElementById('productBookFormatId');

    if (!formSelect) return;

    const selectedValue = formSelect.value || '';
    const formatOptions = productManagerState.bookFormats.map((format) => `
        <option value="${adminEscapeHtml(format.id)}">
            ${adminEscapeHtml(format.format_name)}
        </option>
    `).join('');

    formSelect.innerHTML = `
        <option value="">Select book format</option>
        ${formatOptions}
    `;
    formSelect.value = selectedValue;

    if (formSelect.value !== selectedValue) {
        formSelect.value = '';
    }
}

function isProductBookCategory(categoryId) {
    const normalizedCategoryId = String(categoryId ?? '').trim();

    if (normalizedCategoryId === '9') {
        return true;
    }

    return productManagerState.categories.some((category) => {
        const optionId = String(category.id ?? '').trim();
        const categoryName = String(category.category_name ?? '').trim().toLowerCase();

        return optionId === normalizedCategoryId && categoryName === 'books';
    });
}

function updateProductBookFormatVisibility() {
    const categorySelect = document.getElementById('productCategoryId');
    const group = document.getElementById('productBookFormatGroup');
    const formatSelect = document.getElementById('productBookFormatId');
    const isBook = isProductBookCategory(categorySelect?.value);

    if (group) {
        group.hidden = !isBook;
    }

    if (formatSelect) {
        formatSelect.disabled = !isBook;
        formatSelect.required = isBook;

        if (!isBook) {
            formatSelect.value = '';
        }
    }
}

function updateProductMarginField() {
    const costInput = document.getElementById('productCost');
    const priceInput = document.getElementById('productPrice');

    setProductFormValue('productMargin', calculateMarginFromValues(costInput?.value, priceInput?.value));
}

function getProductSearchValue() {
    return String(document.getElementById('productSearchInput')?.value ?? '')
        .trim()
        .toLowerCase();
}

function applyProductFilters() {
    const categoryFilter = document.getElementById('productCategoryFilter')?.value || 'all';
    const searchValue = getProductSearchValue();

    productManagerState.filteredProducts = productManagerState.products.filter((product) => {
        const matchesCategory = categoryFilter === 'all'
            || String(product.product_category_id ?? '') === String(categoryFilter);
        const searchable = [
            product.product_name,
            product.sku,
            product.asin,
            product.isbn,
            product.product_description,
            product.category_name
        ].join(' ').toLowerCase();
        const matchesSearch = !searchValue || searchable.includes(searchValue);

        return matchesCategory && matchesSearch;
    });

    const totalPages = getProductTotalPages();

    if (productManagerState.currentPage > totalPages) {
        productManagerState.currentPage = totalPages;
    }

    updateProductMetrics();
    renderProducts();
}

function updateProductMetrics() {
    const total = productManagerState.products.length;
    const visible = productManagerState.products.filter((product) => Number(product.visible) === 1).length;
    const filtered = productManagerState.filteredProducts.length;

    const totalElement = document.getElementById('productTotalCount');
    const visibleElement = document.getElementById('productVisibleCount');
    const filteredElement = document.getElementById('productFilteredCount');

    if (totalElement) totalElement.textContent = String(total);
    if (visibleElement) visibleElement.textContent = String(visible);
    if (filteredElement) filteredElement.textContent = String(filtered);
}

function getProductTotalPages() {
    return Math.max(1, Math.ceil(productManagerState.filteredProducts.length / productManagerState.perPage));
}

function renderProducts() {
    const tbody = document.getElementById('productsTableBody');

    if (!tbody) return;

    const start = (productManagerState.currentPage - 1) * productManagerState.perPage;
    const end = start + productManagerState.perPage;
    const pageProducts = productManagerState.filteredProducts.slice(start, end);

    if (pageProducts.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="product-empty-state">
                    No products found.
                </td>
            </tr>
        `;
        updateProductPagination();
        return;
    }

    tbody.innerHTML = pageProducts.map((product) => {
        const imageSrc = adminProductImageSrc(product.image_url);
        const imageMarkup = imageSrc
            ? `<img src="${adminEscapeHtml(imageSrc)}" alt="${adminEscapeHtml(product.product_name)}">`
            : '<span>No image</span>';
        const visibleBadge = Number(product.visible) === 1
            ? '<span class="status-badge active">Visible</span>'
            : '<span class="status-badge muted">Hidden</span>';
        const activeBadge = Number(product.is_active) === 1
            ? '<span class="status-badge active">Active</span>'
            : '<span class="status-badge muted">Inactive</span>';

        return `
            <tr>
                <td>
                    <div class="product-name-cell">
                        <div class="product-thumb">${imageMarkup}</div>
                        <div>
                            <strong>${adminEscapeHtml(product.product_name)}</strong>
                            <small>${adminEscapeHtml(product.product_description || '')}</small>
                        </div>
                    </div>
                </td>
                <td>${adminEscapeHtml(product.sku || 'Pending')}</td>
                <td>${adminEscapeHtml(product.category_name || 'Uncategorized')}</td>
                <td>${formatCurrency(product.cost)}</td>
                <td>${formatCurrency(product.price)}</td>
                <td>${calculateMargin(product)}</td>
                <td>${Number.parseInt(product.inventory_count ?? 0, 10)}</td>
                <td>
                    <div class="status-stack">
                        ${visibleBadge}
                        ${activeBadge}
                    </div>
                </td>
                <td>${formatDate(product.created_at)}</td>
                <td>
                    <div class="product-actions">
                        <button type="button"
                            class="table-action"
                            data-product-action="edit"
                            data-product-id="${adminEscapeHtml(product.id)}">
                            Edit
                        </button>
                        <button type="button"
                            class="table-action danger"
                            data-product-action="delete"
                            data-product-id="${adminEscapeHtml(product.id)}">
                            Delete
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    updateProductPagination();
}

function updateProductPagination() {
    const totalPages = getProductTotalPages();
    const pageInfo = document.getElementById('productPageInfo');
    const prevButton = document.getElementById('productPrevPageBtn');
    const nextButton = document.getElementById('productNextPageBtn');

    if (pageInfo) {
        pageInfo.textContent = `Page ${productManagerState.currentPage} of ${totalPages}`;
    }

    if (prevButton) {
        prevButton.disabled = productManagerState.currentPage <= 1;
    }

    if (nextButton) {
        nextButton.disabled = productManagerState.currentPage >= totalPages;
    }
}

function getProductImages(product = {}) {
    return productImageFields
        .map((field) => String(product?.[field] ?? '').trim())
        .filter(Boolean);
}

function renderProductImageSlots(product = {}) {
    const container = document.getElementById('productImageSlots');

    if (!container) return;

    const images = getProductImages(product);

    container.innerHTML = productImageFields.map((field, index) => {
        const slot = index + 1;
        const imageName = images[index] || '';
        const imageSrc = adminProductImageSrc(imageName);
        const preview = imageSrc
            ? `<img src="${adminEscapeHtml(imageSrc)}" alt="Product image ${slot}">`
            : '<span>Empty</span>';

        return `
            <div class="product-image-slot" data-image-slot="${slot}">
                <input type="hidden" name="existing_image_${slot}" value="${adminEscapeHtml(imageName)}">
                <div class="product-image-preview">${preview}</div>
                <label for="productImage${slot}">Image ${slot}</label>
                <input
                    type="file"
                    id="productImage${slot}"
                    name="product_image_${slot}"
                    accept="image/*"
                >
                <div class="product-image-name" title="${adminEscapeHtml(imageName)}">
                    ${adminEscapeHtml(imageName || 'Empty')}
                </div>
                <button type="button" class="image-clear-button" data-clear-image="${slot}">
                    Clear
                </button>
            </div>
        `;
    }).join('');

    container.querySelectorAll('input[type="file"]').forEach((input) => {
        input.addEventListener('change', handleProductImagePreview);
    });

    container.querySelectorAll('[data-clear-image]').forEach((button) => {
        button.addEventListener('click', () => clearProductImageSlot(button.dataset.clearImage));
    });

    updateProductImageCount();
}

function handleProductImagePreview(event) {
    const input = event.target;
    const slot = input.closest('.product-image-slot');
    const file = input.files?.[0];

    if (!slot || !file) {
        updateProductImageCount();
        return;
    }

    const preview = slot.querySelector('.product-image-preview');
    const name = slot.querySelector('.product-image-name');

    if (preview) {
        const imageUrl = URL.createObjectURL(file);
        preview.innerHTML = `<img src="${imageUrl}" alt="${adminEscapeHtml(file.name)}">`;
    }

    if (name) {
        name.textContent = file.name;
        name.title = file.name;
    }

    updateProductImageCount();
}

function clearProductImageSlot(slotNumber) {
    const slot = document.querySelector(`.product-image-slot[data-image-slot="${slotNumber}"]`);

    if (!slot) return;

    const hiddenInput = slot.querySelector('input[type="hidden"]');
    const fileInput = slot.querySelector('input[type="file"]');
    const preview = slot.querySelector('.product-image-preview');
    const name = slot.querySelector('.product-image-name');

    if (hiddenInput) hiddenInput.value = '';
    if (fileInput) fileInput.value = '';
    if (preview) preview.innerHTML = '<span>Empty</span>';
    if (name) {
        name.textContent = 'Empty';
        name.title = '';
    }

    updateProductImageCount();
}

function updateProductImageCount() {
    const countElement = document.getElementById('productImageCount');
    const slots = Array.from(document.querySelectorAll('.product-image-slot'));
    const count = slots.filter((slot) => {
        const hiddenInput = slot.querySelector('input[type="hidden"]');
        const fileInput = slot.querySelector('input[type="file"]');

        return Boolean(hiddenInput?.value || fileInput?.files?.length);
    }).length;

    if (countElement) {
        countElement.textContent = `${count} / 10`;
    }
}

function setProductFormValue(id, value) {
    const field = document.getElementById(id);

    if (field) {
        field.value = value ?? '';
    }
}

function openProductForm(product = null) {
    const form = document.getElementById('productForm');
    const dialog = document.getElementById('productFormDialog');
    const title = document.getElementById('productFormTitle');

    if (!form || !dialog) return;

    form.reset();

    const isEditing = Boolean(product);

    if (title) {
        title.textContent = isEditing ? 'Edit Product' : 'Add Product';
    }

    setProductFormValue('productId', product?.id || '');
    setProductFormValue('productName', product?.product_name || '');
    setProductFormValue('productCategoryId', product?.product_category_id || '');
    setProductFormValue('productDescription', product?.product_description || '');
    setProductFormValue('productLongDescription', product?.long_description || '');
    setProductFormValue('productCost', product?.cost || '');
    setProductFormValue('productPrice', product?.price || '');
    setProductFormValue('productBookFormatId', product?.format_id || '');
    setProductFormValue('productAsin', product?.asin || '');
    setProductFormValue('productIsbn', product?.isbn || '');
    setProductFormValue('productInventory', product?.inventory_count ?? 0);
    setProductFormValue('productSeoSlug', product?.seo_slug || '');
    setProductFormValue('productMetaTitle', product?.meta_title || '');
    setProductFormValue('productMetaDescription', product?.meta_description || '');

    const visible = document.getElementById('productVisible');
    const active = document.getElementById('productActive');
    const featured = document.getElementById('productFeatured');
    const apparel = document.getElementById('productApparel');

    if (visible) visible.checked = isEditing ? Number(product.visible) === 1 : true;
    if (active) active.checked = isEditing ? Number(product.is_active) === 1 : true;
    if (featured) featured.checked = isEditing ? Number(product.is_featured) === 1 : false;
    if (apparel) apparel.checked = isEditing ? Number(product.is_apparel) === 1 : false;

    renderProductImageSlots(product || {});
    updateProductMarginField();
    updateProductBookFormatVisibility();

    if (typeof dialog.showModal === 'function') {
        dialog.showModal();
    } else {
        dialog.setAttribute('open', '');
    }

    document.getElementById('productName')?.focus();
}

function closeProductDialog() {
    const dialog = document.getElementById('productFormDialog');

    if (!dialog) return;

    if (typeof dialog.close === 'function') {
        dialog.close();
    } else {
        dialog.removeAttribute('open');
    }
}

function editProduct(id) {
    const product = productManagerState.products.find((item) => Number(item.id) === Number(id));

    if (!product) {
        showProductAlert('Product could not be found.', 'error');
        return;
    }

    openProductForm(product);
}

async function deleteProduct(id) {
    const product = productManagerState.products.find((item) => Number(item.id) === Number(id));
    const productName = product?.product_name || 'this product';

    if (!confirm(`Delete ${productName}?`)) {
        return;
    }

    try {
        await fetchAdminJson('/admin/api/deleteProduct.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id })
        });

        showProductAlert('Product deleted.');
        await refreshProducts();
    } catch (error) {
        console.error('Error deleting product:', error);
        showProductAlert(error.message || 'Unable to delete product.', 'error');
    }
}

async function handleProductFormSubmit(event) {
    event.preventDefault();

    const form = event.target;
    const saveButton = document.getElementById('saveProductBtn');

    if (!form) return;

    if (saveButton) {
        saveButton.disabled = true;
        saveButton.textContent = 'Saving...';
    }

    try {
        const data = await fetchAdminJson('/admin/api/saveProduct.php', {
            method: 'POST',
            body: new FormData(form)
        });

        closeProductDialog();
        showProductAlert(data.message || 'Product saved.');
        await refreshProducts();
    } catch (error) {
        console.error('Error saving product:', error);
        showProductAlert(error.message || 'Unable to save product.', 'error');
    } finally {
        if (saveButton) {
            saveButton.disabled = false;
            saveButton.textContent = 'Save Product';
        }
    }
}

async function refreshProducts() {
    const products = await fetchAdminJson('/admin/api/getProducts.php');

    productManagerState.products = Array.isArray(products) ? products : [];
    applyProductFilters();
}

// =====================================
// Blog Post Manager
// =====================================

function adminBlogImageSrc(imageName) {
    const image = String(imageName ?? '').trim();

    if (!image) return '';

    if (/^(https?:)?\/\//i.test(image) || image.startsWith('/') || image.startsWith('data:')) {
        return image;
    }

    return `/images/blog/${encodeURIComponent(image)}`;
}

function formatDateTimeForInput(value) {
    const raw = String(value ?? '').trim();

    if (!raw) return '';

    return raw.replace(' ', 'T').slice(0, 16);
}

function setBlogTableLoading(message = 'Loading blog posts...') {
    const tbody = document.getElementById('blogPostsTableBody');

    if (!tbody) return;

    tbody.innerHTML = `
        <tr>
            <td colspan="7" class="product-empty-state">
                ${adminEscapeHtml(message)}
            </td>
        </tr>
    `;
}

function showBlogAlert(message, type = 'success') {
    const alert = document.getElementById('blogAlert');

    if (!alert) return;

    alert.textContent = message;
    alert.dataset.type = type;
    alert.hidden = false;

    window.setTimeout(() => {
        if (alert.textContent === message) {
            alert.hidden = true;
        }
    }, 5000);
}

async function loadBlogPosts() {
    const tbody = document.getElementById('blogPostsTableBody');

    if (!tbody) return;

    bindBlogManagerEvents();
    setBlogTableLoading();

    try {
        const posts = await fetchAdminJson('/admin/api/getBlogPosts.php');
        let categories = [];

        try {
            categories = await fetchAdminJson('/admin/api/getBlogCategories.php');
        } catch (categoryError) {
            console.warn('Unable to load blog categories endpoint; deriving from posts.', categoryError);
        }

        blogManagerState.posts = Array.isArray(posts) ? posts : [];
        blogManagerState.categories = Array.isArray(categories) && categories.length > 0
            ? categories
            : getCategoriesFromBlogPosts(blogManagerState.posts);
        blogManagerState.currentPage = 1;

        populateBlogCategoryControls();
        renderBlogImageSlots();
        setBlogEditorContent('');
        applyBlogFilters();
    } catch (error) {
        console.error('Error loading blog posts:', error);
        setBlogTableLoading('Unable to load blog posts.');
        showBlogAlert(error.message || 'Unable to load blog posts.', 'error');
    }
}

function getCategoriesFromBlogPosts(posts) {
    const categories = new Map();

    posts.forEach((post) => {
        const id = String(post.blog_category_id ?? '').trim();
        const name = String(post.category_name ?? '').trim();

        if (!id || !name || categories.has(id)) {
            return;
        }

        categories.set(id, {
            id,
            category_name: name
        });
    });

    return Array.from(categories.values())
        .sort((a, b) => a.category_name.localeCompare(b.category_name));
}

function bindBlogManagerEvents() {
    const addButton = document.getElementById('addBlogPostBtn');
    const searchInput = document.getElementById('blogSearchInput');
    const categoryFilter = document.getElementById('blogCategoryFilter');
    const statusFilter = document.getElementById('blogStatusFilter');
    const resetButton = document.getElementById('resetBlogFiltersBtn');
    const prevButton = document.getElementById('blogPrevPageBtn');
    const nextButton = document.getElementById('blogNextPageBtn');
    const tbody = document.getElementById('blogPostsTableBody');
    const form = document.getElementById('blogForm');
    const cancelButton = document.getElementById('cancelBlogBtn');
    const closeButton = document.getElementById('closeBlogDialogBtn');
    const dialog = document.getElementById('blogFormDialog');
    const editor = document.getElementById('blogHtmlEditor');
    const source = document.getElementById('blogHtmlSource');
    const toolbar = document.querySelector('.blog-editor-toolbar');

    addButton?.addEventListener('click', () => openBlogForm());
    searchInput?.addEventListener('input', () => {
        blogManagerState.currentPage = 1;
        applyBlogFilters();
    });
    categoryFilter?.addEventListener('change', () => {
        blogManagerState.currentPage = 1;
        applyBlogFilters();
    });
    statusFilter?.addEventListener('change', () => {
        blogManagerState.currentPage = 1;
        applyBlogFilters();
    });
    resetButton?.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        if (categoryFilter) categoryFilter.value = 'all';
        if (statusFilter) statusFilter.value = 'all';
        blogManagerState.currentPage = 1;
        applyBlogFilters();
    });
    prevButton?.addEventListener('click', () => {
        if (blogManagerState.currentPage > 1) {
            blogManagerState.currentPage--;
            renderBlogPosts();
        }
    });
    nextButton?.addEventListener('click', () => {
        const totalPages = getBlogTotalPages();

        if (blogManagerState.currentPage < totalPages) {
            blogManagerState.currentPage++;
            renderBlogPosts();
        }
    });
    tbody?.addEventListener('click', (event) => {
        const button = event.target?.closest?.('[data-blog-action]');

        if (!button) return;

        const id = Number.parseInt(button.dataset.blogPostId || '0', 10);

        if (!id) return;

        if (button.dataset.blogAction === 'edit') {
            editBlogPost(id);
        }

        if (button.dataset.blogAction === 'delete') {
            deleteBlogPost(id);
        }
    });
    form?.addEventListener('submit', handleBlogFormSubmit);
    cancelButton?.addEventListener('click', closeBlogDialog);
    closeButton?.addEventListener('click', closeBlogDialog);
    dialog?.addEventListener('click', (event) => {
        if (event.target === dialog) {
            closeBlogDialog();
        }
    });
    editor?.addEventListener('input', syncBlogEditorFromVisual);
    source?.addEventListener('input', syncBlogEditorFromSource);
    toolbar?.addEventListener('click', handleBlogEditorToolbarClick);
}

function populateBlogCategoryControls() {
    const filter = document.getElementById('blogCategoryFilter');
    const formSelect = document.getElementById('blogCategoryId');
    const categoryOptions = blogManagerState.categories.map((category) => `
        <option value="${adminEscapeHtml(category.id)}">
            ${adminEscapeHtml(category.category_name)}
        </option>
    `).join('');

    if (filter) {
        const selectedValue = filter.value || 'all';
        filter.innerHTML = `
            <option value="all">All Categories</option>
            ${categoryOptions}
        `;
        filter.value = selectedValue;

        if (filter.value !== selectedValue) {
            filter.value = 'all';
        }
    }

    if (formSelect) {
        formSelect.innerHTML = `
            <option value="">Select category</option>
            ${categoryOptions}
        `;
    }
}

function getBlogSearchValue() {
    return String(document.getElementById('blogSearchInput')?.value ?? '')
        .trim()
        .toLowerCase();
}

function applyBlogFilters() {
    const categoryFilter = document.getElementById('blogCategoryFilter')?.value || 'all';
    const statusFilter = document.getElementById('blogStatusFilter')?.value || 'all';
    const searchValue = getBlogSearchValue();

    blogManagerState.filteredPosts = blogManagerState.posts.filter((post) => {
        const matchesCategory = categoryFilter === 'all'
            || String(post.blog_category_id ?? '') === String(categoryFilter);
        const matchesStatus = statusFilter === 'all'
            || (statusFilter === 'visible' && Number(post.visible) === 1)
            || (statusFilter === 'hidden' && Number(post.visible) !== 1)
            || (statusFilter === 'featured' && Number(post.is_featured) === 1);
        const searchable = [
            post.post_title,
            post.post_excerpt,
            post.category_name,
            post.seo_slug,
            post.meta_title,
            post.meta_description,
            post.author_full_name,
            post.author_email
        ].join(' ').toLowerCase();
        const matchesSearch = !searchValue || searchable.includes(searchValue);

        return matchesCategory && matchesStatus && matchesSearch;
    });

    const totalPages = getBlogTotalPages();

    if (blogManagerState.currentPage > totalPages) {
        blogManagerState.currentPage = totalPages;
    }

    updateBlogMetrics();
    renderBlogPosts();
}

function updateBlogMetrics() {
    const total = blogManagerState.posts.length;
    const visible = blogManagerState.posts.filter((post) => Number(post.visible) === 1).length;
    const featured = blogManagerState.posts.filter((post) => Number(post.is_featured) === 1).length;

    const totalElement = document.getElementById('blogTotalCount');
    const visibleElement = document.getElementById('blogVisibleCount');
    const featuredElement = document.getElementById('blogFeaturedCount');

    if (totalElement) totalElement.textContent = String(total);
    if (visibleElement) visibleElement.textContent = String(visible);
    if (featuredElement) featuredElement.textContent = String(featured);
}

function getBlogTotalPages() {
    return Math.max(1, Math.ceil(blogManagerState.filteredPosts.length / blogManagerState.perPage));
}

function renderBlogPosts() {
    const tbody = document.getElementById('blogPostsTableBody');

    if (!tbody) return;

    const start = (blogManagerState.currentPage - 1) * blogManagerState.perPage;
    const end = start + blogManagerState.perPage;
    const pagePosts = blogManagerState.filteredPosts.slice(start, end);

    if (pagePosts.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="product-empty-state">
                    No blog posts found.
                </td>
            </tr>
        `;
        updateBlogPagination();
        return;
    }

    tbody.innerHTML = pagePosts.map((post) => {
        const imageSrc = adminBlogImageSrc(post.featured_image_url);
        const imageMarkup = imageSrc
            ? `<img src="${adminEscapeHtml(imageSrc)}" alt="${adminEscapeHtml(post.post_title)}">`
            : '<span>No image</span>';
        const visibleBadge = Number(post.visible) === 1
            ? '<span class="status-badge active">Visible</span>'
            : '<span class="status-badge muted">Hidden</span>';
        const featuredBadge = Number(post.is_featured) === 1
            ? '<span class="status-badge role-badge">Featured</span>'
            : '<span class="status-badge muted">Standard</span>';
        const author = String(post.author_full_name || post.author_email || 'N/A').trim();

        return `
            <tr>
                <td>
                    <div class="product-name-cell blog-post-cell">
                        <div class="product-thumb">${imageMarkup}</div>
                        <div>
                            <strong>${adminEscapeHtml(post.post_title)}</strong>
                            <small>${adminEscapeHtml(post.post_excerpt || '')}</small>
                        </div>
                    </div>
                </td>
                <td>${adminEscapeHtml(post.category_name || 'Uncategorized')}</td>
                <td>${adminEscapeHtml(author || 'N/A')}</td>
                <td>${formatDate(post.published_at)}</td>
                <td>
                    <div class="status-stack">
                        ${visibleBadge}
                        ${featuredBadge}
                    </div>
                </td>
                <td>${formatDate(post.updated_at)}</td>
                <td>
                    <div class="product-actions">
                        <button type="button"
                            class="table-action"
                            data-blog-action="edit"
                            data-blog-post-id="${adminEscapeHtml(post.id)}">
                            Edit
                        </button>
                        <button type="button"
                            class="table-action danger"
                            data-blog-action="delete"
                            data-blog-post-id="${adminEscapeHtml(post.id)}">
                            Delete
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    updateBlogPagination();
}

function updateBlogPagination() {
    const totalPages = getBlogTotalPages();
    const pageInfo = document.getElementById('blogPageInfo');
    const prevButton = document.getElementById('blogPrevPageBtn');
    const nextButton = document.getElementById('blogNextPageBtn');

    if (pageInfo) {
        pageInfo.textContent = `Page ${blogManagerState.currentPage} of ${totalPages}`;
    }

    if (prevButton) {
        prevButton.disabled = blogManagerState.currentPage <= 1;
    }

    if (nextButton) {
        nextButton.disabled = blogManagerState.currentPage >= totalPages;
    }
}

function getBlogImages(post = {}) {
    return blogImageFields.map((field) => String(post?.[field] ?? '').trim());
}

function renderBlogImageSlots(post = {}) {
    const container = document.getElementById('blogImageSlots');

    if (!container) return;

    const images = getBlogImages(post);

    container.innerHTML = blogImageFields.map((field, index) => {
        const slot = index + 1;
        const imageName = images[index] || '';
        const imageSrc = adminBlogImageSrc(imageName);
        const label = slot === 1 ? 'Featured Image' : `Image ${slot}`;
        const preview = imageSrc
            ? `<img src="${adminEscapeHtml(imageSrc)}" alt="${adminEscapeHtml(label)}">`
            : '<span>Empty</span>';

        return `
            <div class="product-image-slot blog-image-slot" data-blog-image-slot="${slot}">
                <input type="hidden" name="existing_image_${slot}" value="${adminEscapeHtml(imageName)}">
                <div class="product-image-preview">${preview}</div>
                <label for="blogImage${slot}">${adminEscapeHtml(label)}</label>
                <input
                    type="file"
                    id="blogImage${slot}"
                    name="blog_image_${slot}"
                    accept="image/*"
                >
                <div class="product-image-name" title="${adminEscapeHtml(imageName)}">
                    ${adminEscapeHtml(imageName || 'Empty')}
                </div>
                <button type="button" class="image-clear-button" data-clear-blog-image="${slot}">
                    Clear
                </button>
            </div>
        `;
    }).join('');

    container.querySelectorAll('input[type="file"]').forEach((input) => {
        input.addEventListener('change', handleBlogImagePreview);
    });

    container.querySelectorAll('[data-clear-blog-image]').forEach((button) => {
        button.addEventListener('click', () => clearBlogImageSlot(button.dataset.clearBlogImage));
    });

    updateBlogImageCount();
}

function handleBlogImagePreview(event) {
    const input = event.target;
    const slot = input.closest('.blog-image-slot');
    const file = input.files?.[0];

    if (!slot || !file) {
        updateBlogImageCount();
        return;
    }

    const preview = slot.querySelector('.product-image-preview');
    const name = slot.querySelector('.product-image-name');

    if (preview) {
        const imageUrl = URL.createObjectURL(file);
        preview.innerHTML = `<img src="${imageUrl}" alt="${adminEscapeHtml(file.name)}">`;
    }

    if (name) {
        name.textContent = file.name;
        name.title = file.name;
    }

    updateBlogImageCount();
}

function clearBlogImageSlot(slotNumber) {
    const slot = document.querySelector(`.blog-image-slot[data-blog-image-slot="${slotNumber}"]`);

    if (!slot) return;

    const hiddenInput = slot.querySelector('input[type="hidden"]');
    const fileInput = slot.querySelector('input[type="file"]');
    const preview = slot.querySelector('.product-image-preview');
    const name = slot.querySelector('.product-image-name');

    if (hiddenInput) hiddenInput.value = '';
    if (fileInput) fileInput.value = '';
    if (preview) preview.innerHTML = '<span>Empty</span>';
    if (name) {
        name.textContent = 'Empty';
        name.title = '';
    }

    updateBlogImageCount();
}

function updateBlogImageCount() {
    const countElement = document.getElementById('blogImageCount');
    const slots = Array.from(document.querySelectorAll('.blog-image-slot'));
    const count = slots.filter((slot) => {
        const hiddenInput = slot.querySelector('input[type="hidden"]');
        const fileInput = slot.querySelector('input[type="file"]');

        return Boolean(hiddenInput?.value || fileInput?.files?.length);
    }).length;

    if (countElement) {
        countElement.textContent = `${count} / 5`;
    }
}

function setBlogFormValue(id, value) {
    const field = document.getElementById(id);

    if (field) {
        field.value = value ?? '';
    }
}

function setBlogEditorContent(html) {
    const value = String(html ?? '');
    const editor = document.getElementById('blogHtmlEditor');
    const source = document.getElementById('blogHtmlSource');
    const hidden = document.getElementById('blogPostContent');

    blogManagerState.editorMode = 'visual';

    if (editor) editor.innerHTML = value;
    if (source) source.value = value;
    if (hidden) hidden.value = value;

    updateBlogEditorMode();
}

function updateBlogEditorMode() {
    const editor = document.getElementById('blogHtmlEditor');
    const source = document.getElementById('blogHtmlSource');
    const modeButton = document.getElementById('blogEditorModeBtn');
    const isSource = blogManagerState.editorMode === 'source';

    if (editor) editor.hidden = isSource;
    if (source) source.hidden = !isSource;
    if (modeButton) {
        modeButton.textContent = isSource ? 'Visual' : 'HTML';
        modeButton.title = isSource ? 'Edit visually' : 'Edit HTML source';
    }
}

function syncBlogEditorFromVisual() {
    if (blogManagerState.editorMode !== 'visual') return;

    const editor = document.getElementById('blogHtmlEditor');
    const source = document.getElementById('blogHtmlSource');
    const hidden = document.getElementById('blogPostContent');
    const html = editor?.innerHTML?.trim() || '';

    if (source) source.value = html;
    if (hidden) hidden.value = html;
}

function syncBlogEditorFromSource() {
    if (blogManagerState.editorMode !== 'source') return;

    const source = document.getElementById('blogHtmlSource');
    const hidden = document.getElementById('blogPostContent');

    if (hidden) hidden.value = source?.value || '';
}

function prepareBlogEditorForSubmit() {
    if (blogManagerState.editorMode === 'source') {
        syncBlogEditorFromSource();
        return;
    }

    syncBlogEditorFromVisual();
}

function toggleBlogEditorMode() {
    const editor = document.getElementById('blogHtmlEditor');
    const source = document.getElementById('blogHtmlSource');
    const hidden = document.getElementById('blogPostContent');

    if (blogManagerState.editorMode === 'visual') {
        syncBlogEditorFromVisual();
        if (source) source.value = hidden?.value || '';
        blogManagerState.editorMode = 'source';
    } else {
        syncBlogEditorFromSource();
        if (editor) editor.innerHTML = hidden?.value || '';
        blogManagerState.editorMode = 'visual';
    }

    updateBlogEditorMode();
}

function ensureBlogVisualEditor() {
    if (blogManagerState.editorMode === 'source') {
        toggleBlogEditorMode();
    }

    document.getElementById('blogHtmlEditor')?.focus();
}

function handleBlogEditorToolbarClick(event) {
    const button = event.target?.closest?.('button');

    if (!button) return;

    const command = button.dataset.blogEditorCommand;
    const format = button.dataset.blogEditorFormat;
    const action = button.dataset.blogEditorAction;

    if (action === 'toggle-source') {
        toggleBlogEditorMode();
        return;
    }

    ensureBlogVisualEditor();

    if (command) {
        document.execCommand(command, false, null);
        syncBlogEditorFromVisual();
        return;
    }

    if (format) {
        document.execCommand('formatBlock', false, format);
        syncBlogEditorFromVisual();
        return;
    }

    if (action === 'create-link') {
        const url = prompt('Enter the link URL');

        if (url) {
            document.execCommand('createLink', false, url);
            syncBlogEditorFromVisual();
        }
    }

    if (action === 'insert-image') {
        const url = prompt('Enter the image URL');

        if (url) {
            document.execCommand('insertImage', false, url);
            syncBlogEditorFromVisual();
        }
    }
}

function openBlogForm(post = null) {
    const form = document.getElementById('blogForm');
    const dialog = document.getElementById('blogFormDialog');
    const title = document.getElementById('blogFormTitle');

    if (!form || !dialog) return;

    form.reset();

    const isEditing = Boolean(post);

    if (title) {
        title.textContent = isEditing ? 'Edit Blog Post' : 'Add Blog Post';
    }

    setBlogFormValue('blogPostId', post?.id || '');
    setBlogFormValue('blogPostTitle', post?.post_title || '');
    setBlogFormValue('blogCategoryId', post?.blog_category_id || '');
    setBlogFormValue('blogPublishedAt', formatDateTimeForInput(post?.published_at));
    setBlogFormValue('blogPostExcerpt', post?.post_excerpt || '');
    setBlogFormValue('blogSeoSlug', post?.seo_slug || '');
    setBlogFormValue('blogMetaTitle', post?.meta_title || '');
    setBlogFormValue('blogMetaDescription', post?.meta_description || '');

    const visible = document.getElementById('blogVisible');
    const featured = document.getElementById('blogFeatured');

    if (visible) visible.checked = isEditing ? Number(post.visible) === 1 : true;
    if (featured) featured.checked = isEditing ? Number(post.is_featured) === 1 : false;

    renderBlogImageSlots(post || {});
    setBlogEditorContent(post?.post_content || '');

    if (typeof dialog.showModal === 'function') {
        dialog.showModal();
    } else {
        dialog.setAttribute('open', '');
    }

    document.getElementById('blogPostTitle')?.focus();
}

function closeBlogDialog() {
    const dialog = document.getElementById('blogFormDialog');

    if (!dialog) return;

    if (typeof dialog.close === 'function') {
        dialog.close();
    } else {
        dialog.removeAttribute('open');
    }
}

function editBlogPost(id) {
    const post = blogManagerState.posts.find((item) => Number(item.id) === Number(id));

    if (!post) {
        showBlogAlert('Blog post could not be found.', 'error');
        return;
    }

    openBlogForm(post);
}

async function deleteBlogPost(id) {
    const post = blogManagerState.posts.find((item) => Number(item.id) === Number(id));
    const postTitle = post?.post_title || 'this blog post';

    if (!confirm(`Delete ${postTitle}?`)) {
        return;
    }

    try {
        await fetchAdminJson('/admin/api/deleteBlogPost.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id })
        });

        showBlogAlert('Blog post deleted.');
        await refreshBlogPosts();
    } catch (error) {
        console.error('Error deleting blog post:', error);
        showBlogAlert(error.message || 'Unable to delete blog post.', 'error');
    }
}

async function handleBlogFormSubmit(event) {
    event.preventDefault();

    const form = event.target;
    const saveButton = document.getElementById('saveBlogBtn');

    if (!form) return;

    prepareBlogEditorForSubmit();

    if (saveButton) {
        saveButton.disabled = true;
        saveButton.textContent = 'Saving...';
    }

    try {
        const data = await fetchAdminJson('/admin/api/saveBlogPost.php', {
            method: 'POST',
            body: new FormData(form)
        });

        closeBlogDialog();
        showBlogAlert(data.message || 'Blog post saved.');
        await refreshBlogPosts();
    } catch (error) {
        console.error('Error saving blog post:', error);
        showBlogAlert(error.message || 'Unable to save blog post.', 'error');
    } finally {
        if (saveButton) {
            saveButton.disabled = false;
            saveButton.textContent = 'Save Post';
        }
    }
}

async function refreshBlogPosts() {
    const posts = await fetchAdminJson('/admin/api/getBlogPosts.php');

    blogManagerState.posts = Array.isArray(posts) ? posts : [];
    applyBlogFilters();
}

// =====================================
// Load Users
// =====================================

async function loadUsers() {
    const tbody = document.getElementById('usersTableBody');

    if (!tbody) return;

    bindUserManagerEvents();
    setUserTableLoading();

    try {
        const users = await fetchAdminJson('/admin/api/getUsers.php');
        let roles = [];
        let states = [];

        try {
            roles = await fetchAdminJson('/admin/api/getUserRoles.php');
        } catch (roleError) {
            console.warn('Unable to load user roles endpoint; deriving from users.', roleError);
        }

        try {
            states = await fetchAdminJson('/admin/api/getUserStates.php');
        } catch (stateError) {
            console.warn('Unable to load user states endpoint; deriving from users.', stateError);
        }

        userManagerState.users = Array.isArray(users) ? users : [];
        userManagerState.roles = Array.isArray(roles) && roles.length > 0
            ? roles
            : getRolesFromUsers(userManagerState.users);
        userManagerState.states = Array.isArray(states) && states.length > 0
            ? states
            : getStatesFromUsers(userManagerState.users);
        userManagerState.currentPage = 1;

        populateUserRoleControls();
        populateUserStateControl();
        applyUserFilters();

    } catch (error) {
        console.error('Error loading users:', error);
        setUserTableLoading('Unable to load users.');
        showUserAlert(error.message || 'Unable to load users.', 'error');
    }
}

function bindUserManagerEvents() {
    const addButton = document.getElementById('addUserBtn');
    const searchInput = document.getElementById('userSearchInput');
    const roleFilter = document.getElementById('userRoleFilter');
    const resetButton = document.getElementById('resetUserFiltersBtn');
    const prevButton = document.getElementById('userPrevPageBtn');
    const nextButton = document.getElementById('userNextPageBtn');
    const tbody = document.getElementById('usersTableBody');
    const form = document.getElementById('userForm');
    const cancelButton = document.getElementById('cancelUserBtn');
    const closeButton = document.getElementById('closeUserDialogBtn');
    const dialog = document.getElementById('userFormDialog');
    const previewFields = [
        document.getElementById('userFirstName'),
        document.getElementById('userLastName'),
        document.getElementById('userEmailAddress')
    ];

    addButton?.addEventListener('click', () => openUserForm());
    searchInput?.addEventListener('input', () => {
        userManagerState.currentPage = 1;
        applyUserFilters();
    });
    roleFilter?.addEventListener('change', () => {
        userManagerState.currentPage = 1;
        applyUserFilters();
    });
    resetButton?.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        if (roleFilter) roleFilter.value = 'all';
        userManagerState.currentPage = 1;
        applyUserFilters();
    });
    prevButton?.addEventListener('click', () => {
        if (userManagerState.currentPage > 1) {
            userManagerState.currentPage--;
            renderUsers();
        }
    });
    nextButton?.addEventListener('click', () => {
        const totalPages = getUserTotalPages();

        if (userManagerState.currentPage < totalPages) {
            userManagerState.currentPage++;
            renderUsers();
        }
    });
    tbody?.addEventListener('click', (event) => {
        const button = event.target?.closest?.('[data-user-action]');

        if (!button) return;

        const id = Number.parseInt(button.dataset.userId || '0', 10);

        if (!id) return;

        if (button.dataset.userAction === 'edit') {
            editUser(id);
        }

        if (button.dataset.userAction === 'delete') {
            deleteUser(id);
        }
    });
    form?.addEventListener('submit', handleUserFormSubmit);
    cancelButton?.addEventListener('click', closeUserDialog);
    closeButton?.addEventListener('click', closeUserDialog);
    dialog?.addEventListener('click', (event) => {
        if (event.target === dialog) {
            closeUserDialog();
        }
    });
    previewFields.forEach((field) => {
        field?.addEventListener('input', updateUserPreview);
    });
}

function setUserTableLoading(message = 'Loading users...') {
    const tbody = document.getElementById('usersTableBody');

    if (!tbody) return;

    tbody.innerHTML = `
        <tr>
            <td colspan="8" class="product-empty-state">
                ${adminEscapeHtml(message)}
            </td>
        </tr>
    `;
}

function showUserAlert(message, type = 'success') {
    const alert = document.getElementById('userAlert');

    if (!alert) return;

    alert.textContent = message;
    alert.dataset.type = type;
    alert.hidden = false;

    window.setTimeout(() => {
        if (alert.textContent === message) {
            alert.hidden = true;
        }
    }, 5000);
}

function getRolesFromUsers(users) {
    const roles = new Map();

    users.forEach((user) => {
        const id = String(user.user_role_id ?? '').trim();
        const name = String(user.role_name ?? '').trim();

        if (!id || !name || roles.has(id)) {
            return;
        }

        roles.set(id, {
            id,
            role_name: name
        });
    });

    return Array.from(roles.values())
        .sort((a, b) => a.role_name.localeCompare(b.role_name));
}

function getStatesFromUsers(users) {
    const states = new Map();

    users.forEach((user) => {
        const id = String(user.state_prov_id ?? '').trim();
        const name = String(user.state_province ?? '').trim();

        if (!id || !name || states.has(id)) {
            return;
        }

        states.set(id, {
            id,
            name
        });
    });

    return Array.from(states.values())
        .sort((a, b) => a.name.localeCompare(b.name));
}

function populateUserRoleControls() {
    const filter = document.getElementById('userRoleFilter');
    const formSelect = document.getElementById('userRoleId');
    const roleOptions = userManagerState.roles.map((role) => `
        <option value="${adminEscapeHtml(role.id)}">
            ${adminEscapeHtml(formatRoleName(role.role_name))}
        </option>
    `).join('');

    if (filter) {
        const selectedValue = filter.value || 'all';
        filter.innerHTML = `
            <option value="all">All Roles</option>
            ${roleOptions}
        `;
        filter.value = selectedValue;

        if (filter.value !== selectedValue) {
            filter.value = 'all';
        }
    }

    if (formSelect) {
        formSelect.innerHTML = `
            <option value="">Select role</option>
            ${roleOptions}
        `;
    }
}

function populateUserStateControl() {
    const formSelect = document.getElementById('userStateProvId');

    if (!formSelect) return;

    const stateOptions = userManagerState.states.map((state) => `
        <option value="${adminEscapeHtml(state.id)}">
            ${adminEscapeHtml(state.name)}
        </option>
    `).join('');

    formSelect.innerHTML = `
        <option value="">Select state</option>
        ${stateOptions}
    `;
}

function formatRoleName(roleName) {
    return String(roleName ?? '')
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function getUserSearchValue() {
    return String(document.getElementById('userSearchInput')?.value ?? '')
        .trim()
        .toLowerCase();
}

function applyUserFilters() {
    const roleFilter = document.getElementById('userRoleFilter')?.value || 'all';
    const searchValue = getUserSearchValue();

    userManagerState.filteredUsers = userManagerState.users.filter((user) => {
        const matchesRole = roleFilter === 'all'
            || String(user.user_role_id ?? '') === String(roleFilter);
        const searchable = [
            user.full_name,
            user.first_name,
            user.last_name,
            user.email_address,
            user.phone,
            user.city,
            user.state_province,
            user.postal_code,
            user.country,
            user.role_name
        ].join(' ').toLowerCase();
        const matchesSearch = !searchValue || searchable.includes(searchValue);

        return matchesRole && matchesSearch;
    });

    const totalPages = getUserTotalPages();

    if (userManagerState.currentPage > totalPages) {
        userManagerState.currentPage = totalPages;
    }

    updateUserMetrics();
    renderUsers();
}

function updateUserMetrics() {
    const total = userManagerState.users.length;
    const active = userManagerState.users.filter((user) => Number(user.is_active) === 1).length;
    const filtered = userManagerState.filteredUsers.length;

    const totalElement = document.getElementById('userTotalCount');
    const activeElement = document.getElementById('userActiveCount');
    const filteredElement = document.getElementById('userFilteredCount');

    if (totalElement) totalElement.textContent = String(total);
    if (activeElement) activeElement.textContent = String(active);
    if (filteredElement) filteredElement.textContent = String(filtered);
}

function getUserTotalPages() {
    return Math.max(1, Math.ceil(userManagerState.filteredUsers.length / userManagerState.perPage));
}

function getUserInitials(user) {
    const first = String(user?.first_name ?? '').trim().charAt(0);
    const last = String(user?.last_name ?? '').trim().charAt(0);
    const fallback = String(user?.email_address ?? 'NC').trim().charAt(0);

    return (first + last || fallback || 'NC').toUpperCase();
}

function renderUsers() {
    const tbody = document.getElementById('usersTableBody');

    if (!tbody) return;

    const start = (userManagerState.currentPage - 1) * userManagerState.perPage;
    const end = start + userManagerState.perPage;
    const pageUsers = userManagerState.filteredUsers.slice(start, end);

    if (pageUsers.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="product-empty-state">
                    No users found.
                </td>
            </tr>
        `;
        updateUserPagination();
        return;
    }

    tbody.innerHTML = pageUsers.map((user) => {
        const visibleBadge = Number(user.visible) === 1
            ? '<span class="status-badge active">Visible</span>'
            : '<span class="status-badge muted">Hidden</span>';
        const activeBadge = Number(user.is_active) === 1
            ? '<span class="status-badge active">Active</span>'
            : '<span class="status-badge muted">Inactive</span>';
        const cityState = [
            user.city,
            user.state_province,
            user.postal_code
        ].filter(Boolean).join(', ');

        return `
            <tr>
                <td>
                    <div class="product-name-cell user-name-cell">
                        <div class="user-avatar">${adminEscapeHtml(getUserInitials(user))}</div>
                        <div>
                            <strong>${adminEscapeHtml(user.full_name || `${user.first_name || ''} ${user.last_name || ''}`.trim())}</strong>
                            <small>${adminEscapeHtml(user.email_address || '')}</small>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="status-badge role-badge">
                        ${adminEscapeHtml(formatRoleName(user.role_name || 'Unassigned'))}
                    </span>
                </td>
                <td>${adminEscapeHtml(user.phone || 'N/A')}</td>
                <td>
                    <div class="user-location-cell">
                        <strong>${adminEscapeHtml(cityState || 'N/A')}</strong>
                        <small>${adminEscapeHtml(user.country || '')}</small>
                    </div>
                </td>
                <td>
                    <div class="status-stack">
                        ${visibleBadge}
                        ${activeBadge}
                    </div>
                </td>
                <td>${formatDate(user.last_login_at)}</td>
                <td>${formatDate(user.created_at)}</td>
                <td>
                    <div class="product-actions">
                        <button type="button"
                            class="table-action"
                            data-user-action="edit"
                            data-user-id="${adminEscapeHtml(user.id)}">
                            Edit
                        </button>
                        <button type="button"
                            class="table-action danger"
                            data-user-action="delete"
                            data-user-id="${adminEscapeHtml(user.id)}">
                            Delete
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    updateUserPagination();
}

function updateUserPagination() {
    const totalPages = getUserTotalPages();
    const pageInfo = document.getElementById('userPageInfo');
    const prevButton = document.getElementById('userPrevPageBtn');
    const nextButton = document.getElementById('userNextPageBtn');

    if (pageInfo) {
        pageInfo.textContent = `Page ${userManagerState.currentPage} of ${totalPages}`;
    }

    if (prevButton) {
        prevButton.disabled = userManagerState.currentPage <= 1;
    }

    if (nextButton) {
        nextButton.disabled = userManagerState.currentPage >= totalPages;
    }
}

function setUserFormValue(id, value) {
    const field = document.getElementById(id);

    if (field) {
        field.value = value ?? '';
    }
}

function openUserForm(user = null) {
    const form = document.getElementById('userForm');
    const dialog = document.getElementById('userFormDialog');
    const title = document.getElementById('userFormTitle');
    const password = document.getElementById('userPassword');

    if (!form || !dialog) return;

    form.reset();

    const isEditing = Boolean(user);

    if (title) {
        title.textContent = isEditing ? 'Edit User' : 'Add User';
    }

    setUserFormValue('userId', user?.id || '');
    setUserFormValue('userFirstName', user?.first_name || '');
    setUserFormValue('userLastName', user?.last_name || '');
    setUserFormValue('userEmailAddress', user?.email_address || '');
    setUserFormValue('userPhone', user?.phone || '');
    setUserFormValue('userBirthdate', user?.birthdate || '');
    setUserFormValue('userSweepstakesWonDate', user?.sweepstakes_won_date || '');
    setUserFormValue('userRoleId', user?.user_role_id || '');
    setUserFormValue('userPassword', '');
    setUserFormValue('userAddress1', user?.address_1 || '');
    setUserFormValue('userAddress2', user?.address_2 || '');
    setUserFormValue('userCity', user?.city || '');
    setUserFormValue('userStateProvId', user?.state_prov_id || '');
    setUserFormValue('userPostalCode', user?.postal_code || '');
    setUserFormValue('userCountry', user?.country || '');

    if (password) {
        password.required = !isEditing;
        password.placeholder = isEditing ? 'Leave blank to keep current password' : 'At least 8 characters';
    }

    const visible = document.getElementById('userVisible');
    const active = document.getElementById('userActive');
    const sweepstakesActive = document.getElementById('userSweepstakesActive');
    const sweepstakesWon = document.getElementById('userSweepstakesWon');

    if (visible) visible.checked = isEditing ? Number(user.visible) === 1 : true;
    if (active) active.checked = isEditing ? Number(user.is_active) === 1 : true;
    if (sweepstakesActive) {
        sweepstakesActive.checked = isEditing ? Number(user.sweepstakes_active) === 1 : true;
    }
    if (sweepstakesWon) {
        sweepstakesWon.checked = isEditing ? Number(user.sweepstakes_won) === 1 : false;
    }

    updateUserPreview();

    if (typeof dialog.showModal === 'function') {
        dialog.showModal();
    } else {
        dialog.setAttribute('open', '');
    }

    document.getElementById('userFirstName')?.focus();
}

function closeUserDialog() {
    const dialog = document.getElementById('userFormDialog');

    if (!dialog) return;

    if (typeof dialog.close === 'function') {
        dialog.close();
    } else {
        dialog.removeAttribute('open');
    }
}

function updateUserPreview() {
    const firstName = document.getElementById('userFirstName')?.value || '';
    const lastName = document.getElementById('userLastName')?.value || '';
    const email = document.getElementById('userEmailAddress')?.value || '';
    const avatar = document.getElementById('userAvatarPreview');
    const name = document.getElementById('userPreviewName');
    const emailPreview = document.getElementById('userPreviewEmail');
    const fullName = `${firstName} ${lastName}`.trim();

    if (avatar) {
        avatar.textContent = getUserInitials({
            first_name: firstName,
            last_name: lastName,
            email_address: email
        });
    }

    if (name) {
        name.textContent = fullName || 'New User';
    }

    if (emailPreview) {
        emailPreview.textContent = email || 'No email entered';
    }
}

function editUser(id) {
    const user = userManagerState.users.find((item) => Number(item.id) === Number(id));

    if (!user) {
        showUserAlert('User could not be found.', 'error');
        return;
    }

    openUserForm(user);
}

async function deleteUser(id) {
    const user = userManagerState.users.find((item) => Number(item.id) === Number(id));
    const userName = user?.full_name || 'this user';

    if (!confirm(`Delete ${userName}?`)) {
        return;
    }

    try {
        await fetchAdminJson('/admin/api/deleteUser.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id })
        });

        showUserAlert('User deleted.');
        await refreshUsers();
    } catch (error) {
        console.error('Error deleting user:', error);
        showUserAlert(error.message || 'Unable to delete user.', 'error');
    }
}

async function handleUserFormSubmit(event) {
    event.preventDefault();

    const form = event.target;
    const saveButton = document.getElementById('saveUserBtn');

    if (!form) return;

    if (saveButton) {
        saveButton.disabled = true;
        saveButton.textContent = 'Saving...';
    }

    try {
        const data = await fetchAdminJson('/admin/api/saveUser.php', {
            method: 'POST',
            body: new FormData(form)
        });

        closeUserDialog();
        showUserAlert(data.message || 'User saved.');
        await refreshUsers();
    } catch (error) {
        console.error('Error saving user:', error);
        showUserAlert(error.message || 'Unable to save user.', 'error');
    } finally {
        if (saveButton) {
            saveButton.disabled = false;
            saveButton.textContent = 'Save User';
        }
    }
}

async function refreshUsers() {
    const users = await fetchAdminJson('/admin/api/getUsers.php');

    userManagerState.users = Array.isArray(users) ? users : [];
    applyUserFilters();
}

// =====================================
// Download Manager
// =====================================

async function initDownloadManager() {
    if (!document.getElementById('downloadsTableBody')) return;

    bindDownloadManagerEvents();
    await refreshDownloads();
}

function bindDownloadManagerEvents() {
    const dialog = document.getElementById('downloadFormDialog');
    const search = document.getElementById('downloadSearchInput');
    const visibility = document.getElementById('downloadVisibilityFilter');

    document.getElementById('addDownloadBtn')?.addEventListener('click', () => openDownloadForm());
    document.getElementById('resetDownloadFiltersBtn')?.addEventListener('click', () => {
        if (search) search.value = '';
        if (visibility) visibility.value = 'all';
        applyDownloadFilters();
    });
    search?.addEventListener('input', applyDownloadFilters);
    visibility?.addEventListener('change', applyDownloadFilters);
    document.getElementById('downloadForm')?.addEventListener('submit', saveDownload);
    document.getElementById('cancelDownloadBtn')?.addEventListener('click', closeDownloadDialog);
    document.getElementById('closeDownloadDialogBtn')?.addEventListener('click', closeDownloadDialog);
    dialog?.addEventListener('click', (event) => {
        if (event.target === dialog) closeDownloadDialog();
    });
    document.getElementById('downloadFile')?.addEventListener('change', (event) => {
        const file = event.target.files?.[0];
        const filename = document.getElementById('downloadFilename');

        if (file && filename && !filename.value.trim()) filename.value = file.name;
        updateDownloadFileStatus(file?.name || '');
    });
    document.getElementById('downloadKey')?.addEventListener('input', updateDownloadLinkPreview);
    document.getElementById('downloadsTableBody')?.addEventListener('click', (event) => {
        const button = event.target?.closest?.('[data-download-action]');
        if (!button) return;

        const id = Number.parseInt(button.dataset.downloadId || '0', 10);
        if (!id) return;

        if (button.dataset.downloadAction === 'edit') editDownload(id);
        if (button.dataset.downloadAction === 'delete') deleteDownload(id);
    });
}

async function refreshDownloads() {
    const tbody = document.getElementById('downloadsTableBody');
    if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="product-empty-state">Loading downloads...</td></tr>';

    try {
        const data = await fetchAdminJson('/admin/api/downloads.php');
        downloadManagerState.downloads = Array.isArray(data.downloads) ? data.downloads : [];
        downloadManagerState.metrics = data.metrics || {};
        updateDownloadMetrics();
        applyDownloadFilters();
    } catch (error) {
        if (tbody) tbody.innerHTML = '<tr><td colspan="7" class="product-empty-state">Unable to load downloads.</td></tr>';
        showDownloadAlert(error.message || 'Unable to load downloads.', 'error');
    }
}

function updateDownloadMetrics() {
    const metrics = downloadManagerState.metrics;
    const available = document.getElementById('downloadAvailableCount');
    const total = document.getElementById('downloadTotalCount');
    const records = document.getElementById('downloadRecordCount');

    if (available) available.textContent = Number(metrics.available || 0).toLocaleString();
    if (total) total.textContent = Number(metrics.total_downloads || 0).toLocaleString();
    if (records) records.textContent = Number(metrics.records || 0).toLocaleString();
}

function applyDownloadFilters() {
    const search = String(document.getElementById('downloadSearchInput')?.value || '').trim().toLowerCase();
    const visibility = document.getElementById('downloadVisibilityFilter')?.value || 'all';

    downloadManagerState.filteredDownloads = downloadManagerState.downloads.filter((download) => {
        const matchesSearch = !search || [download.title, download.description, download.filename, download.link]
            .join(' ').toLowerCase().includes(search);
        const isVisible = Number(download.viewable) === 1;
        const matchesVisibility = visibility === 'all'
            || (visibility === 'visible' && isVisible)
            || (visibility === 'hidden' && !isVisible);
        return matchesSearch && matchesVisibility;
    });

    renderDownloads();
}

function renderDownloads() {
    const tbody = document.getElementById('downloadsTableBody');
    if (!tbody) return;

    if (downloadManagerState.filteredDownloads.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="product-empty-state">No downloads found.</td></tr>';
        return;
    }

    tbody.innerHTML = downloadManagerState.filteredDownloads.map((download) => {
        const viewable = Number(download.viewable) === 1;
        const fileExists = Boolean(download.file_exists);
        const status = viewable && fileExists
            ? '<span class="status-badge active">Viewable</span>'
            : viewable
                ? '<span class="status-badge danger">File missing</span>'
                : '<span class="status-badge muted">Hidden</span>';

        return `<tr>
            <td><strong>${adminEscapeHtml(download.title)}</strong></td>
            <td class="download-description-cell">${adminEscapeHtml(download.description)}</td>
            <td>${adminEscapeHtml(download.filename)}${fileExists ? '' : '<br><small>Not in storage</small>'}</td>
            <td>${status}</td>
            <td class="download-link-cell"><a href="${adminEscapeHtml(download.link)}" target="_blank" rel="noopener">${adminEscapeHtml(download.link)}</a></td>
            <td>${Number(download.download_count || 0).toLocaleString()}</td>
            <td><div class="product-actions">
                <button type="button" class="table-action" data-download-action="edit" data-download-id="${adminEscapeHtml(download.id)}">Edit</button>
                <button type="button" class="table-action danger" data-download-action="delete" data-download-id="${adminEscapeHtml(download.id)}">Delete</button>
            </div></td>
        </tr>`;
    }).join('');
}

function createDownloadKey() {
    const bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    return Array.from(bytes, (value) => value.toString(16).padStart(2, '0')).join('');
}

function openDownloadForm(download = null) {
    const form = document.getElementById('downloadForm');
    const dialog = document.getElementById('downloadFormDialog');
    if (!form || !dialog) return;

    form.reset();
    const isEditing = Boolean(download);
    document.getElementById('downloadFormTitle').textContent = isEditing ? 'Edit Download' : 'Add Download';
    document.getElementById('downloadId').value = download?.id || '';
    document.getElementById('downloadTitle').value = download?.title || '';
    document.getElementById('downloadDescription').value = download?.description || '';
    document.getElementById('downloadFilename').value = download?.filename || '';
    document.getElementById('downloadKey').value = download?.download_key || createDownloadKey();
    document.getElementById('downloadCount').value = download?.download_count || 0;
    document.getElementById('downloadViewable').checked = isEditing ? Number(download.viewable) === 1 : true;
    document.getElementById('downloadFile').required = !isEditing;
    document.getElementById('downloadFileHelp').textContent = isEditing
        ? 'Leave blank to keep the current file. Maximum 25 MB.'
        : 'Required for a new download. Maximum 25 MB.';

    updateDownloadFileStatus(isEditing ? download.filename : '', isEditing ? Boolean(download.file_exists) : false);
    updateDownloadLinkPreview();

    if (typeof dialog.showModal === 'function') dialog.showModal();
    else dialog.setAttribute('open', '');
    document.getElementById('downloadTitle')?.focus();
}

function updateDownloadFileStatus(filename = '', exists = true) {
    const status = document.getElementById('downloadFileStatus');
    if (!status) return;
    status.textContent = filename
        ? (exists ? `Current file: ${filename}` : `Selected file: ${filename}`)
        : 'No file uploaded yet.';
}

function updateDownloadLinkPreview() {
    const preview = document.getElementById('downloadLinkPreview');
    const key = String(document.getElementById('downloadKey')?.value || '').trim();
    if (!preview) return;

    if (!key) {
        preview.textContent = 'Generated after save';
        preview.removeAttribute('href');
        return;
    }

    const link = `/customer/download.php?key=${encodeURIComponent(key)}`;
    preview.href = link;
    preview.textContent = link;
}

function closeDownloadDialog() {
    const dialog = document.getElementById('downloadFormDialog');
    if (!dialog) return;
    if (typeof dialog.close === 'function') dialog.close();
    else dialog.removeAttribute('open');
}

function editDownload(id) {
    const download = downloadManagerState.downloads.find((item) => Number(item.id) === Number(id));
    if (!download) {
        showDownloadAlert('Download not found.', 'error');
        return;
    }
    openDownloadForm(download);
}

async function deleteDownload(id) {
    const download = downloadManagerState.downloads.find((item) => Number(item.id) === Number(id));
    if (!confirm(`Hide “${download?.title || 'this download'}”? The stored file will be preserved.`)) return;

    const formData = new FormData();
    formData.set('action', 'delete');
    formData.set('id', String(id));

    try {
        const data = await fetchAdminJson('/admin/api/downloads.php', { method: 'POST', body: formData });
        showDownloadAlert(data.message || 'Download hidden.');
        await refreshDownloads();
    } catch (error) {
        showDownloadAlert(error.message || 'Unable to hide download.', 'error');
    }
}

async function saveDownload(event) {
    event.preventDefault();
    const button = document.getElementById('saveDownloadBtn');
    if (button) {
        button.disabled = true;
        button.textContent = 'Saving...';
    }

    try {
        const data = await fetchAdminJson('/admin/api/downloads.php', {
            method: 'POST',
            body: new FormData(event.target)
        });
        closeDownloadDialog();
        showDownloadAlert(data.message || 'Download saved.');
        await refreshDownloads();
    } catch (error) {
        showDownloadAlert(error.message || 'Unable to save download.', 'error');
    } finally {
        if (button) {
            button.disabled = false;
            button.textContent = 'Save Download';
        }
    }
}

function showDownloadAlert(message, type = 'success') {
    const alert = document.getElementById('downloadAlert');
    if (!alert) return;
    alert.textContent = message;
    alert.dataset.type = type;
    alert.hidden = false;
}

// =====================================
// Task Manager
// =====================================

async function loadTasks() {
    const tbody = document.getElementById('tasksTableBody');

    if (!tbody) return;

    bindTaskManagerEvents();
    setTaskTableLoading();

    try {
        const [tasks, users] = await Promise.all([
            fetchAdminJson('/admin/api/getTasks.php'),
            fetchAdminJson('/admin/api/getTaskUsers.php')
        ]);

        taskManagerState.tasks = Array.isArray(tasks) ? tasks : [];
        taskManagerState.users = Array.isArray(users) ? users : [];
        taskManagerState.currentPage = 1;

        populateTaskUserControls();
        applyTaskFilters();
    } catch (error) {
        console.error('Error loading tasks:', error);
        setTaskTableLoading('Unable to load tasks.');
        showTaskAlert(error.message || 'Unable to load tasks.', 'error');
    }
}

function bindTaskManagerEvents() {
    const addButton = document.getElementById('addTaskBtn');
    const searchInput = document.getElementById('taskSearchInput');
    const userFilter = document.getElementById('taskUserFilter');
    const statusFilter = document.getElementById('taskStatusFilter');
    const priorityFilter = document.getElementById('taskPriorityFilter');
    const resetButton = document.getElementById('resetTaskFiltersBtn');
    const prevButton = document.getElementById('taskPrevPageBtn');
    const nextButton = document.getElementById('taskNextPageBtn');
    const tbody = document.getElementById('tasksTableBody');
    const form = document.getElementById('taskForm');
    const cancelButton = document.getElementById('cancelTaskBtn');
    const closeButton = document.getElementById('closeTaskDialogBtn');
    const dialog = document.getElementById('taskFormDialog');
    const previewFields = [
        document.getElementById('taskTitle'),
        document.getElementById('taskUserId'),
        document.getElementById('taskDueAt'),
        document.getElementById('taskStatus'),
        document.getElementById('taskRecurring'),
        document.getElementById('taskRecurrenceFrequency'),
        document.getElementById('taskRecurrenceInterval'),
        document.getElementById('taskRecurrenceCount')
    ];

    addButton?.addEventListener('click', () => openTaskForm());
    searchInput?.addEventListener('input', () => {
        taskManagerState.currentPage = 1;
        applyTaskFilters();
    });
    userFilter?.addEventListener('change', () => {
        taskManagerState.currentPage = 1;
        applyTaskFilters();
    });
    statusFilter?.addEventListener('change', () => {
        taskManagerState.currentPage = 1;
        applyTaskFilters();
    });
    priorityFilter?.addEventListener('change', () => {
        taskManagerState.currentPage = 1;
        applyTaskFilters();
    });
    resetButton?.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        if (userFilter) userFilter.value = 'all';
        if (statusFilter) statusFilter.value = 'all';
        if (priorityFilter) priorityFilter.value = 'all';
        taskManagerState.currentPage = 1;
        applyTaskFilters();
    });
    prevButton?.addEventListener('click', () => {
        if (taskManagerState.currentPage > 1) {
            taskManagerState.currentPage--;
            renderTasks();
        }
    });
    nextButton?.addEventListener('click', () => {
        const totalPages = getTaskTotalPages();

        if (taskManagerState.currentPage < totalPages) {
            taskManagerState.currentPage++;
            renderTasks();
        }
    });
    tbody?.addEventListener('click', (event) => {
        const button = event.target?.closest?.('[data-task-action]');

        if (!button) return;

        const id = Number.parseInt(button.dataset.taskId || '0', 10);

        if (!id) return;

        if (button.dataset.taskAction === 'edit') {
            editTask(id);
        }

        if (button.dataset.taskAction === 'delete') {
            deleteTask(id);
        }
    });
    form?.addEventListener('submit', handleTaskFormSubmit);
    cancelButton?.addEventListener('click', closeTaskDialog);
    closeButton?.addEventListener('click', closeTaskDialog);
    dialog?.addEventListener('click', (event) => {
        if (event.target === dialog) {
            closeTaskDialog();
        }
    });
    previewFields.forEach((field) => {
        field?.addEventListener('input', updateTaskPreview);
        field?.addEventListener('change', updateTaskPreview);
    });
    document.getElementById('taskRecurring')?.addEventListener('change', toggleTaskRecurrenceFields);
}

function setTaskTableLoading(message = 'Loading tasks...') {
    const tbody = document.getElementById('tasksTableBody');

    if (!tbody) return;

    tbody.innerHTML = `
        <tr>
            <td colspan="7" class="product-empty-state">
                ${adminEscapeHtml(message)}
            </td>
        </tr>
    `;
}

function showTaskAlert(message, type = 'success') {
    const alert = document.getElementById('taskAlert');

    if (!alert) return;

    alert.textContent = message;
    alert.dataset.type = type;
    alert.hidden = false;

    window.setTimeout(() => {
        if (alert.textContent === message) {
            alert.hidden = true;
        }
    }, 5000);
}

function getTaskUserLabel(user) {
    const fullName = String(user?.full_name ?? `${user?.first_name || ''} ${user?.last_name || ''}`.trim()).trim();
    const email = String(user?.email_address ?? '').trim();

    return fullName || email || `User #${user?.id || ''}`.trim();
}

function populateTaskUserControls() {
    const filter = document.getElementById('taskUserFilter');
    const formSelect = document.getElementById('taskUserId');
    const userOptions = taskManagerState.users.map((user) => {
        const inactiveSuffix = Number(user.is_active) === 1 ? '' : ' (inactive)';

        return `
            <option value="${adminEscapeHtml(user.id)}">
                ${adminEscapeHtml(getTaskUserLabel(user) + inactiveSuffix)}
            </option>
        `;
    }).join('');

    if (filter) {
        const selectedValue = filter.value || 'all';
        filter.innerHTML = `
            <option value="all">All Users</option>
            ${userOptions}
        `;
        filter.value = selectedValue;

        if (filter.value !== selectedValue) {
            filter.value = 'all';
        }
    }

    if (formSelect) {
        formSelect.innerHTML = `
            <option value="">Select user</option>
            ${userOptions}
        `;
    }
}

function formatTaskLabel(value) {
    return String(value ?? '')
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
}

function isRecurringTask(task) {
    return String(task?.recurrence_frequency || 'none') !== 'none'
        && Number(task?.recurrence_count || 1) > 1;
}

function formatTaskRecurrence(taskOrFrequency, intervalValue = 1, countValue = 1, sequenceValue = null) {
    const source = typeof taskOrFrequency === 'object' && taskOrFrequency !== null
        ? taskOrFrequency
        : {
            recurrence_frequency: taskOrFrequency,
            recurrence_interval: intervalValue,
            recurrence_count: countValue,
            recurrence_sequence: sequenceValue
        };
    const frequency = String(source.recurrence_frequency || 'none');
    const interval = Math.max(1, Number.parseInt(source.recurrence_interval || '1', 10));
    const count = Math.max(1, Number.parseInt(source.recurrence_count || '1', 10));
    const sequence = Number.parseInt(source.recurrence_sequence || '0', 10);

    if (frequency === 'none' || count <= 1) {
        return 'One-time task';
    }

    const unit = {
        daily: 'day',
        weekly: 'week',
        monthly: 'month',
        yearly: 'year'
    }[frequency] || 'interval';
    const intervalLabel = interval === 1 ? unit : `${interval} ${unit}s`;
    const sequenceLabel = sequence > 0 ? ` | ${sequence} of ${count}` : '';

    return `Repeats every ${intervalLabel}${sequenceLabel}`;
}

function getTaskSearchValue() {
    return String(document.getElementById('taskSearchInput')?.value ?? '')
        .trim()
        .toLowerCase();
}

function applyTaskFilters() {
    const userFilter = document.getElementById('taskUserFilter')?.value || 'all';
    const statusFilter = document.getElementById('taskStatusFilter')?.value || 'all';
    const priorityFilter = document.getElementById('taskPriorityFilter')?.value || 'all';
    const searchValue = getTaskSearchValue();

    taskManagerState.filteredTasks = taskManagerState.tasks.filter((task) => {
        const matchesUser = userFilter === 'all' || String(task.user_id ?? '') === String(userFilter);
        const matchesStatus = statusFilter === 'all' || String(task.task_status ?? '') === String(statusFilter);
        const matchesPriority = priorityFilter === 'all' || String(task.priority ?? '') === String(priorityFilter);
        const searchable = [
            task.title,
            task.description,
            task.task_status,
            task.priority,
            task.recurrence_frequency,
            task.assigned_to,
            task.assigned_email
        ].join(' ').toLowerCase();
        const matchesSearch = !searchValue || searchable.includes(searchValue);

        return matchesUser && matchesStatus && matchesPriority && matchesSearch;
    });

    const totalPages = getTaskTotalPages();

    if (taskManagerState.currentPage > totalPages) {
        taskManagerState.currentPage = totalPages;
    }

    updateTaskMetrics();
    renderTasks();
}

function getTaskDate(value) {
    const raw = String(value ?? '').trim();

    if (!raw) return null;

    const date = new Date(raw.replace(' ', 'T'));

    return Number.isNaN(date.getTime()) ? null : date;
}

function isTaskClosed(task) {
    return ['completed', 'canceled'].includes(String(task?.task_status ?? ''));
}

function isTaskOverdue(task) {
    const dueDate = getTaskDate(task?.due_at);

    return Boolean(dueDate) && !isTaskClosed(task) && dueDate.getTime() < Date.now();
}

function updateTaskMetrics() {
    const total = taskManagerState.tasks.length;
    const open = taskManagerState.tasks.filter((task) => !isTaskClosed(task)).length;
    const overdue = taskManagerState.tasks.filter(isTaskOverdue).length;
    const filtered = taskManagerState.filteredTasks.length;

    const totalElement = document.getElementById('taskTotalCount');
    const openElement = document.getElementById('taskOpenCount');
    const overdueElement = document.getElementById('taskOverdueCount');
    const filteredElement = document.getElementById('taskFilteredCount');

    if (totalElement) totalElement.textContent = String(total);
    if (openElement) openElement.textContent = String(open);
    if (overdueElement) overdueElement.textContent = String(overdue);
    if (filteredElement) filteredElement.textContent = String(filtered);
}

function getTaskTotalPages() {
    return Math.max(1, Math.ceil(taskManagerState.filteredTasks.length / taskManagerState.perPage));
}

function getTaskStatusBadge(status) {
    const normalized = String(status || 'open');
    const className = {
        open: 'role-badge',
        in_progress: 'warning',
        completed: 'active',
        canceled: 'muted'
    }[normalized] || 'muted';

    return `<span class="status-badge ${className}">${adminEscapeHtml(formatTaskLabel(normalized))}</span>`;
}

function getTaskPriorityBadge(priority) {
    const normalized = String(priority || 'normal');
    const className = {
        low: 'muted',
        normal: 'role-badge',
        high: 'warning',
        urgent: 'danger'
    }[normalized] || 'muted';

    return `<span class="status-badge ${className}">${adminEscapeHtml(formatTaskLabel(normalized))}</span>`;
}

function renderTasks() {
    const tbody = document.getElementById('tasksTableBody');

    if (!tbody) return;

    const start = (taskManagerState.currentPage - 1) * taskManagerState.perPage;
    const end = start + taskManagerState.perPage;
    const pageTasks = taskManagerState.filteredTasks.slice(start, end);

    if (pageTasks.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="product-empty-state">
                    No tasks found.
                </td>
            </tr>
        `;
        updateTaskPagination();
        return;
    }

    tbody.innerHTML = pageTasks.map((task) => {
        const description = String(task.description || '').trim();
        const detailLines = [];
        const dueClass = isTaskOverdue(task) ? 'task-due-cell overdue' : 'task-due-cell';
        const completed = task.completed_at ? `Completed ${formatDateTime(task.completed_at)}` : '';
        const calendarUrl = String(task.google_calendar_url || '#');

        if (description) {
            detailLines.push(description);
        }

        if (isRecurringTask(task)) {
            detailLines.push(formatTaskRecurrence(task));
        }

        return `
            <tr>
                <td>
                    <div class="task-title-cell">
                        <strong>${adminEscapeHtml(task.title || 'Untitled task')}</strong>
                        ${detailLines.length > 0 ? `<small>${adminEscapeHtml(detailLines.join(' | '))}</small>` : '<small>No description</small>'}
                    </div>
                </td>
                <td>
                    <div class="task-user-cell">
                        <strong>${adminEscapeHtml(task.assigned_to || 'Unassigned')}</strong>
                        <small>${adminEscapeHtml(task.assigned_email || '')}</small>
                    </div>
                </td>
                <td>${getTaskPriorityBadge(task.priority)}</td>
                <td>${getTaskStatusBadge(task.task_status)}</td>
                <td>
                    <div class="${dueClass}">
                        <strong>${formatDateTime(task.due_at)}</strong>
                        <small>${adminEscapeHtml(completed || (isTaskOverdue(task) ? 'Overdue' : ''))}</small>
                    </div>
                </td>
                <td>${formatDateTime(task.updated_at || task.created_at)}</td>
                <td>
                    <div class="product-actions">
                        <button type="button"
                            class="table-action"
                            data-task-action="edit"
                            data-task-id="${adminEscapeHtml(task.id)}">
                            Edit
                        </button>
                        <a class="table-action task-calendar-link"
                            href="${adminEscapeHtml(calendarUrl)}"
                            target="_blank"
                            rel="noopener noreferrer">
                            Calendar
                        </a>
                        <button type="button"
                            class="table-action danger"
                            data-task-action="delete"
                            data-task-id="${adminEscapeHtml(task.id)}">
                            Delete
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    updateTaskPagination();
}

function updateTaskPagination() {
    const totalPages = getTaskTotalPages();
    const pageInfo = document.getElementById('taskPageInfo');
    const prevButton = document.getElementById('taskPrevPageBtn');
    const nextButton = document.getElementById('taskNextPageBtn');

    if (pageInfo) {
        pageInfo.textContent = `Page ${taskManagerState.currentPage} of ${totalPages}`;
    }

    if (prevButton) {
        prevButton.disabled = taskManagerState.currentPage <= 1;
    }

    if (nextButton) {
        nextButton.disabled = taskManagerState.currentPage >= totalPages;
    }
}

function setTaskFormValue(id, value) {
    const field = document.getElementById(id);

    if (field) {
        field.value = value ?? '';
    }
}

function toggleTaskRecurrenceFields() {
    const checkbox = document.getElementById('taskRecurring');
    const fields = document.getElementById('taskRecurrenceFields');
    const dueAt = document.getElementById('taskDueAt');

    if (!checkbox || !fields) return;

    const enabled = checkbox.checked;

    fields.hidden = !enabled;
    if (dueAt) dueAt.required = enabled;
    fields.querySelectorAll('input, select').forEach((field) => {
        field.disabled = !enabled;
    });

    updateTaskPreview();
}

function openTaskForm(task = null) {
    const form = document.getElementById('taskForm');
    const dialog = document.getElementById('taskFormDialog');
    const title = document.getElementById('taskFormTitle');
    const taskManager = document.querySelector('.task-manager');
    const currentUserId = taskManager?.dataset.currentUserId || '';
    const defaultUserId = taskManagerState.users.some((user) => String(user.id) === String(currentUserId))
        ? currentUserId
        : taskManagerState.users[0]?.id || '';

    if (!form || !dialog) return;

    form.reset();

    const isEditing = Boolean(task);

    if (title) {
        title.textContent = isEditing ? 'Edit Task' : 'Add Task';
    }

    setTaskFormValue('taskId', task?.id || '');
    setTaskFormValue('taskTitle', task?.title || '');
    setTaskFormValue('taskUserId', task?.user_id || defaultUserId);
    setTaskFormValue('taskDueAt', formatDateTimeForInput(task?.due_at));
    setTaskFormValue('taskStatus', task?.task_status || 'open');
    setTaskFormValue('taskPriority', task?.priority || 'normal');
    setTaskFormValue('taskDescription', task?.description || '');

    const recurring = document.getElementById('taskRecurring');
    const isEditingRecurringTask = isRecurringTask(task);

    setTaskFormValue('taskRecurrenceFrequency', isEditingRecurringTask ? task?.recurrence_frequency : 'weekly');
    setTaskFormValue('taskRecurrenceInterval', isEditingRecurringTask ? task?.recurrence_interval : '1');
    setTaskFormValue('taskRecurrenceCount', isEditingRecurringTask ? task?.recurrence_count : '2');

    if (recurring) {
        recurring.checked = isEditingRecurringTask;
        recurring.disabled = isEditing;
    }

    toggleTaskRecurrenceFields();
    updateTaskPreview();

    if (typeof dialog.showModal === 'function') {
        dialog.showModal();
    } else {
        dialog.setAttribute('open', '');
    }

    document.getElementById('taskTitle')?.focus();
}

function closeTaskDialog() {
    const dialog = document.getElementById('taskFormDialog');

    if (!dialog) return;

    if (typeof dialog.close === 'function') {
        dialog.close();
    } else {
        dialog.removeAttribute('open');
    }
}

function updateTaskPreview() {
    const title = document.getElementById('taskTitle')?.value || '';
    const userSelect = document.getElementById('taskUserId');
    const dueAt = document.getElementById('taskDueAt')?.value || '';
    const status = document.getElementById('taskStatus')?.value || 'open';
    const recurringCheckbox = document.getElementById('taskRecurring');
    const recurrenceFrequency = document.getElementById('taskRecurrenceFrequency')?.value || 'weekly';
    const recurrenceInterval = document.getElementById('taskRecurrenceInterval')?.value || '1';
    const recurrenceCount = document.getElementById('taskRecurrenceCount')?.value || '2';
    const recurring = Boolean(recurringCheckbox?.checked)
        && recurrenceFrequency !== 'none'
        && Number.parseInt(recurrenceCount || '1', 10) > 1;
    const previewStatus = document.getElementById('taskPreviewStatus');
    const previewTitle = document.getElementById('taskPreviewTitle');
    const previewUser = document.getElementById('taskPreviewUser');
    const previewDue = document.getElementById('taskPreviewDue');
    const previewRecurrence = document.getElementById('taskPreviewRecurrence');

    if (previewStatus) {
        previewStatus.textContent = formatTaskLabel(status);
    }

    if (previewTitle) {
        previewTitle.textContent = title || 'New Task';
    }

    if (previewUser) {
        previewUser.textContent = userSelect?.selectedOptions?.[0]?.textContent?.trim() || 'No user selected';
    }

    if (previewDue) {
        previewDue.textContent = dueAt ? formatDateTime(dueAt) : 'No due date';
    }

    if (previewRecurrence) {
        previewRecurrence.textContent = recurring
            ? formatTaskRecurrence(recurrenceFrequency, recurrenceInterval, recurrenceCount)
            : 'One-time task';
    }
}

function editTask(id) {
    const task = taskManagerState.tasks.find((item) => Number(item.id) === Number(id));

    if (!task) {
        showTaskAlert('Task could not be found.', 'error');
        return;
    }

    openTaskForm(task);
}

async function deleteTask(id) {
    const task = taskManagerState.tasks.find((item) => Number(item.id) === Number(id));
    const taskTitle = task?.title || 'this task';

    if (!confirm(`Delete ${taskTitle}?`)) {
        return;
    }

    try {
        await fetchAdminJson('/admin/api/deleteTask.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ id })
        });

        showTaskAlert('Task deleted.');
        await refreshTasks();
    } catch (error) {
        console.error('Error deleting task:', error);
        showTaskAlert(error.message || 'Unable to delete task.', 'error');
    }
}

async function handleTaskFormSubmit(event) {
    event.preventDefault();

    const form = event.target;
    const saveButton = document.getElementById('saveTaskBtn');

    if (!form) return;

    if (saveButton) {
        saveButton.disabled = true;
        saveButton.textContent = 'Saving...';
    }

    try {
        const data = await fetchAdminJson('/admin/api/saveTask.php', {
            method: 'POST',
            body: new FormData(form)
        });

        closeTaskDialog();
        showTaskAlert(data.message || 'Task saved.');
        await refreshTasks();
    } catch (error) {
        console.error('Error saving task:', error);
        showTaskAlert(error.message || 'Unable to save task.', 'error');
    } finally {
        if (saveButton) {
            saveButton.disabled = false;
            saveButton.textContent = 'Save Task';
        }
    }
}

async function refreshTasks() {
    const tasks = await fetchAdminJson('/admin/api/getTasks.php');

    taskManagerState.tasks = Array.isArray(tasks) ? tasks : [];
    applyTaskFilters();
}

// =====================================
// Transaction Manager
// =====================================

async function loadTransactions() {
    const tbody = document.getElementById('transactionsTableBody');

    if (!tbody) return;

    bindTransactionManagerEvents();
    setTransactionTableLoading();

    try {
        const transactions = await fetchAdminJson('/admin/api/getTransactions.php');

        transactionManagerState.transactions = Array.isArray(transactions) ? transactions : [];
        transactionManagerState.statuses = getTransactionOptions(transactionManagerState.transactions, 'transaction_status', [
            'paid',
            'completed',
            'pending',
            'failed',
            'refunded'
        ]);
        transactionManagerState.types = getTransactionOptions(transactionManagerState.transactions, 'transaction_type', [
            'sale',
            'refund',
            'return',
            'chargeback'
        ]);
        transactionManagerState.currentPage = 1;

        populateTransactionFilters();
        applyTransactionFilters();
    } catch (error) {
        console.error('Error loading transactions:', error);
        setTransactionTableLoading('Unable to load transactions.');
        showTransactionAlert(error.message || 'Unable to load transactions.', 'error');
    }
}

function bindTransactionManagerEvents() {
    const refreshButton = document.getElementById('refreshTransactionsBtn');
    const searchInput = document.getElementById('transactionSearchInput');
    const statusFilter = document.getElementById('transactionStatusFilter');
    const typeFilter = document.getElementById('transactionTypeFilter');
    const resetButton = document.getElementById('resetTransactionFiltersBtn');
    const prevButton = document.getElementById('transactionPrevPageBtn');
    const nextButton = document.getElementById('transactionNextPageBtn');
    const tbody = document.getElementById('transactionsTableBody');
    const form = document.getElementById('transactionForm');
    const cancelButton = document.getElementById('cancelTransactionBtn');
    const closeButton = document.getElementById('closeTransactionDialogBtn');
    const dialog = document.getElementById('transactionFormDialog');
    const amountInputs = [
        document.getElementById('transactionAmount'),
        document.getElementById('transactionProductSalesAmount'),
        document.getElementById('transactionProductCostAmount'),
        document.getElementById('transactionShippingAmount'),
        document.getElementById('transactionReturnAmount'),
        document.getElementById('transactionSalesTaxAmount'),
        document.getElementById('transactionDiscountAmount'),
        document.getElementById('transactionPaymentFeeAmount'),
        document.getElementById('transactionCurrencyCode')
    ];

    refreshButton?.addEventListener('click', async () => {
        try {
            await refreshTransactions();
            showTransactionAlert('Transactions refreshed.');
        } catch (error) {
            console.error('Error refreshing transactions:', error);
            showTransactionAlert(error.message || 'Unable to refresh transactions.', 'error');
        }
    });
    searchInput?.addEventListener('input', () => {
        transactionManagerState.currentPage = 1;
        applyTransactionFilters();
    });
    statusFilter?.addEventListener('change', () => {
        transactionManagerState.currentPage = 1;
        applyTransactionFilters();
    });
    typeFilter?.addEventListener('change', () => {
        transactionManagerState.currentPage = 1;
        applyTransactionFilters();
    });
    resetButton?.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        if (statusFilter) statusFilter.value = 'all';
        if (typeFilter) typeFilter.value = 'all';
        transactionManagerState.currentPage = 1;
        applyTransactionFilters();
    });
    prevButton?.addEventListener('click', () => {
        if (transactionManagerState.currentPage > 1) {
            transactionManagerState.currentPage--;
            renderTransactions();
        }
    });
    nextButton?.addEventListener('click', () => {
        const totalPages = getTransactionTotalPages();

        if (transactionManagerState.currentPage < totalPages) {
            transactionManagerState.currentPage++;
            renderTransactions();
        }
    });
    tbody?.addEventListener('click', (event) => {
        const button = event.target?.closest?.('[data-transaction-action]');

        if (!button) return;

        const id = Number.parseInt(button.dataset.transactionId || '0', 10);

        if (!id) return;

        if (button.dataset.transactionAction === 'edit') {
            editTransaction(id);
        }
    });
    form?.addEventListener('submit', handleTransactionFormSubmit);
    cancelButton?.addEventListener('click', closeTransactionDialog);
    closeButton?.addEventListener('click', closeTransactionDialog);
    dialog?.addEventListener('click', (event) => {
        if (event.target === dialog) {
            closeTransactionDialog();
        }
    });
    amountInputs.forEach((input) => {
        input?.addEventListener('input', updateTransactionPreviewAmounts);
    });
}

function setTransactionTableLoading(message = 'Loading transactions...') {
    const tbody = document.getElementById('transactionsTableBody');

    if (!tbody) return;

    tbody.innerHTML = `
        <tr>
            <td colspan="9" class="product-empty-state">
                ${adminEscapeHtml(message)}
            </td>
        </tr>
    `;
}

function showTransactionAlert(message, type = 'success') {
    const alert = document.getElementById('transactionAlert');

    if (!alert) return;

    alert.textContent = message;
    alert.dataset.type = type;
    alert.hidden = false;

    window.setTimeout(() => {
        if (alert.textContent === message) {
            alert.hidden = true;
        }
    }, 5000);
}

function getTransactionOptions(transactions, field, defaults = []) {
    const options = new Map();

    defaults.forEach((value) => {
        options.set(value.toLowerCase(), value);
    });

    transactions.forEach((transaction) => {
        const value = String(transaction?.[field] ?? '').trim();

        if (!value) return;

        options.set(value.toLowerCase(), value);
    });

    return Array.from(options.values())
        .sort((a, b) => formatTransactionLabel(a).localeCompare(formatTransactionLabel(b)));
}

function populateTransactionFilters() {
    const statusFilter = document.getElementById('transactionStatusFilter');
    const typeFilter = document.getElementById('transactionTypeFilter');

    if (statusFilter) {
        const selectedValue = statusFilter.value || 'all';
        statusFilter.innerHTML = `
            <option value="all">All Statuses</option>
            ${transactionManagerState.statuses.map((status) => `
                <option value="${adminEscapeHtml(status)}">${adminEscapeHtml(formatTransactionLabel(status))}</option>
            `).join('')}
        `;
        statusFilter.value = selectedValue;

        if (statusFilter.value !== selectedValue) {
            statusFilter.value = 'all';
        }
    }

    if (typeFilter) {
        const selectedValue = typeFilter.value || 'all';
        typeFilter.innerHTML = `
            <option value="all">All Types</option>
            ${transactionManagerState.types.map((type) => `
                <option value="${adminEscapeHtml(type)}">${adminEscapeHtml(formatTransactionLabel(type))}</option>
            `).join('')}
        `;
        typeFilter.value = selectedValue;

        if (typeFilter.value !== selectedValue) {
            typeFilter.value = 'all';
        }
    }
}

function formatTransactionLabel(value) {
    return String(value ?? '')
        .trim()
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (letter) => letter.toUpperCase()) || 'N/A';
}

function getTransactionSearchValue() {
    return String(document.getElementById('transactionSearchInput')?.value ?? '')
        .trim()
        .toLowerCase();
}

function applyTransactionFilters() {
    const statusFilter = document.getElementById('transactionStatusFilter')?.value || 'all';
    const typeFilter = document.getElementById('transactionTypeFilter')?.value || 'all';
    const searchValue = getTransactionSearchValue();

    transactionManagerState.filteredTransactions = transactionManagerState.transactions.filter((transaction) => {
        const matchesStatus = statusFilter === 'all'
            || String(transaction.transaction_status ?? '').toLowerCase() === String(statusFilter).toLowerCase();
        const matchesType = typeFilter === 'all'
            || String(transaction.transaction_type ?? '').toLowerCase() === String(typeFilter).toLowerCase();
        const searchable = [
            transaction.id,
            transaction.order_id,
            transaction.order_number,
            transaction.customer_name,
            transaction.customer_email,
            transaction.transaction_reference,
            transaction.payment_provider,
            transaction.transaction_type,
            transaction.transaction_status,
            transaction.currency_code
        ].join(' ').toLowerCase();
        const matchesSearch = !searchValue || searchable.includes(searchValue);

        return matchesStatus && matchesType && matchesSearch;
    });

    const totalPages = getTransactionTotalPages();

    if (transactionManagerState.currentPage > totalPages) {
        transactionManagerState.currentPage = totalPages;
    }

    updateTransactionMetrics();
    renderTransactions();
}

function updateTransactionMetrics() {
    const total = transactionManagerState.transactions.length;
    const posted = transactionManagerState.transactions.filter((transaction) => Number(transaction.is_posted) === 1).length;
    const filtered = transactionManagerState.filteredTransactions.length;
    const grossAmount = transactionManagerState.transactions.reduce((sum, transaction) => {
        const amount = Number.parseFloat(transaction.transaction_amount ?? 0);

        return sum + (Number.isFinite(amount) ? amount : 0);
    }, 0);

    const totalElement = document.getElementById('transactionTotalCount');
    const postedElement = document.getElementById('transactionPostedCount');
    const amountElement = document.getElementById('transactionGrossAmount');
    const filteredElement = document.getElementById('transactionFilteredCount');

    if (totalElement) totalElement.textContent = String(total);
    if (postedElement) postedElement.textContent = String(posted);
    if (amountElement) amountElement.textContent = formatTransactionMoney(grossAmount, getPrimaryTransactionCurrency());
    if (filteredElement) filteredElement.textContent = String(filtered);
}

function getPrimaryTransactionCurrency() {
    const transaction = transactionManagerState.transactions.find((item) => String(item.currency_code || '').trim());

    return transaction?.currency_code || 'USD';
}

function getTransactionTotalPages() {
    return Math.max(1, Math.ceil(transactionManagerState.filteredTransactions.length / transactionManagerState.perPage));
}

function renderTransactions() {
    const tbody = document.getElementById('transactionsTableBody');

    if (!tbody) return;

    const start = (transactionManagerState.currentPage - 1) * transactionManagerState.perPage;
    const end = start + transactionManagerState.perPage;
    const pageTransactions = transactionManagerState.filteredTransactions.slice(start, end);

    if (pageTransactions.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="product-empty-state">
                    No transactions found.
                </td>
            </tr>
        `;
        updateTransactionPagination();
        return;
    }

    tbody.innerHTML = pageTransactions.map((transaction) => {
        const reference = String(transaction.transaction_reference || '').trim();
        const provider = String(transaction.payment_provider || '').trim();
        const orderLabel = transaction.order_number || `Order #${transaction.order_id || 'N/A'}`;
        const customerName = String(transaction.customer_name || '').trim();
        const customerEmail = String(transaction.customer_email || '').trim();
        const fallbackCustomer = transaction.user_id ? `User #${transaction.user_id}` : 'N/A';
        const displayCustomer = customerName || customerEmail || fallbackCustomer;
        const customerDetail = customerName && customerEmail ? customerEmail : '';
        const visibleBadge = Number(transaction.visible) === 1
            ? '<span class="status-badge active">Visible</span>'
            : '<span class="status-badge muted">Hidden</span>';
        const statusBadge = renderTransactionStatusBadge(transaction);

        return `
            <tr>
                <td>
                    <div class="transaction-reference-cell">
                        <strong>${adminEscapeHtml(reference || `Transaction #${transaction.id}`)}</strong>
                        <small>${adminEscapeHtml(provider || 'No provider')} | ID ${adminEscapeHtml(transaction.id)}</small>
                    </div>
                </td>
                <td>
                    <div class="transaction-order-cell">
                        <strong>${adminEscapeHtml(orderLabel)}</strong>
                        <small>${adminEscapeHtml(formatTransactionLabel(transaction.order_status || 'No status'))}</small>
                    </div>
                </td>
                <td>
                    <div class="transaction-customer-cell">
                        <strong>${adminEscapeHtml(displayCustomer)}</strong>
                        ${customerDetail ? `<small>${adminEscapeHtml(customerDetail)}</small>` : ''}
                    </div>
                </td>
                <td>
                    <span class="status-badge role-badge">
                        ${adminEscapeHtml(formatTransactionLabel(transaction.transaction_type || 'Sale'))}
                    </span>
                </td>
                <td>
                    <div class="status-stack">
                        ${statusBadge}
                        ${visibleBadge}
                    </div>
                </td>
                <td class="money-cell">${formatTransactionMoney(transaction.transaction_amount, transaction.currency_code)}</td>
                <td class="money-cell">${formatTransactionMoney(transaction.net_sales_amount, transaction.currency_code)}</td>
                <td>${formatDateTime(transaction.processed_at || transaction.created_at)}</td>
                <td>
                    <div class="product-actions">
                        <button type="button"
                            class="table-action"
                            data-transaction-action="edit"
                            data-transaction-id="${adminEscapeHtml(transaction.id)}">
                            Edit
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    updateTransactionPagination();
}

function renderTransactionStatusBadge(transaction) {
    const status = String(transaction.transaction_status || '').trim() || 'No status';
    const normalized = status.toLowerCase();
    let className = Number(transaction.is_posted) === 1 ? 'active' : 'muted';

    if (['pending', 'authorized', 'processing'].includes(normalized)) {
        className = 'warning';
    }

    if (['failed', 'declined', 'void', 'voided', 'canceled', 'cancelled', 'expired'].includes(normalized)) {
        className = 'danger';
    }

    return `<span class="status-badge ${className}">${adminEscapeHtml(formatTransactionLabel(status))}</span>`;
}

function updateTransactionPagination() {
    const totalPages = getTransactionTotalPages();
    const pageInfo = document.getElementById('transactionPageInfo');
    const prevButton = document.getElementById('transactionPrevPageBtn');
    const nextButton = document.getElementById('transactionNextPageBtn');

    if (pageInfo) {
        pageInfo.textContent = `Page ${transactionManagerState.currentPage} of ${totalPages}`;
    }

    if (prevButton) {
        prevButton.disabled = transactionManagerState.currentPage <= 1;
    }

    if (nextButton) {
        nextButton.disabled = transactionManagerState.currentPage >= totalPages;
    }
}

function setTransactionFormValue(id, value) {
    const field = document.getElementById(id);

    if (field) {
        field.value = value ?? '';
    }
}

function openTransactionForm(transaction) {
    const form = document.getElementById('transactionForm');
    const dialog = document.getElementById('transactionFormDialog');

    if (!form || !dialog || !transaction) return;

    form.reset();

    setTransactionFormValue('transactionId', transaction.id || '');
    setTransactionFormValue('transactionOrderId', transaction.order_id || '');
    setTransactionFormValue('transactionReference', transaction.transaction_reference || '');
    setTransactionFormValue('transactionProvider', transaction.payment_provider || '');
    setTransactionFormValue('transactionType', transaction.transaction_type || 'sale');
    setTransactionFormValue('transactionStatus', transaction.transaction_status || '');
    setTransactionFormValue('transactionCurrencyCode', transaction.currency_code || 'USD');
    setTransactionFormValue('transactionProcessedAt', formatDateTimeForInput(transaction.processed_at));
    setTransactionFormValue('transactionAmount', transaction.transaction_amount ?? '');
    setTransactionFormValue('transactionProductSalesAmount', transaction.product_sales_amount ?? '0.00');
    setTransactionFormValue('transactionProductCostAmount', transaction.product_cost_amount ?? '0.00');
    setTransactionFormValue('transactionShippingAmount', transaction.shipping_amount ?? '0.00');
    setTransactionFormValue('transactionReturnAmount', transaction.return_amount ?? '0.00');
    setTransactionFormValue('transactionSalesTaxAmount', transaction.sales_tax_amount ?? '0.00');
    setTransactionFormValue('transactionDiscountAmount', transaction.discount_amount ?? '0.00');
    setTransactionFormValue('transactionPaymentFeeAmount', transaction.payment_fee_amount ?? '0.00');

    const visible = document.getElementById('transactionVisible');

    if (visible) {
        visible.checked = Number(transaction.visible) === 1;
    }

    updateTransactionOrderPreview(transaction);
    updateTransactionPreviewAmounts();

    if (typeof dialog.showModal === 'function') {
        dialog.showModal();
    } else {
        dialog.setAttribute('open', '');
    }

    document.getElementById('transactionReference')?.focus();
}

function closeTransactionDialog() {
    const dialog = document.getElementById('transactionFormDialog');

    if (!dialog) return;

    if (typeof dialog.close === 'function') {
        dialog.close();
    } else {
        dialog.removeAttribute('open');
    }
}

function updateTransactionOrderPreview(transaction) {
    const order = document.getElementById('transactionPreviewOrder');
    const customer = document.getElementById('transactionPreviewCustomer');
    const orderTotal = document.getElementById('transactionPreviewOrderTotal');
    const orderLabel = transaction.order_number || `Order #${transaction.order_id || 'N/A'}`;
    const customerName = String(transaction.customer_name || '').trim();
    const customerEmail = String(transaction.customer_email || '').trim();

    if (order) {
        order.textContent = orderLabel;
    }

    if (customer) {
        customer.textContent = customerName || customerEmail || 'N/A';
    }

    if (orderTotal) {
        orderTotal.textContent = formatTransactionMoney(transaction.order_total_amount, transaction.currency_code);
    }
}

function updateTransactionPreviewAmounts() {
    const currencyCode = document.getElementById('transactionCurrencyCode')?.value || 'USD';
    const productSales = getTransactionFormNumber('transactionProductSalesAmount');
    const productCost = getTransactionFormNumber('transactionProductCostAmount');
    const returnAmount = getTransactionFormNumber('transactionReturnAmount');
    const discountAmount = getTransactionFormNumber('transactionDiscountAmount');
    const netSales = Math.max(productSales - returnAmount - discountAmount, 0);
    const grossProfit = Math.max(netSales - productCost, 0);
    const netInput = document.getElementById('transactionNetPreviewInput');
    const grossPreview = document.getElementById('transactionGrossPreview');

    if (netInput) {
        netInput.value = formatTransactionMoney(netSales, currencyCode);
    }

    if (grossPreview) {
        grossPreview.textContent = formatTransactionMoney(grossProfit, currencyCode);
    }
}

function getTransactionFormNumber(id) {
    const value = Number.parseFloat(document.getElementById(id)?.value ?? 0);

    return Number.isFinite(value) ? value : 0;
}

function editTransaction(id) {
    const transaction = transactionManagerState.transactions.find((item) => Number(item.id) === Number(id));

    if (!transaction) {
        showTransactionAlert('Transaction could not be found.', 'error');
        return;
    }

    openTransactionForm(transaction);
}

async function handleTransactionFormSubmit(event) {
    event.preventDefault();

    const form = event.target;
    const saveButton = document.getElementById('saveTransactionBtn');

    if (!form) return;

    if (saveButton) {
        saveButton.disabled = true;
        saveButton.textContent = 'Saving...';
    }

    try {
        const data = await fetchAdminJson('/admin/api/saveTransaction.php', {
            method: 'POST',
            body: new FormData(form)
        });

        closeTransactionDialog();
        showTransactionAlert(data.message || 'Transaction saved.');
        await refreshTransactions();
    } catch (error) {
        console.error('Error saving transaction:', error);
        showTransactionAlert(error.message || 'Unable to save transaction.', 'error');
    } finally {
        if (saveButton) {
            saveButton.disabled = false;
            saveButton.textContent = 'Save Transaction';
        }
    }
}

async function refreshTransactions() {
    setTransactionTableLoading();

    const transactions = await fetchAdminJson('/admin/api/getTransactions.php');

    transactionManagerState.transactions = Array.isArray(transactions) ? transactions : [];
    transactionManagerState.statuses = getTransactionOptions(transactionManagerState.transactions, 'transaction_status', transactionManagerState.statuses);
    transactionManagerState.types = getTransactionOptions(transactionManagerState.transactions, 'transaction_type', transactionManagerState.types);

    populateTransactionFilters();
    applyTransactionFilters();
}

function formatTransactionMoney(value, currencyCode = 'USD') {
    const number = Number.parseFloat(value);
    const currency = String(currencyCode || 'USD').trim().toUpperCase();

    if (!Number.isFinite(number)) {
        return currency === 'USD' ? '$0.00' : `${currency} 0.00`;
    }

    if (/^[A-Z]{3}$/.test(currency)) {
        try {
            return number.toLocaleString('en-US', {
                style: 'currency',
                currency
            });
        } catch (error) {
            return `${currency} ${number.toFixed(2)}`;
        }
    }

    return `$${number.toFixed(2)}`;
}

function formatDateTime(dateString) {
    if (!dateString) {
        return 'N/A';
    }

    const date = new Date(dateString);

    if (Number.isNaN(date.getTime())) {
        return 'N/A';
    }

    return date.toLocaleString('en-US', {
        dateStyle: 'medium',
        timeStyle: 'short'
    });
}
// =========================================
// NEWS AGGREGATOR ADMINISTRATION
// =========================================
let newsAdminState = { csrf: '', sources: [], categories: [], entities: [], newsletters: [], articles: [] };

async function newsAdminRequest(action, options = {}) {
    const method = options.method || 'GET';
    const [actionName, ...queryParts] = action.split('&');
    const querySuffix = queryParts.length ? `&${queryParts.join('&')}` : '';
    const response = await fetch(`/admin/api/news/action.php?action=${encodeURIComponent(actionName)}${querySuffix}`, {
        method,
        headers: method === 'GET' ? {} : { 'Content-Type': 'application/json', 'X-CSRF-Token': newsAdminState.csrf },
        body: method === 'GET' ? undefined : JSON.stringify(options.data || {})
    });
    const payload = await response.json();
    if (!response.ok || !payload.success) throw new Error(payload.message || 'News request failed.');
    return payload;
}

function newsAdminAlert(message, isError = false) {
    const alert = document.getElementById('newsAdminAlert'); if (!alert) return;
    alert.textContent = message; alert.hidden = false; alert.classList.toggle('is-error', isError);
}

function newsEscapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
}

function newsFormData(form) {
    const result = Object.fromEntries(new FormData(form).entries());
    if (typeof result.article_ids === 'string') result.article_ids = result.article_ids.split(',').map(value => value.trim()).filter(Boolean);
    form.querySelectorAll('select[multiple]').forEach(select => result[select.name] = Array.from(select.selectedOptions, option => option.value));
    form.querySelectorAll('input[type="checkbox"]').forEach(input => result[input.name] = input.checked ? '1' : '');
    return result;
}

async function initNewsAdmin() {
    const app = document.getElementById('newsAdminApp'); if (!app || app.dataset.ready) return; app.dataset.ready = '1';
    try {
        const data = await newsAdminRequest('bootstrap'); newsAdminState.csrf = data.csrf_token; newsAdminState.sources = data.sources; newsAdminState.categories = data.categories; newsAdminState.entities = data.entities; newsAdminState.newsletters = data.newsletters || [];
        newsFillSelects(); newsRenderSources(); newsRenderCategories(); await newsLoadDashboard(); await newsLoadArticles();
    } catch (error) { newsAdminAlert(error.message, true); }
    app.addEventListener('click', newsAdminClick);
    document.getElementById('newsSourceForm')?.addEventListener('submit', event => newsSubmitForm(event, 'save_source'));
    document.getElementById('newsManualForm')?.addEventListener('submit', event => newsSubmitForm(event, 'manual_article'));
    document.getElementById('newsArticleForm')?.addEventListener('submit', event => newsSubmitForm(event, 'save_article'));
    document.getElementById('newsCategoryForm')?.addEventListener('submit', event => newsSubmitForm(event, 'save_category'));
    const categoryForm=document.getElementById('newsCategoryForm');if(categoryForm&&!categoryForm.elements.id){const hidden=document.createElement('input');hidden.type='hidden';hidden.name='id';categoryForm.prepend(hidden);}
    const sourceForm=document.getElementById('newsSourceForm');if(sourceForm&&!sourceForm.elements.connector_config_json){const label=document.createElement('label');label.textContent='JSON connector configuration';const textarea=document.createElement('textarea');textarea.name='connector_config_json';textarea.placeholder='{"items_key":"articles","field_map":{"headline":"title"}}';label.append(textarea);sourceForm.querySelector('.product-form-actions')?.before(label);}
    document.getElementById('newsNewsletterForm')?.addEventListener('submit', event => newsSubmitForm(event, 'create_newsletter'));
    document.getElementById('newsEntityForm')?.addEventListener('submit', event => newsSubmitForm(event, 'save_entity'));
    document.getElementById('newsRuleForm')?.addEventListener('submit', event => newsSubmitForm(event, 'save_rule'));
    document.getElementById('newsClusterForm')?.addEventListener('submit', event => newsSubmitForm(event, 'create_cluster'));
    document.getElementById('newsNewsletterArticlesForm')?.addEventListener('submit', event => newsSubmitForm(event, 'newsletter_add_articles'));
    document.getElementById('newsBulkForm')?.addEventListener('submit', newsBulkSubmit);
    document.getElementById('newsImageForm')?.addEventListener('submit', newsImageSubmit);
    document.querySelector('#newsNewsletterArticlesForm select[name="newsletter_id"]')?.addEventListener('change', newsUpdateNewsletterPreview);
    document.getElementById('newsArticleRefresh')?.addEventListener('click', newsLoadArticles);
}

function newsFillSelects() {
    const categoryOptions = '<option value="">Uncategorized</option>' + newsAdminState.categories.map(c => `<option value="${c.id}">${newsEscapeHtml(c.category_name)}</option>`).join('');
    document.querySelectorAll('#newsAdminApp select[name="category_id"],#newsAdminApp select[name="default_category_id"]').forEach(select => select.innerHTML = categoryOptions);
    const sourceOptions = '<option value="">Manual / no source record</option>' + newsAdminState.sources.map(s => `<option value="${s.id}">${newsEscapeHtml(s.source_name)}</option>`).join('');
    document.querySelectorAll('#newsAdminApp select[name="source_id"]').forEach(select => select.innerHTML = sourceOptions);
    const entityOptions = newsAdminState.entities.map(e => `<option value="${e.id}">${newsEscapeHtml(e.entity_name)} (${newsEscapeHtml(e.entity_type)})</option>`).join('');
    document.querySelectorAll('#newsAdminApp select[name="entity_ids"]').forEach(select => select.innerHTML = entityOptions);
    const newsletterOptions = newsAdminState.newsletters.map(n => `<option value="${n.id}">${newsEscapeHtml(n.newsletter_name)} (${newsEscapeHtml(n.status)})</option>`).join('');
    document.querySelectorAll('#newsAdminApp select[name="newsletter_id"]').forEach(select => select.innerHTML = newsletterOptions);
    newsUpdateNewsletterPreview();
}

async function newsLoadDashboard() {
    const data = await newsAdminRequest('dashboard'), m = data.metrics;
    document.getElementById('newsAdminMetrics').innerHTML = [['Active sources',m.active_sources],['Imported today',m.imported_today],['Awaiting review',m.pending],['Published today',m.published_today],['Duplicates',m.duplicates],['Failed imports',m.failed_imports]].map(([label,value]) => `<div><strong>${value}</strong><span>${label}</span></div>`).join('');
    if (m.personalization) document.getElementById('newsAdminMetrics').insertAdjacentHTML('beforeend', [['Followed interests',m.personalization.active_preferences],['Saved stories',m.personalization.saved_articles],['Recorded views',m.personalization.article_views],['Digest failures',m.personalization.digest_failures]].map(([label,value]) => `<div><strong>${value}</strong><span>${label}</span></div>`).join(''));
    document.getElementById('newsImportHistory').innerHTML = m.recent_imports.length ? `<div class="product-table-scroll"><table class="product-table"><thead><tr><th>Source</th><th>Started</th><th>Status</th><th>Imported</th><th>Errors</th></tr></thead><tbody>${m.recent_imports.map(row=>`<tr><td>${newsEscapeHtml(row.source_name||'Unknown')}</td><td>${newsEscapeHtml(row.started_at)}</td><td>${newsEscapeHtml(row.status)}</td><td>${row.items_imported}</td><td>${row.errors_found}</td></tr>`).join('')}</tbody></table></div>` : '<p>No imports recorded yet.</p>';
}

async function newsLoadArticles() {
    const status=document.getElementById('newsArticleStatus')?.value||'pending_review', type=document.getElementById('newsArticleType')?.value||'', q=document.getElementById('newsArticleSearch')?.value||'';
    try { const data=await newsAdminRequest(`articles&status=${encodeURIComponent(status)}&type=${encodeURIComponent(type)}&q=${encodeURIComponent(q)}`); newsAdminState.articles=data.articles; newsRenderArticles(); } catch(error){newsAdminAlert(error.message,true);}
}

function newsRenderArticles() {
    const body=document.getElementById('newsArticleRows'); if(!body)return;
    body.innerHTML=newsAdminState.articles.length?newsAdminState.articles.map(a=>`<tr><td><label><input type="checkbox" data-news-select="${a.id}" aria-label="Select ${newsEscapeHtml(a.headline)}"> <strong>${newsEscapeHtml(a.headline)}</strong></label><small>${newsEscapeHtml(a.source_published_at||a.retrieved_at)}</small></td><td>${newsEscapeHtml(a.source_name||'Manual')}</td><td>${newsEscapeHtml(a.news_type)}</td><td>${newsEscapeHtml(a.relevance_score)}${a.similar_score?`<small>Similar ${a.similar_score}%</small>`:''}</td><td>${newsEscapeHtml(a.status)}</td><td><button class="btn-secondary" data-news-review="${a.id}">Review</button></td></tr>`).join(''):'<tr><td colspan="6" class="product-empty-state">No matching stories.</td></tr>';
}

function newsRenderSources() {
    const body=document.getElementById('newsSourceRows');if(!body)return;
    body.innerHTML=newsAdminState.sources.length?newsAdminState.sources.map(s=>`<tr><td><strong>${newsEscapeHtml(s.source_name)}</strong><small>${newsEscapeHtml(s.feed_url)}</small></td><td>${newsEscapeHtml(s.source_type)}</td><td>${newsEscapeHtml(s.last_error_message||s.last_success_at||'Never')}</td><td>${Number(s.is_active)?'Yes':'No'}</td><td><button class="btn-secondary" data-news-edit-source="${s.id}">Edit</button> <button class="btn-secondary" data-news-test="${s.id}">Test</button> <button class="btn-primary" data-news-import="${s.id}">Import</button></td></tr>`).join(''):'<tr><td colspan="5">No sources yet.</td></tr>';
}

function newsRenderCategories(){const target=document.getElementById('newsCategoryList');if(target)target.innerHTML=`<h2>Categories</h2><div class="news-admin-chip-list">${newsAdminState.categories.map(c=>`<button type="button" data-news-edit-category="${c.id}">${newsEscapeHtml(c.category_name)} <small>${newsEscapeHtml(c.news_type)} · ${Number(c.is_active)?'active':'disabled'}</small></button>`).join('')}</div>`;}

async function newsAdminClick(event) {
    const tab=event.target.closest('[data-news-tab]'); if(tab){document.querySelectorAll('[data-news-tab]').forEach(b=>b.classList.toggle('is-active',b===tab));document.querySelectorAll('[data-news-panel]').forEach(p=>p.hidden=p.dataset.newsPanel!==tab.dataset.newsTab);return;}
    if(event.target.closest('[data-news-close]')){event.target.closest('dialog')?.close();return;}
    const open=event.target.closest('[data-news-open]');if(open){document.getElementById(open.dataset.newsOpen==='source'?'newsSourceDialog':'newsManualDialog')?.showModal();return;}
    const edit=event.target.closest('[data-news-edit-source]');if(edit){const source=newsAdminState.sources.find(s=>String(s.id)===edit.dataset.newsEditSource),form=document.getElementById('newsSourceForm');Object.entries(source||{}).forEach(([key,value])=>{const input=form.elements.namedItem(key);if(!input)return;if(input.type==='checkbox')input.checked=Number(value)===1;else input.value=value??'';});document.getElementById('newsSourceDialog').showModal();return;}
    const editCategory=event.target.closest('[data-news-edit-category]');if(editCategory){const category=newsAdminState.categories.find(c=>String(c.id)===editCategory.dataset.newsEditCategory),form=document.getElementById('newsCategoryForm');Object.entries(category||{}).forEach(([key,value])=>{const input=form.elements.namedItem(key);if(!input)return;if(input.type==='checkbox')input.checked=Number(value)===1;else input.value=value??'';});form.scrollIntoView({behavior:'smooth'});return;}
    const review=event.target.closest('[data-news-review]');if(review){const a=newsAdminState.articles.find(row=>String(row.id)===review.dataset.newsReview),form=document.getElementById('newsArticleForm');Object.entries(a||{}).forEach(([key,value])=>{const input=form.elements.namedItem(key);if(!input)return;if(input.type==='checkbox')input.checked=Number(value)===1;else if(input.type==='datetime-local')input.value=value?String(value).replace(' ','T').slice(0,16):'';else if(input.multiple){const chosen=String(value||'').split(',');Array.from(input.options).forEach(o=>o.selected=chosen.includes(o.value));}else input.value=value??'';});form.querySelector('[data-news-source-link]').href=a.source_url;document.getElementById('newsArticleDialog').showModal();return;}
    const test=event.target.closest('[data-news-test]'),run=event.target.closest('[data-news-import]');if(test||run){const button=test||run;button.disabled=true;try{const data=await newsAdminRequest(test?'test_source':'import_source',{method:'POST',data:{source_id:button.dataset.newsTest||button.dataset.newsImport}});newsAdminAlert(test?`Feed test found ${data.preview.length} preview items.`:`Import complete: ${data.counts.imported} imported, ${data.counts.duplicates} duplicates.`);await newsLoadDashboard();await newsLoadArticles();}catch(error){newsAdminAlert(error.message,true);}finally{button.disabled=false;}return;}
    if(event.target.closest('[data-news-summary]')){const id=document.getElementById('newsArticleForm').elements.id.value;try{await newsAdminRequest('queue_summary',{method:'POST',data:{article_id:id}});newsAdminAlert('Summary queued.');}catch(error){newsAdminAlert(error.message,true);}}
}

async function newsSubmitForm(event, action) {
    event.preventDefault();const button=event.submitter;button.disabled=true;
    try{const data=await newsAdminRequest(action,{method:'POST',data:newsFormData(event.currentTarget)});newsAdminAlert(data.message||'Saved.');event.currentTarget.closest('dialog')?.close();if(action==='save_category')event.currentTarget.reset();if(['save_source','save_category','save_entity','create_newsletter'].includes(action)){const fresh=await newsAdminRequest('bootstrap');newsAdminState={...newsAdminState,csrf:fresh.csrf_token,sources:fresh.sources,categories:fresh.categories,entities:fresh.entities,newsletters:fresh.newsletters||[]};newsFillSelects();newsRenderSources();newsRenderCategories();}if(action==='save_article'||action==='manual_article')await newsLoadArticles();}
    catch(error){newsAdminAlert(error.message,true);}finally{button.disabled=false;}
}

function newsUpdateNewsletterPreview(){const select=document.querySelector('#newsNewsletterArticlesForm select[name="newsletter_id"]'),link=document.getElementById('newsNewsletterPreview');if(link)link.href=select?.value?`/admin/newsletter-preview.php?id=${encodeURIComponent(select.value)}`:'#';}
async function newsBulkSubmit(event){event.preventDefault();const ids=Array.from(document.querySelectorAll('[data-news-select]:checked'),input=>input.dataset.newsSelect);if(!ids.length){newsAdminAlert('Select at least one article.',true);return;}try{await newsAdminRequest('bulk_articles',{method:'POST',data:{article_ids:ids,status:event.currentTarget.elements.status.value}});newsAdminAlert(`${ids.length} articles updated.`);await newsLoadArticles();await newsLoadDashboard();}catch(error){newsAdminAlert(error.message,true);}}
async function newsImageSubmit(event){event.preventDefault();const form=event.currentTarget,data=new FormData(form),button=event.submitter;button.disabled=true;try{const response=await fetch('/admin/api/news/upload-image.php',{method:'POST',headers:{'X-CSRF-Token':newsAdminState.csrf},body:data}),payload=await response.json();if(!response.ok||!payload.success)throw new Error(payload.message||'Upload failed.');newsAdminAlert(`Image uploaded at ${payload.image.path}; approve it during article review.`);form.reset();}catch(error){newsAdminAlert(error.message,true);}finally{button.disabled=false;}}

// =========================================
// EMAIL TOOLS
// =========================================

let emailToolsState = { csrf: '', newsletters: [], newsletter_templates: [], campaigns: [], leads: [], templates: [], signatures: [], metrics: {} };

async function initEmailTools() {
    const app = document.getElementById('emailToolsApp');
    if (!app) return;
    app.querySelectorAll('[data-email-tab]').forEach(button => button.addEventListener('click', () => {
        app.querySelectorAll('[data-email-tab]').forEach(item => item.classList.toggle('is-active', item === button));
        app.querySelectorAll('[data-email-panel]').forEach(panel => panel.hidden = panel.dataset.emailPanel !== button.dataset.emailTab);
    }));
    app.querySelectorAll('[data-editor-command]').forEach(button => button.addEventListener('click', () => {
        document.execCommand(button.dataset.editorCommand, false, button.dataset.editorValue || null);
        document.getElementById('emailNewsletterEditor')?.focus();
    }));
    app.querySelector('[data-editor-link]')?.addEventListener('click', () => {
        const url = window.prompt('Enter an https:// link:');
        if (url && /^https:\/\//i.test(url)) document.execCommand('createLink', false, url);
    });
    document.getElementById('emailNewsletterTemplateForm')?.addEventListener('submit', event => {
        event.currentTarget.elements.html_body.value = document.getElementById('emailNewsletterEditor')?.innerHTML || '';
        emailToolsSubmit(event, 'save_newsletter_template');
    });
    document.getElementById('emailNewsletterTemplateCancelButton')?.addEventListener('click', emailNewsletterTemplateResetForm);
    document.getElementById('emailNewsletterTemplateRows')?.addEventListener('click', async event => {
        const editButton = event.target.closest('[data-edit-newsletter-template]');
        if (editButton) {
            emailNewsletterTemplateEdit(Number(editButton.dataset.editNewsletterTemplate));
            return;
        }
        const deleteButton = event.target.closest('[data-delete-newsletter-template]');
        if (deleteButton && window.confirm('Delete this newsletter template? This cannot be undone.')) {
            await emailToolsAction('delete_newsletter_template', { id: deleteButton.dataset.deleteNewsletterTemplate }, deleteButton);
            if (String(document.getElementById('emailNewsletterTemplateForm')?.elements.id.value) === String(deleteButton.dataset.deleteNewsletterTemplate)) emailNewsletterTemplateResetForm();
        }
    });
    document.getElementById('emailNewsletterSendForm')?.addEventListener('submit', event => emailToolsSubmit(event, 'send_newsletter', 'Queue this newsletter for all eligible customer accounts?'));
    document.getElementById('emailLeadImportForm')?.addEventListener('submit', event => emailToolsSubmit(event, 'import_leads'));
    document.getElementById('emailSignatureForm')?.addEventListener('submit', event => emailToolsSubmit(event, 'save_signature'));
    document.getElementById('emailTemplateForm')?.addEventListener('submit', event => emailToolsSubmit(event, 'save_template'));
    document.getElementById('emailTemplateCancelButton')?.addEventListener('click', emailTemplateResetForm);
    document.getElementById('emailSalesTemplateRows')?.addEventListener('click', async event => {
        const editButton = event.target.closest('[data-edit-sales-template]');
        if (editButton) {
            emailTemplateEdit(Number(editButton.dataset.editSalesTemplate));
            return;
        }
        const deleteButton = event.target.closest('[data-delete-sales-template]');
        if (deleteButton && window.confirm('Delete this sales email template? This cannot be undone.')) {
            await emailToolsAction('delete_template', { id: deleteButton.dataset.deleteSalesTemplate }, deleteButton);
            if (String(document.getElementById('emailTemplateForm')?.elements.id.value) === String(deleteButton.dataset.deleteSalesTemplate)) emailTemplateResetForm();
        }
    });
    document.getElementById('emailCampaignForm')?.addEventListener('submit', event => emailToolsSubmit(event, 'send_campaign', 'Queue this campaign for every eligible active lead?'));
    document.getElementById('emailConfigForm')?.addEventListener('submit', emailConfigSubmit);
    await emailToolsRefresh();
}

async function emailToolsRefresh() {
    try {
        const response = await fetch('/admin/api/email-tools.php?action=bootstrap', { cache: 'no-store', headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'Unable to load Email Tools.');
        emailToolsState = { ...emailToolsState, ...data, csrf: data.csrf_token };
        emailToolsRender();
    } catch (error) { emailToolsAlert(error.message || 'Unable to load Email Tools.', true); }
}

async function emailToolsSubmit(event, action, confirmation = '') {
    event.preventDefault();
    if (confirmation && !window.confirm(confirmation)) return;
    const form = event.currentTarget, button = form.querySelector('button[type="submit"]'), original = button?.textContent;
    let completed = false;
    if (button) { button.disabled = true; button.textContent = 'Working…'; }
    try {
        const body = new FormData(form); body.set('action', action); body.set('csrf_token', emailToolsState.csrf);
        const response = await fetch('/admin/api/email-tools.php', { method: 'POST', body, headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'The request could not be completed.');
        emailToolsAlert(data.message || 'Saved.'); form.reset();
        if (action === 'save_newsletter_template') emailNewsletterTemplateResetForm();
        if (action === 'save_template') emailTemplateResetForm();
        completed = true;
        await emailToolsRefresh();
    } catch (error) { emailToolsAlert(error.message || 'The request could not be completed.', true); }
    finally { if (button) { button.disabled = false; if (!completed || !['save_newsletter_template','save_template'].includes(action)) button.textContent = original; } }
}

async function emailToolsAction(action, values, button) {
    const original = button?.textContent;
    if (button) { button.disabled = true; button.textContent = 'Working…'; }
    try {
        const body = new FormData(); body.set('action', action); body.set('csrf_token', emailToolsState.csrf);
        Object.entries(values).forEach(([key, value]) => body.set(key, value));
        const response = await fetch('/admin/api/email-tools.php', { method: 'POST', body, headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || 'The request could not be completed.');
        emailToolsAlert(data.message || 'Queued.'); await emailToolsRefresh();
    } catch (error) { emailToolsAlert(error.message || 'The request could not be completed.', true); }
    finally { if (button) { button.disabled = false; button.textContent = original; } }
}

function emailToolsRender() {
    const smtp = document.getElementById('emailToolsSmtp');
    if (smtp) { smtp.textContent = emailToolsState.smtp_configured ? 'SMTP configured' : 'SMTP setup required'; smtp.classList.toggle('is-ready', Boolean(emailToolsState.smtp_configured)); }
    const signatureOptions = emailToolsState.signatures.map(item => `<option value="${Number(item.id)}">${emailToolsEscape(item.signature_name)}${Number(item.is_default) ? ' (default)' : ''}</option>`).join('');
    document.querySelectorAll('#emailToolsApp select[name="signature_id"]').forEach(select => select.innerHTML = `<option value="">No signature</option>${signatureOptions}`);
    const templateOptions = emailToolsState.templates.map(item => `<option value="${Number(item.id)}">${emailToolsEscape(item.template_name)}</option>`).join('');
    document.querySelectorAll('#emailToolsApp select[name="template_id"]').forEach(select => select.innerHTML = `<option value="">Choose a template</option>${templateOptions}`);
    const newsletterTemplateOptions = emailToolsState.newsletter_templates.map(item => `<option value="${Number(item.id)}">${emailToolsEscape(item.template_name)}</option>`).join('');
    document.querySelectorAll('#emailToolsApp select[name="newsletter_template_id"]').forEach(select => select.innerHTML = `<option value="">Choose a template</option>${newsletterTemplateOptions}`);
    const newsletterTemplateRows = document.getElementById('emailNewsletterTemplateRows');
    if (newsletterTemplateRows) newsletterTemplateRows.innerHTML = emailToolsState.newsletter_templates.length ? emailToolsState.newsletter_templates.map(item => `<tr><td><strong>${emailToolsEscape(item.template_name)}</strong></td><td>${emailToolsEscape(item.subject_template)}</td><td>${emailToolsDate(item.updated_at)}</td><td><div class="email-template-row-actions"><button type="button" class="btn-secondary btn-small" data-edit-newsletter-template="${Number(item.id)}">Edit</button><button type="button" class="btn-danger btn-small" data-delete-newsletter-template="${Number(item.id)}">Delete</button></div></td></tr>`).join('') : '<tr><td colspan="4">No newsletter templates have been saved.</td></tr>';
    const newsletters = document.getElementById('emailNewsletterRows');
    if (newsletters) newsletters.innerHTML = emailToolsState.newsletters.length ? emailToolsState.newsletters.map(item => `<tr><td><strong>${emailToolsEscape(item.newsletter_name)}</strong><small>${emailToolsEscape(item.subject)}</small></td><td>${emailToolsEscape(item.template_name || 'Legacy template')}</td><td><span class="email-status email-status-${emailToolsEscape(item.status)}">${emailToolsEscape(item.status)}</span></td><td>${Number(item.recipient_count || 0)}</td><td>${Number(item.sent_count || 0)}</td><td>${Number(item.failed_count || 0)} / ${Number(item.skipped_count || 0)}</td></tr>`).join('') : '<tr><td colspan="6">No newsletters have been sent.</td></tr>';
    const campaigns = document.getElementById('emailCampaignRows');
    if (campaigns) campaigns.innerHTML = emailToolsState.campaigns.length ? emailToolsState.campaigns.map(item => `<tr><td><strong>${emailToolsEscape(item.campaign_name)}</strong><small>${emailToolsDate(item.queued_at)}</small></td><td>${emailToolsEscape(item.template_name)}</td><td><span class="email-status email-status-${emailToolsEscape(item.status)}">${emailToolsEscape(item.status)}</span></td><td>${Number(item.recipient_count || 0)}</td><td>${Number(item.sent_count || 0)}</td><td>${Number(item.failed_count || 0)} / ${Number(item.skipped_count || 0)}</td></tr>`).join('') : '<tr><td colspan="6">No campaigns have been sent.</td></tr>';
    const leads = document.getElementById('emailLeadRows');
    if (leads) leads.innerHTML = emailToolsState.leads.length ? emailToolsState.leads.map(item => `<tr><td><strong>${emailToolsEscape(`${item.first_name || ''} ${item.last_name || ''}`.trim())}</strong><small>${emailToolsEscape(item.email_address)}</small></td><td>${emailToolsEscape(item.company || '—')}</td><td>${emailToolsEscape(item.status)}</td><td>${Number(item.total_emails_sent || 0)}</td><td>${emailToolsDate(item.last_contacted_at)}</td></tr>`).join('') : '<tr><td colspan="5">No leads have been imported.</td></tr>';
    const salesTemplates = document.getElementById('emailSalesTemplateRows');
    if (salesTemplates) salesTemplates.innerHTML = emailToolsState.templates.length ? emailToolsState.templates.map(item => {
        const preview = String(item.body_template || '').replace(/\s+/g, ' ').trim();
        return `<tr><td><strong>${emailToolsEscape(item.template_name)}</strong></td><td>${emailToolsEscape(item.subject_template)}</td><td>${emailToolsEscape(preview.length > 110 ? `${preview.slice(0,110)}…` : preview)}</td><td>${emailToolsEscape(item.signature_name || 'None')}</td><td>${emailToolsDate(item.updated_at)}</td><td><div class="email-template-row-actions"><button type="button" class="btn-secondary btn-small" data-edit-sales-template="${Number(item.id)}">Edit</button><button type="button" class="btn-danger btn-small" data-delete-sales-template="${Number(item.id)}">Delete</button></div></td></tr>`;
    }).join('') : '<tr><td colspan="6">No sales email templates have been saved.</td></tr>';
    const metrics = document.getElementById('emailSalesMetrics');
    if (metrics) metrics.innerHTML = [['Leads',emailToolsState.metrics.lead_count],['Active leads',emailToolsState.metrics.active_lead_count],['Completed campaigns',emailToolsState.metrics.completed_campaign_count],['Sales emails sent',emailToolsState.metrics.sales_sent_count],['Suppressed addresses',emailToolsState.metrics.suppression_count]].map(([label,value]) => `<div><strong>${Number(value || 0)}</strong><span>${label}</span></div>`).join('');
    const summary = document.getElementById('emailNewsletterSummary');
    if (summary) { const sent=emailToolsState.newsletters.reduce((sum,item)=>sum+Number(item.sent_count||0),0),active=emailToolsState.newsletters.filter(item=>['queued','sending'].includes(item.status)).length; summary.innerHTML=`<div><dt>Active queues</dt><dd>${active}</dd></div><div><dt>Total delivered</dt><dd>${sent}</dd></div><div><dt>Batch interval</dt><dd>5 minutes</dd></div>`; }
    emailConfigRender();
}

function emailToolsAlert(message, isError = false) { const alert=document.getElementById('emailToolsAlert'); if(!alert)return; alert.textContent=message; alert.classList.toggle('is-error',isError); alert.classList.toggle('is-success',!isError); alert.hidden=false; alert.scrollIntoView({behavior:'smooth',block:'nearest'}); }
function emailToolsEscape(value) { const node=document.createElement('div'); node.textContent=String(value??''); return node.innerHTML; }
function emailToolsDate(value) { if(!value)return '—'; const date=new Date(String(value).replace(' ','T')); return Number.isNaN(date.getTime())?emailToolsEscape(value):emailToolsEscape(date.toLocaleString()); }

function emailNewsletterTemplateEdit(id) {
    const template=emailToolsState.newsletter_templates.find(item=>Number(item.id)===Number(id)),form=document.getElementById('emailNewsletterTemplateForm');
    if(!template||!form){emailToolsAlert('The newsletter template could not be found.',true);return;}
    form.elements.id.value=template.id;
    form.elements.template_name.value=template.template_name||'';
    form.elements.subject.value=template.subject_template||'';
    document.getElementById('emailNewsletterEditor').innerHTML=template.html_body||'';
    form.elements.html_body.value=template.html_body||'';
    document.getElementById('emailNewsletterTemplateFormTitle').textContent='Edit newsletter template';
    document.getElementById('emailNewsletterTemplateSaveButton').textContent='Update Newsletter Template';
    document.getElementById('emailNewsletterTemplateCancelButton').hidden=false;
    form.scrollIntoView({behavior:'smooth',block:'start'});
}

function emailNewsletterTemplateResetForm() {
    const form=document.getElementById('emailNewsletterTemplateForm');if(!form)return;
    form.reset();form.elements.id.value='';form.elements.html_body.value='';
    document.getElementById('emailNewsletterEditor').innerHTML='<p>Hello {FirstName},</p><p>Write your newsletter here.</p>';
    document.getElementById('emailNewsletterTemplateFormTitle').textContent='Create newsletter template';
    document.getElementById('emailNewsletterTemplateSaveButton').textContent='Save Newsletter Template';
    document.getElementById('emailNewsletterTemplateCancelButton').hidden=true;
}

function emailTemplateEdit(id) {
    const template=emailToolsState.templates.find(item=>Number(item.id)===Number(id)),form=document.getElementById('emailTemplateForm');
    if(!template||!form){emailToolsAlert('The sales template could not be found.',true);return;}
    form.elements.id.value=template.id;
    form.elements.template_name.value=template.template_name||'';
    form.elements.subject_template.value=template.subject_template||'';
    form.elements.body_template.value=template.body_template||'';
    form.elements.signature_id.value=template.signature_id||'';
    document.getElementById('emailTemplateFormTitle').textContent='Edit outreach template';
    document.getElementById('emailTemplateSaveButton').textContent='Update Template';
    document.getElementById('emailTemplateCancelButton').hidden=false;
    form.scrollIntoView({behavior:'smooth',block:'start'});
}

function emailTemplateResetForm() {
    const form=document.getElementById('emailTemplateForm');if(!form)return;
    form.reset();form.elements.id.value='';
    document.getElementById('emailTemplateFormTitle').textContent='Outreach template';
    document.getElementById('emailTemplateSaveButton').textContent='Save Template';
    document.getElementById('emailTemplateCancelButton').hidden=true;
}

function emailConfigRender() {
    const form = document.getElementById('emailConfigForm');
    if (!form) return;
    Object.entries(emailToolsState.email_configuration || {}).forEach(([key, value]) => {
        const input = form.elements.namedItem(key);
        if (input && !key.endsWith('_configured') && key !== 'source') input.value = value ?? '';
    });
    const status = document.getElementById('emailConfigStatus');
    status.textContent = emailToolsState.smtp_configured ? 'Both accounts ready' : 'Configuration incomplete';
    status.classList.toggle('is-ready', Boolean(emailToolsState.smtp_configured));
    const keyReady = emailToolsState.email_configuration?.encryption_key_configured;
    for (const profile of ['newsletter','sales']) {
        const configured = emailToolsState.email_configuration?.[`${profile}_password_configured`];
        const help = document.getElementById(`${profile}PasswordHelp`);
        if (help) help.textContent = `${configured ? 'A password is saved. Leave this field blank to keep it.' : 'No password is currently saved.'} ${keyReady ? 'Password encryption is ready.' : 'A protected encryption key will be created when you save.'}`;
    }
}

async function emailConfigSubmit(event) {
    event.preventDefault();
    const form=event.currentTarget,button=form.querySelector('button[type="submit"]'),original=button.textContent;
    button.disabled=true;button.textContent='Saving…';
    try {
        const body=new FormData(form);body.set('action','save_email_configuration');body.set('csrf_token',emailToolsState.csrf);
        const response=await fetch('/admin/api/email-tools.php',{method:'POST',body,headers:{Accept:'application/json'}}),data=await response.json();
        if(!response.ok||!data.success)throw new Error(data.message||'Unable to save email configuration.');
        form.elements.newsletter_smtp_password.value='';form.elements.sales_smtp_password.value='';
        emailConfigAlert(data.message||'Email configuration saved.');await emailToolsRefresh();
    } catch(error) { emailConfigAlert(error.message||'Unable to save email configuration.',true); }
    finally { button.disabled=false;button.textContent=original; }
}

function emailConfigAlert(message, isError = false) { const alert=document.getElementById('emailConfigAlert'); if(!alert)return; alert.textContent=message; alert.classList.toggle('is-error',isError); alert.classList.toggle('is-success',!isError); alert.hidden=false; }
