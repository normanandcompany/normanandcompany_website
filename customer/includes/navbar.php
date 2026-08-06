<nav>
    <ul class="main-nav">
        <li><a href="/customer/" onclick="return navigateSitePage(event, 'home')">Profile</a></li>

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
                <li><a href="/customer/?page=cruiselines" onclick="return navigateSitePage(event, 'cruiselines')">Cruiselines</a></li>
                <li><a href="/customer/?page=portinfo" onclick="return navigateSitePage(event, 'portinfo')">Port Info</a></li>
                <li><a href="/customer/?page=cruisedestinations" onclick="return navigateSitePage(event, 'cruisedestinations')">Cruise Destinations</a></li>
                <li><a href="/customer/?page=shoreexcursions" onclick="return navigateSitePage(event, 'shoreexcursions')">Shore Excursions</a></li>
                <li><a href="/customer/?page=cruisenews" onclick="return navigateSitePage(event, 'cruisenews')">Cruiseline News</a></li>
                <li><a href="/customer/?page=loyaltyprogs" onclick="return navigateSitePage(event, 'loyaltyprogs')">Loyalty Programs</a></li>
            </ul>
        </li>

        <li class="nav-dropdown">
            <span>Resort Travel</span>
            <ul class="submenu">
                <li><a href="/customer/?page=resorts" onclick="return navigateSitePage(event, 'resorts')">Resorts</a></li>
                <li><a href="/customer/?page=resortdestinations" onclick="return navigateSitePage(event, 'resortdestinations')">Resort Destinations</a></li>
                <li><a href="/customer/?page=resortnews" onclick="return navigateSitePage(event, 'resortnews')">Resort News</a></li>
            </ul>
        </li>

        <li class="nav-dropdown">
            <span>Destination Info</span>
            <ul class="submenu">
                <li><a href="/customer/?page=destinations" onclick="return navigateSitePage(event, 'destinations')">Destinations</a></li>
                <li><a href="/customer/?page=travelreqs" onclick="return navigateSitePage(event, 'travelreqs')">Travel Requirements</a></li>
                <li><a href="/customer/?page=besttimes" onclick="return navigateSitePage(event, 'besttimes')">Best Times</a></li>
                <li><a href="/customer/?page=destinationguides" onclick="return navigateSitePage(event, 'destinationguides')">Destination Guides</a></li>
            </ul>
        </li>

        <li class="nav-dropdown">
            <span>Travel Tips</span>
            <ul class="submenu">
                <li><a href="/customer/?page=traveladvice" onclick="return navigateSitePage(event, 'traveladvice')">Advice</a></li>
                <li><a href="/customer/?page=travelessentials" onclick="return navigateSitePage(event, 'travelessentials')">Essentials</a></li>
                <li><a href="/customer/?page=travelplanning" onclick="return navigateSitePage(event, 'travelplanning')">Planning</a></li>
                <li><a href="/customer/?page=travelchecklists" onclick="return navigateSitePage(event, 'travelchecklists')">Travel Checklists</a></li>
            </ul>
        </li>

        <li class="nav-dropdown"><span>My News</span><ul class="submenu"><li><a href="/customer/?page=mynews" onclick="return navigateSitePage(event, 'mynews')">Personalized Feed</a></li><li><a href="/customer/?page=savednews" onclick="return navigateSitePage(event, 'savednews')">Saved News</a></li><li><a href="/customer/?page=newspreferences" onclick="return navigateSitePage(event, 'newspreferences')">News Preferences</a></li></ul></li>
        <li><a href="/customer/?page=blog" onclick="return navigateSitePage(event, 'blog')">Blog</a></li>
        <li><a href="/customer/?page=downloads" onclick="return navigateSitePage(event, 'downloads')">Downloads</a></li>
        <li><a href="/customer/?page=kidscorner" onclick="return navigateSitePage(event, 'kidscorner')">Kids' Corner</a></li>
        <li><a href="/customer/?page=contact" onclick="return navigateSitePage(event, 'contact')">Contact</a></li>
        <li><a href="/customer/api/logout.php">Logout</a></li>
        <li>
            <button type="button" class="cart-nav-button" onclick="openCartDrawer()">
                Cart
                <span id="cartNavCount" class="cart-count-badge">0</span>
            </button>
        </li>
    </ul>
</nav>
