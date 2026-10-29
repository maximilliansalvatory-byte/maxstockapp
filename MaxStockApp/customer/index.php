<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0d9488">
    <meta name="description" content="MaxStockApp — Browse and order from local shops">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="assets/icon-192.png">
    <title>MaxStockApp</title>
    <link rel="stylesheet" href="css/customer.css">
</head>
<body>
    <header class="app-header">
        <div class="logo">MaxStock<span>App</span></div>
        <button class="cart-btn" id="cartBtn" onclick="toggleCart()">
            🛒 <span id="cartCount" class="badge">0</span>
        </button>
    </header>

    <main class="app-main">
        <!-- Store / Product list -->
        <section id="browseView">
            <div class="search-bar">
                <input type="search" id="searchInput" placeholder="Search products...">
            </div>
            <div class="product-grid" id="productGrid">
                <p class="loading">Loading products...</p>
            </div>
        </section>

        <!-- Cart drawer -->
        <aside class="cart-drawer" id="cartDrawer">
            <div class="cart-header">
                <h2>Your Cart</h2>
                <button onclick="toggleCart()">✕</button>
            </div>
            <div class="cart-items" id="cartItems"></div>
            <div class="cart-footer">
                <div class="cart-total">
                    Total: <strong id="cartTotal">TZS 0</strong>
                </div>
                <button class="btn btn-primary" id="checkoutBtn" onclick="startCheckout()" disabled>
                    Proceed to Checkout
                </button>
            </div>
        </aside>
        <div class="overlay" id="overlay" onclick="toggleCart()"></div>
    </main>

    <nav class="bottom-nav">
    <a href="index.php" class="active">🏠 Home</a>
    <a href="orders.php">📦 Orders</a>
    <a href="account.php">👤 Account</a>
</nav>

    <script src="js/customer.js"></script>
</body>
</html>
