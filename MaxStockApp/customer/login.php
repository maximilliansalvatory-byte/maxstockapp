<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0d9488">
    <title>Login — MaxStockApp</title>
    <link rel="stylesheet" href="css/customer.css">
    <style>
        .auth-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: linear-gradient(160deg, #0f766e 0%, #134e4a 100%);
        }
        .auth-card {
            background: #fff;
            width: 100%;
            max-width: 400px;
            border-radius: 16px;
            padding: 2rem 1.5rem;
            box-shadow: 0 20px 40px rgba(0,0,0,.2);
        }
        .auth-card h1 {
            font-size: 1.5rem;
            margin-bottom: 0.3rem;
        }
        .auth-card h1 span { color: #0d9488; }
        .auth-card .sub {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }
        .auth-card .form-group {
            margin-bottom: 1rem;
        }
        .auth-card label {
            display: block;
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 0.35rem;
        }
        .auth-card input {
            width: 100%;
            padding: 0.75rem 0.9rem;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
        }
        .auth-card input:focus {
            outline: none;
            border-color: #0d9488;
            box-shadow: 0 0 0 3px #ccfbf1;
        }
        .auth-card .btn {
            width: 100%;
            margin-top: 0.5rem;
            padding: 0.85rem;
            background: #0d9488;
            color: #fff;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }
        .auth-card .btn:disabled { opacity: 0.6; }
        .auth-card .error {
            color: #dc2626;
            font-size: 0.9rem;
            text-align: center;
            margin-top: 0.8rem;
            display: none;
        }
        .auth-card .links {
            text-align: center;
            margin-top: 1.2rem;
            font-size: 0.9rem;
            color: #64748b;
        }
        .auth-card .links a {
            color: #0d9488;
            text-decoration: none;
            font-weight: 600;
        }
        .guest-link {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: #94a3b8;
            font-size: 0.85rem;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="auth-page">
        <div class="auth-card">
            <h1>MaxStock<span>App</span></h1>
            <p class="sub">Sign in to track orders & save your details</p>

            <form id="loginForm">
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" id="phone" placeholder="0712 345 678" required autocomplete="tel">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" id="password" placeholder="••••••••" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn" id="btnLogin">Sign In</button>
                <p class="error" id="errorMsg"></p>
            </form>

            <p class="links">
                New customer? <a href="register.html">Create account</a>
            </p>
            <a href="index.html" class="guest-link">Continue as guest →</a>
        </div>
    </div>

    <script>
        const form = document.getElementById('loginForm');
        const errorMsg = document.getElementById('errorMsg');
        const btn = document.getElementById('btnLogin');

        // Already logged in?
        try {
            const u = JSON.parse(localStorage.getItem('maxstock_customer') || 'null');
            if (u && u.token) {
                window.location.href = 'account.php';
            }
        } catch (e) {}

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            errorMsg.style.display = 'none';
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
                    // Store as customer session
                    localStorage.setItem('maxstock_customer', JSON.stringify({
                        token: data.data.token,
                        user: data.data.user
                    }));
                    // Also keep generic token for API calls
                    localStorage.setItem('maxstock_token', data.data.token);
                    window.location.href = 'account.php';
                } else {
                    errorMsg.textContent = data.message || 'Login failed';
                    errorMsg.style.display = 'block';
                }
            } catch (err) {
                errorMsg.textContent = 'Network error. Please try again.';
                errorMsg.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Sign In';
            }
        });
    </script>
</body>
</html>