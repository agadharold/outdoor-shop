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
- Change shop settings (shop name, shipping fee, free shipping minimum, announcement)

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
5. The page will show your **admin username and a randomly generated password. It is shown only once, so save it.**
6. Open the shop: `http://localhost/outdoor-shop/`

If the page looks outdated, refresh with **Ctrl + F5**.

### Choose your own admin password (optional)

You can set it yourself with environment variables before running the seed script (for example from the command line):

| Variable | Meaning | Default |
|----------|---------|---------|
| `ADMIN_USER` | Admin username | `admin` |
| `ADMIN_PASS` | Admin password | random, shown once |
| `ADMIN_EMAIL` | Admin email | `admin@example.com` |
| `SEED_DEMO_USER` | Set to `1` to also create a demo customer | `0` (off) |

You can also double-click `setup.bat` to run the setup from the command line.

## Database Settings

The default XAMPP settings are used (host `localhost`, user `root`, no password, database `outdoor_shop`). They can be changed with the `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS` environment variables or in `config.php`.

## Project Structure

```
outdoor-shop/
├── admin/          Admin pages (products, categories, orders, users, settings)
├── assets/         CSS, JavaScript, and product images
├── includes/       Shared header, footer, and admin navigation
├── scripts/        seed.php (creates database and sample data)
├── config.php      Database connection and helper functions
├── index.php       Home page
└── ...             Customer pages (cart, checkout, login, register, orders)
```

## Author

Chikeneyney
