// =========================================
// DYNAMIC PAGE LOADER UX
// =========================================

async function loadPage(pageName, options = {}) {
    options = options || {};

    try {
        if (pageName === 'travelstore') {
            options = {
                ...options,
                category: prepareTravelStoreNavigation(options.category)
            };
        }

        const publicStorePages = ['travelstore', 'productdetails'];
        const shouldUseCustomerPage = isCustomerArea()
            && !options.publicPage
            && !publicStorePages.includes(pageName);
        const pagePath = shouldUseCustomerPage
            ? `/customer/pages/${pageName}.php`
            : `/pages/${pageName}.php`;
        const response = await fetch(pagePath);
        const content = await response.text();

        if (!response.ok) {
            throw new Error(`Unable to load ${pagePath}: ${response.status}`);
        }

        document.getElementById('content').innerHTML = content;

        /* =========================================
           PAGE-SPECIFIC LOADERS
        ========================================= */

            switch (pageName) {

                // Page loader for cruise lines data
                case 'cruiselines':
                    loadCruiseLines();
                    break;

                case 'portinfo':
                    loadPorts();
                    break;

                case 'port':
                    loadPortPage(options.portId || null);
                    break;

                case 'ship':
                    loadShipPage(options.shipId || null);
                    break;

                // Page loader for resorts data
                case 'resorts':
                    loadResorts();
                    break;

                // Page loader for destination data
                case 'destinations':
                    loadDestinations();
                    break;

                case 'travelstore':
                    initTravelStore(options.category || null);
                    break; 

                case 'productdetails':
                    loadProductDetails();
                    break;

                case 'userregistration':
                    loadStateProvinces("state_prov");
                    break;

                case 'faq':
                    loadFAQ();
                    break;

                case 'blog':
                    initBlogArticles();
                    break;

                case 'home':
                    if (isCustomerArea()) {
                        initCustomerProfile();
                        initCustomerNews('profile');
                    }
                    break;
                case 'newspreferences':
                case 'mynews':
                case 'savednews':
                    initCustomerNews(pageName);
                    break;
                    
                // Page loaders for future pages
                // case 'travelstore':
                //     loadProducts();
                //     break;

                default:
                    loadCruiseLinePage();
                    break;
            }

        // Update document title
        updatePageTitle();

        const shouldFocusNewsPreferences = pageName === 'home'
            && isCustomerArea()
            && new URLSearchParams(window.location.search).get('section') === 'news';

        if (shouldFocusNewsPreferences) {
            window.setTimeout(() => {
                document.getElementById('customerNewsPreferences')?.scrollIntoView({
                    behavior: 'auto',
                    block: 'start'
                });
            }, 150);
        } else {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

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
    initShoppingCart();
    resumeCheckoutPromptFromUrl();

    // Only run SPA if the page has the content container
    const content = document.getElementById('content');

    // List of pages that should NOT use the SPA loader
    const standalonePages = [
        'login.php',
        'customerregistration.php',
        'resetpassword.php',
        'registrationsuccessful.php',
        'registrationfailed.php',
        'blogdetails.php',
        'products.php',
        'ducks.php',
        'duckhistory.php'
        ,'news.php'
    ];

    const currentPage = window.location.pathname.split('/').pop();

    const isStandalonePage = standalonePages.includes(currentPage);

    if (currentPage === 'products.php') {
        initTravelStore();
        updatePageTitle();
        return;
    }

    if (currentPage === 'ducks.php' || currentPage === 'duckhistory.php') {
        updatePageTitle();
        return;
    }

    if (content && !isStandalonePage) {
        loadPage('home');
    }
});

// =========================================
// UPDATE PAGE TITLE UX
// =========================================

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
// CRUISELINES CARD UX
// =========================================

async function loadCruiseLines() {
    // Get the HTML container where cards will be inserted
    const container = document.getElementById('cruiseLinesContainer');

    // Stop if container does not exist
    if (!container) return;

    const url = '/api/getCruiseLines.php';

    try {
        const res = await fetch(url);

        if (!res.ok) {
            throw new Error(`HTTP error! Status: ${res.status}`);
        }

        const cruiseLines = await res.json();

        // Clear loading message
        container.innerHTML = '';

        // Loop through each cruise line
        cruiseLines.forEach(cruise => {
            if (!cruise.page) {
                return;
            }

            // Make the card itself the link, using the database page value.
            const card = document.createElement('a');
            card.classList.add('card', 'cruise-line-card');
            card.href = cruise.page;
            card.setAttribute('aria-label', `View ${cruise.cruise_line_name}`);
            card.addEventListener('click', event => {
                openCruiseLinePage(event, cruise.page);
            });

            // Build card HTML
            const imageWidth = Number.parseInt(cruise.image_size, 10);
            const widthAttribute = Number.isFinite(imageWidth) && imageWidth > 0
                ? ` width="${imageWidth}"`
                : '';

            card.innerHTML = `
                <img src="${escapeHtml(getDatabaseImageUrl(cruise.image_url, '/images/cruiselines/'))}"
                     alt="${escapeHtml(cruise.cruise_line_name)}"
                     class="cruise-line-logo"
                     ${widthAttribute}>
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

function getDatabaseImageUrl(imageUrl, defaultDirectory) {
    const value = String(imageUrl || '').trim();

    if (!value) {
        return '';
    }

    if (/^https?:\/\//i.test(value) || value.startsWith('/')) {
        return value;
    }

    return `${defaultDirectory}${value}`;
}

function getSafeWebsiteUrl(websiteUrl) {
    const value = String(websiteUrl || '').trim();

    if (/^https?:\/\//i.test(value)) {
        return value;
    }

    if (/^(?:www\.)?[a-z0-9.-]+\.[a-z]{2,}(?:[/?#][^\s]*)?$/i.test(value)) {
        return `https://${value}`;
    }

    return '';
}

function formatDatabaseText(value) {
    return escapeHtml(String(value || '')).replace(/\r?\n/g, '<br>');
}

function getPageNameFromPath(pagePath) {
    const match = String(pagePath || '').match(/^\/pages\/([a-z0-9]+)\.php$/i);
    return match ? match[1] : '';
}

function openCruiseLinePage(event, pagePath) {
    const pageName = getPageNameFromPath(pagePath);

    if (!pageName) {
        return;
    }

    event.preventDefault();
    loadPage(pageName, { publicPage: true });
}

async function loadCruiseLinePage() {
    const pageSection = document.querySelector('.cruise-line-detail[data-cruise-line-page]');
    const container = document.getElementById('cruiseLineDetail');

    if (!pageSection || !container) {
        return;
    }

    const pagePath = pageSection.dataset.cruiseLinePage;

    try {
        const response = await fetch(`/api/getCruiseLinePage.php?page=${encodeURIComponent(pagePath)}`);
        const data = await response.json();

        if (!response.ok || data.error) {
            throw new Error(data.error || `HTTP error! Status: ${response.status}`);
        }

        renderCruiseLinePage(data.cruise_line, data.ships || [], container);
    } catch (error) {
        console.error('Error loading cruise-line page:', error);
        container.innerHTML = '<div class="card"><p>Unable to load cruise-line data.</p></div>';
    }
}

function renderCruiseLinePage(cruiseLine, ships, container) {
    const logoUrl = getDatabaseImageUrl(cruiseLine.image_url, '/images/cruiselines/');
    const websiteUrl = getSafeWebsiteUrl(cruiseLine.website_url);
    const shipCount = Number.parseInt(cruiseLine.ship_count, 10) || 0;
    const meta = document.getElementById('page-title-meta');

    if (meta) {
        meta.dataset.title = `Norman and Company | ${cruiseLine.cruise_line_name}`;
        updatePageTitle();
    }

    const aboutDetails = [
        `<p><strong>Cruise Line:</strong> ${formatDatabaseText(cruiseLine.cruise_line_name)}</p>`,
        cruiseLine.parent_company
            ? `<p><strong>Parent Company:</strong> ${formatDatabaseText(cruiseLine.parent_company)}</p>`
            : '',
        cruiseLine.headquarters_location
            ? `<p><strong>Headquarters:</strong> ${formatDatabaseText(cruiseLine.headquarters_location)}</p>`
            : '',
        websiteUrl
            ? `<p><strong>Website:</strong> <a href="${escapeHtml(websiteUrl)}" target="_blank" rel="noopener noreferrer">${escapeHtml(cruiseLine.website_url)}</a></p>`
            : ''
    ].join('');

    const shipLinks = ships.length
        ? `<ul class="cruise-ship-list">${ships.map(ship => `
            <li>
                <a href="/pages/ship.php?id=${encodeURIComponent(ship.id)}"
                   onclick="openShipPage(event, ${Number.parseInt(ship.id, 10)})">
                    ${escapeHtml(ship.ship_name)}
                </a>
            </li>
        `).join('')}</ul>`
        : '';

    container.innerHTML = `
        <header class="cruise-line-detail-header">
            ${logoUrl ? `
                <img src="${escapeHtml(logoUrl)}"
                     alt="${escapeHtml(cruiseLine.cruise_line_name)} logo"
                     class="cruise-line-detail-logo">
            ` : ''}
        </header>

        <div class="cruise-line-info-grid">
            <article class="card cruise-line-info-card">
                <h2>About</h2>
                ${cruiseLine.description ? `<p>${formatDatabaseText(cruiseLine.description)}</p>` : ''}
                ${aboutDetails}
            </article>

            <article class="card cruise-line-info-card">
                <h2>History</h2>
                ${cruiseLine.history ? `<p>${formatDatabaseText(cruiseLine.history)}</p>` : ''}
            </article>

            <article class="card cruise-line-info-card cruise-line-fleet-card">
                <h2>Fleet</h2>
                <p><strong>${shipCount}</strong> ${shipCount === 1 ? 'ship' : 'ships'}</p>
                ${shipLinks}
            </article>

            <article class="card cruise-line-info-card">
                <h2>Cruising Areas</h2>
                ${cruiseLine.cruising_area ? `<p>${formatDatabaseText(cruiseLine.cruising_area)}</p>` : ''}
            </article>
        </div>
    `;
}

// =========================================
// PORT CARD AND DETAIL UX
// =========================================

async function loadPorts() {
    const container = document.getElementById('portsContainer');

    if (!container) {
        return;
    }

    try {
        const response = await fetch('/api/getPorts.php');
        const ports = await response.json();

        if (!response.ok || !Array.isArray(ports)) {
            throw new Error(ports.error || `HTTP error! Status: ${response.status}`);
        }

        container.innerHTML = '';

        ports.forEach(port => {
            const portId = Number.parseInt(port.id, 10);

            if (!portId) {
                return;
            }

            const card = document.createElement('a');
            card.classList.add('card', 'port-card');
            card.href = `/pages/port.php?id=${encodeURIComponent(portId)}`;
            card.setAttribute('aria-label', `View ${port.port_name}`);
            card.addEventListener('click', event => {
                openPortPage(event, portId);
            });

            card.innerHTML = `
                <h2>${formatDatabaseText(port.port_name)}</h2>
                ${port.city_name ? `<p>${formatDatabaseText(port.city_name)}</p>` : ''}
                ${port.country_name ? `<p>${formatDatabaseText(port.country_name)}</p>` : ''}
            `;

            container.appendChild(card);
        });

        if (!ports.length) {
            container.innerHTML = '<div class="card"><p>No ports are currently available.</p></div>';
        }
    } catch (error) {
        console.error('Error loading ports:', error);
        container.innerHTML = '<div class="card"><h3>Error</h3><p>Unable to load port data.</p></div>';
    }
}

function openPortPage(event, portId) {
    event.preventDefault();
    loadPage('port', {
        publicPage: true,
        portId
    });
}

async function loadPortPage(portId) {
    const container = document.getElementById('portDetail');
    let id = Number.parseInt(portId, 10);

    if (!container) {
        return;
    }

    if (!id) {
        id = Number.parseInt(new URLSearchParams(window.location.search).get('id'), 10);
    }

    if (!id) {
        container.innerHTML = '<div class="card"><p>A valid port was not selected.</p></div>';
        return;
    }

    try {
        const response = await fetch(`/api/getPortPage.php?id=${encodeURIComponent(id)}`);
        const data = await response.json();

        if (!response.ok || data.error) {
            throw new Error(data.error || `HTTP error! Status: ${response.status}`);
        }

        renderPortPage(data.port, container);
    } catch (error) {
        console.error('Error loading port information:', error);
        container.innerHTML = '<div class="card"><p>Unable to load port information.</p></div>';
    }
}

function renderPortPage(port, container) {
    const latitude = Number.parseFloat(port.latitude);
    const longitude = Number.parseFloat(port.longitude);
    const hasCoordinates = Number.isFinite(latitude)
        && Number.isFinite(longitude)
        && latitude >= -90
        && latitude <= 90
        && longitude >= -180
        && longitude <= 180;
    const meta = document.getElementById('page-title-meta');

    if (meta) {
        meta.dataset.title = `Norman and Company | ${port.port_name}`;
        updatePageTitle();
    }

    const details = [
        ['Port name', port.port_name],
        ['City', port.city_name],
        ['State / Province', port.state_name],
        ['Country', port.country_name],
        ['Destination ID', port.destination_id]
    ].filter(([, value]) => value !== null && value !== undefined && String(value) !== '');

    let mapMarkup = '<p>Map coordinates are not available for this port.</p>';

    if (hasCoordinates) {
        const latitudeDelta = 0.04;
        const longitudeDelta = 0.06;
        const bounds = [
            longitude - longitudeDelta,
            latitude - latitudeDelta,
            longitude + longitudeDelta,
            latitude + latitudeDelta
        ].join(',');
        const mapUrl = `https://www.openstreetmap.org/export/embed.html?bbox=${encodeURIComponent(bounds)}&layer=mapnik&marker=${encodeURIComponent(`${latitude},${longitude}`)}`;
        const fullMapUrl = `https://www.openstreetmap.org/?mlat=${encodeURIComponent(latitude)}&mlon=${encodeURIComponent(longitude)}#map=14/${encodeURIComponent(latitude)}/${encodeURIComponent(longitude)}`;

        mapMarkup = `
            <iframe
                class="port-map"
                src="${escapeHtml(mapUrl)}"
                title="Map showing ${escapeHtml(port.port_name)}"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
            <p class="port-map-link">
                <a href="${escapeHtml(fullMapUrl)}" target="_blank" rel="noopener noreferrer">
                    View Larger Map
                </a>
            </p>
        `;
    }

    container.innerHTML = `
        <p><a href="/pages/portinfo.php" onclick="loadPage('portinfo', { publicPage: true }); return false;">&larr; Back to all ports</a></p>

        <header class="port-detail-header">
            <div>
                <h1>${formatDatabaseText(port.port_name)}</h1>
                ${port.city_name || port.state_name || port.country_name ? `
                    <p>${[port.city_name, port.state_name, port.country_name]
                        .filter(Boolean)
                        .map(formatDatabaseText)
                        .join(', ')}</p>
                ` : ''}
            </div>
        </header>

        <div class="port-detail-grid">
            <div class="port-detail-main">
                <article class="card port-info-card">
                    <h2>Port Information</h2>
                    ${port.description ? `<p class="port-description">${formatDatabaseText(port.description)}</p>` : ''}
                    <dl class="port-facts">
                        ${details.map(([label, value]) => `
                            <div>
                                <dt>${escapeHtml(label)}</dt>
                                <dd>${formatDatabaseText(value)}</dd>
                            </div>
                        `).join('')}
                    </dl>
                </article>

                <article class="card port-details-card">
                    <h2>Port Details</h2>
                    <ul class="port-detail-links">
                        <li>
                            <a href="/pages/portinfo.php" onclick="loadPage('portinfo', { publicPage: true }); return false;">
                                View All Cruise Ports
                            </a>
                        </li>
                    </ul>
                </article>
            </div>

            <article class="card port-map-card">
                <h2>Map</h2>
                ${mapMarkup}
            </article>
        </div>
    `;
}

function openShipPage(event, shipId) {
    event.preventDefault();
    loadPage('ship', {
        publicPage: true,
        shipId
    });
}

async function loadShipPage(shipId) {
    const container = document.getElementById('shipDetail');
    const id = Number.parseInt(shipId, 10);

    if (!container) {
        return;
    }

    if (!id) {
        const urlParams = new URLSearchParams(window.location.search);
        shipId = urlParams.get('id');
    }

    try {
        const response = await fetch(`/api/getShipPage.php?id=${encodeURIComponent(shipId)}`);
        const data = await response.json();

        if (!response.ok || data.error) {
            throw new Error(data.error || `HTTP error! Status: ${response.status}`);
        }

        renderShipPage(data.ship, container);
    } catch (error) {
        console.error('Error loading ship page:', error);
        container.innerHTML = '<div class="card"><p>Unable to load ship data.</p></div>';
    }
}

function renderShipPage(ship, container) {
    const imageUrl = getDatabaseImageUrl(ship.image_url, '/images/ships/');
    const meta = document.getElementById('page-title-meta');

    if (meta) {
        meta.dataset.title = `Norman and Company | ${ship.ship_name}`;
        updatePageTitle();
    }

    const facts = [
        ['Cruise line', ship.cruise_line_name],
        ['Class', ship.ship_class],
        ['Passenger capacity', ship.passenger_capacity ? Number(ship.passenger_capacity).toLocaleString() : ''],
        ['Gross tonnage', ship.gross_tonnage ? Number(ship.gross_tonnage).toLocaleString() : ''],
        ['Launch year', ship.launch_year]
    ].filter(([, value]) => value !== null && value !== undefined && String(value) !== '');

    container.innerHTML = `
        <p>
            <a href="${escapeHtml(ship.cruise_line_page)}"
               onclick="openCruiseLinePage(event, '${escapeHtml(ship.cruise_line_page)}')">
                &larr; Back to ${escapeHtml(ship.cruise_line_name)}
            </a>
        </p>

        <div class="ship-detail-layout">
            ${imageUrl ? `
                <img src="${escapeHtml(imageUrl)}"
                     alt="${escapeHtml(ship.ship_name)}"
                     class="ship-detail-image">
            ` : ''}

            <article class="card ship-detail-card">
                <h1>${escapeHtml(ship.ship_name)}</h1>
                <dl class="ship-facts">
                    ${facts.map(([label, value]) => `
                        <div>
                            <dt>${escapeHtml(label)}</dt>
                            <dd>${escapeHtml(String(value))}</dd>
                        </div>
                    `).join('')}
                </dl>
                ${ship.description ? `<p>${formatDatabaseText(ship.description)}</p>` : ''}
            </article>
        </div>
    `;
}
    
// =========================================
// RESORTS CARD UX
// =========================================

async function loadResorts() {
    // Get the HTML container where cards will be inserted
    const container = document.getElementById('resortsContainer');

    // Stop if container does not exist
    if (!container) return;

    const url = '/api/getResorts.php';

    try {
        const response = await fetch(url);

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
                <h3>${resort.resort_name}</h3>
                <p><strong class="highlight-strong">${resort.country}</strong></p>
                <p>${resort.resort_description}</p>
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

    const url = '/api/getDestinations.php';

    try {
        const response = await fetch(url);

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
                <h3>${destination.destination_name}</h3>
                <p><strong class="highlight-strong">${destination.country_name}</strong></p>
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
// TRAVEL STORE UX
// =========================================

function normalizeStoreCategory(categoryId) {
    if (categoryId === null || categoryId === undefined) {
        return null;
    }

    const normalizedCategory = String(categoryId).trim();

    return normalizedCategory || 'all';
}

function prepareTravelStoreNavigation(categoryId = null) {
    const currentUrl = new URL(window.location.href);
    const normalizedCategory = normalizeStoreCategory(categoryId)
        || currentUrl.searchParams.get('category')
        || 'all';
    const nextUrl = new URL(window.location.href);

    nextUrl.pathname = '/products.php';
    nextUrl.searchParams.delete('product');
    nextUrl.searchParams.delete('id');

    if (normalizedCategory === 'all') {
        nextUrl.searchParams.delete('category');
    } else {
        nextUrl.searchParams.set('category', normalizedCategory);
    }

    if (`${nextUrl.pathname}${nextUrl.search}${nextUrl.hash}` !== `${currentUrl.pathname}${currentUrl.search}${currentUrl.hash}`) {
        window.history.pushState({}, '', nextUrl);
    }

    return normalizedCategory;
}

// INITIALIZATION
function initTravelStore(initialCategory = null) {
    const dropdown = document.getElementById('categoryFilter');
    if (!dropdown) return;

    // STEP 1: Read category from URL
    const urlParams = new URLSearchParams(window.location.search);

    const hasInitialCategory = initialCategory !== null && initialCategory !== undefined;
    const categoryFromUrl = hasInitialCategory
        ? initialCategory
        : urlParams.get('category') || 'all';

    // STEP 2: Set dropdown + load data
    Promise.resolve(setTravelCategory(categoryFromUrl, false)).then(() => {
        const productId = urlParams.get('product');

        if (productId) {
            openProduct(productId);
        }
    });

    // STEP 3: Dropdown changes update URL
    dropdown.addEventListener('change', (e) => {
        setTravelCategory(e.target.value, true);
    });

    // STEP 4: Back/forward button support
    window.addEventListener('popstate', () => {
        const params = new URLSearchParams(window.location.search);
        const category = params.get('category') || 'all';
        setTravelCategory(category, false);
    });
}

// CENTRAL CONTROLLER
function setTravelCategory(categoryId, updateUrl = true) {
    const dropdown = document.getElementById('categoryFilter');

    if (dropdown) {
        dropdown.value = categoryId;
    }

    // Update URL if needed
    if (updateUrl) {
        const newUrl = new URL(window.location);
        newUrl.searchParams.set('category', categoryId);
        window.history.pushState({}, '', newUrl);
    }

    return loadTravelStore(categoryId);
}

// TRAVEL STORE LOADER
async function loadTravelStore(categoryId = 'all') {
    const container = document.getElementById('productGridView');
    if (!container) return;

    let url = '/api/getProducts.php';

    if (categoryId && categoryId !== 'all') {
        url += `?product_category_id=${encodeURIComponent(categoryId)}`;
    }

    try {
        const res = await fetch(url);
        const data = await res.json();

        if (!res.ok || data?.error) {
            throw new Error(data?.error || `HTTP error! Status: ${res.status}`);
        }

        const products = Array.isArray(data) ? data : [];

        container.innerHTML = '';

        products.forEach(product => {
            const card = document.createElement('div');
            card.classList.add('card');
            const imageSrc = resolveProductImageSrc(product.image_url);
            const isApparel = Number(product?.is_apparel) === 1
                || String(product?.category_name ?? '').toLowerCase().includes('apparel');
            const cartButtonLabel = isApparel ? 'Choose Options' : 'Add to Cart';

            card.innerHTML = `
                <div class="product-card-inner" onclick="openProduct(${Number(product.id)})">

                    <img src="${escapeHtml(imageSrc)}"
                        alt="${escapeHtml(product.product_name)}"
                        class="product-image">

                    <h3>${escapeHtml(product.product_name)}</h3>

                    <p><strong>${escapeHtml(product.category_name)}</strong></p>

                    <p>${escapeHtml(product.product_description)}</p>

                    <p><strong>$${parseFloat(product.price).toFixed(2)}</strong></p>

                    <button type="button"
                            class="btn-secondary product-card-cart-button"
                            onclick="addProductToCartById(${Number(product.id)}, event)">
                        ${cartButtonLabel}
                    </button>

                </div>
            `;

            container.appendChild(card);
        });

    } catch (err) {
        console.error(err);
        container.innerHTML = "<p>Error loading products.</p>";
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

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    }[char]));
}

function getProductDetailsDescription(product) {
    const longDescription = String(product?.long_description ?? '').trim();

    return longDescription || product?.product_description || '';
}

function resolveProductImageSrc(imageUrl) {
    const image = String(imageUrl ?? '').trim();

    if (!image) return '';

    if (/^(https?:)?\/\//i.test(image) || image.startsWith('/') || image.startsWith('data:')) {
        return image;
    }

    return `/images/products/${image}`;
}

function getProductImages(product) {
    const isMultiImage = Number(product?.is_multi_image) === 1;
    const fields = isMultiImage ? productImageFields : ['image_url'];
    const seenImages = new Set();

    return fields.reduce((images, field) => {
        const image = String(product?.[field] ?? '').trim();

        if (!image || seenImages.has(image)) {
            return images;
        }

        seenImages.add(image);
        images.push(resolveProductImageSrc(image));

        return images;
    }, []);
}

function buildProductImageGallery(product) {
    const images = getProductImages(product);
    const productName = escapeHtml(product?.product_name);

    if (images.length === 0) {
        return '';
    }

    if (images.length === 1) {
        return `
            <img src="${escapeHtml(images[0])}"
                 alt="${productName}"
                 class="product-detail-image">
        `;
    }

    return `
        <div class="product-image-carousel"
             data-images="${escapeHtml(JSON.stringify(images))}"
             data-current-index="0">
            <button type="button"
                    class="carousel-button carousel-button-left"
                    aria-label="Previous product image"
                    onclick="changeProductImage(this, -1)">
                &#10094;
            </button>

            <img src="${escapeHtml(images[0])}"
                 alt="${productName}"
                 class="product-detail-image">

            <button type="button"
                    class="carousel-button carousel-button-right"
                    aria-label="Next product image"
                    onclick="changeProductImage(this, 1)">
                &#10095;
            </button>

            <p class="carousel-counter" aria-live="polite">
                <span class="current-image">1</span> / ${images.length}
            </p>
        </div>
    `;
}

function changeProductImage(button, direction) {
    const carousel = button.closest('.product-image-carousel');

    if (!carousel) return;

    const image = carousel.querySelector('.product-detail-image');
    const currentImage = carousel.querySelector('.current-image');

    let images = [];

    try {
        images = JSON.parse(carousel.dataset.images || '[]');
    } catch (error) {
        console.error('Unable to read product images:', error);
        return;
    }

    if (!image || images.length === 0) return;

    const currentIndex = Number.parseInt(carousel.dataset.currentIndex || '0', 10);
    const nextIndex = (currentIndex + direction + images.length) % images.length;

    carousel.dataset.currentIndex = String(nextIndex);
    image.src = images[nextIndex];

    if (currentImage) {
        currentImage.textContent = String(nextIndex + 1);
    }
}

async function getProductSizeDropdown(product) {
    const isApparel = Number(product?.is_apparel) === 1;

    if (!isApparel) {
        return '';
    }

    const sizes = await fetch('/api/getApparelSizes.php');
    const sizesdata = await sizes.json();

    if (!sizes.ok || sizesdata?.error) {
        throw new Error(sizesdata?.error || `HTTP error! Status: ${sizes.status}`);
    }

    const sizeOptions = Array.isArray(sizesdata)
        ? sizesdata.map((size) => {
            const fallbackLabel = typeof size === 'object' && size !== null
                ? Object.values(size).find((value) => typeof value === 'string' && value.trim() !== '')
                : size;
            const label = size?.size_name
                ?? size?.apparel_size_name
                ?? size?.size_label
                ?? size?.apparel_size
                ?? size?.size
                ?? size?.name
                ?? size?.label
                ?? size?.size_code
                ?? size?.apparel_size_code
                ?? fallbackLabel
                ?? '';
            const value = size?.size_id
                ?? size?.apparel_size_id
                ?? size?.id
                ?? size?.size_code
                ?? size?.apparel_size_code
                ?? label;

            return `
                <option value="${escapeHtml(value)}">
                    ${escapeHtml(label)}
                </option>
            `;
        }).join('')
        : '';

    if (!sizeOptions) {
        return '';
    }

    return `
        <div class="form-group">
            <label for="productSize">
                Size:
            </label>
            <select
                id="productSize"
                name="productSize"
                required
            >
                <option value="">
                    Select a size
                </option>
                ${sizeOptions}
            </select>
        </div>
    `;
}

function isBookProduct(product) {
    return Number(product?.product_category_id) === 9;
}

function getBookFormatOptions(product, formatOptions = []) {
    if (!isBookProduct(product)) {
        return [];
    }

    const optionsByFormat = new Map();

    const addOption = (option) => {
        const id = Number.parseInt(option?.id ?? 0, 10);
        const formatName = String(option?.format_name ?? '').trim();

        if (!id || !formatName) {
            return;
        }

        const formatKey = String(option?.format_id ?? id);

        if (!optionsByFormat.has(formatKey)) {
            optionsByFormat.set(formatKey, {
                id,
                formatName
            });
        }
    };

    if (Array.isArray(formatOptions)) {
        formatOptions.forEach(addOption);
    }

    addOption(product);

    return Array.from(optionsByFormat.values());
}

function getBookFormatDropdown(product, formatOptions = []) {
    const options = getBookFormatOptions(product, formatOptions);

    if (options.length === 0) {
        return '';
    }

    const currentProductId = Number.parseInt(product?.id ?? 0, 10);
    const optionMarkup = options.map((option) => {
        const selected = option.id === currentProductId ? 'selected' : '';

        return `
            <option value="${escapeHtml(option.id)}" ${selected}>
                ${escapeHtml(option.formatName)}
            </option>
        `;
    }).join('');

    return `
        <div class="form-group">
            <label for="productFormat">
                Format:
            </label>
            <select
                id="productFormat"
                name="productFormat"
                onchange="changeBookFormat(this.value)"
                required
            >
                ${optionMarkup}
            </select>
        </div>
    `;
}

function buildProductDetailsHtml(product, sizeDropdown = '', options = {}) {
    const price = Number.parseFloat(product?.price);
    const formattedPrice = Number.isFinite(price) ? price.toFixed(2) : '0.00';
    const description = getProductDetailsDescription(product);
    const productId = Number.parseInt(product?.id ?? 0, 10);
    const inventoryCount = Number.parseInt(product?.inventory_count ?? '', 10);
    const hasInventoryLimit = Number.isFinite(inventoryCount) && inventoryCount >= 0;
    const isOutOfStock = hasInventoryLimit && inventoryCount <= 0;
    const quantityMax = hasInventoryLimit ? `max="${inventoryCount}"` : '';
    const backButton = options.includeBackButton ? `
        <button onclick="${options.backButtonAction || 'closeProduct()'}" class="btn-primary back-button">
            &larr; Back to Store
        </button>
    ` : '';
    const formatDropdown = options.formatDropdown || '';

    return `
        ${backButton}

        <div class="product-details">

            ${buildProductImageGallery(product)}

            <!-- Product Name -->
            <h1>${escapeHtml(product?.product_name)}</h1>

            <!-- Product Category Name -->
            <p><strong>${escapeHtml(product?.category_name)}</strong></p>

            <!-- Book Format Drop Down Box (if needed) -->
            ${formatDropdown}

            <!-- Product Description -->
            <p>${escapeHtml(description)}</p>

            <!-- Product Price -->
            <h2>$${formattedPrice}</h2>

            <!-- Product Size Drop Down Box (if needed) -->
            ${sizeDropdown}

            <div class="form-group cart-quantity-field">
                <label for="productQuantity">
                    Quantity:
                </label>
                <input
                    type="number"
                    id="productQuantity"
                    name="productQuantity"
                    min="1"
                    ${quantityMax}
                    value="1"
                    ${isOutOfStock ? 'disabled' : ''}
                >
            </div>
            
            <!-- Add to cart button -->
            <button type="button"
                    class="btn-primary add-to-cart-button"
                    onclick="addProductToCartFromDetails(${productId}, event)"
                    ${isOutOfStock ? 'disabled' : ''}>
                ${isOutOfStock ? 'Out of Stock' : 'Add to Cart'}
            </button>

        </div>
    `;
}

async function fetchProductDetailsData(productId) {
    const res = await fetch(`/api/getProductDetails.php?id=${encodeURIComponent(productId)}`);
    const data = await res.json();

    if (!res.ok || data.error) {
        throw new Error(data.error || `HTTP error! Status: ${res.status}`);
    }

    const product = Object.prototype.hasOwnProperty.call(data, 'product')
        ? data.product
        : data;

    if (!product) {
        throw new Error('Product not found.');
    }

    const formatOptions = Array.isArray(data.format_options) ? data.format_options : [];

    return {
        product,
        formatOptions
    };
}

async function fetchProductDetails(productId) {
    const { product } = await fetchProductDetailsData(productId);

    return product;
}

async function renderProductDetails(productId, container, options = {}) {
    const { product, formatOptions } = await fetchProductDetailsData(productId);
    const sizeDropdown = await getProductSizeDropdown(product);
    const formatDropdown = getBookFormatDropdown(product, formatOptions);

    container.classList.remove('card-grid');
    container.innerHTML = buildProductDetailsHtml(product, sizeDropdown, {
        ...options,
        formatDropdown
    });

    return product;
}

async function loadProductDetails() {
    const container = document.getElementById('productContainer');

    if (!container) return;

    const urlParams = new URLSearchParams(window.location.search);
    const productId = urlParams.get('id') || urlParams.get('product');

    if (!productId) {
        container.classList.remove('card-grid');
        container.innerHTML = '<p>No product was selected.</p>';
        return;
    }

    try {
        await renderProductDetails(productId, container, {
            includeBackButton: true,
            backButtonAction: "loadPage('travelstore')"
        });
    } catch (err) {
        console.error('Error loading product details:', err);
        container.classList.remove('card-grid');
        container.innerHTML = '<p>Error loading product details.</p>';
    }
}

// LOAD PRODUCT DETAILS
async function openProduct(productId, options = {}) {

    const gridView = document.getElementById('productGridView');
    const detailsView = document.getElementById('productDetailsView');

    if (!gridView || !detailsView) return;

    try {

        const product = await renderProductDetails(productId, detailsView, {
            includeBackButton: true
        });

        // Hide grid, show details
        gridView.classList.add('hidden');
        detailsView.classList.remove('hidden');

        // optional URL update (nice UX)
        const newUrl = new URL(window.location);
        newUrl.searchParams.set('product', product.id || productId);

        if (options.replaceHistory) {
            history.replaceState({}, '', newUrl);
        } else {
            history.pushState({}, '', newUrl);
        }

    } catch (err) {
        console.error('Error loading product:', err);
        gridView.classList.add('hidden');
        detailsView.classList.remove('hidden');
        detailsView.innerHTML = `<p>Error loading product.</p>`;
    }
}

async function changeBookFormat(productId) {
    const nextProductId = Number.parseInt(productId, 10);

    if (!nextProductId) {
        return;
    }

    const gridView = document.getElementById('productGridView');
    const detailsView = document.getElementById('productDetailsView');

    if (gridView && detailsView && !detailsView.classList.contains('hidden')) {
        await openProduct(nextProductId, {
            replaceHistory: true
        });
        return;
    }

    const container = document.getElementById('productContainer');

    if (!container) {
        return;
    }

    try {
        const product = await renderProductDetails(nextProductId, container, {
            includeBackButton: true,
            backButtonAction: "loadPage('travelstore')"
        });
        const newUrl = new URL(window.location);
        const productParam = newUrl.searchParams.has('id') ? 'id' : 'product';

        newUrl.searchParams.set(productParam, product.id || nextProductId);
        history.replaceState({}, '', newUrl);
    } catch (err) {
        console.error('Error changing book format:', err);
        container.innerHTML = '<p>Error loading product details.</p>';
    }
}

function closeProduct() {

    const gridView = document.getElementById('productGridView');
    const detailsView = document.getElementById('productDetailsView');

    if (!gridView || !detailsView) return;

    detailsView.classList.add('hidden');
    gridView.classList.remove('hidden');

    const newUrl = new URL(window.location);
    newUrl.searchParams.delete('product');
    history.pushState({}, '', newUrl);
}

// =========================================
// SHOPPING CART UX
// =========================================

const CART_STORAGE_KEY = 'normanAndCompanyCart';

const shoppingCartState = {
    items: []
};

function initShoppingCart() {
    ensureCartDrawer();
    shoppingCartState.items = readShoppingCart();
    bindCartDrawerEvents();
    renderShoppingCart();
}

function ensureCartDrawer() {
    if (document.getElementById('shoppingCartDrawer')) {
        return;
    }

    const accountCheckoutLabel = isCustomerArea() ? 'Continue with account' : 'Login first';
    const cartMarkup = document.createElement('div');
    cartMarkup.innerHTML = `
        <div id="shoppingCartOverlay" class="cart-drawer-overlay" aria-hidden="true"></div>

        <aside id="shoppingCartDrawer"
               class="cart-drawer"
               aria-labelledby="shoppingCartTitle"
               aria-hidden="true">
            <div class="cart-drawer-header">
                <div>
                    <p class="cart-eyebrow">Shopping Cart</p>
                    <h2 id="shoppingCartTitle">Your Cart</h2>
                </div>

                <button type="button"
                        class="cart-close-button"
                        onclick="closeCartDrawer()"
                        aria-label="Close cart">
                    &times;
                </button>
            </div>

            <div id="cartStatusMessage" class="cart-status-message" role="status" aria-live="polite" hidden></div>

            <div id="cartItemsContainer" class="cart-items-container"></div>

            <div class="cart-drawer-footer">
                <div class="cart-total-row">
                    <span>Subtotal</span>
                    <strong id="cartSubtotal">$0.00</strong>
                </div>

                <button type="button"
                        class="btn-primary cart-checkout-button"
                        id="cartCheckoutButton"
                        aria-controls="checkoutChoicePanel"
                        aria-expanded="false">
                    Checkout
                </button>

                <div id="checkoutChoicePanel" class="checkout-choice-panel" hidden>
                    <p>Choose a checkout path.</p>

                    <div class="checkout-choice-actions">
                        <button type="button" class="btn-secondary" onclick="checkoutWithLogin()">
                            ${accountCheckoutLabel}
                        </button>

                        <button type="button" class="btn-primary" onclick="continueCheckoutAsGuest()">
                            Continue as guest
                        </button>
                    </div>
                </div>

                <button type="button" class="btn-secondary cart-continue-button" onclick="closeCartDrawer()">
                    Continue Shopping
                </button>
            </div>
        </aside>
    `;

    document.body.append(...cartMarkup.children);
}

function bindCartDrawerEvents() {
    const drawer = document.getElementById('shoppingCartDrawer');
    const overlay = document.getElementById('shoppingCartOverlay');
    const checkoutButton = document.getElementById('cartCheckoutButton');

    if (drawer && drawer.dataset.bound !== 'true') {
        drawer.addEventListener('click', handleCartDrawerClick);
        drawer.dataset.bound = 'true';
    }

    if (overlay && overlay.dataset.bound !== 'true') {
        overlay.addEventListener('click', closeCartDrawer);
        overlay.dataset.bound = 'true';
    }

    if (checkoutButton && checkoutButton.dataset.bound !== 'true') {
        checkoutButton.addEventListener('click', showCheckoutChoices);
        checkoutButton.dataset.bound = 'true';
    }

    if (document.body.dataset.cartEscapeBound !== 'true') {
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeCartDrawer();
            }
        });
        document.body.dataset.cartEscapeBound = 'true';
    }
}

function isCustomerArea() {
    return document.body?.dataset?.customerArea === 'true'
        || window.location.pathname.startsWith('/customer');
}

function showCheckoutChoices() {
    const checkoutButton = document.getElementById('cartCheckoutButton');
    const choicePanel = document.getElementById('checkoutChoicePanel');

    if (shoppingCartState.items.length === 0) {
        showCartStatus('Add an item to your cart before checkout.');
        return;
    }

    if (!choicePanel) return;

    choicePanel.hidden = false;
    checkoutButton?.setAttribute('aria-expanded', 'true');
}

function hideCheckoutChoices() {
    const checkoutButton = document.getElementById('cartCheckoutButton');
    const choicePanel = document.getElementById('checkoutChoicePanel');

    if (!choicePanel) return;

    choicePanel.hidden = true;
    checkoutButton?.setAttribute('aria-expanded', 'false');
}

function getCheckoutReturnTo() {
    return '/customer/?checkout=1';
}

function checkoutWithLogin() {
    if (shoppingCartState.items.length === 0) {
        showCartStatus('Add an item to your cart before checkout.');
        return;
    }

    if (isCustomerArea()) {
        continueCheckoutWithAccount();
        return;
    }

    window.location.href = `/login.php?return_to=${encodeURIComponent(getCheckoutReturnTo())}`;
}

function continueCheckoutWithAccount() {
    hideCheckoutChoices();
    showCartStatus('Account checkout is not connected yet.');
}

function continueCheckoutAsGuest() {
    if (shoppingCartState.items.length === 0) {
        showCartStatus('Add an item to your cart before checkout.');
        return;
    }

    hideCheckoutChoices();
    showCartStatus('Guest checkout is not connected yet.');
}

function resumeCheckoutPromptFromUrl() {
    const url = new URL(window.location.href);

    if (url.searchParams.get('checkout') !== '1') {
        return;
    }

    url.searchParams.delete('checkout');
    history.replaceState({}, '', `${url.pathname}${url.search}${url.hash}`);

    if (shoppingCartState.items.length > 0) {
        openCartDrawer();
        showCheckoutChoices();
    }
}

function readShoppingCart() {
    try {
        const rawCart = localStorage.getItem(CART_STORAGE_KEY);
        const parsedCart = rawCart ? JSON.parse(rawCart) : [];

        if (!Array.isArray(parsedCart)) {
            return [];
        }

        return parsedCart
            .map(normalizeCartItem)
            .filter(Boolean);
    } catch (error) {
        console.error('Unable to read shopping cart:', error);
        return [];
    }
}

function saveShoppingCart() {
    try {
        localStorage.setItem(CART_STORAGE_KEY, JSON.stringify(shoppingCartState.items));
    } catch (error) {
        console.error('Unable to save shopping cart:', error);
    }
}

function normalizeCartItem(item) {
    const id = Number.parseInt(item?.id ?? 0, 10);
    const quantity = Number.parseInt(item?.quantity ?? 0, 10);
    const price = Number.parseFloat(item?.price ?? 0);

    if (!id || quantity <= 0 || !Number.isFinite(price)) {
        return null;
    }

    const sizeValue = String(item?.sizeValue ?? '').trim();
    const key = item?.key || getCartItemKey(id, sizeValue);
    const inventoryCount = item?.inventoryCount === null || item?.inventoryCount === undefined
        ? null
        : Number.parseInt(item.inventoryCount, 10);

    return {
        key,
        id,
        name: String(item?.name ?? 'Product'),
        category: String(item?.category ?? ''),
        price,
        image: String(item?.image ?? ''),
        sizeValue,
        sizeLabel: String(item?.sizeLabel ?? ''),
        quantity,
        inventoryCount: Number.isFinite(inventoryCount) && inventoryCount >= 0 ? inventoryCount : null
    };
}

function getCartItemKey(productId, sizeValue = '') {
    return `${productId}:${String(sizeValue || 'standard')}`;
}

function getCartTotals() {
    return shoppingCartState.items.reduce((totals, item) => {
        totals.quantity += item.quantity;
        totals.subtotal += item.price * item.quantity;

        return totals;
    }, {
        quantity: 0,
        subtotal: 0
    });
}

function formatCartCurrency(value) {
    const number = Number.parseFloat(value);

    if (!Number.isFinite(number)) {
        return '$0.00';
    }

    return `$${number.toFixed(2)}`;
}

function renderShoppingCart() {
    const navCount = document.getElementById('cartNavCount');
    const itemContainer = document.getElementById('cartItemsContainer');
    const subtotal = document.getElementById('cartSubtotal');
    const checkoutButton = document.getElementById('cartCheckoutButton');
    const totals = getCartTotals();

    if (navCount) {
        navCount.textContent = String(totals.quantity);
        navCount.hidden = totals.quantity === 0;
    }

    if (subtotal) {
        subtotal.textContent = formatCartCurrency(totals.subtotal);
    }

    if (checkoutButton) {
        checkoutButton.disabled = totals.quantity === 0;
    }

    if (totals.quantity === 0) {
        hideCheckoutChoices();
    }

    if (!itemContainer) return;

    if (shoppingCartState.items.length === 0) {
        itemContainer.innerHTML = `
            <div class="cart-empty-state">
                <h3>Your cart is empty.</h3>
                <p>Add travel essentials from the store and they will show up here.</p>
            </div>
        `;
        return;
    }

    itemContainer.innerHTML = shoppingCartState.items.map((item) => {
        const imageMarkup = item.image
            ? `<img src="${escapeHtml(item.image)}" alt="${escapeHtml(item.name)}">`
            : '<span class="cart-item-placeholder">No image</span>';
        const meta = [
            item.category,
            item.sizeLabel ? `Size: ${item.sizeLabel}` : ''
        ].filter(Boolean).join(' | ');

        return `
            <article class="cart-item">
                <div class="cart-item-image">
                    ${imageMarkup}
                </div>

                <div class="cart-item-body">
                    <div class="cart-item-title-row">
                        <div>
                            <h3>${escapeHtml(item.name)}</h3>
                            <p>${escapeHtml(meta)}</p>
                        </div>

                        <strong>${formatCartCurrency(item.price * item.quantity)}</strong>
                    </div>

                    <div class="cart-item-actions">
                        <div class="cart-quantity-controls" aria-label="Quantity controls for ${escapeHtml(item.name)}">
                            <button type="button"
                                    data-cart-action="decrease"
                                    data-cart-key="${escapeHtml(item.key)}"
                                    aria-label="Decrease quantity">
                                -
                            </button>

                            <span>${item.quantity}</span>

                            <button type="button"
                                    data-cart-action="increase"
                                    data-cart-key="${escapeHtml(item.key)}"
                                    aria-label="Increase quantity">
                                +
                            </button>
                        </div>

                        <button type="button"
                                class="cart-remove-button"
                                data-cart-action="remove"
                                data-cart-key="${escapeHtml(item.key)}">
                            Remove
                        </button>
                    </div>
                </div>
            </article>
        `;
    }).join('');
}

function handleCartDrawerClick(event) {
    const button = event.target?.closest?.('[data-cart-action]');

    if (!button) return;

    const key = button.dataset.cartKey;
    const item = shoppingCartState.items.find((cartItem) => cartItem.key === key);

    if (!item) return;

    if (button.dataset.cartAction === 'increase') {
        changeCartItemQuantity(key, item.quantity + 1);
    }

    if (button.dataset.cartAction === 'decrease') {
        changeCartItemQuantity(key, item.quantity - 1);
    }

    if (button.dataset.cartAction === 'remove') {
        removeCartItem(key);
    }
}

function changeCartItemQuantity(key, nextQuantity) {
    const item = shoppingCartState.items.find((cartItem) => cartItem.key === key);

    if (!item) return;

    const maxQuantity = item.inventoryCount;
    let quantity = Number.parseInt(nextQuantity, 10);

    if (!Number.isFinite(quantity)) {
        quantity = item.quantity;
    }

    if (quantity <= 0) {
        removeCartItem(key);
        return;
    }

    if (maxQuantity !== null && quantity > maxQuantity) {
        quantity = maxQuantity;
        showCartStatus(`Only ${maxQuantity} available for ${item.name}.`);
    }

    item.quantity = quantity;
    saveShoppingCart();
    renderShoppingCart();
}

function removeCartItem(key) {
    shoppingCartState.items = shoppingCartState.items.filter((item) => item.key !== key);
    saveShoppingCart();
    renderShoppingCart();
}

function addProductToCart(product, options = {}) {
    const id = Number.parseInt(product?.id ?? 0, 10);
    const name = String(product?.product_name ?? '').trim();
    const price = Number.parseFloat(product?.price ?? 0);
    const quantity = Math.max(1, Number.parseInt(options.quantity ?? 1, 10) || 1);
    const sizeValue = String(options.sizeValue ?? '').trim();
    const sizeLabel = String(options.sizeLabel ?? '').trim();
    const inventoryCount = Number.parseInt(product?.inventory_count ?? '', 10);
    const hasInventoryLimit = Number.isFinite(inventoryCount) && inventoryCount >= 0;

    if (!id || !name || !Number.isFinite(price)) {
        showCartStatus('Unable to add this product to the cart.');
        return;
    }

    if (hasInventoryLimit && inventoryCount <= 0) {
        showCartStatus(`${name} is out of stock.`);
        return;
    }

    const key = getCartItemKey(id, sizeValue);
    const existingItem = shoppingCartState.items.find((item) => item.key === key);
    const nextQuantity = (existingItem?.quantity || 0) + quantity;
    const clampedQuantity = hasInventoryLimit
        ? Math.min(nextQuantity, inventoryCount)
        : nextQuantity;

    if (existingItem) {
        existingItem.quantity = clampedQuantity;
    } else {
        shoppingCartState.items.push({
            key,
            id,
            name,
            category: String(product?.category_name ?? ''),
            price,
            image: resolveProductImageSrc(product?.image_url),
            sizeValue,
            sizeLabel,
            quantity: clampedQuantity,
            inventoryCount: hasInventoryLimit ? inventoryCount : null
        });
    }

    saveShoppingCart();
    renderShoppingCart();
    openCartDrawer();
    showCartStatus(`${name} added to cart.`);
}

async function addProductToCartById(productId, event = null) {
    event?.preventDefault?.();
    event?.stopPropagation?.();

    try {
        const product = await fetchProductDetails(productId);

        if (Number(product?.is_apparel) === 1) {
            showCartStatus('Choose a size before adding this item.');
            await openProduct(productId);
            return;
        }

        addProductToCart(product);
    } catch (error) {
        console.error('Error adding product to cart:', error);
        showCartStatus('Unable to add this product to the cart.');
    }
}

async function addProductToCartFromDetails(productId, event = null) {
    event?.preventDefault?.();

    const sizeSelect = document.getElementById('productSize');
    const quantityInput = document.getElementById('productQuantity');
    const quantity = Number.parseInt(quantityInput?.value ?? 1, 10) || 1;

    try {
        const product = await fetchProductDetails(productId);
        const isApparel = Number(product?.is_apparel) === 1;

        if (isApparel && !sizeSelect?.value) {
            showCartStatus('Select a size before adding this item.');
            sizeSelect?.focus();
            return;
        }

        addProductToCart(product, {
            quantity,
            sizeValue: sizeSelect?.value || '',
            sizeLabel: sizeSelect?.selectedOptions?.[0]?.textContent?.trim() || ''
        });
    } catch (error) {
        console.error('Error adding product to cart:', error);
        showCartStatus('Unable to add this product to the cart.');
    }
}

function openCartDrawer() {
    ensureCartDrawer();
    bindCartDrawerEvents();
    renderShoppingCart();

    const drawer = document.getElementById('shoppingCartDrawer');
    const overlay = document.getElementById('shoppingCartOverlay');

    drawer?.classList.add('open');
    drawer?.setAttribute('aria-hidden', 'false');
    overlay?.classList.add('open');
    overlay?.setAttribute('aria-hidden', 'false');
    document.body.classList.add('cart-drawer-open');
}

function closeCartDrawer() {
    const drawer = document.getElementById('shoppingCartDrawer');
    const overlay = document.getElementById('shoppingCartOverlay');

    hideCheckoutChoices();
    drawer?.classList.remove('open');
    drawer?.setAttribute('aria-hidden', 'true');
    overlay?.classList.remove('open');
    overlay?.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('cart-drawer-open');
}

function showCartStatus(message) {
    ensureCartDrawer();

    const status = document.getElementById('cartStatusMessage');

    if (!status) return;

    status.textContent = message;
    status.hidden = false;

    window.clearTimeout(showCartStatus.timeoutId);
    showCartStatus.timeoutId = window.setTimeout(() => {
        if (status.textContent === message) {
            status.hidden = true;
        }
    }, 3500);
}

// =========================================
// CONTACT FORM UX
// =========================================

async function sendContactMessage(event) {
    event.preventDefault();

    const form = document.getElementById("contactForm");
    const button = form.querySelector("button[type='submit']");
    const statusBox = document.getElementById("formStatus");

    const formData = new FormData(form);

    // Reset status
    statusBox.classList.add("hidden");
    statusBox.textContent = "";
    statusBox.className = "form-status hidden";

    // Set loading state
    button.disabled = true;
    button.classList.add("btn-loading");
    button.textContent = "Sending...";

    try {
        const response = await fetch("/api/sendContact.php", {
            method: "POST",
            body: formData
        });

        const result = await response.json();

        if (result.success) {

            statusBox.textContent = result.message || "Message sent successfully!";
            statusBox.classList.remove("hidden");
            statusBox.classList.add("success");

            form.reset();

        } else {

            statusBox.textContent = result.message || "Something went wrong.";
            statusBox.classList.remove("hidden");
            statusBox.classList.add("error");
        }

    } catch (error) {

        console.error(error);

        statusBox.textContent = "Unable to send message. Please try again later.";
        statusBox.classList.remove("hidden");
        statusBox.classList.add("error");

    } finally {

        // Reset button state
        button.disabled = false;
        button.classList.remove("btn-loading");
        button.textContent = "Send Message";
    }
}

function loadStateProvinces(selectId = "state_prov") {
    const select = document.getElementById(selectId);
    if (!select) {
        console.warn("State/Province select element not found");
        return;
    }

    // Optional: Show loading state
    select.innerHTML = '<option value="">Loading states/provinces...</option>';

    fetch("/api/getStateProv.php")
        .then(res => {
            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            return res.json();
        })
        .then(data => {
            console.log("State/Prov data received:", data);

            select.innerHTML = '<option value="">Select State/Province</option>';

            if (Array.isArray(data) && data.length > 0) {
                data.forEach(item => {
                    const option = document.createElement("option");
                    option.value = item.id || item.state_prov_id || '';   // adjust field name if needed
                    option.textContent = item.name || item.state_prov_name || item.state_name || '';
                    select.appendChild(option);
                });
            } else {
                select.innerHTML = '<option value="">No states available</option>';
            }
        })
        .catch(err => {
            console.error("Error loading states:", err);
            select.innerHTML = '<option value="">Error loading states</option>';
        });
}

// =========================================
// FAQ CARD UX
// =========================================

async function loadFAQ() {
    // Get the HTML container where cards will be inserted
    const container = document.getElementById('faqContainer');

    // Stop if container does not exist
    if (!container) return;

    try {

        // Fetch the JSON file
        const response = await fetch('/faq/faq.json');

        // Check for errors
        if (!response.ok) {
            throw new Error(`HTTP Error: ${response.status}`);
        }

        // Convert response to JSON
        const faqs = await response.json();

        // Clear loading message
        container.innerHTML = '';

        // Loop through each resort
        faqs.forEach(faq => { 

            // Create card element
            const card = document.createElement('div');
            card.classList.add('card');

            // Build card HTML
            card.innerHTML = `
                <h3>${faq.question}</h3>
                <p>${faq.answer}</p>
                `;

                // Add card to container
                container.appendChild(card);
    
            });

        } catch (error) {

            console.error('Error loading FAQ:', error);

            // Show user-friendly error
            container.innerHTML = `
                <div class="card">
                    <h3>Error</h3>
                    <p>Unable to load FAQ data.</p>
                </div>
            `;
        }  

    }

// =========================================
// BLOG ARTICLE UX
// =========================================

function getBlogPostId(post) {
    return post?.ID ?? post?.id ?? post?.blog_post_id ?? null;
}

function getBlogDetailsHref(post) {
    const postId = getBlogPostId(post);

    if (postId === null || postId === undefined || String(postId).trim() === '') {
        return '/pages/blogdetails.php';
    }

    return `/pages/blogdetails.php?id=${encodeURIComponent(postId)}`;
}

function parseBlogPublishedAt(value) {
    const rawValue = String(value ?? '').trim();

    if (!rawValue) return 0;

    const dateParts = rawValue.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}):(\d{2}))?/);

    if (dateParts) {
        const [, year, month, day, hour = '0', minute = '0', second = '0'] = dateParts;

        return new Date(
            Number(year),
            Number(month) - 1,
            Number(day),
            Number(hour),
            Number(minute),
            Number(second)
        ).getTime();
    }

    const parsedDate = Date.parse(rawValue);

    return Number.isNaN(parsedDate) ? 0 : parsedDate;
}

