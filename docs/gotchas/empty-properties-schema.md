# Gotcha: Empty Properties Schema

**Severity:** Critical — ability fails silently

## The Problem

For abilities that take no arguments, you might write:

```php
// WRONG — breaks validation silently
'input_schema' => array(
    'type'       => 'object',
    'properties' => (object) array(),
),
```

This causes the WordPress schema validator to reject the ability. No error message. The ability just doesn't work when called.

## The Fix

**Omit the `input_schema` key entirely** for no-arg abilities:

```php
// CORRECT — simply don't include 'input_schema' in the registration array
```

Or if you must have a schema:

```php
// CORRECT — type only, no properties key
'input_schema' => array(
    'type' => 'object',
),
```

## Why This Happens

WordPress core's JSON Schema validation treats `(object) array()` differently from "no properties key". The empty object fails type checking internally. This is a WordPress core behavior, not our code.

## Detection

If a no-arg ability returns validation errors or "ability not found" when called through the MCP bridge, check for `(object) array()` in the registration.

Grep for it:
```bash
grep -rn "(object) array()" includes/
```
