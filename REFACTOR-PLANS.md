# Abilities for AI for WordPress — Refactoring SSD Plans

> Four independent, detailed plans for refactoring the plugin.
> Each plan is self-contained and can be handed to an agent for execution.
> Plans are ordered by dependency — Plan 4 (Schemas) has no deps, Plan 1 (Registration) builds on Plan 4, Plan 2 (Autoloading) is structural, Plan 3 (Testability) depends on all others.

---

## Current State Summary (for agent context)

- **Plugin:** Abilities for AI for WordPress v3.7.0
- **Location:** `/Users/wicked/my-agent/wordpress-plugins-temp/abilities-for-ai/`
- **Architecture:** Procedural, hook-driven, flat module structure
- **Scale:** 111 abilities, 18 modules, ~8,300 lines PHP, 27 files
- **Entry point:** `abilities-for-ai.php` → requires 4 infrastructure files → 18 ability modules → admin dashboard
- **Hook:** All abilities register on `wp_abilities_api_init` via `wp_register_ability()`
- **Permissions:** `abilities_for_ai_get_permissions($module)` returns `['read' => bool, 'write' => bool, 'delete' => bool]`
- **Pro gate:** `abilities_for_ai_pro_gate($name, $callback)` wraps callbacks with license check
- **No tests, no Composer, no autoloading, no classes for abilities**

---

# Plan 1: Extract Registration Abstraction

## Specification

### Problem
Every ability module repeats identical boilerplate:
1. `add_action('wp_abilities_api_init', ...)` wrapper
2. `$perms = abilities_for_ai_get_permissions('module')` call
3. `if ($perms['read']) { ... }` / `if ($perms['write']) { ... }` / `if ($perms['delete']) { ... }` blocks
4. Each `wp_register_ability()` call repeats `'meta' => array(...)` with identical `show_in_rest`, `mcp`, and `annotations` fields
5. `'permission_callback' => function() { return current_user_can('...'); }` is copy-pasted per ability

This boilerplate accounts for ~30% of every module file and is error-prone (inconsistent formatting, missing tier fields, copy-paste bugs).

### What Varies Per Ability (the actual domain logic)
- Ability name (e.g., `'cron/list-events'`)
- Label and description strings
- Category slug
- Input schema (unique per ability)
- Output schema (often missing, inconsistent)
- Execute callback (the actual business logic)
- WordPress capability required (e.g., `'manage_options'`, `'edit_posts'`)
- Annotations: `readonly`, `destructive`, `idempotent` (3 booleans)
- Tier: `'free'` or `'pro'`
- Operation type: `'read'`, `'write'`, or `'delete'` (determines which permission gate)

### What Is Identical Across ALL Abilities
```php
'meta' => array(
    'show_in_rest' => true,
    'mcp' => array( 'public' => true, 'type' => 'tool' ),
    'annotations' => array( 'readonly' => $X, 'destructive' => $Y, 'idempotent' => $Z ),
    'tier' => $TIER,
),
'permission_callback' => function() { return current_user_can( $CAPABILITY ); },
```

## Strategy

### Step 1: Create `includes/class-ability-registrar.php`

Create a new file with a registration helper class:

```php
<?php
defined( 'ABSPATH' ) || exit;

/**
 * Ability Registrar — eliminates boilerplate from ability module files.
 *
 * Usage in a module file:
 *   $reg = new Abilities_For_AI_Registrar( 'cron', 'manage_options' );
 *   $reg->read( 'cron/list-events', [
 *       'label'       => 'List Cron Events',
 *       'description' => '...',
 *       'input_schema' => [...],
 *       'callback'    => function( $params ) { ... },
 *   ]);
 */
class Abilities_For_AI_Registrar {

    private $module;
    private $capability;
    private $perms;

    /**
     * @param string $module     Module slug (e.g., 'cron', 'content').
     * @param string $capability Default WordPress capability for this module.
     */
    public function __construct( $module, $capability ) {
        $this->module     = $module;
        $this->capability = $capability;
        $this->perms      = abilities_for_ai_get_permissions( $module );
    }

    /**
     * Register a read-only ability (readonly=true, destructive=false, idempotent=true, tier=free).
     */
    public function read( $name, $config ) {
        if ( empty( $this->perms['read'] ) ) return;
        $this->register( $name, $config, 'read' );
    }

    /**
     * Register a write ability (readonly=false, destructive=false, idempotent=true, tier=pro).
     */
    public function write( $name, $config ) {
        if ( empty( $this->perms['write'] ) ) return;
        $config = array_merge( array( 'tier' => 'pro' ), $config );
        $this->register( $name, $config, 'write' );
    }

    /**
     * Register a delete ability (readonly=false, destructive=true, idempotent=true, tier=pro).
     */
    public function delete( $name, $config ) {
        if ( empty( $this->perms['delete'] ) ) return;
        $config = array_merge( array( 'tier' => 'pro' ), $config );
        $this->register( $name, $config, 'delete' );
    }

    /**
     * Internal: build the full wp_register_ability() args from compact config.
     */
    private function register( $name, $config, $op_type ) {
        $tier       = $config['tier'] ?? ( $op_type === 'read' ? 'free' : 'pro' );
        $capability = $config['capability'] ?? $this->capability;
        $callback   = $config['callback'];

        // Determine annotations from operation type.
        $annotations = array(
            'readonly'    => $op_type === 'read',
            'destructive' => $op_type === 'delete',
            'idempotent'  => true,
        );

        // Allow annotation overrides (e.g., idempotent=false for non-idempotent writes).
        if ( isset( $config['annotations'] ) ) {
            $annotations = array_merge( $annotations, $config['annotations'] );
        }

        // Wrap Pro callbacks with license gate.
        if ( $tier === 'pro' ) {
            $callback = abilities_for_ai_pro_gate( $name, $callback );
        }

        $args = array(
            'label'               => $config['label'],
            'description'         => $config['description'],
            'category'            => $config['category'] ?? $this->module,
            'input_schema'        => $config['input_schema'] ?? array( 'type' => 'object' ),
            'execute_callback'    => $callback,
            'permission_callback' => function() use ( $capability ) {
                return current_user_can( $capability );
            },
            'meta' => array(
                'show_in_rest' => true,
                'mcp'          => array( 'public' => true, 'type' => 'tool' ),
                'annotations'  => $annotations,
                'tier'         => $tier,
            ),
        );

        // Only include output_schema if provided.
        if ( isset( $config['output_schema'] ) ) {
            $args['output_schema'] = $config['output_schema'];
        }

        wp_register_ability( $name, $args );
    }
}
```

