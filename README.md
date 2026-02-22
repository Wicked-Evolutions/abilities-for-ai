# WordPress Abilities Suite

Comprehensive WordPress management abilities for the Model Context Protocol (MCP). Content, Taxonomies, Plugins, Media, Users, Comments, and Menus — 51 abilities across 7 categories.

## Requirements

- WordPress 6.9+ (Abilities API in core)
- PHP 7.4+
- [WP MCP Adapter](https://github.com/Influencentricity/wp-mcp-adapter) (for MCP integration)

## 51 Abilities

### Content Management (10)

| Ability | Type | Description |
|---------|------|-------------|
| `content/list` | read | List posts, pages, or custom post types with filtering |
| `content/get` | read | Get a specific post by ID |
| `content/create` | write | Create new content |
| `content/update` | write | Update existing content |
| `content/delete` | write | Delete content |
| `content/discover-types` | read | Discover all available post types |
| `content/find-by-url` | read | Find content by URL |
| `content/get-by-slug` | read | Get content by slug |
| `content/list-v2` | read | List content with extended filtering |
| `content/get-v2` | read | Get content with raw/rendered output |

### Taxonomy Management (8)

| Ability | Type | Description |
|---------|------|-------------|
| `taxonomies/discover` | read | List all available taxonomies |
| `taxonomies/list-terms` | read | List terms in a taxonomy |
| `taxonomies/get-term` | read | Get a specific term |
| `taxonomies/create-term` | write | Create new terms |
| `taxonomies/update-term` | write | Update existing terms |
| `taxonomies/delete-term` | write | Delete terms |
| `taxonomies/assign-to-content` | write | Assign terms to posts |
| `taxonomies/get-content-terms` | read | Get all terms for a post |

### Plugin Management (6)

| Ability | Type | Description |
|---------|------|-------------|
| `plugins/list` | read | List all installed plugins |
| `plugins/get` | read | Get detailed plugin information |
| `plugins/activate` | write | Activate plugins |
| `plugins/deactivate` | write | Deactivate plugins |
| `plugins/install` | write | Install from WordPress.org |
| `plugins/search-repository` | read | Search WordPress.org plugins |

### Media Library (5)

| Ability | Type | Description |
|---------|------|-------------|
| `media/list` | read | List media library items |
| `media/create` | write | Upload media from URL |
| `media/upload` | write | Upload media from base64 data |
| `media/update` | write | Update media metadata |
| `media/delete` | write | Delete media items |

### User Management (5)

| Ability | Type | Description |
|---------|------|-------------|
| `users/list` | read | List WordPress users |
| `users/get` | read | Get user details |
| `users/create` | write | Create new users |
| `users/update` | write | Update user information |
| `users/delete` | write | Delete users |

### Comment Management (5)

| Ability | Type | Description |
|---------|------|-------------|
| `comments/list` | read | List comments with filtering |
| `comments/get` | read | Get specific comment |
| `comments/create` | write | Create new comments |
| `comments/update` | write | Update comments |
| `comments/delete` | write | Delete comments |

### Menu Management (12)

| Ability | Type | Description |
|---------|------|-------------|
| `menus/list-menus` | read | List all navigation menus |
| `menus/get-menu` | read | Get menu with hierarchical items |
| `menus/create-menu` | write | Create a new menu |
| `menus/delete-menu` | write | Delete a menu |
| `menus/list-menu-items` | read | List items in a menu |
| `menus/add-menu-item` | write | Add item to a menu |
| `menus/update-menu-item` | write | Update a menu item |
| `menus/delete-menu-item` | write | Delete a menu item |
| `menus/reorder-menu-items` | write | Reorder menu items |
| `menus/list-locations` | read | List registered menu locations |
| `menus/assign-location` | write | Assign a menu to a location |
| `menus/unassign-location` | write | Unassign a menu from a location |

## Installation

1. Upload to `wp-content/plugins/wordpress-abilities-suite/`
2. Activate the plugin
3. All abilities are auto-discovered by the MCP Adapter

No Composer dependencies required.

## Multisite Support

Network activation supported. Plugin management is multisite-aware.

## Security

All abilities include WordPress capability checks:
- `edit_posts` — content and taxonomy operations
- `activate_plugins` — plugin management
- `upload_files` — media operations
- `create_users` — user management
- `moderate_comments` — comment operations
- `edit_theme_options` — menu operations

## Version

**Current:** 2.0.0

## Author

[Influencentricity](https://influencentricity.com)

## License

GPL-2.0-or-later
