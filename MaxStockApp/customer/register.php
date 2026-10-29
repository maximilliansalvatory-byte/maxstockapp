<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0d9488">
    <title>Register — MaxStockApp</title>
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
        .auth-card h1 { font-size: 1.5rem; margin-bottom: 0.3rem; }
        .auth-card h1 span { color: #0d9488; }
        .auth-card .sub {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }
        .auth-card .form-group { margin-bottom: 1rem; }
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
    </style>
</head>
<body>
    <div class="auth-page">
        <div class="auth-card">
            <h1>MaxStock<span>App</span></h1>
            <p class="sub">Create your customer account</p>

            <form id="registerForm">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" id="fullName" required placeholder="Your name" autocomplete="name">
                </div>
                <div class="form-group">
                    <label>Phone Number *</label>
                    <input type="tel" id="phone" required placeholder="0712 345 678" autocomplete="tel">
                </div>
                <div class="form-group">
                    <label>Email (optional)</label>
                    <input type="email" id="email" placeholder="you@example.com" autocomplete="email">
                </div>
                <div class="form-group">
                    <label>Password *</label>
                    <input type="password" id="password" required minlength="6" placeholder="Min 6 characters" autocomplete="new-password">
                </div>
                <button type="submit" class="btn" id="btnRegister">Create Account</button>
                <p class="error" id="errorMsg"></p>
            </form>

            <p class="links">
                Already have an account? <a href="login.php">Sign in</a>
            </p>
        </div>
    </div>

    <script>
        const form = document.getElementById('registerForm');
        const errorMsg = document.getElementById('errorMsg');
        const btn = document.getElementById('btnRegister');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            errorMsg.style.display = 'none';
            btn.disabled = true;
            btn.textContent = 'Creating...';

            const payload = {
                full_name: document.getElementById('fullName').value.trim(),
                phone: document.getElementById('phone').value.trim(),
                email: document.getElementById('email').value.trim(),
                password: document.getElementById('password').value,
                role: 'customer'
            };

            try {
                const res = await fetch('../api/auth/register.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.success) {
                    localStorage.setItem('maxstock_customer', JSON.stringify({
                        token: data.data.token,
                        user: data.data.user
                    }));
                    localStorage.setItem('maxstock_token', data.data.token);
                    window.location.href = 'account.php';
                } else {
                    errorMsg.textContent = data.message || 'Registration failed';
                    errorMsg.style.display = 'block';
                }
            } catch (err) {
                errorMsg.textContent = 'Network error. Please try again.';
                errorMsg.style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Create Account';
            }
        });
    </script>
</body>
</html>