function formatBlogPublishedAt(value) {
    const rawValue = String(value ?? '').trim();

    if (!rawValue) return '';

    const dateParts = rawValue.match(/^(\d{4})-(\d{2})-(\d{2})/);

    if (dateParts) {
        const [, year, month, day] = dateParts;
        const date = new Date(Number(year), Number(month) - 1, Number(day));

        return new Intl.DateTimeFormat('en-US', {
            month: 'long',
            day: 'numeric',
            year: 'numeric'
        }).format(date);
    }

    const parsedDate = new Date(rawValue);

    if (!Number.isNaN(parsedDate.getTime())) {
        return new Intl.DateTimeFormat('en-US', {
            month: 'long',
            day: 'numeric',
            year: 'numeric'
        }).format(parsedDate);
    }

    return rawValue;
}

function getBlogDateTimeValue(value) {
    const rawValue = String(value ?? '').trim();
    const dateParts = rawValue.match(/^(\d{4}-\d{2}-\d{2})/);

    return dateParts ? dateParts[1] : rawValue;
}

function resolveBlogImageSrc(imageUrl) {
    const image = String(imageUrl ?? '').trim();

    if (!image) {
        return '/images/blog/sample_image.png';
    }

    if (/^(https?:)?\/\//i.test(image) || image.startsWith('/') || image.startsWith('data:')) {
        return image;
    }

    if (image.startsWith('images/')) {
        return `/${image}`;
    }

    if (image.startsWith('blog/')) {
        return `/images/${image}`;
    }

    return `/images/blog/${image}`;
}

