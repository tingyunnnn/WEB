# TideBites

**Catch a Wave of Flavor!**

TideBites is an ocean-themed campus dining website for students, featuring comforting meals and refreshing desserts. The customer site reads its menu from a MySQL database and submits real orders, and an admin Control Deck manages food items and shows incoming orders.

## Features

### Customer site (`index.html`)

- Menu loaded from the database (`api/foods/list.php`); only active items are shown, ordered by display order.
- ALL / MEALS / DESSERTS filters and up to six dishes per page.
- Dish details, quantity selection, subtotals, and enlarged photos.
- A browser-local cart with quantity controls, removal, customer name, phone, notes, and campus pickup location.
- Order review, then **Place Order** submits to `api/orders/place.php`. The server re-checks prices, recalculates totals, saves the order, and returns an order number shown on the completion screen.
- Expandable FAQs, responsive navigation, and mobile menu.
- Sea-life animations and silent click bubbles (no opening screen, background music, or sound effects).

### Admin Control Deck (`admin/`)

- Sign-in with session, CSRF protection, and login throttling.
- Food Management: list, search, filter by category and status, add, edit (including image upload), activate / deactivate, and delete.
- Orders: searchable, paginated order list and order detail pages (view only).

## Requirements

- PHP 8.4 or newer with `pdo_mysql`, `fileinfo`, `mbstring`, and `session` enabled.
- MySQL 8.4 LTS or newer (not MariaDB: the schema uses MySQL 8 `utf8mb4_0900_*` collations).
- Verified on PHP 8.4.28 + MySQL 8.4.9, and on PHP 8.6.0 + MySQL 26.10.0 (Windows; all admin integration checks in `tests/run-admin-tests.py` pass).
- Internet access for external stylesheets and fonts.

Opening `index.html` directly from the file system (`file://`) does **not** work: the menu and ordering need the PHP API.

## Setup (Windows)

Run these from the project root in PowerShell. Passwords are typed into hidden prompts; never put them in command arguments or chat.

1. **Create the database, app account, and local config** (first time only):

   ```powershell
   powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\database\manage.ps1 -Action Install
   ```

   You will be asked for your existing MySQL root password and a new app password (16+ bytes). The connection settings are written **outside** the project, to `../tidebites-local/database.php` by default. Set `TIDEBITES_CONFIG` to use a different external path.

2. **Check the connection** (should report `PASS` and `food_count`):

   ```powershell
   powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\database\manage.ps1 -Action Check
   ```

3. **Create an admin account** for the Control Deck (username 3–100 characters of `A–Z a–z 0–9 . _ -`, password 12–72 bytes):

   ```powershell
   powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\database\manage.ps1 -Action CreateAdmin
   ```

4. **Existing databases only — add new columns** (for example `orders.pickup_time`). Installs made before this column existed must run this once; it asks for the MySQL root password and is safe to repeat:

   ```powershell
   powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\database\manage.ps1 -Action Migrate
   ```

See `docs/database-setup.md` for details, troubleshooting, and macOS / Linux notes.

## Run

Double-click `start.bat`, or run:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\start.ps1
```

It starts the PHP built-in server on `127.0.0.1:8000` with `router.php` and prints:

- Website: `http://127.0.0.1:8000/index.html`
- Admin: `http://127.0.0.1:8000/admin/login.php`

Use `-Port <number>` for another port, or `-Php '<full path to php.exe>'` if PHP is not on PATH. Press Ctrl+C to stop.

Always start the server with `router.php`; it blocks internal folders (`config/`, `database/`, `docs/`, `tests/`, `admin/includes/`). The PHP built-in server is for local development only. `.htaccess` provides equivalent rules for Apache but has not been tested on a real deployment.

## Structure

- `index.html`: customer page content, dialogs, and resource links.
- `css/style.css`: layout, responsive rules, and animations.
- `js/script.js`: menu loading, filters, pagination, dialogs, cart, student discount, order submission, FAQ, and sea-life effects.
- `api/foods/list.php`: public menu API (GET, JSON).
- `api/orders/place.php`: public order API (POST, JSON).
- `api/orders/options.php`: pickup locations and bookable pickup times for the cart (GET, JSON).
- `config/order-options.php`: shared pickup locations, delivery hours, pickup-time rule, and phone rule used by both order APIs.
- `admin/`: Control Deck pages, shared includes, and admin CSS / JS.
- `config/database.php`: shared PDO connection; reads the external local config.
- `database/`: SQL schema and seed (`tidebites.sql`), install / check / admin / test tools.
- `uploads/foods/`: images uploaded from the admin (ignored by Git).
- `assets/`: food photos, decorative images, and icons.
- `docs/`: database contract, setup, admin system, and test records.
- `router.php`, `start.bat`, `start.ps1`: local development server.

## Ordering rules

- Prices are whole NT dollars. The 10% Student Discount is optional: the customer ticks "I'll show my student ID at pickup" in the cart. Unticked orders pay the full price. When it is ticked, the cart, order summary, and confirmation remind the customer to bring the orderer's student ID, and the admin order detail tells staff to check it at pickup.
- Subtotal is the sum of unit price × quantity. With the student discount, the discount is 10% of the subtotal rounded to the nearest NT dollar (halves round up); without it, the discount is 0. Total is subtotal minus discount. The API takes `student_discount: true` and returns `student_discount` in its response. No new database column is used: an order has the student discount when `discount_amount > 0`.
- The cart shows these amounts as a preview; the server recalculates them from current database prices. If a price changed since the page loaded, the order is rejected and the page reloads with the new prices. Inactive or deleted items cannot be ordered.
- Customer name, phone, and a pickup location are required; notes are optional (up to 500 characters). Phone numbers need 8–15 digits; spaces, hyphens, parentheses, and a leading `+` are allowed. Pickup locations (Campus Main Gate, Student Center, Library Entrance) are defined once in `config/order-options.php`.
- A pickup time is required. Times are hourly, on the hour, within the delivery hours shown in the footer (Mon–Fri 11:00–21:00, Sat 12:00–21:00, Sun 12:00–20:00; the closing hour is included). The rule uses Asia/Taipei time on the server:
  - before 10:00: any time today;
  - 10:00–19:59: today, no earlier than the current time plus one hour, rounded up to the next whole hour;
  - 20:00–20:59: orders are not accepted;
  - from 21:00: any time tomorrow.
  If the chosen time expires while the customer is reviewing, the order is rejected and the cart shows fresh times.
- Each order stores a snapshot of item names and unit prices, so order history is kept even if a dish is later edited or deleted.
- There is no payment processing, delivery tracking, order status, or student verification.

Cart contents stay in this browser when local storage is available; customer details, notes, and pickup location are not kept across reloads.

The site plays no audio; the former background music and click sounds have been removed.

## Resources and attribution

- [W3.CSS 6 stylesheet](https://www.w3schools.com/w3css/6/w3.css) and the preserved [Powered by w3.css](https://www.w3schools.com/w3css/default.asp) footer link.
- [Google Fonts](https://fonts.google.com/): Lato, the single typeface for the customer site and the admin.
- [Font Awesome 4.7](https://fontawesome.com/v4/): interface icons.
- Existing food photographs and sea-life assets are retained. Team ownership, ingredient accuracy, image cleanup, and media licensing/source verification remain review items; this project makes no new ownership or license claims.

See `docs/TideBites_Web_Content_Package.md` for content rules and open team decisions.
