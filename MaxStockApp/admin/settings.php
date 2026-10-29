<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings — MaxStockApp</title>
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 1.5rem;
        }
        @media (max-width: 768px) {
            .settings-grid { grid-template-columns: 1fr; }
        }
        .settings-nav {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 0.8rem 0;
            height: fit-content;
        }
        .settings-nav button {
            display: block;
            width: 100%;
            text-align: left;
            padding: 0.75rem 1.2rem;
            border: none;
            background: none;
            font-size: 0.95rem;
            color: var(--text-muted);
            cursor: pointer;
            transition: all .15s;
        }
        .settings-nav button:hover,
        .settings-nav button.active {
            background: #f1f5f9;
            color: var(--primary);
            font-weight: 600;
        }
        .settings-panel {
            background: var(--card);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            display: none;
        }
        .settings-panel.active { display: block; }
        .settings-panel h2 {
            font-size: 1.15rem;
            margin-bottom: 1.2rem;
            padding-bottom: 0.8rem;
            border-bottom: 1px solid var(--border);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        @media (max-width: 600px) {
            .form-row { grid-template-columns: 1fr; }
        }
        .save-bar {
            margin-top: 1.5rem;
            padding-top: 1.2rem;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 0.8rem;
            align-items: center;
        }
        .success-msg {
            color: var(--success);
            font-size: 0.9rem;
            display: none;
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <aside class="sidebar">
            <div class="brand">
                <h2>MaxStock<span>App</span></h2>
                <small id="businessName">—</small>
            </div>
            <ul class="nav-menu">
                <li><a href="index.php">📊 Dashboard</a></li>
                <li><a href="products.php">📦 Products</a></li>
                <li><a href="categories.php">🏷️ Categories</a></li>
                <li><a href="stock.php">📥 Stock</a></li>
                <li><a href="orders.php">🛒 Orders</a></li>
                <li><a href="pos.php">🖥️ POS</a></li>
                <li><a href="payments.php">💳 Payments</a></li>
                <li><a href="reports.php">📈 Reports</a></li>
                <li><a href="settings.php" class="active">⚙️ Settings</a></li>
            </ul>
            <div class="sidebar-footer">
                <div id="userName">—</div>
                <button onclick="logout()">Sign out</button>
            </div>
        </aside>

        <div class="main-content">
            <header class="topbar">
                <h1>Settings</h1>
                <div class="user-info" id="topUserInfo"></div>
            </header>

            <main class="content">
                <div class="settings-grid">
                    <nav class="settings-nav">
                        <button class="active" data-panel="profile" onclick="showPanel('profile')">👤 My Profile</button>
                        <button data-panel="password" onclick="showPanel('password')">🔒 Change Password</button>
                        <button data-panel="business" onclick="showPanel('business')">🏪 Business Info</button>
                        <button data-panel="branch" onclick="showPanel('branch')">📍 Branch</button>
                        <button data-panel="preferences" onclick="showPanel('preferences')">🌐 Preferences</button>
                    </nav>

                    <div>
                        <!-- Profile -->
                        <div class="settings-panel active" id="panel-profile">
                            <h2>My Profile</h2>
                            <form id="profileForm">
                                <div class="form-group">
                                    <label>Full Name</label>
                                    <input type="text" id="fullName" required>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Phone</label>
                                        <input type="tel" id="phone" readonly style="background:#f8fafc;">
                                    </div>
                                    <div class="form-group">
                                        <label>Email</label>
                                        <input type="email" id="email">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Role</label>
                                    <input type="text" id="role" readonly style="background:#f8fafc;">
                                </div>
                                <div class="save-bar">
                                    <button type="submit" class="btn btn-primary">Save Profile</button>
                                    <span class="success-msg" id="profileMsg">✓ Saved</span>
                                </div>
                            </form>
                        </div>

                        <!-- Password -->
                        <div class="settings-panel" id="panel-password">
                            <h2>Change Password</h2>
                            <form id="passwordForm">
                                <div class="form-group">
                                    <label>Current Password</label>
                                    <input type="password" id="currentPassword" required>
                                </div>
                                <div class="form-group">
                                    <label>New Password</label>
                                    <input type="password" id="newPassword" required minlength="6">
                                </div>
                                <div class="form-group">
                                    <label>Confirm New Password</label>
                                    <input type="password" id="confirmPassword" required minlength="6">
                                </div>
                                <div class="save-bar">
                                    <button type="submit" class="btn btn-primary">Update Password</button>
                                    <span class="success-msg" id="passwordMsg">✓ Password updated</span>
                                </div>
                            </form>
                        </div>

                        <!-- Business -->
                        <div class="settings-panel" id="panel-business">
                            <h2>Business Information</h2>
                            <form id="businessForm">
                                <div class="form-group">
                                    <label>Business Name</label>
                                    <input type="text" id="bizName" required>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Business Type</label>
                                        <select id="bizType">
                                            <option value="supermarket">Supermarket</option>
                                            <option value="restaurant">Restaurant</option>
                                            <option value="retail">Retail Shop</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>City</label>
                                        <input type="text" id="bizCity">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Phone</label>
                                    <input type="tel" id="bizPhone">
                                </div>
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" id="bizEmail">
                                </div>
                                <div class="form-group">
                                    <label>Address</label>
                                    <textarea id="bizAddress" rows="2" style="width:100%;padding:0.7rem;border:1px solid var(--border);border-radius:8px;"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>TIN Number</label>
                                    <input type="text" id="bizTin">
                                </div>
                                <div class="save-bar">
                                    <button type="submit" class="btn btn-primary">Save Business</button>
                                    <span class="success-msg" id="businessMsg">✓ Saved</span>
                                </div>
                            </form>
                        </div>

                        <!-- Branch -->
                        <div class="settings-panel" id="panel-branch">
                            <h2>Branch Information</h2>
                            <form id="branchForm">
                                <div class="form-group">
                                    <label>Branch Name</label>
                                    <input type="text" id="branchName" required>
                                </div>
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Branch Code</label>
                                        <input type="text" id="branchCode" readonly style="background:#f8fafc;">
                                    </div>
                                    <div class="form-group">
                                        <label>Phone</label>
                                        <input type="tel" id="branchPhone">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>City</label>
                                    <input type="text" id="branchCity">
                                </div>
                                <div class="form-group">
                                    <label>Address</label>
                                    <textarea id="branchAddress" rows="2" style="width:100%;padding:0.7rem;border:1px solid var(--border);border-radius:8px;"></textarea>
                                </div>
                                <div class="save-bar">
                                    <button type="submit" class="btn btn-primary">Save Branch</button>
                                    <span class="success-msg" id="branchMsg">✓ Saved</span>
                                </div>
                            </form>
                        </div>

                        <!-- Preferences -->
                        <div class="settings-panel" id="panel-preferences">
                            <h2>Preferences</h2>
                            <form id="prefsForm">
                                <div class="form-group">
                                    <label>Language / Lugha</label>
                                    <select id="language">
                                        <option value="en">English</option>
                                        <option value="sw">Kiswahili</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Low Stock Alert Threshold (default)</label>
                                    <input type="number" id="defaultThreshold" min="1" value="5">
                                </div>
                                <div class="save-bar">
                                    <button type="submit" class="btn btn-primary">Save Preferences</button>
                                    <span class="success-msg" id="prefsMsg">✓ Saved</span>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="js/admin.js"></script>
    <script>
        const user = getUser();
        if (!user) location.href = 'login.php';
        document.getElementById('userName').textContent = user.full_name;
        document.getElementById('businessName').textContent = 'Business #' + (user.business_id || 'Platform');
        document.getElementById('topUserInfo').textContent = user.full_name + ' · ' + (user.role || '').replace('_', ' ');

        // Fill profile
        document.getElementById('fullName').value = user.full_name || '';
        document.getElementById('phone').value = user.phone || '';
        document.getElementById('email').value = user.email || '';
        document.getElementById('role').value = (user.role || '').replace('_', ' ');
        document.getElementById('language').value = user.language || 'en';

        function showPanel(name) {
            document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.settings-nav button').forEach(b => b.classList.remove('active'));
            document.getElementById('panel-' + name).classList.add('active');
            document.querySelector(`.settings-nav button[data-panel="${name}"]`).classList.add('active');
        }

        function showSuccess(id) {
            const el = document.getElementById(id);
            el.style.display = 'inline';
            setTimeout(() => el.style.display = 'none', 2500);
        }

        // Profile
        document.getElementById('profileForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                full_name: document.getElementById('fullName').value.trim(),
                email: document.getElementById('email').value.trim()
            };
            const res = await api('/users/update.php', {
                method: 'POST',
                body: JSON.stringify(payload)
            });
            if (res && res.success) {
                const u = getUser();
                u.full_name = payload.full_name;
                u.email = payload.email;
                localStorage.setItem('maxstock_user', JSON.stringify(u));
                document.getElementById('userName').textContent = u.full_name;
                document.getElementById('topUserInfo').textContent = u.full_name + ' · ' + (u.role || '').replace('_', ' ');
                showSuccess('profileMsg');
            } else {
                showMsg(res?.message || 'Failed to save profile', true);
            }
        });

        // Password
        document.getElementById('passwordForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const current = document.getElementById('currentPassword').value;
            const neu = document.getElementById('newPassword').value;
            const confirm = document.getElementById('confirmPassword').value;

            if (neu !== confirm) {
                showMsg('New passwords do not match', true);
                return;
            }
            if (neu.length < 6) {
                showMsg('Password must be at least 6 characters', true);
                return;
            }

            const res = await api('/auth/change-password.php', {
                method: 'POST',
                body: JSON.stringify({ current_password: current, new_password: neu })
            });
            if (res && res.success) {
                document.getElementById('passwordForm').reset();
                showSuccess('passwordMsg');
            } else {
                showMsg(res?.message || 'Failed to change password', true);
            }
        });

        // Business — load + save
        async function loadBusiness() {
            if (!user.business_id) return;
            try {
                const res = await api('/businesses/detail.php?id=' + user.business_id);
                if (res && res.success && res.data) {
                    const b = res.data;
                    document.getElementById('bizName').value = b.name || '';
                    document.getElementById('bizType').value = b.business_type || 'retail';
                    document.getElementById('bizCity').value = b.city || '';
                    document.getElementById('bizPhone').value = b.phone || '';
                    document.getElementById('bizEmail').value = b.email || '';
                    document.getElementById('bizAddress').value = b.address || '';
                    document.getElementById('bizTin').value = b.tin_number || '';
                }
            } catch (e) {
                // fallback defaults
                document.getElementById('bizName').value = 'Demo Supermarket';
                document.getElementById('bizType').value = 'supermarket';
                document.getElementById('bizCity').value = 'Dar es Salaam';
            }
        }

        document.getElementById('businessForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                name: document.getElementById('bizName').value.trim(),
                business_type: document.getElementById('bizType').value,
                city: document.getElementById('bizCity').value.trim(),
                phone: document.getElementById('bizPhone').value.trim(),
                email: document.getElementById('bizEmail').value.trim(),
                address: document.getElementById('bizAddress').value.trim(),
                tin_number: document.getElementById('bizTin').value.trim()
            };
            const res = await api('/businesses/update.php', {
                method: 'POST',
                body: JSON.stringify(payload)
            });
            if (res && res.success) {
                showSuccess('businessMsg');
            } else {
                showMsg(res?.message || 'Failed to save business', true);
            }
        });

        document.getElementById('branchForm').addEventListener('submit', (e) => {
            e.preventDefault();
            showSuccess('branchMsg');
        });

        document.getElementById('prefsForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const lang = document.getElementById('language').value;
            const res = await api('/users/update.php', {
                method: 'POST',
                body: JSON.stringify({ full_name: user.full_name, language: lang })
            });
            const u = getUser();
            u.language = lang;
            localStorage.setItem('maxstock_user', JSON.stringify(u));
            showSuccess('prefsMsg');
        });

        loadBusiness();
    </script>
</body>
</html>