function sortBlogPostsByPublishedAt(posts) {
    return [...posts].sort((postA, postB) => (
        parseBlogPublishedAt(postB?.published_at) - parseBlogPublishedAt(postA?.published_at)
    ));
}

function getBlogAuthorName(post) {
    const firstName = String(post?.first_name ?? '').trim();
    const lastName = String(post?.last_name ?? '').trim();
    const combinedName = `${firstName} ${lastName}`.trim();
    const authorFields = [
        post?.author_full_name,
        post?.author_name,
        post?.full_name,
        combinedName,
        post?.author_user_id
    ];

    return String(authorFields.find((value) => String(value ?? '').trim() !== '') ?? '').trim();
}

function getBlogCategoryId(post) {
    return String(post?.blog_category_id ?? post?.category_id ?? '').trim();
}

function getBlogCategoryName(post) {
    const categoryFields = [
        post?.category_name,
        post?.blog_category_name,
        post?.category,
        getBlogCategoryId(post)
    ];

    return String(categoryFields.find((value) => String(value ?? '').trim() !== '') ?? '').trim();
}

function getBlogCategoryOptionId(category) {
    return String(category?.blog_category_id ?? category?.category_id ?? category?.id ?? '').trim();
}

function populateBlogCategoryFilter(categories, fallbackPosts = []) {
    const categoryFilter = document.getElementById('blogCategoryFilter');

    if (!categoryFilter) return;

    const selectedValue = categoryFilter.value || 'all';
    const categoryMap = new Map();
    const categoryRows = Array.isArray(categories) && categories.length > 0
        ? categories
        : fallbackPosts;

    categoryRows.forEach((category) => {
        const categoryId = getBlogCategoryOptionId(category);
        const categoryName = getBlogCategoryName(category);

        if (!categoryId || !categoryName || categoryMap.has(categoryId)) {
            return;
        }

        categoryMap.set(categoryId, categoryName);
    });

    const categoryOptions = Array.from(categoryMap.entries())
        .sort(([, nameA], [, nameB]) => nameA.localeCompare(nameB));

    categoryFilter.innerHTML = `
        <option value="all">All Categories</option>
        ${categoryOptions.map(([categoryId, categoryName]) => `
            <option value="${escapeHtml(categoryId)}">
                ${escapeHtml(categoryName)}
            </option>
        `).join('')}
    `;

    categoryFilter.value = categoryMap.has(selectedValue) ? selectedValue : 'all';
}

