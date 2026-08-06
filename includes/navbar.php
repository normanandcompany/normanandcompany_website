<nav>
    <ul class="main-nav">
        <li><a href="/" onclick="return navigateSitePage(event, 'home')">Home</a></li>
        <li><a href="/?page=about" onclick="return navigateSitePage(event, 'about')">About Us</a></li>
        <li class="nav-dropdown"><a href="/products.php" onclick="loadProductCategory(event, 'all')">Store</a>
            <ul class="submenu">
                <li><a href="/products.php?category=1" onclick="loadProductCategory(event, '1')">Apparel & Fashion</a></li>
                <li><a href="/products.php?category=5" onclick="loadProductCategory(event, '5')">Beach Accessories</a></li>
                <li><a href="/products.php?category=9" onclick="loadProductCategory(event, '9')">Books</a></li>
                <li><a href="/products.php?category=8" onclick="loadProductCategory(event, '8')">Cruise Essentials</a></li>
                <li><a href="/products.php?category=3" onclick="loadProductCategory(event, '3')">Health & Wellness</a></li>
                <li><a href="/products.php?category=7" onclick="loadProductCategory(event, '7')">Kid's Collection</a></li>
                <li><a href="/products.php?category=6" onclick="loadProductCategory(event, '6')">Novelty Items</a></li>
                <li><a href="/products.php?category=2" onclick="loadProductCategory(event, '2')">Stickers & Decals</a></li>
                <li><a href="/products.php?category=4" onclick="loadProductCategory(event, '4')">Travel Accessories</a></li>
            </ul>
        </li>
        <li class="nav-dropdown">
            <span>Cruise Travel</span>
            <ul class="submenu">
                <li><a href="/?page=cruiselines" onclick="return navigateSitePage(event, 'cruiselines')">Cruiselines</a></li>
                <li><a href="/?page=portinfo" onclick="return navigateSitePage(event, 'portinfo')">Port Info</a></li>
            </ul>
        </li>

        <li class="nav-dropdown">
            <span>Resort Travel</span>
            <ul class="submenu">
                <li><a href="/?page=resorts" onclick="return navigateSitePage(event, 'resorts')">Resorts</a></li>
            </ul>
        </li>

        <li class="nav-dropdown">
            <span>Destination Info</span>
            <ul class="submenu">
                <li><a href="/?page=destinations" onclick="return navigateSitePage(event, 'destinations')">Destinations</a></li>
                <li><a href="/?page=travelreqs" onclick="return navigateSitePage(event, 'travelreqs')">Travel Requirements</a></li>
                <li><a href="/?page=besttimes" onclick="return navigateSitePage(event, 'besttimes')">Best Times</a></li>
            </ul>
        </li>

        <li class="nav-dropdown">
            <span>Travel Tips</span>
            <ul class="submenu">
                <li><a href="/?page=traveladvice" onclick="return navigateSitePage(event, 'traveladvice')">Advice</a></li>
                <li><a href="/?page=travelessentials" onclick="return navigateSitePage(event, 'travelessentials')">Essentials</a></li>
                <li><a href="/?page=travelplanning" onclick="return navigateSitePage(event, 'travelplanning')">Planning</a></li>
            </ul>
        </li>

        <li><a href="/?page=blog" onclick="return navigateSitePage(event, 'blog')">Blog</a></li>
        <li><a href="/?page=contact" onclick="return navigateSitePage(event, 'contact')">Contact</a></li>
        <li><a href="/login.php">Login</a></li>
        <li>
            <button type="button" class="cart-nav-button" onclick="openCartDrawer()">
                Cart
                <span id="cartNavCount" class="cart-count-badge">0</span>
            </button>
        </li>
    </ul>
</nav>
