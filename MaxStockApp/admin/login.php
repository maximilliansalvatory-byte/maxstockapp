<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MaxStockApp — Admin Login</title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body class="login-page">
    <div class="login-card">
        <div class="logo">
            <h1>MaxStock<span>App</span></h1>
            <p>Merchant Dashboard</p>
        </div>

        <form id="loginForm">
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="tel" id="phone" name="phone" placeholder="0712 345 678" required autocomplete="username">
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="••••••••" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary" id="btnLogin">Sign In</button>
            <p class="error-msg" id="errorMsg" hidden></p>
        </form>

        <p class="register-link">
            New merchant? <a href="register.php">Register your business</a>
        </p>
        <p class="demo-hint">
            Demo: 255712345679 / password
        </p>
    </div>

    <script>
        const form = document.getElementById('loginForm');
        const errorMsg = document.getElementById('errorMsg');
        const btn = document.getElementById('btnLogin');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            errorMsg.hidden = true;
            btn.disabled = true;
            btn.textContent = 'Signing in...';

            const phone = document.getElementById('phone').value.trim();
            const password = document.getElementById('password').value;

            try {
                const res = await fetch('../api/auth/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ phone, password })
                });
                const data = await res.json();

                if (data.success) {
                    localStorage.setItem('maxstock_token', data.data.token);
                    localStorage.setItem('maxstock_user', JSON.stringify(data.data.user));
                    window.location.href = 'index.php';
                } else {
                    errorMsg.textContent = data.message || 'Login failed';
                    errorMsg.hidden = false;
                }
            } catch (err) {
                errorMsg.textContent = 'Network error. Please try again.';
                errorMsg.hidden = false;
            } finally {
                btn.disabled = false;
                btn.textContent = 'Sign In';
            }
        });
    </script>
</body>
</html>
