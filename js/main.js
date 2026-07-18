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
        'products.php'
    ];

    const currentPage = window.location.pathname.split('/').pop();

    const isStandalonePage = standalonePages.includes(currentPage);

    if (currentPage === 'products.php') {
        initTravelStore();
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
            // Create card element
            const card = document.createElement('div');
            card.classList.add('card', 'cruise-line-card');

            // Build card HTML
            card.innerHTML = `
                <img src="/images/cruiselines/${cruise.image_url}" 
                     alt="${cruise.cruise_line_name}"
                     class="cruise-line-logo"
                     width="${cruise.image_size}">
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
