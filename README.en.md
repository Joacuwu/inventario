# Inventory and stock control

🇦🇷 [Leer en español](README.md)

Stock management for a retail business (the demo is a hardware store), built in **framework-free PHP** (compatible with PHP 5.6 to 8.x), **MySQL**, **Bootstrap 5**, **jQuery** and **Ajax**. The UI and code identifiers are in Spanish, since it targets Argentine businesses.

## Features
- **Dashboard** with product count, units in stock, stock value and products below minimum, computed in a single SQL query.
- **Stock list** with search by code or name, category and "below minimum" filters, and pagination. Products that need restocking are listed first and highlighted in yellow (red when out of stock).
- **Stock in/out via Ajax:** a modal records the movement with jQuery and the row updates without reloading the page.
- **Movement history** filtered by product, type and date range, showing who made each change and the resulting stock (`JOIN` across movements, products and users).
- **Excel (CSV) export** of the filtered movements.
- **Roles:** the admin creates and edits products; the warehouse operator can only browse and record movements.

## Design decisions
| Decision | Why |
|---|---|
| Stock only changes through movements | Editing a product cannot touch its stock. Every unit in or out is recorded with user, date and reason, so the history always adds up to the current stock. |
| Initial stock as the first movement | Creating a product with stock records an automatic "in" movement in the same transaction. |
| Stock out via `UPDATE ... SET stock = stock - ? WHERE id = ? AND stock >= ?` | If two operators sell the last unit at the same time, the database accepts only one. Reading the stock, subtracting in PHP and saving could go negative. |
| Transactions (`beginTransaction` / `commit` / `rollBack`) | The stock change and its history record are saved together or not at all. |
| CSV with `;` and a UTF-8 BOM | That is what Excel expects with Argentine regional settings to split columns and show accents correctly. |
| CSV cells starting with `=`, `+`, `-` or `@` get a leading `'` | Prevents formula injection in Excel if someone enters a malicious reason. |

## Security
| Risk | How it is handled |
|---|---|
| SQL injection | Every query is a PDO prepared statement; `LIKE` wildcards are escaped. |
| XSS | All output goes through `htmlspecialchars` (the `e()` helper); JavaScript uses `.text()`, never `.html()`, with server data. |
| CSRF | Per-session token on every form and every Ajax request. |
| Permissions | `requiere_admin()` on product screens; the Ajax endpoint requires a session and returns a JSON 401 when it expires. |
| Passwords | `password_hash` / `password_verify`, session regeneration and a 5-minute lockout after 5 failed attempts. |
| Internal files | `.htaccess` denies `includes/`, `data/`, `sql/` and `tests/`. |

## Documented use cases
| # | Case | Expected result |
|---|---|---|
| 1 | Operator records 5 units out with stock at 14 | The row changes to 9 without reloading; a confirmation toast appears. |
| 2 | 15 units out with stock at 14 | Not recorded; the modal shows "Insufficient stock: 14 units available". |
| 3 | A movement leaves stock at or below the minimum | The row turns yellow (red at 0). |
| 4 | Operator opens `producto.php` | Responds 403. |
| 5 | Admin edits a product | Can change code, name, price and minimum, but not the stock. |
| 6 | Create with an existing code | Not saved; "A product with that code already exists". |
| 7 | Session expires while the modal is open | The Ajax request gets a 401 and redirects to the login page. |
| 8 | Export last month's movements filtered by "Out" | Downloads a CSV that Excel opens with correct columns and accents. |

Cases 2 and 6, and the logic behind the rest are automated in `tests/pruebas.php` (25 tests: queries, movements, validation and SQL injection).

## Structure
```
index.php            Dashboard and stock list
producto.php         Create and edit products (admin)
movimientos.php      History with filters
exportar.php         CSV download
api/movimiento.php   Ajax endpoint (JSON) for stock in/out
login.php / logout.php
includes/
  config.php         Settings (reads config.local.php or environment variables)
  db.php             PDO connection (MySQL, or SQLite in demo mode)
  funciones.php      Session, roles, CSRF, JSON, flash messages
  inventario.php     Queries, transactions and validation
  layout.php         Shared header and footer
assets/js/app.js     Movement modal and Ajax call
sql/schema.sql       MySQL tables
sql/crear_usuario.php  Create users from the command line
tests/pruebas.php    Dependency-free tests
```

## Running it
**Local (demo mode, no MySQL needed):**
```bash
php -S localhost:8000
```
Log in with `admin` / `admin1234` or `operador` / `operador1234`. It creates `data/demo.sqlite` with 17 products and their history.

**Tests:**
```bash
php tests/pruebas.php
```

**Server with MySQL (e.g. InfinityFree):**
1. Run `sql/schema.sql` in phpMyAdmin.
2. Copy `includes/config.local.ejemplo.php` to `includes/config.local.php` and fill it in.
3. Create the first user: `php sql/crear_usuario.php admin <password> admin "Full Name"` (or from a local environment pointing to the same database).
4. Upload the files to `htdocs/`.

Requires PHP 5.6+ with PDO (`pdo_mysql`, or `pdo_sqlite` for demo mode).

## Screenshots
| Stock | Stock movements | Login |
|---|---|---|
| ![](docs/capturas/2-stock.png) | ![](docs/capturas/3-movimientos.png) | ![](docs/capturas/1-login.png) |
