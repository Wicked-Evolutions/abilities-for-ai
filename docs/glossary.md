# Glossary

Key terms used across the Abilities for AI ecosystem.

---

## Ability

A registered WordPress capability that AI agents can discover and execute through the Abilities API.

### What it is

An ability is a named function with:
- A **name** — 2-segment slug: `namespace/action` (e.g., `content/list`, `fluent-crm/list-contacts`)
- A **label** — human-readable name for admin UI
- A **description** — what it does (shown as tool description in MCP)
- A **category** — grouping (must be registered first via `wp_register_ability_category()`)
- An **execute_callback** — PHP function that does the work
- A **permission_callback** — checks WordPress capabilities before execution
- An **input_schema** — JSON Schema for parameter validation
- **Annotations** — `readonly`, `destructive`, `idempotent` (control REST method mapping)

### Registration

Abilities register via `wp_register_ability()` on the `wp_abilities_api_init` hook. In practice, most abilities use the [Registrar](abilities-api-architecture.md) convenience layer (`$reg->read()`, `$reg->write()`, `$reg->delete()`).

### Execution

Abilities execute via `wp_get_ability( $name )` → `$ability->execute( $input )`. Input is validated against the schema before the callback fires. There is no `wp_execute_ability()` function.

### Discovery

AI agents discover available abilities via:
- MCP `tools/list` call (through the [Abilities MCP](https://github.com/Wicked-Evolutions/abilities-mcp) bridge)
- REST endpoint `/wp-json/wp-abilities/v1/`
- The [Abilities MCP Adapter](https://github.com/Wicked-Evolutions/abilities-mcp-adapter) translates abilities into MCP tool definitions automatically

### Not to be confused with

| Term | What it is |
|------|-----------|
| **WordPress Capability** | A user permission flag (`manage_options`, `edit_posts`). Abilities *check* capabilities, they aren't capabilities themselves. |
| **MCP Tool** | The MCP protocol's name for callable functions. Abilities *become* MCP tools through the Adapter. |
| **SKILL** | A documented multi-step procedure. An ability is a single atomic operation. |

---

## Category

A grouping for abilities. Registered via `wp_register_ability_category()`. Every ability must belong to a registered category — WordPress silently drops abilities whose category doesn't exist.

---

## Annotation

Metadata on an ability that controls behavior:

| Annotation | Effect |
|-----------|--------|
| `readonly: true` | REST method → GET |
| `destructive: true` | REST method → DELETE (if also `idempotent`) |
| `idempotent: true` | Safe to retry |
| `permission` | Read, write, or delete — flows into MCP tool annotations |

---

## Registrar

The `Abilities_For_AI_Registrar` class — a convenience layer over `wp_register_ability()` that auto-injects annotations, permission callbacks, tier gating, and REST visibility. See [Abilities API Architecture](abilities-api-architecture.md) for details.

---

## Tier

Abilities are either **free** (read operations) or **pro** (write/delete operations). The Registrar auto-assigns tiers based on which method is called. Pro-gated abilities require an active license key.
