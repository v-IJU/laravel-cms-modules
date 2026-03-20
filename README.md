# yourname/laravel-cms

A modular, theme-driven CMS package for Laravel 9-12 — actively maintained
fork of [phpworkers/cms](https://github.com/shunnmugam/laravel-admin)
with Laravel 12 support, PHP 8.2+ compatibility, and Tabler Bootstrap 5 theme.

---

## What's new compared to phpworkers/cms

| Feature                    | phpworkers/cms             | yourname/laravel-cms          |
| -------------------------- | -------------------------- | ----------------------------- |
| Laravel support            | 5.4 — 8                    | 9, 10, 11, 12                 |
| PHP support                | 7.x                        | 8.1, 8.2, 8.3                 |
| UI Theme                   | Bootstrap 3 (Gentelella)   | Tabler Bootstrap 5            |
| Icons                      | Font Awesome 4             | Tabler Icons                  |
| laravelcollective/html     | Required (abandoned)       | Replaced with compatible fork |
| DataTables                 | v9                         | v9 — v12                      |
| Controller syntax          | String `Controller@method` | Class array syntax            |
| Middleware in constructors | Yes (removed in L11+)      | Fixed — route level           |
| Active maintenance         | No (last commit 2022)      | Yes                           |

---

## Features

- Module based app (core + local modules)
- Theme based (multiple themes supported)
- menu.xml driven sidebar navigation (like Joomla)
- Roles and Permissions (CGate — custom module)
- User Management
- User Group Management
- Menu Builder
- Page Creation
- Blog Module
- Mail Configuration
- Site Configuration
- Plugin system
- Tabler Bootstrap 5 admin UI
- Dark mode support
- Laravel 9, 10, 11, 12 compatible
- PHP 8.1, 8.2, 8.3 compatible

---

## Requirements

- PHP ^8.1
- Laravel ^9.0|^10.0|^11.0|^12.0
- MySQL 8.0+ or PostgreSQL

---

## Installation

```bash
composer require yourname/laravel-cms
```

### Setup

```bash
# Publish config, cms folder structure, assets
php artisan vendor:publish --tag=cms-config
php artisan vendor:publish --tag=cms-structure
php artisan vendor:publish --tag=cms-assets

# Dump autoload
composer dump-autoload

# Run migrations
php artisan cms-migrate

# Seed default data
php artisan db:cms-seed

# Register modules
php artisan update:cms-module

# Register plugins
php artisan update:cms-plugins

# Register menus
php artisan update:cms-menu
```

Then visit: `http://yourdomain.com/administrator`

```
Username: admin
Password: admin123
```

---

## Folder Structure

```
cms/
├── core/                    ← pre-built core modules (do not modify)
│   ├── admin/               ← login, dashboard
│   ├── layout/              ← master layout, sidebar, topnav
│   ├── user/                ← user management
│   ├── usergroup/           ← user groups
│   ├── gate/                ← roles & permissions
│   ├── menu/                ← menu builder
│   └── configurations/      ← site & mail settings
│
└── local/                   ← your modules live here
    └── themes/
        └── theme1/          ← active theme
            └── modules/     ← your custom modules

public/
└── skin/
    └── theme1/              ← theme assets (css, js, fonts)
```

---

## Commands

### Module commands

```bash
# Create new module
php artisan make:cms-module {module-name}

# Create with CRUD
php artisan make:cms-module {module-name} --crud

# Create controller inside module
php artisan make:cms-controller {name} {module}

# Create model inside module
php artisan make:cms-model {name} {module}

# Create migration inside module
php artisan make:cms-migration {name} {module}

# Create command inside module
php artisan make:cms-command {name} {module}
```

### Database commands

```bash
# Migrate all modules
php artisan cms-migrate

# Seed all modules
php artisan db:cms-seed
```

### Registration commands

```bash
# Register modules to database
php artisan update:cms-module

# Register plugins
php artisan update:cms-plugins

# Register menus from menu.xml
php artisan update:cms-menu
```

---

## Create your own module

```bash
php artisan make:cms-module Products
```

This creates:

```
cms/local/themes/theme1/
└── Products/
    ├── Controllers/
    ├── Models/
    ├── Database/
    │   ├── Migration/
    │   └── Seeds/
    ├── resources/
    │   └── views/
    ├── Providers/
    ├── helpers/
    ├── module.json          ← module metadata
    ├── composer.json        ← module composer info
    ├── menu.xml             ← defines sidebar menu items
    ├── routes.php           ← frontend routes
    └── adminroutes.php      ← admin routes
```

### menu.xml example

```xml
<?xml version="1.0" encoding="utf-8"?>
<menus>
    <group name="Shop" order="1">
        <menugroup name="Products" icon="ti ti-package" order="0">
            <menu name="All Products" route="products.index"/>
            <menu name="Add Product" route="products.create"/>
        </menugroup>
    </group>
</menus>
```

Then register:

```bash
php artisan update:cms-menu
```

---

## Themes

### Default theme

Default theme is `theme1` — uses Tabler Bootstrap 5.

### Create new theme

```bash
# Just create a new folder inside cms/local/
mkdir cms/local/themes/mytheme
```

### Switch theme

Go to Admin Panel → Site Configuration → Theme

### Skin (assets)

```
public/skin/{theme-name}/
├── css/
├── js/
├── fonts/
└── img/
```

---

## Migrating from phpworkers/cms

### Step 1 — Update composer.json

```bash
composer remove phpworkers/cms
composer require yourname/laravel-cms
```

### Step 2 — Run install

```bash
php artisan cms:install
```

### Step 3 — Fix your existing modules

**validate()** — VS Code find & replace:

```
Find:    $this->validate($request,
Replace: $request->validate(
```

**DataTables** — VS Code find & replace:

```
Find:    Datatables::of(
Replace: DataTables::of(
```

Add import to controller:

```
Find:    use Datatables;
Replace: use Yajra\DataTables\Facades\DataTables;
```

**Icons** — VS Code find & replace:

```
Find:    glyphicon glyphicon-
Replace: ti ti-
```

**Bootstrap 5** — VS Code find & replace:

```
Find:    btn-default
Replace: btn-secondary

Find:    data-toggle=
Replace: data-bs-toggle=

Find:    data-dismiss=
Replace: data-bs-dismiss=
```

---

## Version Support

| Package Version | Laravel       | PHP           |
| --------------- | ------------- | ------------- |
| 1.x             | 9, 10, 11, 12 | 8.1, 8.2, 8.3 |

---

## Changelog

### v1.0.0

- Laravel 9, 10, 11, 12 support
- PHP 8.1, 8.2, 8.3 compatibility
- Replaced laravelcollective/html with maintained fork
- Tabler Bootstrap 5 theme
- Fixed $this->validate() → $request->validate()
- Fixed DataTables v12 compatibility
- Fixed middleware in constructors (Laravel 11+)
- Removed Schema::defaultStringLength(191)
- Modern icon system (Tabler Icons)
- Bootstrap 5 components throughout

---

## Credits

This package is a fork of
[phpworkers/cms](https://github.com/shunnmugam/laravel-admin)
originally created by [Ramesh](https://github.com/shunnmugam) —
licensed under MIT.

---

## License

MIT License — see [LICENSE](LICENSE) file for details.

---

## Contributing

Pull requests are welcome! Please:

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing`)
3. Commit your changes (`git commit -m 'feat: add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing`)
5. Open a Pull Request

---

## Support

- Open an issue on GitHub
- Star the repo if it helps you ⭐