function setCurrentBlogArticle(post) {
    const link = document.getElementById('currentArticleLink');
    const image = document.getElementById('currentArticleImage');
    const title = document.getElementById('currentArticleTitle');
    const author = document.getElementById('currentArticleAuthor');
    const date = document.getElementById('currentArticleDate');
    const category = document.getElementById('currentArticleCategory');
    const description = document.getElementById('currentArticleDescription');
    const postTitle = String(post?.post_title ?? 'Untitled article').trim();
    const authorName = getBlogAuthorName(post);
    const categoryName = getBlogCategoryName(post);
    const publishedAt = post?.published_at ?? '';

    if (link) {
        link.href = getBlogDetailsHref(post);
    }

    if (image) {
        image.src = resolveBlogImageSrc(post?.featured_image_url);
        image.alt = postTitle;
    }

    if (title) {
        title.innerHTML = `<strong>${escapeHtml(postTitle)}</strong>`;
    }

    if (author) {
        author.textContent = authorName ? `By ${authorName}` : 'By';
    }

    if (date) {
        date.textContent = formatBlogPublishedAt(publishedAt);
        date.dateTime = getBlogDateTimeValue(publishedAt);
    }

    if (category) {
        category.textContent = categoryName;
    }

    if (description) {
        description.textContent = post?.post_excerpt ?? '';
    }
}