### Step 2: Create `includes/class-module.php` (optional base for module files)

A thin wrapper that replaces the `add_action` + permission-check boilerplate:

```php
<?php
defined( 'ABSPATH' ) || exit;

/**
 * Base class for ability modules.
 *
 * Usage:
 *   class Cron_Module extends Abilities_For_AI_Module {
 *       protected $module     = 'cron';
 *       protected $capability = 'manage_options';
 *
 *       public function register( $reg ) {
 *           $reg->read( 'cron/list-events', [...] );
 *       }
 *   }
 *   new Cron_Module(); // Self-registers on wp_abilities_api_init
 */
abstract class Abilities_For_AI_Module {
    protected $module;
    protected $capability;

    public function __construct() {
        add_action( 'wp_abilities_api_init', array( $this, 'boot' ) );
    }

    public function boot() {
        $reg = new Abilities_For_AI_Registrar( $this->module, $this->capability );
        $this->register( $reg );
    }

    abstract public function register( Abilities_For_AI_Registrar $reg );
}
```

### Step 3: Migrate ONE module as proof-of-concept

Convert `cron-abilities.php` (smallest at 143 lines, 3 abilities, read-only) from:

```php
// BEFORE (cron-abilities.php — 143 lines)
add_action( 'wp_abilities_api_init', 'wp_native_register_cron_abilities' );
function wp_native_register_cron_abilities() {
    $perms = abilities_for_ai_get_permissions( 'cron' );
    if ( $perms['read'] ) {
        wp_register_ability( 'cron/list-events', array(
            'label'       => 'List Cron Events',
            'description' => '...',
            'category'    => 'cron',
            'input_schema' => array( ... ),
            'execute_callback' => function( $params ) { ... },
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
            'meta' => array( 'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' ), 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'tier' => 'free', ),
        ));
        // ... 2 more abilities with same boilerplate ...
    }
}
```

To:

```php
// AFTER (cron-abilities.php — ~80 lines, 44% reduction)
defined( 'ABSPATH' ) || exit;

add_action( 'wp_abilities_api_init', function() {
    $reg = new Abilities_For_AI_Registrar( 'cron', 'manage_options' );

    $reg->read( 'cron/list-events', array(
        'label'       => 'List Cron Events',
        'description' => '...',
        'input_schema' => array( ... ),
        'callback'    => function( $params ) { /* unchanged business logic */ },
    ));

    $reg->read( 'cron/list-schedules', array( ... ));
    $reg->read( 'cron/get-event', array( ... ));
});
```

### Step 4: Migrate remaining 17 modules

After proof-of-concept is verified working, migrate all modules in order of complexity:

**Batch 1 — Read-only modules (simplest, no write/delete blocks):**
1. `cron-abilities.php` (3 abilities) ← proof-of-concept
2. `site-health-abilities.php` (4 abilities)
3. `themes-abilities.php` (5 abilities)
4. `rest-discovery-abilities.php` (4 abilities)

**Batch 2 — Read+Write modules:**
5. `rewrite-abilities.php` (3 abilities)
6. `settings-abilities.php` (5 abilities)
7. `patterns-abilities.php` (5 abilities)
8. `meta-abilities.php` (11 abilities)
9. `transients-abilities.php` (7 abilities)
10. `blocks-abilities.php` (8 abilities)

**Batch 3 — Full CRUD modules (most complex):**
11. `comment-abilities.php` (5 abilities)
12. `user-abilities.php` (5 abilities)
13. `media-abilities.php` (5 abilities)
14. `plugin-abilities.php` (6 abilities)
15. `taxonomy-abilities.php` (8 abilities)
16. `menu-abilities.php` (12 abilities)
17. `content-abilities.php` (11 abilities)
18. `filesystem-abilities.php` (4 abilities)

