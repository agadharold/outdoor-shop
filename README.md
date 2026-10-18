# Outdoor Boys - Outdoor Shop

A simple online shop for camping and outdoor gear, built with **PHP** and **MySQL**. It runs locally using **XAMPP**.

> **Learning project.** Do not deploy this to a real server without a security review. Always use your own strong passwords.

## Features

**Customers**
- Browse products and view product details
- Register, log in, and manage an account
- Add items to a cart and check out
- View past orders

**Admin panel**
- Add, edit, and manage products
- Manage categories
- View and manage orders
- Manage users
- Change shop settings (shop name, shipping fee, free shipping minimum, announcement, maintenance mode)
- Owner only: sales reports and CSV downloads, activity log, sale prices, refunds

## User Roles

There are four roles. The **Owner** is the top role, and there is only one owner at a time.

| What they can do | Customer | Staff | Admin | Owner |
|------------------|:--------:|:-----:|:-----:|:-----:|
| Browse, order, view own orders | ✅ | ✅ | ✅ | ✅ |
| View orders, move them forward (placed → confirmed → shipped → delivered) | | ✅ | ✅ | ✅ |
| Cancel an order that is still pending | | | ✅ | ✅ |
| Add and edit products, stock, categories | | | ✅ | ✅ |
| Set the price of a **new** product | | | ✅ | ✅ |
| Edit customer and staff accounts (name, email, password reset) | | | ✅ | ✅ |
| Change prices, set sale prices | | | | ✅ |
| Refund or void paid orders, move orders backward | | | | ✅ |
| Sales reports and CSV downloads | | | | ✅ |
| Activity log (who changed what) | | | | ✅ |
| Shop settings and maintenance mode | | | | ✅ |
| Delete products, categories, orders and users | | | | ✅ |
| Change roles, manage other admins, unlock accounts | | | | ✅ |
| Transfer ownership to another admin | | | | ✅ |

The owner account cannot be edited or deleted by any admin. The first account created by `seed.php` is the owner. Every rule is checked on the server, not only hidden in the page.

### Owner safety features

- **Activity log** (Admin > Activity log): records log-ins, order changes, refunds, price changes, product/category/user changes, role changes, settings, maintenance mode and CSV downloads, with who did it, when and from which computer. It cannot be edited or cleared from the website.
- **Confirm your password**: before the owner deletes something, changes a role, resets another admin's password or refunds an order, the site asks for the owner's password once. It then stays unlocked for 5 minutes.
- **Login protection**: 5 wrong passwords for an account locks it for 15 minutes (trying the email instead of the username does not help). The owner sees a *Locked* label on the Users page and can press *Unlock*. A password reset by the owner also unlocks the account. If the owner is ever locked out, run `scripts/unlock.php` from the command line (see below).
- **Maintenance mode** (Admin > Settings): shows customers a "We'll be right back" page. Staff, admins and the owner can still use the shop, and an orange bar reminds them it is on.
- **Sale prices**: the owner can set a sale price on a product; customers see it with the normal price crossed out, and orders keep the price that was paid. Admins cannot change prices.
- **Refund / void**: on the Orders page, the owner can refund or void a paid order with a reason (kept on the order and in the log) and choose whether the items go back in stock.
- **Reports**: sales, order count, average order, top products, orders by status, for any date range. CSV downloads (orders, order items, products) open in Excel.

## Tech Stack

- PHP
- MySQL
- HTML, CSS, JavaScript
- XAMPP (Apache + MySQL)

## How to Install (XAMPP)

1. Download this project (green **Code** button, then **Download ZIP**) and unzip it.
2. Copy the `outdoor-shop` folder into `C:\xampp\htdocs\`.
3. Open the XAMPP Control Panel and start **Apache** and **MySQL**.
4. **First install only:** open this in your browser to create the database and sample products:
   `http://localhost/outdoor-shop/scripts/seed.php`
5. The page will show your **owner username and a randomly generated password. It is shown only once, so save it.**
6. Open the shop: `http://localhost/outdoor-shop/`

If the page looks outdated, refresh with **Ctrl + F5**.

### Choose your own admin password (optional)

You can set it yourself with environment variables before running the seed script (for example from the command line):

| Variable | Meaning | Default |
|----------|---------|---------|
| `ADMIN_USER` | Owner username | `admin` |
| `ADMIN_PASS` | Owner password | random, shown once |
| `ADMIN_EMAIL` | Owner email | `admin@example.com` |
| `SEED_DEMO_USER` | Set to `1` to also create a demo customer | `0` (off) |

You can also double-click `setup.bat` to run the setup from the command line.

### Updating an older install

Copy the new files over the old ones, then open `http://localhost/outdoor-shop/scripts/seed.php` once more. Until you do, the shop shows a "One-time update needed" message. It is safe to run again: it adds the owner role, staff role, sale prices, refunds, activity log and login protection to an existing database, and makes the first admin the Owner. No data is deleted.

### If the owner is locked out

Open a Command Prompt in the project folder and run `C:\xampp\php\php.exe scripts\unlock.php`. It clears every login lock.

## Database Settings

The default XAMPP settings are used (host `localhost`, user `root`, no password, database `outdoor_shop`). They can be changed with the `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` environment variables or in `config.php`.

## Project Structure

```
outdoor-shop/
├── admin/          Admin pages (orders, refunds, products, categories, users, settings, reports, activity log)
├── assets/         CSS, JavaScript, and product images
├── includes/       Shared header, footer, admin navigation, security helpers (log, lockout)
├── scripts/        seed.php (creates/upgrades the database), unlock.php (clears login locks)
├── config.php      Database connection and helper functions
├── index.php       Home page
└── ...             Customer pages (cart, checkout, login, register, orders)
```

## Author

Your Name
