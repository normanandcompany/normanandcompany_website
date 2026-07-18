// =========================================
// DYNAMIC PAGE LOADER UX
// =========================================
async function loadPage(pageName) {

    try {
        const response = await fetch(`/customer/pages/${pageName}.php`);
        const content = await response.text();

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
                initTravelStore();
                break; 

            case 'productdetails':
                loadProductDetails();
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
// TRAVEL STORE UX
// =========================================

// INITIALIZATION
function initTravelStore() {
    const dropdown = document.getElementById('categoryFilter');

    if (!dropdown) return;

    // initial load
    loadTravelStore(dropdown.value);

    // bind event once
    dropdown.addEventListener('change', () => {
        loadTravelStore(dropdown.value);
    });
}

// LOAD TRAVEL STORE
async function loadTravelStore(categoryId = null) {
    const container = document.getElementById('productContainer');
    if (!container) return;

    let url = '/api/getProducts.php';

    if (categoryId && categoryId !== 'all') {
        url += `?product_category_id=${encodeURIComponent(categoryId)}`;
    }

    try {
        const res = await fetch(url);
        const data = await res.json();

        container.innerHTML = '';

        data.forEach(product => {
            const card = document.createElement('div');
            card.classList.add('card');

            card.innerHTML = `
                <a href="/pages/productdetails.html?id=${product.id}" class="product-details-link"><img src="/images/products/${product.image_url}" alt="${product.product_name}" class="product-image"></a>
                <a href="/pages/productdetails.html?id=${product.id}" class="product-details-link"><h3>${product.product_name}</h3></a>
                <p><strong>${product.category_name}</strong></p>
                <p>${product.product_description}</p>
                <p><strong>$${parseFloat(product.price).toFixed(2)}</strong></p>
            `;

            container.appendChild(card);
        });

    } catch (err) {
        console.error(err);
        container.innerHTML = "<p>Error loading products.</p>";
    }
}

// LOAD PRODUCT DETAILS
async function loadProductDetails() {
    const container = document.getElementById('productContainer');
    if (!container) return;

    let url = '/api/getProductDetails.php';

    try {
        const res = await fetch(url);
        const data = await res.json();

        container.innerHTML = '';

        data.forEach(product => {
            const card = document.createElement('div');
            card.classList.add('card');

            card.innerHTML = `
                <a href="/pages/productdetails.html?id=${product.id}" class="product-details-link"><img src="/images/products/${product.image_url}" alt="${product.product_name}" class="product-image"></a>
                <a href="/pages/productdetails.html?id=${product.id}" class="product-details-link"><h3>${product.product_name}</h3></a>
                <p><strong>${product.category_name}</strong></p>
                <p>${product.product_description}</p>
                <p><strong>$${parseFloat(product.price).toFixed(2)}</strong></p>
            `;

            container.appendChild(card);
        });

    } catch (err) {
        console.error(err);
        container.innerHTML = "<p>Error loading product details.</p>";
    }
}
