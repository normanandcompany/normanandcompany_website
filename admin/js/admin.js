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

            // Page loader for blog post data
            case 'blogposts':
                loadBlogPosts();
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

    try {

        const response = await fetch('/admin/api/getDashboard.php');
        const data = await response.json();

        const dashboard = data[0];

        document
            .querySelectorAll('[data-field]')
            .forEach(element => {

                const field = element.dataset.field;

                if (dashboard[field] !== undefined) {
                    element.textContent = dashboard[field];
                }

            });

    } catch (err) {

        console.error(err);

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

    if (visible) visible.checked = isEditing ? Number(user.visible) === 1 : true;
    if (active) active.checked = isEditing ? Number(user.is_active) === 1 : true;

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