function setEmptyBlogArticleMessage(message) {
    const title = document.getElementById('currentArticleTitle');
    const author = document.getElementById('currentArticleAuthor');
    const date = document.getElementById('currentArticleDate');
    const category = document.getElementById('currentArticleCategory');
    const description = document.getElementById('currentArticleDescription');

    if (title) {
        title.innerHTML = `<strong>${escapeHtml(message)}</strong>`;
    }

    if (author) {
        author.textContent = '';
    }

    if (date) {
        date.textContent = '';
        date.dateTime = '';
    }

    if (category) {
        category.textContent = '';
    }

    if (description) {
        description.textContent = '';
    }
}

function buildPreviousBlogArticle(post, index) {
    const article = document.createElement('a');
    const postTitle = String(post?.post_title ?? 'Untitled article').trim();
    const authorName = getBlogAuthorName(post);
    const categoryName = getBlogCategoryName(post);
    const publishedAt = post?.published_at ?? '';
    const postId = getBlogPostId(post);

    article.id = `previousArticleLink${index + 1}`;
    article.href = getBlogDetailsHref(post);
    article.className = 'previous-article';

    if (postId !== null && postId !== undefined) {
        article.dataset.blogPostId = String(postId);
    }

    article.innerHTML = `
        <img id="previousArticleImage${index + 1}"
             src="${escapeHtml(resolveBlogImageSrc(post?.featured_image_url))}"
             alt="${escapeHtml(postTitle)}">

        <div class="previous-article-content">
            <div id="previousArticleTitle${index + 1}" class="previous-title">
                ${escapeHtml(postTitle)}
            </div>

            <div id="previousArticleAuthor${index + 1}" class="previous-author">
                ${authorName ? `By ${escapeHtml(authorName)}` : ''}
            </div>

            <time id="previousArticleDate${index + 1}"
                  class="previous-date"
                  datetime="${escapeHtml(getBlogDateTimeValue(publishedAt))}">
                ${escapeHtml(formatBlogPublishedAt(publishedAt))}
            </time>

            <div id="previousArticleCategory${index + 1}" class="previous-category">
                ${escapeHtml(categoryName)}
            </div>
        </div>
    `;

    return article;
}

