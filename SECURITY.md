# Security Policy

## Reporting a Vulnerability

If you discover a security vulnerability in Abilities for AI, **do not open a public issue.**

Instead, please use [GitHub's private vulnerability reporting](https://github.com/Wicked-Evolutions/abilities-for-ai/security/advisories/new) to report it directly.

Include:
- Description of the vulnerability
- Steps to reproduce
- Potential impact
- Suggested fix (if you have one)

We review private vulnerability reports as bandwidth allows. We do not commit to specific response or fix timelines — this is a small team, and timing depends on severity, complexity, and what else is in flight. We will respond when we have something useful to say. Critical issues are prioritized.

## Scope

This policy covers:

### Ability registration & execution
- Ability handlers across all modules (content, blocks, meta, settings, cron, themes, patterns, site health, REST discovery, menus, filesystem, knowledge, users, revisions, multisite)
- Per-ability permission enforcement (WordPress capabilities + per-module Read/Write/Delete toggles + Pro license tier)
- Filesystem safety (path traversal, ABSPATH containment, extension allowlist, `DISALLOW_FILE_MODS` / `DISALLOW_FILE_EDIT` honored)
- Input validation, schema enforcement, output sanitization

### Knowledge Layer
- `kl_documents`, `kl_observations`, `kl_sessions`, `kl_revisions`, `kl_tags`, `kl_taggables`, `kl_activity`, `kl_boundary` schemas
- REST routes under `abilities-kl/v1/*` (all `manage_options`-gated)
- Boundary event writer sanitization (metadata-only allowlist; no raw API keys, tokens, or response bodies)

### Third-party plugin suites
- Astra, Spectra, Presto Player, SureCart suite implementations register through the same Abilities API contract; vulnerabilities in their handlers are in scope for this repo

For vulnerabilities in the MCP transport, response redaction filter, or rate limiter, use the relevant repository:
- [abilities-mcp-adapter](https://github.com/Wicked-Evolutions/abilities-mcp-adapter/security/advisories/new) — WordPress-side MCP protocol layer
- [abilities-mcp](https://github.com/Wicked-Evolutions/abilities-mcp/security/advisories/new) — Node bridge

## Out of scope

- WordPress core security — report to WordPress Security Team
- Third-party plugins that register their own abilities directly (i.e. not via this plugin's suite handlers) — report to those plugin authors
- Theme-level vulnerabilities

## Supported Versions

We support the latest released version. Older versions do not receive security patches — please update.
