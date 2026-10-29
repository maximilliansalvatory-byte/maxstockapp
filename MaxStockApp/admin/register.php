<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Business — MaxStockApp</title>
    <link rel="stylesheet" href="css/admin.css">
</head>
<body class="login-page">
    <div class="login-card" style="max-width:440px;">
        <div class="logo">
            <h1>MaxStock<span>App</span></h1>
            <p>Register your business</p>
        </div>

        <form id="registerForm">
            <div class="form-group">
                <label>Your Full Name *</label>
                <input type="text" id="fullName" required placeholder="John Doe">
            </div>
            <div class="form-group">
                <label>Phone Number *</label>
                <input type="tel" id="phone" required placeholder="0712 345 678">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" id="email" placeholder="you@example.com">
            </div>
            <div class="form-group">
                <label>Password *</label>
                <input type="password" id="password" required minlength="6" placeholder="Min 6 characters">
            </div>
            <hr style="border:none;border-top:1px solid var(--border);margin:1.2rem 0;">
            <div class="form-group">
                <label>Business Name *</label>
                <input type="text" id="businessName" required placeholder="My Supermarket">
            </div>
            <div class="form-group">
                <label>Business Type</label>
                <select id="businessType">
                    <option value="supermarket">Supermarket</option>
                    <option value="restaurant">Restaurant</option>
                    <option value="retail">Retail Shop</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-group">
                <label>City</label>
                <input type="text" id="city" value="Dar es Salaam">
            </div>

            <button type="submit" class="btn btn-primary" id="btnRegister">Create Account</button>
            <p class="error-msg" id="errorMsg" hidden></p>
        </form>

        <p class="register-link">
            Already have an account? <a href="login.php">Sign in</a>
        </p>
    </div>

    <script>
        const form = document.getElementById('registerForm');
        const errorMsg = document.getElementById('errorMsg');
        const btn = document.getElementById('btnRegister');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            errorMsg.hidden = true;
            btn.disabled = true;
            btn.textContent = 'Creating...';

            const payload = {
                full_name: document.getElementById('fullName').value.trim(),
                phone: document.getElementById('phone').value.trim(),
                email: document.getElementById('email').value.trim(),
                password: document.getElementById('password').value,
                role: 'business_admin',
                business_name: document.getElementById('businessName').value.trim(),
                business_type: document.getElementById('businessType').value,
                city: document.getElementById('city').value.trim() || 'Dar es Salaam'
            };

            try {
                const res = await fetch('../api/auth/register.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.success) {
                    localStorage.setItem('maxstock_token', data.data.token);
                    localStorage.setItem('maxstock_user', JSON.stringify(data.data.user));
                    window.location.href = 'index.php';
                } else {
                    errorMsg.textContent = data.message || 'Registration failed';
                    errorMsg.hidden = false;
                }
            } catch (err) {
                errorMsg.textContent = 'Network error. Please try again.';
                errorMsg.hidden = false;
            } finally {
                btn.disabled = false;
                btn.textContent = 'Create Account';
            }
        });
    </script>
</body>
</html>