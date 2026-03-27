# viju/laravel-cms-modules

A modular, SaaS-ready CMS package for Laravel 9–12.
Actively maintained fork of [phpworkers/cms](https://github.com/shunnmugam/laravel-admin)
with Laravel 12 support, PHP 8.2+, Tabler Bootstrap 5, and full multi-tenancy (SaaS) support.

---

## What's new vs phpworkers/cms

| Feature                | phpworkers/cms             | viju/laravel-cms-modules      |
| ---------------------- | -------------------------- | ----------------------------- |
| Laravel support        | 5.4 — 8                    | 9, 10, 11, 12                 |
| PHP support            | 7.x                        | 8.1, 8.2, 8.3                 |
| UI Theme               | Bootstrap 3 (Gentelella)   | Tabler Bootstrap 5            |
| Icons                  | Font Awesome 4             | Tabler Icons                  |
| laravelcollective/html | Required (abandoned)       | Replaced with fork            |
| DataTables             | v9                         | v9 — v12                      |
| Controller syntax      | String `Controller@method` | Class array syntax            |
| Middleware             | In constructors            | Route level (L11+ compatible) |
| Multi-tenancy (SaaS)   | ❌                         | ✅ stancl/tenancy v3          |
| Subscription plans     | ❌                         | ✅ Plans + features           |
| Tenant onboarding      | ❌                         | ✅ Trial → Approve → Active   |
| Active maintenance     | No (last commit 2022)      | Yes                           |

---

## Requirements

- PHP ^8.1
- Laravel ^9.0 \| ^10.0 \| ^11.0 \| ^12.0
- MySQL 8.0+
- Composer 2.x
- Node.js 18+ (for assets)

---

## Installation

### Option A — Public GitHub (recommended)

Add to your project `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:v-IJU/laravel-cms-modules.git"
    }
  ],
  "require": {
    "viju/laravel-cms-modules": "dev-setupv12/modules"
  }
}
```

Then install:

```bash
composer install
```

### Option B — Local development (path repo)

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "./packages/laravel-cms",
      "options": { "symlink": true }
    }
  ],
  "require": {
    "viju/laravel-cms-modules": "*@dev"
  }
}
```

---

## SSH key setup (for GitHub access)

```bash
# 1. Generate SSH key
ssh-keygen -t ed25519 -C "your@email.com"

# 2. Copy public key
cat ~/.ssh/id_ed25519.pub

# 3. Add to GitHub → Settings → SSH Keys

# 4. Test connection
ssh -T git@github.com
# Hi username! You've successfully authenticated ✅
```

Ask team lead to add your GitHub account as collaborator to `v-IJU/laravel-cms-modules`.

---

## Quick Setup — Normal Mode (single app)

```bash
# Install
composer install

# Run CMS installer
php artisan cms:install
# → Answer: No to multi-tenancy