### Step 5: Update main plugin file

Add `require_once` for the new class files BEFORE the ability modules:

```php
// In abilities-for-ai.php, after tier-gate.php:
require_once ABILITIES_FOR_AI_PATH . 'includes/class-ability-registrar.php';
```

### Step 6: Update audit-schema.php

No changes needed — the audit validates registered abilities at runtime, not source code. The Registrar produces identical `wp_register_ability()` calls.

## Deliverables

1. **New file:** `includes/class-ability-registrar.php` — the `Abilities_For_AI_Registrar` class
2. **Modified file:** `abilities-for-ai.php` — add require_once for new class
3. **Modified files:** All 18 `*-abilities.php` files — converted to use Registrar
4. **Verification:** Run `wp eval-file audit-schema.php` on production to confirm all 111 abilities still pass schema validation
5. **Verification:** Run `wp eval 'echo count(wp_get_abilities());'` to confirm 111 abilities registered
6. **No behavioral changes** — this is a pure refactor, zero functional impact

### Estimated Reduction
- **Before:** ~2,200 lines of boilerplate (meta arrays, permission callbacks, permission gates)
- **After:** ~800 lines (Registrar class + compact configs)
- **Net savings:** ~1,400 lines (~17% of total codebase)

### Critical Constraints
- `wp_register_ability()` arguments must remain IDENTICAL after refactor
- The `meta` array structure must not change (MCP bridge reads it)
- Permission gates (`$perms['read']`, etc.) must still prevent registration when disabled
- Pro gate wrapping must still happen for `tier === 'pro'` abilities
- Module files must remain individual files (don't merge into one mega-file)
- The Registrar must support per-ability capability overrides (some modules have mixed caps)

---

# Plan 2: Introduce Autoloading (PSR-4)

## Specification

### Problem
The plugin uses 22 `require_once` statements in the main plugin file, loading every file regardless of whether it's needed. There's no `composer.json`, no namespace structure, no autoloader. Class names use `Abilities_For_AI_` prefix convention instead of namespaces. Adding new modules requires manually adding a `require_once` line to the main file.

### Current Load Chain
```
abilities-for-ai.php (lines 24-56):
  require_once includes/helpers.php
  require_once includes/permissions.php
  require_once includes/license-manager.php
  require_once includes/tier-gate.php
  require_once includes/ability-categories.php
  require_once includes/content-abilities.php
  require_once includes/taxonomy-abilities.php
  ... (13 more require_once)
  require_once includes/filesystem-abilities.php
```

### Why This Matters
- No code organization beyond flat `includes/` directory
- Every file loaded on every request (even if abilities not needed)
- No namespacing means all functions pollute global scope
- Can't use modern PHP tooling (static analysis, IDE navigation)
- Adding a module = editing 3 places (file, require_once, permission defaults)

## Strategy

### Step 1: Add `composer.json`

```json
{
    "name": "influencentricity/abilities-for-ai",
    "description": "111 native WordPress abilities across 18 modules",
    "type": "wordpress-plugin",
    "license": "GPL-2.0-or-later",
    "require": {
        "php": ">=7.4"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.6",
        "yoast/phpunit-polyfills": "^2.0"
    },
    "autoload": {
        "psr-4": {
            "Jeager\\AbilitiesSuite\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Jeager\\AbilitiesSuite\\Tests\\": "tests/"
        }
    },
    "config": {
        "allow-plugins": {
            "dealerdirect/phpcodesniffer-composer-installer": true
        }
    }
}
```

> **Namespace choice:** `Jeager\AbilitiesSuite` — uses the brand vendor namespace. Discuss with J before finalizing.

### Step 2: Create `src/` directory structure

```
src/
├── Core/
│   ├── Registrar.php          ← moved from includes/class-ability-registrar.php
│   ├── LicenseManager.php     ← moved from includes/license-manager.php
│   ├── TierGate.php           ← moved from includes/tier-gate.php
│   ├── Permissions.php        ← moved from includes/permissions.php
│   └── Categories.php         ← moved from includes/ability-categories.php
├── Helpers/
│   ├── Pagination.php         ← extracted from helpers.php
│   ├── MenuTree.php           ← extracted from helpers.php (menu helpers)
│   ├── Validation.php         ← extracted from helpers.php (post validation, IP check)
│   └── ErrorFactory.php       ← extracted from helpers.php (wp_native_error)
└── Modules/
    ├── ContentModule.php
    ├── TaxonomyModule.php
    ├── PluginModule.php
    ├── MediaModule.php
    ├── UserModule.php
    ├── CommentModule.php
    ├── MenuModule.php
    ├── BlocksModule.php
    ├── PatternsModule.php
    ├── MetaModule.php
    ├── SettingsModule.php
    ├── SiteHealthModule.php
    ├── TransientsModule.php
    ├── CronModule.php
    ├── ThemesModule.php
    ├── RestDiscoveryModule.php
    ├── RewriteModule.php
    └── FilesystemModule.php
```

### Step 3: Add namespace to each class

Example for LicenseManager:

```php
<?php
namespace Jeager\AbilitiesSuite\Core;

defined( 'ABSPATH' ) || exit;

class LicenseManager {
    // ... exact same methods, no Abilities_For_AI_ prefix needed
}
```

### Step 4: Create backward-compatible function aliases

To avoid breaking the 18 module files (if not yet refactored), keep global function aliases in a new `includes/compat.php`:

```php
<?php
// Backward-compatible global function aliases.
// These can be removed once all modules use namespaced classes.

use Jeager\AbilitiesSuite\Helpers\Pagination;
use Jeager\AbilitiesSuite\Helpers\ErrorFactory;
use Jeager\AbilitiesSuite\Helpers\Validation;
use Jeager\AbilitiesSuite\Helpers\MenuTree;
use Jeager\AbilitiesSuite\Core\Permissions;

if ( ! function_exists( 'wp_native_pagination' ) ) {
    function wp_native_pagination( $input, $default = 20 ) {
        return Pagination::resolve( $input, $default );
    }
}

if ( ! function_exists( 'wp_native_pagination_schema' ) ) {
    function wp_native_pagination_schema() {
        return Pagination::schema();
    }
}

// ... etc for all global functions
```

### Step 5: Update main plugin file to use autoloader

```php
<?php
// ... plugin header unchanged ...

defined( 'ABSPATH' ) || exit;

define( 'ABILITIES_FOR_AI_VERSION', '3.7.0' );
define( 'ABILITIES_FOR_AI_PATH', plugin_dir_path( __FILE__ ) );
define( 'ABILITIES_FOR_AI_URL', plugin_dir_url( __FILE__ ) );

// PSR-4 autoloader.
if ( file_exists( ABILITIES_FOR_AI_PATH . 'vendor/autoload.php' ) ) {
    require_once ABILITIES_FOR_AI_PATH . 'vendor/autoload.php';
}

// Backward-compatible global function aliases.
require_once ABILITIES_FOR_AI_PATH . 'includes/compat.php';

// Load ability categories FIRST.
require_once ABILITIES_FOR_AI_PATH . 'includes/ability-categories.php';

// Load all ability modules.
// (These will gradually migrate to src/Modules/ and be autoloaded.)
$modules = glob( ABILITIES_FOR_AI_PATH . 'includes/*-abilities.php' );
foreach ( $modules as $module_file ) {
    require_once $module_file;
}

// Admin dashboard.
if ( is_admin() ) {
    require_once ABILITIES_FOR_AI_PATH . 'admin/dashboard.php';
}

// Activation/deactivation hooks unchanged.
```

### Step 6: Run `composer dump-autoload`

Generate the autoloader. The `vendor/` directory must be committed or built during deployment (decision for J).

## Deliverables

1. **New file:** `composer.json`
2. **New directory:** `src/` with `Core/`, `Helpers/`, `Modules/` subdirectories
3. **Moved + namespaced files:** `LicenseManager.php`, `TierGate.php`, `Permissions.php`
4. **Extracted + namespaced files:** `Pagination.php`, `MenuTree.php`, `Validation.php`, `ErrorFactory.php` from `helpers.php`
5. **New file:** `includes/compat.php` — backward-compatible function aliases
6. **Modified file:** `abilities-for-ai.php` — autoloader + glob-based module loading
7. **Generated:** `vendor/autoload.php` via `composer dump-autoload`

### Critical Constraints
- **PHP 7.4 minimum** — no typed properties, no union types, no named arguments
- **No functional changes** — all 111 abilities must register identically
- **Backward compat** — global functions (`wp_native_pagination`, etc.) must still work until all callers are migrated
- **`vendor/` strategy** — decide whether to commit vendor/ or use build step. WordPress.org plugins typically commit it.
- **Module files stay in `includes/`** until Plan 1 (Registration Abstraction) is also applied — then they can move to `src/Modules/`
- The old `includes/helpers.php` file should be kept (empty, just requiring compat.php) as a safety net until all callers are verified migrated

### Migration Order
This plan can be executed BEFORE or AFTER Plan 1. However:
- If done BEFORE Plan 1: module files stay procedural in `includes/`, only infrastructure moves to `src/`
- If done AFTER Plan 1: module files can be converted to namespaced Module classes in `src/Modules/` simultaneously

**Recommended: Execute Plan 1 first, then Plan 2.**

---

# Plan 3: Make Callbacks Testable

## Specification

### Problem
All 111 ability callbacks are anonymous closures defined inline within `wp_register_ability()` calls. This makes them:

1. **Untestable in isolation** — can't call a closure without registering the ability first
2. **Tightly coupled to WordPress** — closures call 20+ WordPress functions directly (`WP_Query`, `current_user_can()`, `get_post()`, `$wpdb`, etc.)
3. **Impossible to mock** — no dependency injection, no interfaces, no seams
4. **Copy-paste prone** — identical patterns (input normalization, pagination, error handling, response formatting) are repeated in every closure

### Current State: Zero Test Coverage
- No `tests/` directory
- No `phpunit.xml`
- No `composer.json` (covered in Plan 2)
- No CI/CD test workflow (`.github/workflows/` has only project automation)

### Closure Anatomy (common pattern across all 111)
```
1. Sanitize/normalize input parameters
2. Validate preconditions (post exists, user can edit, etc.)
3. Build query args from input
4. Execute WordPress API call (WP_Query, get_terms, $wpdb, etc.)
5. Loop through results, format each item
6. Return response array
```

Steps 1, 2, 5, 6 are nearly identical across abilities. Step 3 varies but follows patterns. Step 4 is the only truly unique part.

## Strategy

### Phase 1: Test Infrastructure Setup

#### Step 1: Add PHPUnit via Composer (depends on Plan 2)

If Plan 2 is done first, `composer.json` already has PHPUnit. If not, create a minimal one:

```json
{
    "require-dev": {
        "phpunit/phpunit": "^9.6",
        "yoast/phpunit-polyfills": "^2.0"
    }
}
```

#### Step 2: Create test directory and config

```
tests/
├── bootstrap.php           ← WordPress test bootstrap
├── phpunit.xml.dist         ← PHPUnit configuration
└── Unit/
    └── .gitkeep
```

**`phpunit.xml.dist`:**
```xml
<?xml version="1.0"?>
<phpunit
    bootstrap="tests/bootstrap.php"
    backupGlobals="false"
    colors="true"
    testdox="true"
>
    <testsuites>
        <testsuite name="unit">
            <directory suffix="Test.php">tests/Unit</directory>
        </testsuite>
        <testsuite name="integration">
            <directory suffix="Test.php">tests/Integration</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

**`tests/bootstrap.php`:**
```php
<?php
/**
 * PHPUnit bootstrap for WordPress integration tests.
 *
 * Uses the WordPress test library (wp-phpunit).
 */

// Load Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Point to WordPress test library.
$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';
require_once $_tests_dir . '/includes/functions.php';

// Load the plugin.
tests_add_filter( 'muplugins_loaded', function() {
    require dirname( __DIR__ ) . '/abilities-for-ai.php';
});

require $_tests_dir . '/includes/bootstrap.php';
```

#### Step 3: Add GitHub Actions CI workflow

Create `.github/workflows/tests.yml`:

```yaml
name: Tests
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: ['7.4', '8.0', '8.1', '8.2']
        wp: ['6.9']
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: wordpress_tests
        ports: ['3306:3306']
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
      - run: composer install --no-progress
      - name: Install WP Tests
        run: bash bin/install-wp-tests.sh wordpress_tests root root 127.0.0.1 ${{ matrix.wp }}
      - run: vendor/bin/phpunit
```

### Phase 2: Extract Callbacks to Named Functions

**This is the minimum viable testability refactor.** No classes needed — just move closures to named functions that can be called independently.

#### Pattern: Before
```php
wp_register_ability( 'cron/list-events', array(
    // ...
    'execute_callback' => function( $params ) {
        $crons = _get_cron_array();
        // ... 25 lines of logic ...
        return array( 'events' => $events, 'total' => count( $events ) );
    },
));
```

#### Pattern: After
```php
wp_register_ability( 'cron/list-events', array(
    // ...
    'execute_callback' => 'abilities_for_ai_execute_cron_list_events',
));

// --- Testable named function ---
function abilities_for_ai_execute_cron_list_events( $params ) {
    $crons = _get_cron_array();
    // ... 25 lines of logic (UNCHANGED) ...
    return array( 'events' => $events, 'total' => count( $events ) );
}
```

#### Naming Convention
```
abilities_for_ai_execute_{module}_{ability_slug_underscored}
```

Examples:
- `cron/list-events` → `abilities_for_ai_execute_cron_list_events`
- `content/get-snapshot` → `abilities_for_ai_execute_content_get_snapshot`
- `menu/add-menu-item` → `abilities_for_ai_execute_menu_add_menu_item`

#### Migration Order (same as Plan 1)

**Batch 1 — Read-only (easiest to test, no side effects):**
1. `cron-abilities.php` (3 functions)
2. `site-health-abilities.php` (4 functions)
3. `themes-abilities.php` (5 functions)
4. `rest-discovery-abilities.php` (4 functions)

**Batch 2 — Read+Write:**
5-10. Settings, patterns, meta, transients, blocks, rewrite (39 functions total)

**Batch 3 — Full CRUD:**
11-18. Comment, user, media, plugin, taxonomy, menu, content, filesystem (56 functions total)

### Phase 3: Extract Pure Transformation Functions

Many callbacks contain pure logic that can be tested WITHOUT WordPress:

#### Example: Menu tree building (already in helpers.php — good pattern)

#### Example: Cron event formatting (currently inline)
```php
// EXTRACT from cron/list-events callback:
function abilities_for_ai_format_cron_event( $hook, $timestamp, $data ) {
    return array(
        'hook'      => $hook,
        'next_run'  => date( 'Y-m-d H:i:s', $timestamp ),
        'timestamp' => $timestamp,
        'schedule'  => $data['schedule'] ?? false,
        'interval'  => $data['interval'] ?? null,
        'args'      => '[stripped for security]',
    );
}
```

This function is pure PHP — no WordPress dependencies — and can be unit-tested without any bootstrap.

#### Example: Settings allowlist check (currently inline in settings/update)
```php
// EXTRACT:
function abilities_for_ai_is_writable_setting( $name, $writable_settings ) {
    return in_array( $name, $writable_settings, true );
}
```

#### Candidates for extraction across all modules:
- **Response formatters:** Functions that take a WP_Post/WP_User/WP_Term and return a flat array
- **Input normalizers:** Functions that sanitize and default input parameters
- **Validators:** Functions that check allowlists, ranges, formats
- **Tree builders:** Already extracted for menus, but similar patterns exist in taxonomy term trees

### Phase 4: Write Tests

#### Integration Tests (require WordPress)

```php
class CronAbilitiesTest extends WP_UnitTestCase {

    public function test_list_events_returns_array() {
        $this->set_current_user_as_admin();
        $result = abilities_for_ai_execute_cron_list_events( array() );
        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'events', $result );
        $this->assertArrayHasKey( 'total', $result );
    }

    public function test_list_events_filters_by_search() {
        $result = abilities_for_ai_execute_cron_list_events( array( 'search' => 'wp_cron' ) );
        foreach ( $result['events'] as $event ) {
            $this->assertStringContainsString( 'wp_cron', $event['hook'] );
        }
    }

    public function test_get_event_returns_error_for_missing_hook() {
        $result = abilities_for_ai_execute_cron_get_event( array( 'hook' => 'nonexistent_hook_xyz' ) );
        $this->assertInstanceOf( WP_Error::class, $result );
    }
}
```

#### Unit Tests (no WordPress needed)

```php
class CronFormattersTest extends TestCase {

