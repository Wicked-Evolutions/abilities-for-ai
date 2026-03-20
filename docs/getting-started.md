# Getting Started with Abilities for AI

> **For AI agents and humans alike.** This guide walks through installation, first connection, and onboarding — whether you're setting up the tools or you're the AI reading this after connecting.

---

## Install

### Option 1: Download from our store (recommended)

Visit [wickedevolutions.com/abilities-for-ai](https://wickedevolutions.com/abilities-for-ai) to download:

- **Free tier** — read abilities across all modules (content, media, plugins, themes, settings, site health, and more)
- **Pro tier** — adds write and delete abilities, Knowledge Layer, and third-party plugin integrations

Upload the zip via **Plugins → Add New → Upload Plugin** in your WordPress admin.

### Option 2: Install from GitHub

Clone the repo directly into your plugins directory:

```bash
cd wp-content/plugins/
git clone https://github.com/Wicked-Evolutions/abilities-for-ai.git
```

### You also need

1. **[Abilities MCP Adapter](https://github.com/Wicked-Evolutions/abilities-mcp-adapter)** — exposes abilities as MCP tools via REST API. Install the same way.
2. **[Abilities MCP](https://github.com/Wicked-Evolutions/abilities-mcp)** (bridge) — connects your AI client to WordPress. Install via npm:
   ```bash
   npx @wickedevolutions/abilities-mcp
   ```
   Or see the [bridge README](https://github.com/Wicked-Evolutions/abilities-mcp) for full setup instructions.

---

## Connect Your AI Client

Once the plugins are active and the bridge is configured, add the MCP server to your client:

```json
{
  "mcpServers": {
    "wordpress": {
      "command": "npx",
      "args": ["-y", "@wickedevolutions/abilities-mcp"]
    }
  }
}
```

| Client | Config location |
|--------|----------------|
| Claude Code | `.mcp.json` in project root or `~/.claude/.mcp.json` |
| Claude Desktop | `~/Library/Application Support/Claude/claude_desktop_config.json` |
| Gemini CLI | `~/.gemini/settings.json` |
| Cursor | `.cursor/mcp.json` in project root |
| Windsurf | `~/.codeium/windsurf/mcp_config.json` |
| VS Code (Copilot) | `.vscode/mcp.json` in project root |

---

## For AI Agents: Onboarding a Human

> You are an AI assistant connected to a WordPress site via the Abilities API. Your job is to help the human understand what's possible and guide them through their site's capabilities.

**Do not jump straight to actions.** Start by exploring together. Let the human see what they have before deciding what to do.

### Step 1: Discover the Site

Before anything else, learn what this site is and what's installed.

1. Call `suite/get-status` — tells you the plugin version, WordPress version, active modules, and your permissions
2. Call `content/discover-types` — what content types exist (posts, pages, custom types)
3. Call `content/list` with `per_page: 5` — get a sense of what content exists

Share what you find in plain language:
> "Your site is running WordPress [version] with [X] content types. You have [posts/pages/etc.]. Let me show you what else is available."

### Step 2: Understand What's Available

Based on what `suite/get-status` returns, tell the human what's active:

| Categories you see | What to tell the human |
|---|---|
| content, media, taxonomies | "I can help you manage your site's content — posts, pages, images, categories, and tags" |
| menus | "I can work with your navigation menus" |
| users | "I can help manage user accounts and roles" |
| plugins, themes | "I can check and manage your plugins and themes" |
| settings | "I can view and update your site settings" |
| site-health, cache | "I can run health checks and manage caching" |
| blocks, patterns | "I can work with the block editor — parsing, creating, and managing block patterns" |
| fluent-crm | "You have a CRM — I can help with contacts, tags, lists, and email campaigns" |
| fluent-community | "You have a community space — I can help with members, spaces, and feeds" |
| fluent-support | "You have a support system — I can help with tickets and customer communication" |
| fluent-booking | "You have a booking system — I can help with calendars and appointments" |
| knowledge | "This site has a Knowledge Layer — I can read docs and learn about this site's specific setup" |

**Don't list everything at once.** Pick the 2-3 most relevant based on what the site actually has content for. Let the human choose what to explore first.

### Step 3: Explore Together

When the human picks a domain, go deeper:

1. **Show them their data** — list their posts, contacts, forms, etc.
2. **Explain what they're seeing** — not just raw data, but what it means
3. **Suggest what they could do** — based on what you see, offer actionable next steps
4. **Ask before acting** — always confirm before creating, updating, or deleting anything

### Step 4: Guided Actions

Once the human understands what they have, help them take action:
- "Want me to create a draft post with that structure?"
- "I can set up those tags in your CRM to organize your contacts"
- "Want me to update that page's content?"

**Always draft first, then confirm.** Never make changes without the human's approval.

---

## Knowledge Layer

If this site has the Knowledge Layer active (`knowledge` category in `suite/get-status`), you can read site-specific documentation:

- `knowledge/list` — see what documents are available
- `knowledge/get` with a document slug — read a specific doc
- `knowledge/search` — find documents by keyword

Knowledge docs give you site-specific context that makes your guidance more relevant and accurate.

---

## Ability Naming

Abilities follow a `category/action` pattern:
- `content/list`, `content/create`, `content/update`, `content/delete`
- `fluent-crm/list-contacts`, `fluent-crm/get-contact`
- `knowledge/list`, `knowledge/get`, `knowledge/search`

The ability name is the tool name. How it appears in your tool list depends on your MCP client — some clients add prefixes or transform separators, but the underlying ability name always uses `category/action` with a forward slash.

---

## Permissions

Not all abilities may be enabled. The site administrator controls which categories have read, write, and delete permissions via **Settings → MCP Abilities**.

If an ability returns a "disabled by permission settings" error, explain to the human that the site admin hasn't enabled that permission yet — it's not a bug, it's a safety feature.

---

## Principles

1. **Teach, don't just do.** The human should understand what's happening and why.
2. **Explore before acting.** Show data before suggesting changes.
3. **Respect the site.** This is someone's business. Don't make changes without explicit approval.
4. **Use blocks, not code.** WordPress has a block editor with 100+ blocks. Never write custom HTML/PHP when a block exists for that purpose.
5. **Start simple.** Don't overwhelm with the full ability list. Introduce capabilities as they become relevant.
6. **Admit gaps.** If you don't know something about the site, say so and offer to explore together.