# Done! Visit:
# http://localhost/administrator
# Username: admin
# Password: admin123
```

---

## Quick Setup — Tenancy Mode (SaaS)

### Step 1 — Install stancl/tenancy

```bash
composer require stancl/tenancy
php artisan tenancy:install
php artisan migrate
```

### Step 2 — Install CMS with tenancy

```bash
php artisan cms:install
# → Answer: Yes to multi-tenancy
```

### Step 3 — Create first tenant

```bash
php artisan cms:create-tenant
```

```
Tenant ID:   acme
Name:        Acme Corp
Email:       admin@acme.com
Plan:        Basic ($9.99/monthly)
Trial days:  14
```

### Step 4 — Add to hosts file (local dev)

Windows: `C:\Windows\System32\drivers\etc\hosts`
Mac/Linux: `/etc/hosts`

```
127.0.0.1  acme.localhost
```

### Step 5 — Visit tenant panel

```
http://acme.localhost:8100/administrator
Username: admin
Password: admin123
```

---

## CMS Install Command (step by step)

```bash
php artisan cms:install
```

What it does:

```
[1] Publish config files         → config/cms.php, config/lfm.php
[2] Publish CMS modules          → cms/core/* (base modules)
[3] Publish skin assets          → public/skin/
[4] Setup mode (normal/tenancy)
[5] Run migrations               → php artisan migrate + cms-migrate
[6] Register modules             → update:cms-module
    Register menus               → update:cms-menu
[7] Seed data                    → db:cms-seed
[8] Save config                  → tenancy_enabled, install_mode
```

---

## Upgrade existing app to tenancy

```bash
# Install stancl/tenancy first
composer require stancl/tenancy
php artisan tenancy:install

# Then upgrade
php artisan cms:setup-tenancy
```

---

## Folder Structure

```
cms/
├── core/                    ← pre-built core modules
│   ├── admin/               ← login, dashboard
│   ├── layout/              ← master layout, sidebar, topnav
│   ├── user/                ← user management
│   ├── usergroup/           ← user groups
│   ├── gate/                ← roles & permissions
│   ├── menu/                ← menu builder
│   ├── configurations/      ← site & mail settings
│   └── subscription/        ← plans, tenants, subscriptions (tenancy only)
│
└── local/
    └── themes/
        └── theme1/          ← your custom modules go here

public/
└── skin/
    └── theme1/              ← theme assets
```

---

## All Available Commands

### Installation

```bash
php artisan cms:install              # Fresh install (normal or tenancy)
php artisan cms:setup-tenancy        # Upgrade existing app to SaaS/tenancy
```

### Tenant management (tenancy mode only)

```bash
php artisan cms:create-tenant        # Create tenant interactively
php artisan cms:migrate-tenants      # Run migrations across all tenant DBs
php artisan cms:sync-tenant-modules  # Sync modules/menus for all tenants
php artisan cms:add-module-to-plan {plan} {module}  # Add module to plan + sync tenants
```

### Module creation

```bash
php artisan make:cms-module {name}           # Create new module
php artisan make:cms-module {name} --crud    # Create with CRUD scaffold
php artisan make:cms-controller {name} {module}
php artisan make:cms-model {name} {module}
php artisan make:cms-migration {name} {module}
php artisan make:cms-command {name} {module}
php artisan make:cms-seeder {name} {module}
```

### Database

```bash
php artisan cms-migrate              # Migrate all modules (normal mode)
php artisan cms-migrate --db=central # Central DB only (tenancy)
php artisan cms-migrate --db=tenant  # Tenant DB only (tenancy)
php artisan cms-migrate --module=Blog # Specific module
php artisan db:cms-seed              # Seed all modules
```

### Registration

```bash
php artisan update:cms-module        # Register all modules
php artisan update:cms-module --modules=Blog --modules=Products  # Specific modules
php artisan update:cms-menu          # Register all menus
php artisan update:cms-menu --modules=Blog  # Specific module menus
```

---

## Create your own module

```bash
php artisan make:cms-module Products --crud
```

Creates:

```
cms/local/themes/theme1/Products/
├── Controllers/
│   └── ProductsController.php
├── Models/
│   └── Products.php
├── Database/
│   ├── Migration/
│   │   └── 2025_01_01_create_products_table.php
│   └── Seeds/
├── resources/views/admin/
│   ├── index.blade.php
│   ├── create.blade.php
│   └── edit.blade.php
├── Providers/
│   └── ProductsServiceProvider.php
├── module.json
├── menu.xml
├── adminroutes.php
└── routes.php
```

### menu.xml example

```xml
<?xml version="1.0" encoding="utf-8"?>
<menus>
    <group name="Shop" order="1">
        <menugroup name="Products" icon="ti ti-package" order="0">
            <menu name="All Products" route="products.index"/>
            <menu name="Add Product"  route="products.create"/>
        </menugroup>
    </group>
</menus>
```

### Register after creating

```bash
php artisan cms-migrate --module=Products
php artisan update:cms-module
php artisan update:cms-menu
```

---

## Subscription / SaaS Plans

### Plan management

```
http://localhost/administrator/subscription/plans
```

- Create plans with module features
- Set max users, max modules
- Set trial days
- Enable/disable modules per plan

### Tenant onboarding flow

```
Admin creates tenant → Trial period starts
        ↓
Admin reviews tenant
        ↓
Admin approves → Subscription starts → Tenant activated
        ↓
OR Admin rejects → Tenant removed
```

### Add module to a plan (and sync all tenants)

```bash
php artisan cms:add-module-to-plan pro blog --migrate
```

This will:

- Add `module_blog` to pro plan features
- Find ALL tenants on pro plan
- Run blog migration in each tenant DB
- Register blog module + menu for each tenant

---

## module.json reference

```json
{
  "name": "products",
  "db_scope": "tenant",
  "version": "1.0.0",
  "type": "local",
  "providers": ["providers\\ProductsServiceProvider"],
  "helpers": {
    "Products": "cms\\local\\themes\\theme1\\Products\\helpers\\Products"
  }
}
```

`db_scope` values:

| Value     | When to use                                |
| --------- | ------------------------------------------ |
| `central` | Central DB only (e.g. subscription, plans) |
| `tenant`  | Tenant DB only (e.g. local modules)        |
| `both`    | Both DBs (e.g. user, menu, configurations) |

---

## Blade directives (tenancy mode)

```blade
@canModule('blog')
    <a href="{{ route('blog.index') }}">Blog</a>
@endcanModule

@subscriptionActive
    <p>Your subscription is active</p>
@endsubscriptionActive
```

---

## Migrate from phpworkers/cms

```bash
composer remove phpworkers/cms
composer require viju/laravel-cms-modules
php artisan cms:install
```

### Fix existing modules (VS Code find & replace)

**validate():**

```
Find:    $this->validate($request,
Replace: $request->validate(
```

**DataTables:**

```
Find:    Datatables::of(
Replace: DataTables::of(

Find:    use Datatables;
Replace: use Yajra\DataTables\Facades\DataTables;
```

**Icons:**

```
Find:    glyphicon glyphicon-
Replace: ti ti-
```

**Bootstrap 5:**

```
Find:    btn-default     → btn-secondary
Find:    data-toggle=    → data-bs-toggle=
Find:    data-dismiss=   → data-bs-dismiss=
```

---

## Troubleshooting

**Tenant not found:**

```bash
php artisan tinker
DB::table('domains')->get();
```

**Views not updating:**

```bash
php artisan view:clear
php artisan cache:clear
```

**Modules not loading:**

```bash
composer dump-autoload
php artisan update:cms-module
php artisan update:cms-menu
```

**DB connection error:**

```bash
php artisan config:clear
# Check .env DB_* values
```

**Tenant DB wrong:**

```bash
# Add to hosts file
127.0.0.1  acme.localhost

# Check domain in DB
php artisan tinker
DB::table('domains')->get();
```

---

## .env reference (tenancy mode)

```env
APP_NAME="My SaaS"
APP_URL=http://localhost:8100
APP_DOMAIN=localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=myapp_central
DB_USERNAME=root
DB_PASSWORD=secret

DB_ROOT_USERNAME=root
DB_ROOT_PASSWORD=secret
```

---

## Version Support

| Package Version | Laravel       | PHP           |
| --------------- | ------------- | ------------- |
| 1.x             | 9, 10, 11, 12 | 8.1, 8.2, 8.3 |

---

## Credits

Fork of [phpworkers/cms](https://github.com/shunnmugam/laravel-admin)
by [Ramesh](https://github.com/shunnmugam) — MIT License.

Modified and maintained by Viju (2025).

---

## License

MIT License — see [LICENSE](LICENSE) for details.