    public function test_format_cron_event() {
        $result = abilities_for_ai_format_cron_event(
            'my_hook',
            1709856000,
            array( 'schedule' => 'hourly', 'interval' => 3600 )
        );
        $this->assertEquals( 'my_hook', $result['hook'] );
        $this->assertEquals( 'hourly', $result['schedule'] );
        $this->assertEquals( 3600, $result['interval'] );
        $this->assertEquals( '[stripped for security]', $result['args'] );
    }
}
```

#### Test Coverage Targets

| Phase | Test Type | Tests | Coverage |
|-------|-----------|-------|----------|
| Phase 2 | Integration (WP) | ~111 (1 per ability) | Basic smoke tests — does each ability return expected shape? |
| Phase 3 | Unit (pure PHP) | ~50 (formatters, validators) | Pure logic extracted from closures |
| Phase 4 | Integration (write) | ~44 (Pro abilities) | Create/update/delete operations with assertions |
| Total | | ~205 | Every ability + extracted helpers |

## Deliverables

1. **New file:** `composer.json` (or updated if Plan 2 done first)
2. **New file:** `phpunit.xml.dist`
3. **New file:** `tests/bootstrap.php`
4. **New file:** `.github/workflows/tests.yml`
5. **New file:** `bin/install-wp-tests.sh` (standard WP test installer script)
6. **Modified files:** All 18 `*-abilities.php` — closures extracted to named functions
7. **New directory:** `tests/Integration/` — one test file per module (18 files)
8. **New directory:** `tests/Unit/` — tests for pure extracted functions
9. **Target:** 205+ passing tests covering all 111 abilities

### Critical Constraints
- **Named functions must accept the same `$params` array** — don't change the interface
- **Pro gate wrapping stays at registration** — don't move it into the named function
- **`use (...)` closures:** Some callbacks use `function() use ($settings_groups)` to capture local variables. When extracting to named functions, these become function parameters. Example:
  ```php
  // BEFORE:
  'execute_callback' => function( $params ) use ( $settings_groups ) { ... }

  // AFTER:
  function abilities_for_ai_execute_settings_list( $params ) {
      $settings_groups = abilities_for_ai_settings_groups(); // Extract to helper
      // ...
  }
  ```
- **Don't refactor business logic** — just move it out of closures. Fix bugs separately.
- **WordPress test library** requires a running MySQL instance — CI needs a service container
- **PHP 7.4 compat** — no `match()`, no named args, no union types in tests

### Dependency on Other Plans
- **Plan 2 (Autoloading):** Provides `composer.json` and autoloader — saves duplicating Composer setup
- **Plan 1 (Registration):** If done first, callbacks are already referenced by name in the Registrar config — extraction is simpler
- **Can be done standalone** if needed — just add a minimal `composer.json` for PHPUnit

---

# Plan 4: Centralize Shared Schemas

## Specification

### Problem
Identical JSON Schema fragments are copy-pasted across all 18 module files:

1. **Pagination schema** — `page` + `per_page` properties appear in 15+ abilities with 3 different formats:
   - Inline with varying descriptions, defaults, and min/max values
   - Via `wp_native_pagination_schema()` helper (only used in ~3 abilities)
   - With non-standard names (`posts_per_page`, `paged` in content module)

2. **Meta annotations** — The exact string `'show_in_rest' => true, 'mcp' => array( 'public' => true, 'type' => 'tool' )` appears 111 times

3. **Common input properties** — `post_id` (integer, required), `post_type` (string), `search`/`s` (string) appear in many abilities with slightly different descriptions

4. **Common output shapes** — List responses always have an items array + total count + pages, but field names vary (`posts`/`media`/`events`/`schedules`, `total`/`count`, `pages`/`page`)

### Impact
- **Schema inconsistency breaks MCP clients** — clients that expect `per_page` get `posts_per_page` from the content module
- **Maintenance burden** — changing pagination max from 100 to 200 requires editing 15+ files
- **Output shape variance** — MCP clients can't predict response format

## Strategy

### Step 1: Create `includes/schemas.php`

A single file containing all reusable schema fragments as functions:

```php
<?php
defined( 'ABSPATH' ) || exit;

