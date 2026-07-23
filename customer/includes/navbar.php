<nav>
    <ul class="main-nav">
        <li><a href="#" onclick="loadPage('home')">Profile</a></li>
        
        <li><a href="#" onclick="loadPage('about')">About Us</a></li>

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
                <li><a href="#" onclick="loadPage('cruiselines')">Cruiselines</a></li>
                <li><a href="#" onclick="loadPage('fleetinfo')">Fleet Info</a></li>
                <li><a href="#" onclick="loadPage('shipreviews')">Ship Reviews</a></li>
                <li><a href="#" onclick="loadPage('portinfo')">Port Info</a></li>
                <li><a href="#" onclick="loadPage('cruisedestinations')">Cruise Destinations</a></li>
                <li><a href="#" onclick="loadPage('shoreexcursions')">Shore Excursions</a></li>
                <li><a href="#" onclick="loadPage('cruisenews')">Cruiseline News</a></li>
                <li><a href="#" onclick="loadPage('loyaltyprogs')">Loyalty Programs</a></li>
            </ul>
        </li>

        <li class="nav-dropdown">
            <span>Resort Travel</span>
            <ul class="submenu">
                <li><a href="#" onclick="loadPage('resorts')">Resorts</a></li>
                <li><a href="#" onclick="loadPage('resortreviews')">Resort Reviews</a></li>
                <li><a href="#" onclick="loadPage('resortdestinations')">Resort Destinations</a></li>
                <li><a href="#" onclick="loadPage('resortnews')">Resort News</a></li>
            </ul>
        </li>

        <li class="nav-dropdown">
            <span>Destination Info</span>
            <ul class="submenu">
                <li><a href="#" onclick="loadPage('destinations')">Destinations</a></li>
                <li><a href="#" onclick="loadPage('travelreqs')">Travel Requirements</a></li>
                <li><a href="#" onclick="loadPage('besttimes')">Best Times</a></li>
                <li><a href="#" onclick="loadPage('destinationguides')">Destination Guides</a></li>
            </ul>
        </li>

        <li class="nav-dropdown">
            <span>Travel Tips</span>
            <ul class="submenu">
                <li><a href="#" onclick="loadPage('traveladvice')">Advice</a></li>
                <li><a href="#" onclick="loadPage('travelessentials')">Essentials</a></li>
                <li><a href="#" onclick="loadPage('travelplanning')">Planning</a></li>
                <li><a href="#" onclick="loadPage('travelchecklists')">Travel Checklists</a></li>
            </ul>
        </li>

        <li class="nav-dropdown"><span>My News</span><ul class="submenu"><li><a href="#" onclick="loadPage('mynews')">Personalized Feed</a></li><li><a href="#" onclick="loadPage('savednews')">Saved News</a></li><li><a href="#" onclick="loadPage('newspreferences')">News Preferences</a></li></ul></li>
        <li><a href="#" onclick="loadPage('blog')">Blog</a></li>
        <li><a href="#" onclick="loadPage('kidscorner')">Kids' Corner</a></li>
        <li><a href="#" onclick="loadPage('contact')">Contact</a></li>
        <li><a href="/customer/api/logout.php">Logout</a></li>
        <li>
            <button type="button" class="cart-nav-button" onclick="openCartDrawer()">
                Cart
                <span id="cartNavCount" class="cart-count-badge">0</span>
            </button>
        </li>
    </ul>
</nav>
