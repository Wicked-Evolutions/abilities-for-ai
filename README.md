# WordPress Abilities Suite

A comprehensive WordPress plugin that provides 40 MCP-compatible abilities for managing WordPress content, taxonomies, plugins, media, users, and comments.

**Latest Version:** 1.0.5 - Now with base64 media upload support!

## Features

### 40 Total Abilities Across 6 Categories:

#### Content Management (8 abilities)
- `content/list` - List posts, pages, or custom post types with filtering
- `content/get` - Get a specific post by ID
- `content/create` - Create new content
- `content/update` - Update existing content
- `content/delete` - Delete content
- `content/discover-types` - Discover all available post types
- `content/find-by-url` - Find content by URL
- `content/get-by-slug` - Get content by slug

#### Taxonomy Management (8 abilities)
- `taxonomies/discover` - List all available taxonomies
- `taxonomies/list-terms` - List terms in a taxonomy
- `taxonomies/get-term` - Get a specific term
- `taxonomies/create-term` - Create new terms
- `taxonomies/update-term` - Update existing terms
- `taxonomies/delete-term` - Delete terms
- `taxonomies/assign-to-content` - Assign terms to posts
- `taxonomies/get-content-terms` - Get all terms for a post

#### Plugin Management (6 abilities)
- `plugins/list` - List all installed plugins
- `plugins/get` - Get detailed plugin information
- `plugins/activate` - Activate plugins
- `plugins/deactivate` - Deactivate plugins
- `plugins/install` - Install from WordPress.org
- `plugins/search-repository` - Search WordPress.org plugins

#### Media Library (5 abilities)
- `media/list` - List media library items
- `media/create` - Upload media from URL
- `media/upload` - Upload media from base64 data ⭐ NEW in v1.0.5
- `media/update` - Update media metadata
- `media/delete` - Delete media items

#### User Management (5 abilities)
- `users/list` - List WordPress users
- `users/get` - Get user details
- `users/create` - Create new users
- `users/update` - Update user information
- `users/delete` - Delete users

#### Comment Management (5 abilities)
- `comments/list` - List comments with filtering
- `comments/get` - Get specific comment
- `comments/create` - Create new comments
- `comments/update` - Update comments
- `comments/delete` - Delete comments

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- [WordPress Abilities API](https://github.com/Automattic/wordpress-abilities) plugin
- [WP MCP Adapter](https://github.com/Automattic/wp-mcp-adapter) plugin (for MCP integration)

## Installation

1. Upload the plugin files to `/wp-content/plugins/wordpress-abilities-suite/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. For multisite: Network activate the plugin
4. Access the dashboard at **Abilities Suite** in the WordPress admin menu

## Dashboard Features

The plugin includes a comprehensive admin dashboard with:

- **Overview** - View all registered abilities and statistics
- **Test Interface** - Test abilities and view their input/output schemas
- **Settings** - Check system requirements and debug information

## Multisite Support

This plugin is fully compatible with WordPress multisite networks:
- Network activation supported
- Network-wide plugin management
- Multisite-aware permissions

## Security

All abilities include proper permission checks using WordPress capabilities:
- `edit_posts` - For content and taxonomy operations
- `activate_plugins` - For plugin viewing
- `install_plugins` - For plugin installation
- `upload_files` - For media operations
- `create_users` - For user management
- `moderate_comments` - For comment operations

## Development

### File Structure
```
wordpress-abilities-suite/
├── wordpress-abilities-suite.php  (Main plugin file)
├── includes/
│   ├── content-abilities.php      (Content management)
│   ├── taxonomy-abilities.php     (Taxonomy management)
│   ├── plugin-abilities.php       (Plugin management)
│   ├── media-abilities.php        (Media library)
│   ├── user-abilities.php         (User management)
│   └── comment-abilities.php      (Comment management)
├── admin/
│   ├── dashboard.php              (Admin UI)
│   └── css/
│       └── dashboard.css          (Dashboard styles)
└── README.md
```

## License

This plugin is provided as-is for use with the Model Context Protocol (MCP).

## Support

For issues and feature requests, please contact the plugin author.

## What's New in v1.0.5

### Base64 Media Upload
Upload images and files directly from base64-encoded data! This enables Claude Desktop to:
- Generate images locally
- Upload them directly to WordPress
- Set them as featured images
- Complete fully automated blog publishing workflows

**Example:**
```json
{
  "ability": "media/upload",
  "input": {
    "file_data": "iVBORw0KGgoAAAANSUh...",
    "filename": "my-image.png",
    "title": "Generated Image",
    "alt_text": "AI-generated header image"
  }
}
```

See [QUICK-START-media-upload.md](QUICK-START-media-upload.md) for complete usage guide.

## Changelog

### 1.0.5 (2025-12-12)
- Added `media/upload` ability for base64 file uploads
- Total abilities: 40 (was 39)
- Media abilities: 5 (was 4)
- See [CHANGELOG.md](CHANGELOG.md) for details

### 1.0.4 (2025-12-12)
- Fixed ability persistence bug
- Added category registration system
- Dashboard improvements
- 39 abilities working correctly

### 1.0.3 (2025-12-11)
- Initial release
- 39 total abilities across 6 categories
- Admin dashboard for ability management
- Multisite support
