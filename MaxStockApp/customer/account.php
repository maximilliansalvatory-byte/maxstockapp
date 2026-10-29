<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0d9488">
    <title>My Account — MaxStockApp</title>
    <link rel="stylesheet" href="css/customer.css">
    <style>
        .account-header {
            background: linear-gradient(135deg, #0d9488, #0f766e);
            color: #fff;
            padding: 1.5rem 1rem 2rem;
        }
        .account-header h1 {
            font-size: 1.25rem;
            font-weight: 700;
        }
        .account-header p {
            opacity: 0.9;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }
        .account-body {
            padding: 1rem;
            margin-top: -0.5rem;
        }
        .account-card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
            margin-bottom: 1rem;
            overflow: hidden;
        }
        .account-card a, .account-card button.row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 1rem 1.1rem;
            border: none;
            background: none;
            text-decoration: none;
            color: #0f172a;
            font-size: 0.95rem;
            border-bottom: 1px solid #f1f5f9;
            cursor: pointer;
            text-align: left;
        }
        .account-card a:last-child,
        .account-card button.row:last-child {
            border-bottom: none;
        }
        .account-card .icon { margin-right: 0.75rem; }
        .account-card .label { flex: 1; }
        .account-card .arrow { color: #94a3b8; }
        .logout-btn {
            width: 100%;
            padding: 0.9rem;
            background: #fee2e2;
            color: #dc2626;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            margin-top: 0.5rem;
        }
        .guest-box {
            text-align: center;
            padding: 2rem 1rem;
        }
        .guest-box a {
            display: inline-block;
            margin: 0.4rem;
            padding: 0.7rem 1.4rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
        }
        .guest-box .primary {
            background: #0d9488;
            color: #fff;
        }
        .guest-box .outline {
            border: 1px solid #e2e8f0;
            color: #0f172a;
        }
    </style>
</head>
<body>
    <div class="account-header">
        <h1 id="greetName">My Account</h1>
        <p id="greetPhone"></p>
    </div>

    <div class="account-body" id="accountBody">
        <!-- filled by JS -->
    </div>

    <nav class="bottom-nav">
    <a href="index.php">🏠 Home</a>
    <a href="orders.php">📦 Orders</a>
    <a href="account.php">👤 Account</a>
</nav>

    <script>
        function getCustomer() {
            try {
                return JSON.parse(localStorage.getItem('maxstock_customer') || 'null');
            } catch {
                return null;
            }
        }

        function logout() {
            localStorage.removeItem('maxstock_customer');
            localStorage.removeItem('maxstock_token');
            window.location.href = 'login.php';
        }

        const session = getCustomer();
        const body = document.getElementById('accountBody');

        if (!session || !session.user) {
            document.getElementById('greetName').textContent = 'Welcome';
            document.getElementById('greetPhone').textContent = 'Sign in to manage your account';
            body.innerHTML = `
                <div class="account-card guest-box">
                    <p style="color:#64748b;margin-bottom:1rem;">You are browsing as a guest</p>
                    <a href="login.php" class="primary">Sign In</a>
                    <a href="register.php" class="outline">Create Account</a>
                </div>
            `;
        } else {
            const u = session.user;
            document.getElementById('greetName').textContent = u.full_name || 'My Account';
            document.getElementById('greetPhone').textContent = u.phone || '';

            body.innerHTML = `
                <div class="account-card">
                    <a href="orders.php">
                        <span class="icon">📦</span>
                        <span class="label">My Orders</span>
                        <span class="arrow">›</span>
                    </a>
                    <a href="index.php">
                        <span class="icon">🛍️</span>
                        <span class="label">Continue Shopping</span>
                        <span class="arrow">›</span>
                    </a>
                </div>
                <div class="account-card">
                    <div style="padding:1rem 1.1rem;border-bottom:1px solid #f1f5f9;">
                        <div style="font-size:0.8rem;color:#64748b;">Full name</div>
                        <div style="font-weight:600;">${escapeHtml(u.full_name || '—')}</div>
                    </div>
                    <div style="padding:1rem 1.1rem;border-bottom:1px solid #f1f5f9;">
                        <div style="font-size:0.8rem;color:#64748b;">Phone</div>
                        <div style="font-weight:600;">${escapeHtml(u.phone || '—')}</div>
                    </div>
                    <div style="padding:1rem 1.1rem;">
                        <div style="font-size:0.8rem;color:#64748b;">Email</div>
                        <div style="font-weight:600;">${escapeHtml(u.email || '—')}</div>
                    </div>
                </div>
                <button class="logout-btn" onclick="logout()">Sign Out</button>
            `;
        }

        function escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }
    </script>
</body>
</html>