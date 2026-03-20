# Register an Ability

Step-by-step guide for registering a new WordPress ability using the Registrar.

## Prerequisites

- Ability category already registered (see [Register a Category](register-a-category.md))
- Code runs inside a plugin loaded before `wp_abilities_api_init`

## The Pattern (Registrar)

The Registrar handles annotations, permission gating, tier assignment, and REST visibility automatically.

```php
add_action( 'wp_abilities_api_init', function() {
    $reg = new Abilities_For_AI_Registrar( 'my-category', 'edit_posts' );

    $reg->read( 'my-category/list-things', array(
        'label'        => 'List Things',
        'description'  => 'One sentence: what this does and returns.',
        'input_schema' => array(
            'type'       => 'object',
            'properties' => array(
                'status' => array(
                    'type'        => 'string',
                    'description' => 'Filter by status.',
                    'enum'        => array( 'all', 'active', 'inactive' ),
                    'default'     => 'all',
                ),
                'search' => array(
                    'type'        => 'string',
                    'description' => 'Search by name (partial match).',
                ),
            ),
        ),
        'output_schema' => abilities_for_ai_schema_collection_output( 'things', array(
            'id'     => array( 'type' => 'integer' ),
            'name'   => array( 'type' => 'string' ),
            'status' => array( 'type' => 'string' ),
        ) ),
        'callback' => function( $input ) {
            $results = get_things( $input['status'] ?? 'all' );

            return array(
                'things' => $results,
                'total'  => count( $results ),
            );
        },
    ) );
} );
```

**What the Registrar does for you:**
- `$reg->read()` → `readonly: true`, `destructive: false`, `idempotent: true`, `tier: free`
- `$reg->write()` → `readonly: false`, `destructive: false`, `idempotent: true`, `tier: pro`
- `$reg->delete()` → `readonly: false`, `destructive: true`, `idempotent: true`, `tier: pro`
- Wraps callback with permission gate (`abilities_for_ai_ability_enabled()`)
- Wraps pro-tier callbacks with license gate (`abilities_for_ai_pro_gate()`)
- Sets `permission_callback` to `current_user_can( $capability )`
- Sets `show_in_rest: true` and `mcp.public: true`

## Config Keys

| Key | Required | Notes |
|-----|----------|-------|
| `label` | Yes | Human-readable name shown in admin UI |
| `description` | Yes | One sentence — shown as tool description in MCP |
| `callback` | Yes | Function receiving `$input` array, returning result array |
| `input_schema` | No | JSON Schema (draft-04) for parameters. Omit entirely for no-arg abilities |
| `output_schema` | No | JSON Schema for the return value. Use `abilities_for_ai_schema_collection_output()` for list endpoints |
| `category` | No | Defaults to the Registrar's `$module`. Override if ability belongs to a different category |
| `tier` | No | `'free'` or `'pro'`. Defaults: read=free, write/delete=pro |
| `capability` | No | Override the Registrar's default WordPress capability |
| `annotations` | No | Partial override of auto-generated annotations array |

## Direct Registration (WordPress Core API)

For abilities outside the Registrar pattern:

```php
add_action( 'wp_abilities_api_init', function() {
    wp_register_ability( 'my-namespace/my-action', array(
        'label'               => 'My Action',
        'description'         => 'What this ability does.',
        'category'            => 'my-category',
        'execute_callback'    => 'my_callback_function',
        'permission_callback' => function() {
            return current_user_can( 'edit_posts' );
        },
        'input_schema'        => array( /* ... */ ),
        'meta' => array(
            'show_in_rest' => true,
            'annotations'  => array(
                'readonly'    => true,
                'destructive' => false,
                'idempotent'  => true,
            ),
        ),
    ) );
} );
```

Note: the callback key is `execute_callback` when calling `wp_register_ability()` directly. The Registrar accepts `callback` and maps it internally.

## Annotation Values (Registrar Auto-Sets)

| Method | readonly | destructive | idempotent | tier |
|--------|----------|-------------|------------|------|
| `$reg->read()` | `true` | `false` | `true` | free |
| `$reg->write()` | `false` | `false` | `true` | pro |
| `$reg->delete()` | `false` | `true` | `true` | pro |

Override via `'annotations' => array('idempotent' => false)` in the config if needed.

## Checklist

- [ ] Name is 2-segment: `namespace/action` (lowercase, hyphens only)
- [ ] Category slug matches a registered category exactly
- [ ] `input_schema` uses valid JSON Schema draft-04
- [ ] No-arg abilities: **omit** `input_schema` entirely (see [Empty Properties Schema gotcha](../gotchas/empty-properties-schema.md))
- [ ] Array types include `items`: `'items' => array('type' => 'string')`
- [ ] `label` and `description` are both set
- [ ] Callback returns an array (not WP_Error for success cases)
- [ ] Hook is `wp_abilities_api_init` — no other hook works

## Common Mistakes

1. **Wrong hook** — using `init` or `plugins_loaded` instead of `wp_abilities_api_init`
2. **Unregistered category** — ability [silently dropped](../gotchas/silent-validation-failures.md)
3. **Empty properties object** — `(object) array()` [breaks validation](../gotchas/empty-properties-schema.md)
4. **Missing `items` on arrays** — validator rejects the schema silently
5. **Using `callback` with direct registration** — the WP core key is `execute_callback`
6. **Forgetting `label`** — required by the admin UI
