# Bundled libraries of tool_mulib

This directory holds the third party PHP libraries used by tool_mulib, installed by Composer
and committed to the repository, so the plugin works on sites installed from a ZIP file as well
as on sites that manage plugins with Composer.

Libraries: opis/json-schema with opis/string and opis/uri.

## Why the Composer autoloader is not used

Never include `vendor/autoload.php` of this directory. Every Composer autoloader registers itself
in front of all other class loaders:

* the plugin then becomes the Composer root package for the rest of the request,
  `Composer\InstalledVersions::getRootPackage()` returns this directory instead of Moodle and
  core environment checks inspect the wrong installation,
* copies of packages that Moodle installs too (psr/*, symfony/*, ...) are loaded from here
  instead of from the Moodle vendor directory, possibly in a different version,
* the Composer runtime classes of two Composer versions get mixed.

Moodle 5.3 requires admins to run `composer install` in the Moodle root with production flags,
the Moodle vendor directory must stay the only Composer installation that PHP sees.

## How the libraries are loaded

`tool_mulib\local\vendor_loader::register()` reads the maps Composer generates in
`vendor/composer/autoload_classmap.php`, `autoload_psr4.php`, `autoload_namespaces.php` and
`autoload_files.php` and appends a plain class loader after all existing loaders:

* Moodle always wins, a class Moodle can load is never loaded from here,
* Composer runtime state (registered loaders, installed packages, root package) is not touched,
* autoloaded files are included once, shared with Composer through
  `$GLOBALS['__composer_autoload_files']`,
* registering the same directory again does nothing.

The plugin calls it right before the libraries are needed:

```php
\tool_mulib\local\vendor_loader::register(__DIR__ . '/../../vendor');
```

## Upgrading the libraries

Composer runs inside this directory, `composer.json` sets `"vendor-dir": "."`.

1. Check what is outdated:
   ```
   cd public/admin/tool/mulib/vendor
   composer outdated
   ```
2. Update, always without development packages and with an optimised class map:
   ```
   composer update --no-dev --optimize-autoloader --no-plugins --no-scripts
   ```
   To allow a new major version edit the constraint in `composer.json` first.
3. Update the versions in `thirdpartylibs.xml` of the plugin, add or remove libraries there
   when the dependency tree changed (`composer show --tree`).
4. Remove files Composer does not need for runtime only when they are not referenced by the
   generated maps, the maps are the only thing the loader uses.
5. Run the plugin PHPUnit and Behat tests, then commit the whole directory including
   `composer.json`, `composer.lock`, `composer/` and this README.

Do not add the libraries to the Moodle root `composer.json` and do not require
`vendor/autoload.php` anywhere.

## Future plan: libraries as Composer dependencies

Bundling is the simple way that works everywhere today. The planned next step, for all MuTMS
plugins with bundled libraries:

1. The plugin root `composer.json` requires the libraries too, sites that install plugins
   with Composer get them in the Moodle root vendor directory from the one
   `composer install --no-dev` the admin runs, shared packages resolved once.
2. In git the plugin `vendor/` keeps only the recipe: `composer.json`, `composer.lock`,
   `README.md` and a `.gitignore` ignoring everything else. Development tools (mudev) run
   `composer install --no-dev --optimize-autoloader` in every plugin `vendor/` after checkout.
3. `vendor_loader::register()` does nothing when `vendor/composer/` does not exist, the classes
   come from the Moodle root vendor then; otherwise it appends as now, so the root always wins.
4. An environment check verifies that the required classes exist and tells the admin to run
   Composer in the plugin `vendor/` directory when they do not.
5. Release builds: ZIP packages for GitHub and MuTMS Camp have `vendor/` baked in, the Composer
   package does not.
6. A test keeps the library constraints of the root `composer.json` and `vendor/composer.json`
   in sync.

Plugin code does not change for this: it already loads the libraries only through
`vendor_loader`.
