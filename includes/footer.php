<footer>
    <div class="footer-grid">

        <div>
            <h3>Norman and Company</h3>
            <p>Your trusted Caribbean travel companion.</p>
            <img src="/images/NormanAndCompany - FooterLogo.png"
                 alt="Normand and Company Logo"
                 class="footer-logo">
        </div>

        <div>
            <h3>Products</h3>
            <ul>
                <li><a href="/products.php?category=1" onclick="loadProductCategory(event, '1')">Apparel & Fashion</a></li>
                <li><a href="/products.php?category=2" onclick="loadProductCategory(event, '2')">Stickers & Decals</a></li>
                <li><a href="/products.php?category=3" onclick="loadProductCategory(event, '3')">Health & Wellness</a></li>
                <li><a href="/products.php?category=4" onclick="loadProductCategory(event, '4')">Travel Accessories</a></li>
                <li><a href="/products.php?category=5" onclick="loadProductCategory(event, '5')">Beach Accessories</a></li>
                <li><a href="/products.php?category=6" onclick="loadProductCategory(event, '6')">Novelty Items</a></li>
                <li><a href="/products.php?category=7" onclick="loadProductCategory(event, '7')">Kids' Collection</a></li>
                <li><a href="/products.php?category=8" onclick="loadProductCategory(event, '8')">Cruise Essentials</a></li>
            </ul>
        </div>

        <div>
            <h3>Resources</h3>
            <ul>
                <li><a href="#" onclick="loadPage('cruiselines')">Cruiselines</a></li>
                <li><a href="#" onclick="loadPage('resorts')">Resorts</a></li>
                <li><a href="#" onclick="loadPage('destinations')">Destinations</a></li>
                <li><a href="#" onclick="loadPage('travelessentials')">Travel Essentials</a></li>
                <li><a href="#" onclick="loadPage('traveladvice')">Travel Advice</a></li>
                <li><a href="#" onclick="loadPage('blog')">Travel Blog</a></li>
            </ul>
        </div>

        <div>
            <h3>About</h3>
            <ul>
                <li><a href="#" onclick="loadPage('about')">About Us</a></li>
                <li><a href="#" onclick="loadPage('faq')">FAQ</a></li>
                <li><a href="#" onclick="loadPage('privacy')">Privacy</a></li>
                <li><a href="#" onclick="loadPage('contact')">Contact Us</a></li>
            </ul>
            
            <!-- SOCIAL MEDIA ICONS -->
            <div class="social-icons">

                <a href="#">
                    <img src="/images/Facebook-80x80.png"
                         alt="Norman on Facebook">
                </a>

                <a href="https://www.instagram.com/normancaribco?igsh=d2NnZ2ljbjhpZnpm">
                    <img src="/images/Instagram-80x80.png"
                         alt="Norman on Instagram">
                </a>

                <a href="https://x.com/NormanCaribCo">
                    <img src="/images/X-80x80.png"
                         alt="Norman on X">
                </a>

                <a href="https://www.pinterest.com/normancaribbean/">
                    <img src="/images/Pinterest-80x80.png"
                         alt="Norman on Pinterest">
                </a>

                <a href="https://www.reddit.com/user/NormanAndCompany/">
                    <img src="/images/Reddit-80x80.png"
                         alt="Norman on Reddit">
                </a>

            </div>
        </div>
    </div>
</footer>
    <div class="footer-bottom">
        <p class="copyright">
            © 2026 Norman and Company. All rights reserved.
        </p>
    </div>
<script>
function loadProductCategory(event, categoryId) {
    if (event) {
        event.preventDefault();
    }

    const normalizedCategory = String(categoryId || 'all').trim() || 'all';
    const fallbackUrl = new URL('/products.php', window.location.origin);

    if (normalizedCategory !== 'all') {
        fallbackUrl.searchParams.set('category', normalizedCategory);
    }

    if (typeof loadPage !== 'function' || window.location.pathname.startsWith('/admin')) {
        window.location.href = fallbackUrl.toString();
        return;
    }

    Promise.resolve(loadPage('travelstore', { category: normalizedCategory })).then(() => {
        if (typeof setTravelCategory === 'function') {
            return;
        }

        const dropdown = document.getElementById('categoryFilter');
        if (dropdown) {
            dropdown.value = normalizedCategory;
        }

        if (typeof loadTravelStore === 'function') {
            loadTravelStore(normalizedCategory);
        }
    });
}
</script>
</body>
</html>