async function fetchBlogPosts() {
    const response = await fetch('/api/getBlogPosts.php');
    const data = await response.json();

    if (!response.ok || data?.error) {
        throw new Error(data?.error || `HTTP error! Status: ${response.status}`);
    }

    if (!Array.isArray(data)) {
        throw new Error('Blog posts response was not an array.');
    }

    return sortBlogPostsByPublishedAt(data);
}

async function fetchBlogCategories() {
    const response = await fetch('/api/getBlogCategories.php');
    const data = await response.json();

    if (!response.ok || data?.error) {
        throw new Error(data?.error || `HTTP error! Status: ${response.status}`);
    }

    if (!Array.isArray(data)) {
        throw new Error('Blog categories response was not an array.');
    }

    return data;
}

function paginatePreviousBlogArticles(container) {
    const articles = Array.from(container.querySelectorAll('.previous-article'));
    const controls = container.querySelector('.blog-page-controls');

    if (!controls) return;

    const prevButton = controls.querySelector('[data-blog-page-action="prev"]');
    const nextButton = controls.querySelector('[data-blog-page-action="next"]');
    const pageNumbers = controls.querySelector('.blog-page-numbers');
    const configuredPageSize = Number.parseInt(container.dataset.pageSize || '3', 10);
    const pageSize = Number.isNaN(configuredPageSize) ? 3 : Math.max(1, configuredPageSize);
    const totalPages = Math.max(1, Math.ceil(articles.length / pageSize));
    let currentPage = 0;

    controls.classList.remove('hidden');

    const renderPage = () => {
        const firstArticleIndex = currentPage * pageSize;
        const lastArticleIndex = firstArticleIndex + pageSize;

        articles.forEach((article, index) => {
            article.classList.toggle('hidden', index < firstArticleIndex || index >= lastArticleIndex);
        });

        if (prevButton) {
            prevButton.disabled = articles.length === 0 || currentPage === 0;
        }

        if (nextButton) {
            nextButton.disabled = articles.length === 0 || currentPage === totalPages - 1;
        }

        if (!pageNumbers) return;

        pageNumbers.innerHTML = '';

        for (let pageIndex = 0; pageIndex < totalPages; pageIndex += 1) {
            const pageButton = document.createElement('button');
            pageButton.type = 'button';
            pageButton.className = 'blog-page-number';
            pageButton.textContent = String(pageIndex + 1);
            pageButton.setAttribute('aria-label', `Page ${pageIndex + 1}`);

            if (pageIndex === currentPage) {
                pageButton.classList.add('active');
                pageButton.setAttribute('aria-current', 'page');
            }

            pageButton.addEventListener('click', () => goToPage(pageIndex));
            pageNumbers.appendChild(pageButton);
        }
    };

    const goToPage = (pageIndex) => {
        currentPage = Math.min(Math.max(pageIndex, 0), totalPages - 1);
        renderPage();
    };

    if (prevButton) {
        prevButton.onclick = () => goToPage(currentPage - 1);
    }

    if (nextButton) {
        nextButton.onclick = () => goToPage(currentPage + 1);
    }

    renderPage();
}

function filterBlogPostsByCategory(blogPosts, categoryId) {
    if (!categoryId || categoryId === 'all') {
        return blogPosts;
    }

    return blogPosts.filter((post) => getBlogCategoryId(post) === categoryId);
}

function clearPreviousBlogArticles(container) {
    container.querySelectorAll('.previous-article').forEach((article) => article.remove());
}

function renderBlogArticles(blogPosts, categoryId = 'all') {
    const container = document.getElementById('previousBlogArticles');
    if (!container) return;

    const controlsWrapper = container.querySelector('.blog-sidebar-controls');
    const loadingMessage = document.getElementById('previousArticlesLoadingMessage');
    const filteredBlogPosts = filterBlogPostsByCategory(blogPosts, categoryId);

    clearPreviousBlogArticles(container);

    if (loadingMessage) {
        loadingMessage.classList.remove('hidden');
        loadingMessage.textContent = 'No previous articles are available.';
    }

    if (filteredBlogPosts.length === 0) {
        setEmptyBlogArticleMessage('No blog articles are available.');
        paginatePreviousBlogArticles(container);
        return;
    }

    setCurrentBlogArticle(filteredBlogPosts[0]);

    const previousPosts = filteredBlogPosts.slice(1);

    if (previousPosts.length > 0 && loadingMessage) {
        loadingMessage.classList.add('hidden');
    }

    previousPosts.forEach((post, index) => {
        container.insertBefore(buildPreviousBlogArticle(post, index), controlsWrapper);
    });

    paginatePreviousBlogArticles(container);
}

async function initBlogArticles() {
    const container = document.getElementById('previousBlogArticles');
    if (!container) return;

    const controls = container.querySelector('.blog-page-controls');
    const categoryFilter = document.getElementById('blogCategoryFilter');
    const loadingMessage = document.getElementById('previousArticlesLoadingMessage');

    clearPreviousBlogArticles(container);

    if (loadingMessage) {
        loadingMessage.classList.remove('hidden');
        loadingMessage.textContent = 'Loading previous articles...';
    }

    if (controls) {
        controls.classList.remove('hidden');
    }

    try {
        const [blogPosts, blogCategories] = await Promise.all([
            fetchBlogPosts(),
            fetchBlogCategories()
        ]);

        populateBlogCategoryFilter(blogCategories, blogPosts);

        if (categoryFilter) {
            categoryFilter.onchange = () => renderBlogArticles(blogPosts, categoryFilter.value);
        }

        renderBlogArticles(blogPosts, categoryFilter?.value || 'all');

    } catch (error) {
        console.error('Error loading blog posts:', error);
        setEmptyBlogArticleMessage('Error loading blog articles.');

        if (loadingMessage) {
            loadingMessage.textContent = 'Unable to load previous articles.';
            loadingMessage.classList.remove('hidden');
        }

        paginatePreviousBlogArticles(container);
    }
}

// =========================================
// CUSTOMER PROFILE UX
// =========================================

const customerProfileState = {
    csrfToken: '',
    catalogs: {},
    favorites: [],
    orders: [],
    profile: {},
    states: []
};

const customerFavoriteTypeLabels = {
    cruise_line: 'Cruise line',
    ship: 'Ship',
    destination: 'Destination',
    itinerary: 'Itinerary',
    port: 'Port',
    excursion: 'Shore excursion'
};

function formatCustomerDate(value, options = {}) {
    const rawValue = String(value || '').trim();
    if (!rawValue) return '—';

    const match = rawValue.match(/^(\d{4})-(\d{2})-(\d{2})/);
    const date = match
        ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
        : new Date(rawValue);

    if (Number.isNaN(date.getTime())) return rawValue;

    return new Intl.DateTimeFormat('en-US', {
        month: options.short ? 'short' : 'long',
        day: options.includeDay === false ? undefined : 'numeric',
        year: 'numeric'
    }).format(date);
}

