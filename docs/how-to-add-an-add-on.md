# How to add a Kjeks add-on

An **add-on** is a small, standalone plugin that teaches Kjeks to gate one more
kind of tracker. It lives in its own repository, self-updates from GitHub, and
declares core as a hard dependency (`Requires Plugins: kjeks`). Only the core
plugin ships to WordPress.org; add-ons are distributed as GitHub releases.

Existing examples: `kjeks-embeds` (oEmbed iframes), `kjeks-scripting` (any
enqueued script handle), `kjeks-google`, `kjeks-ai-reviewer`. This guide uses
`kjeks-scripting` as the reference.

## Two ways to integrate

1. **Just call the public API.** If you only need to gate a script or embed from
   an existing plugin or theme, you do not need an add-on at all — use the
   functions in [src/functions.php](../src/functions.php) on the
   `kjeks_register_integrations` action. See
   [Adding a custom integration](README.md#adding-a-custom-integration).
2. **Build an add-on** when you want a distributable plugin with its own admin
   screen and release cadence. That is what this guide covers. Add-ons build on
   the shared `Soderlind\Kjeks\AddonKit` base classes so they don't
   re-implement the settings screen, option storage, or category picker.

## The AddonKit base classes

Provided by core in [src/AddonKit/](../src/AddonKit). Because core loads before
its dependents (WordPress Plugin Dependencies), these classes are available to
any add-on at runtime — no separate library to install.

| Class | Purpose |
| --- | --- |
| `AddonKit\Plugin` | Singleton bootstrap. Extend it; implement `register()`. |
| `AddonKit\SettingsPage` | Abstract settings screen that mounts as a submenu of the core Kjeks "Cookie Consent" menu; handles the Multisite/single-site split, menus, nonces, saving, and resolution. |
| `AddonKit\Options` | `read()` / `write()` / `delete()` an option in the right place (network option on Multisite, regular option on single site). |
| `AddonKit\Categories` | The consent-category picker: `choices()`, `is_valid()`, `coerce()`, `render_select()`. Backed by core's optional categories. |

## Conventions

- **Name** by domain, like the core family: `kjeks-<thing>`, namespace
  `Soderlind\Kjeks<Thing>`.
- **Headers:** `Requires Plugins: kjeks`, `Requires at least: 6.8`,
  `Requires PHP: 8.3`, `Network: true`, `Text Domain: kjeks-<thing>`.
- **Gate client-side.** Register handles/scripts with core and let the core
  script gate emit them inert — do not print trackers yourself. This keeps
  output cache-friendly (see [ADR-0003](adr/0003-client-side-gating.md)).
- **Keep config logic pure.** Put normalisation in a WordPress-free class so it
  is unit-testable (see `ScriptRules` below).
- **Distribute from GitHub.** Wire the self-updater per add-on; the updater is
  intentionally **not** in core.

## File layout

```text
kjeks-scripting/
  kjeks-scripting.php        Bootstrap: constants, autoload, updater, boot
  includes/
    Plugin.php               extends AddonKit\Plugin
    Settings.php             extends AddonKit\SettingsPage
    ScriptRules.php          pure config normalisation (unit-tested)
    Gate.php                 registers integrations on the front end
  uninstall.php
  composer.json              php >=8.3, soderlind/wordpress-github-updater ^2.0
  phpcs.xml.dist             WordPress standard, own text domain + prefixes
  phpunit.xml.dist
  tests/
    Pest.php
    Unit/ScriptRulesTest.php
  .github/workflows/         release-zip build (copy from an existing add-on)
```

## 1. Bootstrap

Define constants, load the add-on's own autoloader, wire the GitHub updater,
then boot **inside `plugins_loaded`** — guarded so the add-on degrades quietly
if core is missing or too old.

```php
add_action( 'plugins_loaded', static function (): void {
    // Core (a declared dependency) provides the AddonKit base classes.
    if ( ! class_exists( \Soderlind\Kjeks\AddonKit\Plugin::class ) ) {
        return;
    }

    require_once KJEKS_SCRIPTING_DIR . 'includes/ScriptRules.php';
    require_once KJEKS_SCRIPTING_DIR . 'includes/Settings.php';
    require_once KJEKS_SCRIPTING_DIR . 'includes/Gate.php';
    require_once KJEKS_SCRIPTING_DIR . 'includes/Plugin.php';

    Plugin::instance()->boot();
} );
```

## 2. Plugin

```php
use Soderlind\Kjeks\AddonKit\Plugin as AddonPlugin;

final class Plugin extends AddonPlugin {
    protected function register(): void {
        $settings = new Settings();
        $settings->hooks();
        ( new Gate( $settings ) )->hooks();
    }
}
```

## 3. Settings screen

Extend `AddonKit\SettingsPage` and implement the abstract methods. The screen
mounts as a submenu of the core **Cookie Consent** menu (slug `kjeks-network`)
in both Multisite and single-site; the kit handles the menu, nonces, and saving
for you.

```php
use Soderlind\Kjeks\AddonKit\Categories;
use Soderlind\Kjeks\AddonKit\SettingsPage;

final class Settings extends SettingsPage {
    protected function option_key(): string  { return 'kjeks_scripting'; }
    protected function menu_slug(): string   { return 'kjeks-scripting'; }
    protected function page_title(): string  { return __( 'Kjeks Scripting', 'kjeks-scripting' ); }
    protected function menu_title(): string  { return __( 'Scripting', 'kjeks-scripting' ); }

    protected function defaults(): array     { return ScriptRules::defaults(); }
    protected function normalize( array $raw ): array { return ScriptRules::normalize( $raw ); }

    protected function render_fields( string $prefix, array $config ): void {
        // Build field names with $this->field_name( $prefix, 'handle' ) so they
        // work on both the site form and the network form. Render category
        // pickers with Categories::render_select( $name, $id, $current ).
    }
}
```

Abstract methods you implement:

| Method | Returns / does |
| --- | --- |
| `option_key()` | Option name (also the settings group and config filter prefix). |
| `menu_slug()` | Admin page slug; also derives the save nonce/action. |
| `page_title()` | Screen title / `<h1>` (translate in the add-on's text domain). |
| `defaults()` | Default, normalised config. |
| `normalize( $raw )` | Coerce submitted **or** stored values into the config shape. Must be idempotent. |
| `render_fields( $prefix, $config )` | Echo the form rows inside the kit-provided `<form>`. |

Optional overrides: `menu_title()` (short menu label, e.g. `Scripting`; defaults
to the page title) and `parent_slug()` (parent menu; defaults to the core
`kjeks-network` menu).

Read the effective config anywhere with `$settings->resolve()` (reads storage →
`normalize()` → `apply_filters( "{$option_key}_config", $config )`).

## 4. Pure config rules

Keep normalisation free of WordPress state so it is directly unit-testable.

```php
use Soderlind\Kjeks\AddonKit\Categories;

final class ScriptRules {
    public static function defaults(): array { return array( 'handles' => array() ); }

    public static function normalize( array $raw ): array {
        // ...map submitted handle[]/category[] (or a stored map) to
        // [ handle => Categories::coerce( $category ) ]...
    }
}
```

`Categories::coerce()` guarantees a valid gating category, falling back to
`marketing` for anything invalid — so bad input can never store a broken value.

## 5. Front-end gate

Register each configured item with core on the front end. Core's script gate
rewrites the tag to an inert, consent-aware form; you write no output.

```php
final class Gate {
    public function __construct( private readonly Settings $settings ) {}

    public function hooks(): void {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_integrations' ), 1000 );
    }

    public function register_integrations(): void {
        if ( is_admin() || ! function_exists( 'kjeks_register_integration' ) ) {
            return;
        }
        foreach ( ScriptRules::handles( $this->settings->resolve() ) as $handle => $category ) {
            kjeks_register_integration( 'scripting-' . $handle, array(
                'category' => $category,
                'label'    => $handle,
                'handles'  => array( $handle ),
            ) );
        }
    }
}
```

The core API you call from a gate: `kjeks_register_integration()`,
`kjeks_enqueue_script()`, `kjeks_add_inline_script()`, `kjeks_embed()`,
`kjeks_is_granted()` — all in [src/functions.php](../src/functions.php).

## 6. Tests

Add-on config classes reference `AddonKit\Categories`, which lives in core. In
CLI tests the core classes are not autoloaded, so register a **targeted**
loader in `tests/Pest.php`.

> **Do not** `require` core's real `vendor/autoload.php` in tests. Its
> `files`-autoloaded `functions.php` calls `exit` when `ABSPATH` is undefined
> (CLI), which silently kills the test run (zero output, exit 0).

```php
spl_autoload_register( static function ( string $class ): void {
    $prefix = 'Soderlind\\Kjeks\\';
    if ( ! str_starts_with( $class, $prefix ) ) {
        return;
    }
    $file = dirname( __DIR__, 2 ) . '/kjeks/src/'
        . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
    if ( is_readable( $file ) ) {
        require_once $file;
    }
} );

// Core Consent\Categories::all() uses __() and apply_filters():
uses()->beforeEach( function (): void {
    Brain\Monkey\setUp();
    Brain\Monkey\Functions\when( '__' )->returnArg( 1 );
    Brain\Monkey\Functions\when( 'apply_filters' )->returnArg( 2 );
} )->afterEach( fn () => Brain\Monkey\tearDown() )->in( 'Unit' );
```

Run the suite with `vendor/bin/pest`.

## 7. Tooling and release

- **PHPCS:** copy an add-on's `phpcs.xml.dist`; set the add-on's own
  `text_domain` and global `prefixes`. Lint against core's binary:
  `../kjeks/vendor/bin/phpcs --standard=phpcs.xml.dist`.
- **composer.json:** require `php >=8.3` and
  `soderlind/wordpress-github-updater ^2.0`; PSR-4 map the add-on namespace to
  `includes/`.
- **CI:** copy `.github/workflows/` from an existing add-on to build the release
  zip (with `--no-dev` vendor) on publish.

## Checklist

- [ ] `Requires Plugins: kjeks` and `Network: true` in the header.
- [ ] Boot guarded by `class_exists( AddonKit\Plugin::class )`.
- [ ] Config normalisation is pure and unit-tested.
- [ ] Gate registers with core on the front end; no tracker output written directly.
- [ ] PHPCS clean; Pest green; own GitHub release workflow wired.
