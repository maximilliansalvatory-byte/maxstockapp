# MaxStockApp

Unified stock & commerce management platform for Tanzanian merchants (supermarkets, restaurants, retail shops).

**Stack:** PHP + MySQL + HTML/CSS/JS (XAMPP / LAMP)  
**Customer App:** Progressive Web App (PWA)

## Quick Start (Local XAMPP)

1. Copy the `maxstockapp` folder into your XAMPP `htdocs/` directory.
2. Start Apache + MySQL in XAMPP Control Panel.
3. Open phpMyAdmin → Import `sql/schema.sql` (creates database + seed data).
4. Visit:
   - Admin Dashboard: `http://localhost/maxstockapp/admin/login.php`
   - Customer App: `http://localhost/maxstockapp/customer/`

### Demo Credentials
| Role            | Phone         | Password  |
|-----------------|---------------|-----------|
| Business Admin  | 255712345679  | password  |
| Super Admin     | 255700000000  | password  |

> Change these passwords immediately after first login.

## Project Structure

```
maxstockapp/
├── api/                  # REST-style JSON API
│   ├── auth/             # login, register
│   ├── products/         # CRUD
│   ├── stock/            # stock adjustments
│   ├── orders/
│   ├── payments/         # M-Pesa, Tigo, Airtel, NMB, CRDB
│   └── reports/
├── admin/                # Merchant dashboard (HTML/CSS/JS)
├── customer/             # Customer PWA
├── config/               # database.php etc.
├── includes/             # Database, Auth, Response helpers
├── sql/                  # schema.sql
└── uploads/
```

## Phase 1 MVP Status

- [x] Project scaffolding & directory structure
- [x] Full MySQL schema (businesses, branches, users, products, stock, orders, payments, audit)
- [x] PDO database layer
- [x] Auth (register / login + JWT-style tokens)
- [x] Products CRUD API + Admin UI
- [x] Stock adjustment API
- [ ] Customer browse / cart / checkout (in progress)
- [ ] M-Pesa sandbox integration
- [ ] Basic sales reporting

## Configuration

Edit `config/database.php` if your MySQL credentials differ from default XAMPP (`root` / empty password).

For production:
- Move secrets to environment variables
- Enable HTTPS
- Restrict CORS
- Change the JWT secret in `includes/Auth.php`

## Payment Integrations (Phase 1 target)

Primary channel for MVP: **Vodacom M-Pesa** (Open API)  
Later: Tigo/Yas, Airtel Money, NMB, CRDB  
Recommended: use a licensed aggregator (Selcom, DPO, Flutterwave) to simplify compliance.

## License

Proprietary — All rights reserved.