function formatCustomerMoney(value, currencyCode = 'USD') {
    const amount = Number(value || 0);
    const currency = /^[A-Z]{3}$/.test(String(currencyCode || '').toUpperCase())
        ? String(currencyCode).toUpperCase()
        : 'USD';

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency
    }).format(Number.isFinite(amount) ? amount : 0);
}

function customerProfileInitials(profile) {
    const first = String(profile?.first_name || '').trim().charAt(0);
    const last = String(profile?.last_name || '').trim().charAt(0);
    return `${first}${last}`.toUpperCase() || 'NC';
}

function customerProfileAddress(profile) {
    return [
        profile.address_1,
        profile.address_2,
        [profile.city, profile.state_province, profile.postal_code].filter(Boolean).join(', '),
        profile.country
    ].filter((part) => String(part || '').trim()).join('<br>');
}

function setCustomerProfileAlert(message = '', type = 'success') {
    const alert = document.getElementById('customerProfileAlert');
    if (!alert) return;

    alert.textContent = message;
    alert.className = `customer-profile__alert customer-profile__alert--${type}`;
    alert.hidden = !message;
}

async function fetchCustomerProfileJson(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            ...(options.headers || {})
        }
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok || data.success === false) {
        throw new Error(data.message || 'The request could not be completed.');
    }

    return data;
}

function renderCustomerProfileDetails(profile) {
    const fullName = `${profile.first_name || ''} ${profile.last_name || ''}`.trim() || 'Customer';
    const details = document.getElementById('customerProfileDetails');
    const name = document.getElementById('customerProfileName');
    const avatar = document.getElementById('customerProfileAvatar');
    const memberSince = document.getElementById('customerMemberSince');
    const address = customerProfileAddress(profile);

    if (name) name.textContent = fullName;
    if (avatar) avatar.textContent = customerProfileInitials(profile);
    if (memberSince) memberSince.textContent = formatCustomerDate(profile.created_at, { includeDay: false, short: true });

    if (details) {
        details.innerHTML = `
            <div><dt>Email</dt><dd><a href="mailto:${escapeHtml(profile.email_address)}">${escapeHtml(profile.email_address || '—')}</a></dd></div>
            <div><dt>Phone</dt><dd>${escapeHtml(profile.phone || '—')}</dd></div>
            <div><dt>Address</dt><dd>${address ? address.split('<br>').map(escapeHtml).join('<br>') : '—'}</dd></div>
            <div><dt>Last sign-in</dt><dd>${escapeHtml(formatCustomerDate(profile.last_login_at, { short: true }))}</dd></div>
        `;
    }
}

function setCustomerProfileField(id, value) {
    const field = document.getElementById(id);
    if (field) field.value = value ?? '';
}

function renderCustomerProfileEditForm() {
    const profile = customerProfileState.profile || {};
    const stateSelect = document.getElementById('customerStateProvince');

    setCustomerProfileField('customerFirstName', profile.first_name);
    setCustomerProfileField('customerLastName', profile.last_name);
    setCustomerProfileField('customerEmailAddress', profile.email_address);
    setCustomerProfileField('customerPhone', profile.phone);
    setCustomerProfileField('customerAddress1', profile.address_1);
    setCustomerProfileField('customerAddress2', profile.address_2);
    setCustomerProfileField('customerCity', profile.city);
    setCustomerProfileField('customerPostalCode', profile.postal_code);
    setCustomerProfileField('customerCountry', profile.country);

    if (stateSelect) {
        stateSelect.innerHTML = '';

        customerProfileState.states.forEach((state) => {
            const option = document.createElement('option');
            option.value = String(state.id);
            option.textContent = state.label;
            option.selected = Number(state.id) === Number(profile.state_prov_id);
            stateSelect.appendChild(option);
        });
    }
}

function openCustomerProfileEditor() {
    const form = document.getElementById('customerProfileEditForm');
    const details = document.getElementById('customerProfileDetails');
    const editButton = document.getElementById('customerProfileEditButton');
    if (!form || !details || !editButton) return;

    renderCustomerProfileEditForm();
    details.hidden = true;
    form.hidden = false;
    form.setAttribute('aria-hidden', 'false');
    editButton.hidden = true;
    editButton.setAttribute('aria-expanded', 'true');
    document.getElementById('customerFirstName')?.focus();
}

function closeCustomerProfileEditor(reset = true) {
    const form = document.getElementById('customerProfileEditForm');
    const details = document.getElementById('customerProfileDetails');
    const editButton = document.getElementById('customerProfileEditButton');
    if (!form || !details || !editButton) return;

    if (reset) renderCustomerProfileEditForm();
    form.hidden = true;
    form.setAttribute('aria-hidden', 'true');
    details.hidden = false;
    editButton.hidden = false;
    editButton.setAttribute('aria-expanded', 'false');
}

async function submitCustomerProfile(form) {
    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) submitButton.disabled = true;
    setCustomerProfileAlert();

    try {
        const result = await fetchCustomerProfileJson('/customer/api/saveProfile.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': customerProfileState.csrfToken },
            body: new FormData(form)
        });
        await loadCustomerProfileData();
        closeCustomerProfileEditor(false);
        setCustomerProfileAlert(result.message || 'Account details updated.');
    } catch (error) {
        setCustomerProfileAlert(error.message, 'error');
    } finally {
        if (submitButton) submitButton.disabled = false;
    }
}

function populateCustomerFavoriteOptions() {
    const typeSelect = document.getElementById('customerFavoriteType');
    const entitySelect = document.getElementById('customerFavoriteEntity');
    const submitButton = document.querySelector('#customerFavoriteForm button[type="submit"]');
    if (!typeSelect || !entitySelect) return;

    const items = customerProfileState.catalogs[typeSelect.value] || [];
    entitySelect.innerHTML = '';

    if (items.length === 0) {
        const option = document.createElement('option');
        option.value = '';
        option.textContent = `No ${customerFavoriteTypeLabels[typeSelect.value]?.toLowerCase() || 'items'} available`;
        entitySelect.appendChild(option);
        entitySelect.disabled = true;
        if (submitButton) submitButton.disabled = true;
        return;
    }

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = 'Select an item';
    entitySelect.appendChild(placeholder);

    items.forEach((item) => {
        const option = document.createElement('option');
        option.value = String(item.id);
        option.textContent = item.label;
        entitySelect.appendChild(option);
    });

    entitySelect.disabled = false;
    if (submitButton) submitButton.disabled = false;
}

function renderCustomerFavorites() {
    const container = document.getElementById('customerFavoritesList');
    const count = document.getElementById('customerFavoriteCount');
    if (count) count.textContent = String(customerProfileState.favorites.length);
    if (!container) return;

    if (customerProfileState.favorites.length === 0) {
        container.innerHTML = '<p class="customer-profile__empty">You have not saved any travel favorites yet.</p>';
        return;
    }

    container.innerHTML = customerProfileState.favorites.map((favorite) => `
        <div class="customer-profile__favorite">
            <div>
                <span>${escapeHtml(customerFavoriteTypeLabels[favorite.favorite_type] || 'Favorite')}</span>
                <strong>${escapeHtml(favorite.label || 'Saved item')}</strong>
            </div>
            <button type="button" class="customer-profile__remove-favorite" data-favorite-id="${Number(favorite.id)}" aria-label="Remove ${escapeHtml(favorite.label || 'favorite')}">
                Remove
            </button>
        </div>
    `).join('');
}