/**
 * Centralized schema definitions for Abilities for AI.
 *
 * All reusable JSON Schema fragments live here.
 * Module files reference these instead of defining inline.
 */

// ============================================================
// INPUT SCHEMA FRAGMENTS
// ============================================================

/**
 * Standard pagination input properties.
 * Use with array_merge() in input_schema properties.
 *
 * @param int $default_per_page Default items per page.
 * @return array Schema properties for page + per_page.
 */
function abilities_for_ai_schema_pagination( $default_per_page = 20 ) {
    return array(
        'page' => array(
            'type'        => 'integer',
            'description' => 'Page number.',
            'default'     => 1,
            'minimum'     => 1,
        ),
        'per_page' => array(
            'type'        => 'integer',
            'description' => 'Items per page (max 100).',
            'default'     => $default_per_page,
            'minimum'     => 1,
            'maximum'     => 100,
        ),
    );
}

/**
 * Post ID input property (required).
 */
function abilities_for_ai_schema_post_id( $description = 'Post ID.' ) {
    return array(
        'post_id' => array(
            'type'        => 'integer',
            'description' => $description,
        ),
    );
}

/**
 * Post type input property (optional, defaults to 'post').
 */
function abilities_for_ai_schema_post_type() {
    return array(
        'post_type' => array(
            'type'        => 'string',
            'description' => 'Post type slug.',
            'default'     => 'post',
        ),
    );
}

