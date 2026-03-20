# Register a Category

How to register an ability category. Must happen before any abilities that reference it.

## Why Categories Matter

Every ability belongs to a category. If the category isn't registered when the ability registers, WordPress silently drops the ability. No error. No log. The ability just doesn't appear.

## The Pattern

Categories register on `wp_abilities_api_categories_init` — a **different hook** from abilities (`wp_abilities_api_init`). WordPress runs the categories hook first.

```php
add_action( 'wp_abilities_api_categories_init', function() {

    wp_register_ability_category( 'my-category', array(
        'label'       => __( 'My Category', 'my-plugin' ),
        'description' => __( 'Human-readable description of this group.', 'my-plugin' ),
    ) );

} );
```

Then abilities register on the later hook:

```php
add_action( 'wp_abilities_api_init', function() {
    $reg = new Abilities_For_AI_Registrar( 'my-category', 'edit_posts' );
    $reg->read( 'my-category/list-things', array( /* ... */ ) );
} );
```

## Conditional Categories (Third-Party Plugins)

Categories for third-party plugin integrations should only register when the target plugin is active:

```php
add_action( 'wp_abilities_api_categories_init', function() {
    if ( class_exists( 'PrestoPlayer\Plugin' ) ) {
        wp_register_ability_category( 'presto-player', array(
            'label'       => __( 'Presto Player', 'abilities-for-ai' ),
            'description' => __( 'Video and media player abilities.', 'abilities-for-ai' ),
        ) );
    }
} );
```

Common guards:

| Category | Guard |
|----------|-------|
| `presto-player` | `class_exists( 'PrestoPlayer\Plugin' )` |
| `spectra` | `class_exists( 'UAGB_Loader' )` |
| `surecart` | `defined( 'SURECART_PLUGIN_FILE' )` |
| `astra` | `defined( 'ASTRA_THEME_VERSION' )` |

## Naming Convention

- **Core WordPress:** short, descriptive — `content`, `media`, `cache`
- **Third-party:** plugin brand name — `presto-player`, `surecart`, `astra`, `spectra`
- **Internal:** descriptive — `knowledge`

## Checklist

- [ ] Hook is `wp_abilities_api_categories_init` (not `wp_abilities_api_init`)
- [ ] Category slug is lowercase with hyphens only
- [ ] `label` and `description` are both set
- [ ] Third-party categories are gated with `class_exists()` or `defined()`
- [ ] Category registers before any abilities that reference it
