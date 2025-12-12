# Changelog

All notable changes to WordPress Abilities Suite will be documented in this file.

## [1.0.5] - 2025-12-12

### Added
- **NEW ABILITY:** `media/upload` - Upload media directly from base64-encoded file data
  - Enables Claude Desktop to upload locally-created images without public URLs
  - Supports all WordPress-compatible file types (images, documents, video, audio)
  - Full metadata support (title, caption, description, alt_text)
  - Optional post attachment via `post_id` parameter
  - Secure validation with automatic temp file cleanup
  - Returns attachment ID, URL, title, and MIME type

### Changed
- Updated media abilities count from 4 to 5
- Updated total abilities count from 39 to 40

### Technical Details
- File: `includes/media-abilities.php` (lines 256-405)
- Uses `media_handle_sideload()` for upload processing
- Temporary files created in `wp-content/uploads/` directory
- Permission check: `current_user_can('upload_files')`
- Error handling for invalid base64, invalid filename, and file write failures

---

## [1.0.4] - 2025-12-12

### Fixed
- **CRITICAL FIX:** Abilities now persist in registry after plugin activation
- Root cause: WordPress Abilities API v0.4.0 requires categories registered before abilities

### Added
- **NEW FILE:** `includes/ability-categories.php`
  - Registers 6 ability categories on `wp_abilities_api_categories_init` hook
  - Categories: content, taxonomies, plugins, media, users, comments
- Category registration now happens before ability registration

### Changed
- Updated main plugin file to load `ability-categories.php` first
- Fixed dashboard to use `wp_get_abilities()` instead of non-existent `wp_list_abilities()`
- Added WP_Ability object to array conversion for dashboard display
- Updated plugin version to 1.0.4

### Technical Details
- Hook order: `wp_abilities_api_categories_init` (priority 10) → `wp_abilities_api_init` (priority 100)
- All 39 abilities now register successfully (3 core + 36 custom)
- Dashboard displays all registered abilities with categories, descriptions, and annotations

---

## [1.0.3] - 2025-12-11

### Added
- Initial release of WordPress Abilities Suite
- 39 total abilities across 7 categories:
  - Core: 3 abilities (site info, user info, environment info)
  - Content: 8 abilities (list, get, create, update, delete, discover types, find by URL, get by slug)
  - Taxonomies: 8 abilities (discover, list terms, get/create/update/delete terms, assign, get content terms)
  - Plugins: 6 abilities (list, get, activate, deactivate, install, search repository)
  - Media: 4 abilities (list, create from URL, update, delete)
  - Users: 5 abilities (list, get, create, update, delete)
  - Comments: 5 abilities (list, get, create, update, delete)

### Features
- Network-wide activation support for WordPress Multisite
- Admin dashboard with ability browser
- Test interface for individual abilities
- Settings page with system diagnostics
- RESTful API integration via MCP adapter
- Comprehensive input/output schemas for all abilities
- Permission callbacks for security
- Annotations (readonly, destructive, idempotent)

### Technical Details
- Requires: PHP 7.4+, WordPress 6.0+
- Requires: WordPress Abilities API plugin
- Compatible with: wp-mcp-adapter plugin
- Network activation supported

---

## Upgrade Guide

### From 1.0.4 to 1.0.5
**No breaking changes. Safe to upgrade.**

1. Deactivate v1.0.4
2. Upload and activate v1.0.5
3. No configuration changes needed
4. New `media/upload` ability immediately available

### From 1.0.3 to 1.0.4
**Critical bug fix. Recommended upgrade.**

1. Deactivate v1.0.3
2. Upload and activate v1.0.4
3. Navigate to Abilities Suite dashboard
4. Verify all 39 abilities are displayed
5. No configuration changes needed

---

## Version History

| Version | Date | Abilities | Key Changes |
|---------|------|-----------|-------------|
| 1.0.5 | 2025-12-12 | 40 | Added base64 media upload |
| 1.0.4 | 2025-12-12 | 39 | Fixed ability persistence bug |
| 1.0.3 | 2025-12-11 | 39 | Initial release |

---

## Links

- **Documentation:** See README.md
- **Quick Start:** See QUICK-START-media-upload.md
- **Technical Docs:** See media-upload-base64-ability.md
- **Platform Database:** See jacobmarinko-platform-database.md
- **MCP Integration:** See mcp-servers-documentation.md

---

## Support

For issues, check:
1. WordPress debug log: `wp-content/debug.log`
2. Dashboard settings page for system diagnostics
3. Verify WordPress Abilities API is active
4. Verify wp-mcp-adapter is configured correctly

---

## License

GPL-3.0-or-later