/**
 * Search/filter input property.
 */
function abilities_for_ai_schema_search( $description = 'Search keyword.' ) {
    return array(
        'search' => array(
            'type'        => 'string',
            'description' => $description,
        ),
    );
}

/**
 * Sort order input properties.
 */
function abilities_for_ai_schema_orderby( $default_orderby = 'date', $default_order = 'DESC' ) {
    return array(
        'orderby' => array(
            'type'        => 'string',
            'description' => 'Sort field.',
            'default'     => $default_orderby,
        ),
        'order' => array(
            'type'        => 'string',
            'description' => 'Sort direction: ASC or DESC.',
            'default'     => $default_order,
            'enum'        => array( 'ASC', 'DESC' ),
        ),
    );
}

// ============================================================
// OUTPUT SCHEMA BUILDERS
// ============================================================

/**
 * Standard paginated list output schema.
 *
 * @param string $items_key  Key name for the items array (e.g., 'posts', 'media').
 * @param array  $item_props Properties of each item in the array (optional).
 * @return array Output schema.
 */
function abilities_for_ai_schema_list_output( $items_key, $item_props = array() ) {
    $items_schema = array( 'type' => 'object' );
    if ( ! empty( $item_props ) ) {
        $items_schema['properties'] = $item_props;
    }

    return array(
        'type'       => 'object',
        'properties' => array(
            $items_key => array(
                'type'  => 'array',
                'items' => $items_schema,
            ),
            'total' => array(
                'type'        => 'integer',
                'description' => 'Total items matching query.',
            ),
            'pages' => array(
                'type'        => 'integer',
                'description' => 'Total pages.',
            ),
        ),
    );
}

