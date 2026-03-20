# Gotcha: Silent Validation Failures

**Severity:** Critical — abilities disappear without any error

## The Pattern

WordPress has **two silent validation paths** that drop abilities without any error:

### 1. Unregistered Category

If the `category` slug in `wp_register_ability()` doesn't match a registered category, the ability is silently discarded.

**Symptom:** Ability doesn't appear in `discover-abilities` output. No PHP error. No log entry.

**Fix:** Verify the category slug is registered via `wp_register_ability_category()` before the ability registers. Exact string match required.

### 2. Invalid Schema

If `input_schema` contains invalid JSON Schema (e.g., `(object) array()` for properties, missing `items` on array types), the ability registers but fails validation when called.

**Symptom:** Ability appears in tool list but returns validation error on call. Error message may be generic.

**Fix:** See [Empty Properties Schema](empty-properties-schema.md) for the properties case. For arrays, always include `items`:

```php
'my_array_param' => array(
    'type'  => 'array',
    'items' => array( 'type' => 'string' ),  // REQUIRED
),
```

## Debugging Checklist

When an ability isn't working:

1. **Not in discover-abilities?** → Category mismatch or wrong hook
2. **In discover-abilities but fails on call?** → Schema validation issue
3. **Returns 403 "ability_disabled"?** → Permission gating — check Settings → MCP Abilities

## The Broader Lesson

WordPress's Abilities API prefers silent failure over throwing errors. This is a design choice — WordPress traditionally avoids fatal errors. But it means **debugging requires checking every layer**: hook, category, schema, permissions.