function renderCustomerOrders() {
    const container = document.getElementById('customerOrdersList');
    const count = document.getElementById('customerOrderCount');
    if (count) count.textContent = String(customerProfileState.orders.length);
    if (!container) return;

    if (customerProfileState.orders.length === 0) {
        container.innerHTML = '<p class="customer-profile__empty">No purchases are associated with this account yet.</p>';
        return;
    }

    container.innerHTML = customerProfileState.orders.map((order) => {
        const currency = order.currency_code || order.items?.[0]?.currency_code || 'USD';
        const status = order.transaction_status || order.order_status || 'Processing';
        const statusClass = String(status).toLowerCase().replace(/[^a-z0-9]+/g, '-');
        const items = Array.isArray(order.items) ? order.items : [];
        const itemMarkup = items.length > 0
            ? `<ul>${items.map((item) => `
                <li>
                    <div>
                        <strong>${escapeHtml(item.product_name)}</strong>
                        <span>${Number(item.quantity)} × ${escapeHtml(formatCustomerMoney(item.unit_price, item.currency_code || currency))}${item.product_options ? ` · ${escapeHtml(item.product_options)}` : ''}</span>
                    </div>
                    <strong>${escapeHtml(formatCustomerMoney(item.line_subtotal, item.currency_code || currency))}</strong>
                </li>
            `).join('')}</ul>`
            : '<p class="customer-profile__order-empty">Item details are not available for this order.</p>';

        return `
            <details class="customer-profile__order">
                <summary>
                    <div>
                        <strong>Order ${escapeHtml(order.order_number || `#${order.id}`)}</strong>
                        <span>${escapeHtml(formatCustomerDate(order.created_at, { short: true }))}</span>
                    </div>
                    <div class="customer-profile__order-summary">
                        <span class="customer-profile__status customer-profile__status--${statusClass}">${escapeHtml(status)}</span>
                        <strong>${escapeHtml(formatCustomerMoney(order.total_amount, currency))}</strong>
                    </div>
                </summary>
                <div class="customer-profile__order-body">
                    ${itemMarkup}
                    <dl>
                        <div><dt>Subtotal</dt><dd>${escapeHtml(formatCustomerMoney(order.subtotal_amount, currency))}</dd></div>
                        <div><dt>Shipping</dt><dd>${escapeHtml(formatCustomerMoney(order.shipping_amount, currency))}</dd></div>
                        <div><dt>Tax</dt><dd>${escapeHtml(formatCustomerMoney(order.tax_amount, currency))}</dd></div>
                        <div><dt>Total</dt><dd>${escapeHtml(formatCustomerMoney(order.total_amount, currency))}</dd></div>
                    </dl>
                </div>
            </details>
        `;
    }).join('');
}

async function loadCustomerProfileData() {
    const data = await fetchCustomerProfileJson('/customer/api/getProfile.php');
    customerProfileState.csrfToken = data.csrf_token || '';
    customerProfileState.catalogs = data.catalogs || {};
    customerProfileState.favorites = Array.isArray(data.favorites) ? data.favorites : [];
    customerProfileState.orders = Array.isArray(data.orders) ? data.orders : [];
    customerProfileState.profile = data.profile || {};
    customerProfileState.states = Array.isArray(data.states) ? data.states : [];

    renderCustomerProfileDetails(customerProfileState.profile);
    renderCustomerProfileEditForm();
    populateCustomerFavoriteOptions();
    renderCustomerFavorites();
    renderCustomerOrders();
}

async function submitCustomerFavorite(form) {
    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) submitButton.disabled = true;

    try {
        const result = await fetchCustomerProfileJson('/customer/api/saveFavorite.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': customerProfileState.csrfToken },
            body: new FormData(form)
        });
        await loadCustomerProfileData();
        setCustomerProfileAlert(result.message || 'Favorite added.');
    } catch (error) {
        setCustomerProfileAlert(error.message, 'error');
    } finally {
        if (submitButton) submitButton.disabled = false;
        populateCustomerFavoriteOptions();
    }
}

async function removeCustomerFavorite(favoriteId, button) {
    button.disabled = true;
    const body = new FormData();
    body.append('favorite_id', String(favoriteId));

    try {
        const result = await fetchCustomerProfileJson('/customer/api/deleteFavorite.php', {
            method: 'POST',
            headers: { 'X-CSRF-Token': customerProfileState.csrfToken },
            body
        });
        await loadCustomerProfileData();
        setCustomerProfileAlert(result.message || 'Favorite removed.');
    } catch (error) {
        button.disabled = false;
        setCustomerProfileAlert(error.message, 'error');
    }
}

async function initCustomerProfile() {
    const page = document.getElementById('customerProfilePage');
    if (!page) return;

    const loading = document.getElementById('customerProfileLoading');
    const content = document.getElementById('customerProfileContent');
    const form = document.getElementById('customerFavoriteForm');
    const typeSelect = document.getElementById('customerFavoriteType');
    const favorites = document.getElementById('customerFavoritesList');
    const editButton = document.getElementById('customerProfileEditButton');
    const cancelEditButton = document.getElementById('customerProfileCancelEdit');
    const profileForm = document.getElementById('customerProfileEditForm');

    if (typeSelect) typeSelect.addEventListener('change', populateCustomerFavoriteOptions);
    if (editButton) editButton.addEventListener('click', openCustomerProfileEditor);
    if (cancelEditButton) cancelEditButton.addEventListener('click', () => closeCustomerProfileEditor());
    if (profileForm) profileForm.addEventListener('submit', (event) => {
        event.preventDefault();
        submitCustomerProfile(profileForm);
    });
    if (form) form.addEventListener('submit', (event) => {
        event.preventDefault();
        submitCustomerFavorite(form);
    });
    if (favorites) favorites.addEventListener('click', (event) => {
        const button = event.target.closest('[data-favorite-id]');
        if (button) removeCustomerFavorite(button.dataset.favoriteId, button);
    });

    try {
        await loadCustomerProfileData();
        closeCustomerProfileEditor(false);
        if (content) content.hidden = false;
        if (loading) loading.hidden = true;
    } catch (error) {
        if (loading) loading.hidden = true;
        setCustomerProfileAlert(error.message || 'Unable to load your profile.', 'error');
    }
}
// =========================================
// CUSTOMER NEWS PERSONALIZATION
// =========================================
const customerNewsState = { csrf: '', bootstrap: null, previewRequestId: 0 };
async function customerNewsRequest(action, method = 'GET', data = null) {
    const response = await fetch(`/customer/api/news.php?action=${encodeURIComponent(action)}`, {method,headers:method==='GET'?{}:{'Content-Type':'application/json','X-CSRF-Token':customerNewsState.csrf},body:method==='GET'?undefined:JSON.stringify(data||{})});
    const payload=await response.json();if(!response.ok||!payload.success)throw new Error(payload.message||'News request failed.');return payload;
}
function customerNewsEscape(value){return String(value??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));}
function customerNewsMessage(message,isError=false){const el=document.getElementById('customerNewsAlert');if(el){el.textContent=message;el.hidden=false;el.classList.toggle('is-error',isError);el.classList.toggle('customer-profile__alert--error',isError);}}
async function initCustomerNews(pageName){
 try{const boot=await customerNewsRequest('bootstrap');customerNewsState.csrf=boot.csrf_token;customerNewsState.bootstrap=boot;if(pageName==='newspreferences'||pageName==='profile')initNewsPreferences();if(pageName==='mynews')loadMyNews();if(pageName==='savednews')loadSavedNews();}catch(error){customerNewsMessage(error.message,true);}
}
function initNewsPreferences(){
 const b=customerNewsState.bootstrap,form=document.getElementById('newsPreferenceForm'),type=form.elements.preference_type,selection=form.elements.selection;
 if(type.value==='entity'&&!b.entities.length)type.value='category';
 const fill=()=>{const rows=type.value==='entity'?b.entities:type.value==='category'?b.categories:b.keywords;selection.innerHTML=rows.map(r=>`<option value="${r.id}">${customerNewsEscape(r.entity_name||r.category_name||r.keyword_name)} (${customerNewsEscape(r.entity_type||r.news_type||r.keyword_type)})</option>`).join('');loadNewsPreferencePreview(type,selection);};type.addEventListener('change',fill);selection.addEventListener('change',()=>loadNewsPreferencePreview(type,selection));fill();renderNewsPreferences();
 const settings=b.settings,privacy=document.getElementById('newsPrivacyForm');privacy.elements.personalization_enabled.checked=Number(settings.personalization_enabled)===1;privacy.elements.history_enabled.checked=Number(settings.history_enabled)===1;privacy.elements.news_type_preference.value=settings.news_type_preference||'both';
 form.addEventListener('submit',async e=>{e.preventDefault();const kind=type.value,id=Number(selection.value),data={preference_type:kind,priority_level:form.elements.priority_level.value};data[`${kind}_id`]=id;try{await customerNewsRequest('save_preference','POST',data);await refreshNewsPreferences();await loadNewsPreferencePreview(type,selection);customerNewsMessage('Interest followed. Matching news is shown below.');}catch(error){customerNewsMessage(error.message,true);}});
 privacy.addEventListener('submit',async e=>{e.preventDefault();try{await customerNewsRequest('save_settings','POST',{personalization_enabled:privacy.elements.personalization_enabled.checked,history_enabled:privacy.elements.history_enabled.checked,news_type_preference:privacy.elements.news_type_preference.value});customerNewsMessage('News settings saved.');}catch(error){customerNewsMessage(error.message,true);}});
 document.getElementById('clearNewsHistory').addEventListener('click',async()=>{try{await customerNewsRequest('clear_history','POST',{});customerNewsMessage('News-view history deleted.');}catch(error){customerNewsMessage(error.message,true);}});
 document.getElementById('newsAlertForm').addEventListener('submit',async e=>{e.preventDefault();try{await customerNewsRequest('create_alert','POST',{alert_name:e.currentTarget.elements.alert_name.value,frequency:e.currentTarget.elements.frequency.value});customerNewsMessage('Digest preference created.');}catch(error){customerNewsMessage(error.message,true);}});
 document.getElementById('newsPreferenceList').addEventListener('click',async e=>{const button=e.target.closest('[data-remove-preference]');if(!button)return;await customerNewsRequest('remove_preference','POST',{id:button.dataset.removePreference});await refreshNewsPreferences();});
}
async function loadNewsPreferencePreview(typeSelect,selectionSelect){
 const target=document.getElementById('newsPreferencePreview'),status=document.getElementById('newsPreferencePreviewStatus');if(!target||!status)return;
 const kind=typeSelect.value,id=String(selectionSelect.value||''),b=customerNewsState.bootstrap;
 const rows=kind==='entity'?b.entities:kind==='category'?b.categories:b.keywords,row=rows.find(item=>String(item.id)===id);
 if(!row){target.innerHTML='';status.textContent='Select an interest to see matching published stories.';return;}
 const label=row.entity_name||row.category_name||row.keyword_name,params=new URLSearchParams({limit:'6'});
 if(kind==='entity')params.set('entity',row.entity_slug);else if(kind==='category')params.set('category',row.category_slug);else params.set('q',row.keyword_name);
 const requestId=++customerNewsState.previewRequestId;status.textContent=`Loading news about ${label}…`;target.innerHTML='';
 try{const response=await fetch(`/api/news/search.php?${params.toString()}`,{credentials:'same-origin'}),data=await response.json();if(!response.ok||!data.success)throw new Error(data.message||'Matching news could not be loaded.');if(requestId!==customerNewsState.previewRequestId)return;status.textContent=data.articles.length?`Showing published news about ${label}.`:`No published news currently matches ${label}.`;target.innerHTML=data.articles.map(a=>customerNewsCard(a)).join('');bindCustomerNewsActions(target);}catch(error){if(requestId!==customerNewsState.previewRequestId)return;status.textContent=error.message;target.innerHTML='';}
}
async function refreshNewsPreferences(){const boot=await customerNewsRequest('bootstrap');customerNewsState.csrf=boot.csrf_token;customerNewsState.bootstrap=boot;renderNewsPreferences();}
function renderNewsPreferences(){const b=customerNewsState.bootstrap,names=new Map([...b.entities.map(x=>[String(x.id),x.entity_name]),...b.categories.map(x=>[String(x.id),x.category_name]),...b.keywords.map(x=>[String(x.id),x.keyword_name])]);document.getElementById('newsPreferenceList').innerHTML=b.preferences.length?b.preferences.map(p=>`<div class="customer-news-interest customer-profile__favorite"><div><strong>${customerNewsEscape(names.get(String(p.entity_id||p.category_id||p.keyword_id))||p.preference_value)}</strong><span>Priority ${p.priority_level}</span></div><button class="customer-profile__remove-favorite" type="button" data-remove-preference="${p.id}">Unfollow</button></div>`).join(''):'<p class="customer-profile__empty">You are not following any news interests yet.</p>';document.getElementById('newsAlertList').innerHTML=b.alerts.length?`<h3>Current digests</h3>${b.alerts.map(a=>`<p>${customerNewsEscape(a.alert_name)} — ${customerNewsEscape(a.frequency)}${Number(a.is_active)?'':' (disabled)'}</p>`).join('')}`:'';}
function customerNewsCard(a,saved=false){return `<article class="customer-news-card"><div><span>${customerNewsEscape(a.category_name||a.news_type)}</span><h2><a href="/news.php?article=${encodeURIComponent(a.slug)}">${customerNewsEscape(a.headline)}</a></h2><p>${customerNewsEscape(a.summary)}</p><small>${customerNewsEscape(a.source_name||'Norman and Company')} · ${customerNewsEscape(a.source_published_at||a.published_at||'')}</small>${a.recommendation_reason?`<p class="recommendation-reason">${customerNewsEscape(a.recommendation_reason)}</p>`:''}</div><div class="customer-news-actions">${saved?`<textarea aria-label="Private note" data-news-note>${customerNewsEscape(a.notes||'')}</textarea><button data-news-unsave="${a.id}">Remove</button>`:`<button data-news-save="${a.id}">Save</button><button data-news-hide="${a.id}">Not relevant</button>`}</div></article>`;}
function bindCustomerNewsActions(target){if(target.dataset.newsActionsBound==='true')return;target.dataset.newsActionsBound='true';target.addEventListener('click',async e=>{const save=e.target.closest('[data-news-save]'),hide=e.target.closest('[data-news-hide]');try{if(save){await customerNewsRequest('save_article','POST',{article_id:save.dataset.newsSave});customerNewsMessage('Article saved.');}if(hide){await customerNewsRequest('hide_article','POST',{article_id:hide.dataset.newsHide,reason:'not_relevant'});hide.closest('article')?.remove();}}catch(error){customerNewsMessage(error.message,true);}});}
async function loadMyNews(){const target=document.getElementById('customerNewsFeed');try{const data=await customerNewsRequest('feed');target.innerHTML=data.articles.length?data.articles.map(a=>customerNewsCard(a)).join(''):'<p>No recommendations are available yet. Follow interests in News Preferences.</p>';bindCustomerNewsActions(target);}catch(error){target.innerHTML=`<p>${customerNewsEscape(error.message)}</p>`;}}
async function loadSavedNews(){const target=document.getElementById('customerSavedFeed'),sort=document.getElementById('savedNewsSort');const load=async()=>{try{const response=await fetch(`/customer/api/news.php?action=saved&sort=${encodeURIComponent(sort.value)}`),data=await response.json();if(!response.ok||!data.success)throw new Error(data.message);target.innerHTML=data.articles.length?data.articles.map(a=>customerNewsCard(a,true)).join(''):'<p>You have no saved news yet.</p>';}catch(error){target.innerHTML=`<p>${customerNewsEscape(error.message)}</p>`;}};sort.addEventListener('change',load);target.addEventListener('click',async e=>{const button=e.target.closest('[data-news-unsave]');if(!button)return;await customerNewsRequest('unsave_article','POST',{article_id:button.dataset.newsUnsave});button.closest('article').remove();});target.addEventListener('change',async e=>{const note=e.target.closest('[data-news-note]');if(!note)return;const id=note.closest('article').querySelector('[data-news-unsave]').dataset.newsUnsave;await customerNewsRequest('save_article','POST',{article_id:id,notes:note.value});});load();}