/**
 * Standard single-item output schema.
 *
 * @param array $properties Item properties.
 * @return array Output schema.
 */
function abilities_for_ai_schema_item_output( $properties ) {
    return array(
        'type'       => 'object',
        'properties' => $properties,
    );
}

/**
 * Standard success output for write/delete operations.
 */
function abilities_for_ai_schema_success_output( $extra_props = array() ) {
    return array(
        'type'       => 'object',
        'properties' => array_merge(
            array(
                'success' => array( 'type' => 'boolean' ),
            ),
            $extra_props
        ),
    );
}
```

### Step 2: Replace the existing `wp_native_pagination_schema()`

In `helpers.php`, deprecate the old function and alias to new:

```php
/**
 * @deprecated Use abilities_for_ai_schema_pagination() instead.
 */
function wp_native_pagination_schema() {
    return abilities_for_ai_schema_pagination();
}
```

### Step 3: Migrate content module's non-standard field names

The content module uses `posts_per_page` and `paged` instead of `per_page` and `page`. This is a **breaking schema change** for MCP clients, so it requires a migration approach:

**Option A (recommended): Accept BOTH names in the callback**
```php
// In content/list callback:
$per_page = $input['per_page'] ?? $input['posts_per_page'] ?? 10;
$page     = $input['page'] ?? $input['paged'] ?? 1;
```

And update the input_schema to use the standard names (`per_page`, `page`) while the callback accepts legacy names for backward compat.

**Option B: Keep content module's names** — only if MCP clients are already trained on `posts_per_page`.

### Step 4: Standardize output field names

Current inconsistency:
| Module | Items Key | Count Key | Pages Key |
|--------|-----------|-----------|-----------|
| content | `posts` | `total` | `pages` |
| media | `media` | `total` | `pages` |
| users | `users` | `total` | `pages` |
| cron | `events` | `total` | — |
| cron/schedules | `schedules` | `count` | — |
| transients | `transients` | `total` | `pages` |

**Decision needed:** Keep module-specific item keys (`posts`, `media`, etc.) — they're semantically correct. But standardize the count/pages keys to ALWAYS use `total` + `pages`.

Replace `'count' => count( $result )` with `'total' => count( $result )` in:
- `cron/list-schedules` (line 88: `'count'` → `'total'`)
- Any other abilities using `'count'` instead of `'total'`

### Step 5: Add output_schema to abilities that are missing it

Currently many abilities have no `output_schema`. Add them using the schema builders:

```php
// cron/list-events:
'output_schema' => abilities_for_ai_schema_list_output( 'events' ),

// settings/get:
'output_schema' => abilities_for_ai_schema_item_output( array(
    'option_name' => array( 'type' => 'string' ),
    'value'       => array( 'type' => 'string' ),
)),
```

### Step 6: Migrate all 18 modules to use centralized schemas

**Example migration (cron-abilities.php):**

Before:
```php
'input_schema' => array(
    'type'       => 'object',
    'properties' => array(
        'search' => array( 'type' => 'string', 'description' => 'Filter by hook name' ),
    ),
),
```

After:
```php
'input_schema' => array(
    'type'       => 'object',
    'properties' => abilities_for_ai_schema_search( 'Filter by hook name' ),
),
```

**Example migration (user-abilities.php list):**

Before:
```php
'input_schema' => array(
    'type'       => 'object',
    'properties' => array(
        'per_page' => array( 'type' => 'integer', 'description' => 'Users per page', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ),
        'page'     => array( 'type' => 'integer', 'description' => 'Page number', 'default' => 1, 'minimum' => 1 ),
        'role'     => array( 'type' => 'string', 'description' => 'Filter by role' ),
        'search'   => array( 'type' => 'string', 'description' => 'Search users' ),
        'orderby'  => array( 'type' => 'string', 'description' => 'Sort field', 'default' => 'registered' ),
        'order'    => array( 'type' => 'string', 'description' => 'Sort direction', 'default' => 'DESC' ),
    ),
),
```

After:
```php
'input_schema' => array(
    'type'       => 'object',
    'properties' => array_merge(
        abilities_for_ai_schema_pagination(),
        abilities_for_ai_schema_search( 'Search users' ),
        abilities_for_ai_schema_orderby( 'registered' ),
        array(
            'role' => array( 'type' => 'string', 'description' => 'Filter by role' ),
        )
    ),
),
```

### Step 7: Update audit-schema.php

Add validation rule: if an ability has pagination properties, they must use the standard names (`page`, `per_page`) — not `paged`, `posts_per_page`.

## Deliverables

1. **New file:** `includes/schemas.php` — all shared schema fragment functions
2. **Modified file:** `abilities-for-ai.php` — add `require_once` for schemas.php (before modules)
3. **Modified file:** `includes/helpers.php` — deprecate `wp_native_pagination_schema()`, alias to new
4. **Modified files:** All 18 `*-abilities.php` — replace inline schemas with function calls
5. **Modified file:** Content module — standardize `posts_per_page`/`paged` to `per_page`/`page` (with backward compat in callback)
6. **Modified files:** All abilities missing `output_schema` — add via schema builders
7. **Standardized:** All list outputs use `total` + `pages` consistently (fix `count` → `total` where inconsistent)
8. **Modified file:** `audit-schema.php` — add pagination naming validation rule

### Critical Constraints
- **Content module backward compat** — MCP clients may send `posts_per_page`. The callback must accept both old and new names.
- **Item key names stay module-specific** — `posts`, `media`, `users`, etc. are semantically correct. Don't flatten to generic `items`.
- **Schema functions must return valid JSON Schema** — test with `audit-schema.php` after every migration
- **`array_merge()` order matters** — module-specific properties should come AFTER shared fragments to allow overrides
- **No `minimum`/`maximum` in pagination helper breaks backward compat for content module** — the content module currently has `maximum: 100` but uses `posts_per_page` with WP_Query which enforces its own max. Standardize to `maximum: 100` in the helper.

### Dependency on Other Plans
- **Independent of all other plans** — can be executed first
- **Complements Plan 1** — if Registrar exists, schemas are passed through config and boilerplate is even smaller
- **Helps Plan 3** — standardized output shapes make test assertions simpler

---

# Execution Order Recommendation

```
Plan 4 (Schemas)     →  No dependencies, smallest blast radius, immediate quality improvement
Plan 1 (Registrar)   →  Depends on nothing, but benefits from Plan 4's schemas
Plan 2 (Autoloading) →  Structural, best done after Plan 1 so module classes can move to src/
Plan 3 (Testability) →  Benefits from ALL other plans, should be last
```

**Optimal parallel tracks:**
- Track A: Plan 4 → Plan 1 (schema + registration refactor)
- Track B: Plan 2 (can start independently for infrastructure classes)
- Final: Plan 3 (once A + B complete, test everything)

Each plan is designed to be **independently executable and independently verifiable** — you can run `wp eval-file audit-schema.php` and `wp eval 'echo count(wp_get_abilities());'` after each plan to confirm nothing broke.
