# WordPress Block Editor — AI Knowledge Doc

> You are building pages and posts for a human. WordPress has a native block editor with 100+ blocks. Your job is to USE these blocks, never to write custom HTML/PHP/CSS to replicate what a block already does.

---

## Rule Zero

**NEVER write custom code to achieve something a core block already does.**

If the human asks for "an accordion" → use `core/details` (or `core/accordion` in WP 7.0+).
If the human asks for "a two-column layout" → use `core/columns`.
If the human asks for "a hero section with text over an image" → use `core/cover`.
If the human asks for "a FAQ section" → use `core/details` blocks inside a `core/group`.
If the human asks for "show latest blog posts" → use `core/query` loop or `core/latest-posts`.
If the human asks for "a table of contents" → use `core/table-of-contents`.

**Check this list before writing a single line of code.**

---

## How Blocks Work

Every piece of content in WordPress is a **block**. A paragraph is a block. An image is a block. A full page layout is blocks nested inside blocks.

Blocks are stored as HTML comments in post content:
```
<!-- wp:paragraph -->
<p>Hello world</p>
<!-- /wp:paragraph -->
```

You create and modify content by composing blocks via the content abilities (`content/create`, `content/update`). The block markup is the content body.

### Key Concepts

- **Block name**: `core/paragraph`, `core/heading`, etc. Third-party blocks use their plugin namespace.
- **Attributes**: Configuration stored in the block comment as JSON. E.g., `<!-- wp:heading {"level":3} -->`
- **Inner blocks**: Some blocks contain other blocks (group, columns, cover, query). These are nested in the markup.
- **Block styles**: Named visual variants. E.g., `core/button` has `fill` and `outline` styles.
- **Supports**: What the block can do — alignment, colors, typography, spacing, borders. Theme controls what's available.
- **Patterns**: Pre-composed block arrangements. The site may have registered patterns.

---

## Core Blocks — Complete Reference

### Layout and Structure

| Block | Name | Use for | Key attributes |
|-------|------|---------|---------------|
| Group | `core/group` | Any container — wraps blocks for shared styling, background, spacing | `tagName` (div/section/article/aside/main/header/footer), `layout` (constrained/flex/grid) |
| Columns | `core/columns` | Multi-column layouts | Contains `core/column` children, each with `width` attribute. Stacks on mobile automatically. |
| Column | `core/column` | Single column inside Columns | `width` (e.g., "66.66%"), `verticalAlignment` |
| Cover | `core/cover` | Hero sections — image/video with overlay text | `url`, `dimRatio` (overlay opacity 0-100), `overlayColor`, `minHeight`, `contentPosition` |
| Spacer | `core/spacer` | Vertical whitespace | `height` (e.g., "50px") |
| Separator | `core/separator` | Horizontal divider | Styles: `default`, `wide`, `dots` |

**Layout types for Group block:**
- `constrained` — centered content with max-width (default, like a container)
- `flex` — flexbox row or column (`orientation: "horizontal"` or `"vertical"`, `justifyContent`, `flexWrap`)
- `grid` — CSS grid (`columnCount` or `minimumColumnWidth`)

### Text

| Block | Name | Use for | Key attributes |
|-------|------|---------|---------------|
| Paragraph | `core/paragraph` | Body text | `dropCap`, `fontSize`, `textColor`, `backgroundColor` |
| Heading | `core/heading` | Section headings | `level` (1-6), `textAlign`, `anchor` (for linking) |
| List | `core/list` | Ordered/unordered lists | `ordered` (boolean). Contains `core/list-item` children. |
| Quote | `core/quote` | Block quotes | `citation` attribute for attribution |
| Details | `core/details` | Accordion/disclosure — collapsible content | `showContent` (default open/closed), `summary` (the clickable title). **Use this for FAQ.** |
| Table | `core/table` | Data tables | `hasFixedLayout`, `head`, `body`, `foot` arrays. Styles: `default`, `stripes` |
| Code | `core/code` | Code snippets | Monospaced, no syntax highlighting by default |
| Preformatted | `core/preformatted` | Whitespace-preserved text | Like Code but for non-code |

### Media

| Block | Name | Use for | Key attributes |
|-------|------|---------|---------------|
| Image | `core/image` | Single image | `url`, `alt` (ALWAYS set for accessibility), `caption`, `sizeSlug` (thumbnail/medium/large/full), `linkDestination`. Styles: `default`, `rounded` |
| Gallery | `core/gallery` | Multi-image grid | Contains `core/image` children. `columns` (1-8), `imageCrop` (boolean), `fixedHeight` |
| Media and Text | `core/media-text` | Side-by-side media + text | `mediaPosition` (left/right), `mediaWidth` (percent), `mediaType`, `isStackedOnMobile` |
| Video | `core/video` | Video player | `src`, `poster`, `autoplay`, `loop`, `muted`, `controls` |
| Audio | `core/audio` | Audio player | `src`, `autoplay`, `loop` |
| File | `core/file` | Download link | `href`, `fileName`, `showDownloadButton` |
| Embed | `core/embed` | YouTube, Vimeo, Twitter, etc. | `url`, `providerNameSlug`. Just paste the URL — WordPress handles oEmbed automatically. |

### Buttons

| Block | Name | Use for | Key attributes |
|-------|------|---------|---------------|
| Buttons | `core/buttons` | Button group (container) | `layout` for horizontal/vertical arrangement |
| Button | `core/button` | Individual button | `url`, `text`, `linkTarget` (_blank for new tab), `rel`. Styles: `fill` (default), `outline` |

### Dynamic Content (Query Loop)

This is how you display posts, pages, or any post type dynamically. **Do not hardcode post lists — use Query Loop.**

| Block | Name | Use for |
|-------|------|---------|
| Query | `core/query` | Container — defines what to query (post type, category, tag, per_page, order, etc.) |
| Post Template | `core/post-template` | Template for each result — contains the blocks that render per post |
| Post Title | `core/post-title` | The post's title (linked or not) |
| Post Excerpt | `core/post-excerpt` | The post's excerpt |
| Post Featured Image | `core/post-featured-image` | The featured image |
| Post Date | `core/post-date` | Publication date |
| Post Author | `core/post-author` | Author name and avatar |
| Post Terms | `core/post-terms` | Categories/tags |
| Post Time to Read | `core/post-time-to-read` | Estimated reading time |
| Query Pagination | `core/query-pagination` | Prev/next navigation for results |
| Query No Results | `core/query-no-results` | What to show when query returns empty |

**Query Loop example structure:**
```
<!-- wp:query {"queryId":1,"query":{"perPage":6,"postType":"post","order":"desc","orderBy":"date"}} -->
  <!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->
    <!-- wp:post-featured-image /-->
    <!-- wp:post-title /-->
    <!-- wp:post-excerpt /-->
    <!-- wp:post-date /-->
  <!-- /wp:post-template -->
  <!-- wp:query-pagination -->
    <!-- wp:query-pagination-previous /-->
    <!-- wp:query-pagination-numbers /-->
    <!-- wp:query-pagination-next /-->
  <!-- /wp:query-pagination -->
  <!-- wp:query-no-results -->
    <!-- wp:paragraph -->
    <p>No posts found.</p>
    <!-- /wp:paragraph -->
  <!-- /wp:query-no-results -->
<!-- /wp:query -->
```

### Navigation and Site Identity

| Block | Name | Use for |
|-------|------|---------|
| Navigation | `core/navigation` | Full site navigation menu |
| Site Logo | `core/site-logo` | The site's logo |
| Site Title | `core/site-title` | The site's name |
| Breadcrumbs | `core/breadcrumbs` | Breadcrumb trail |
| Table of Contents | `core/table-of-contents` | Auto-generated from headings on the page |
| Search | `core/search` | Search form |
| Social Links | `core/social-links` | Row of social media icons |

### New in WordPress 7.0+

| Block | Name | Use for |
|-------|------|---------|
| Accordion | `core/accordion` | Native accordion (multi-item) |
| Tabs | `core/tab` + `core/tab-panel` | Tabbed content sections |
| Icon | `core/icon` | SVG icon display |
| Form | `core/form` | Native form builder |

### Widget Blocks

| Block | Name | Use for |
|-------|------|---------|
| Latest Posts | `core/latest-posts` | Simple recent posts list (for sidebars — use Query Loop for main content) |
| Categories | `core/categories` | Category list/dropdown |
| Archives | `core/archives` | Monthly archive links |
| Calendar | `core/calendar` | Post calendar |
| RSS | `core/rss` | External RSS feed |

---

## Common Page Patterns (Block Compositions)

### Landing Page
```
Group (constrained) → Cover (hero) → Heading + Paragraph + Buttons
Group (constrained) → Columns (3) → [Image + Heading + Paragraph] × 3
Group (constrained) → Heading + Query Loop (latest posts, grid 3)
Group (constrained) → Heading + Paragraph + Buttons (final CTA)
```

### Blog Archive
```
Query Loop (post type: post, per_page: 10) →
  Post Template (grid or list) →
    Post Featured Image + Post Title + Post Excerpt + Post Date
  Query Pagination
```

### FAQ Page
```
Group (constrained) → Heading "Frequently Asked Questions"
  Details (summary: "Question 1") → Paragraph (answer)
  Details (summary: "Question 2") → Paragraph (answer)
  Details (summary: "Question 3") → Paragraph (answer)
```

### About Page
```
Cover (hero with portrait) → Heading + Paragraph
Group → Media and Text (image left, bio right)
Group → Columns (3) → [values/highlights]
Group → Heading + Paragraph + Buttons (contact CTA)
```

---

## What To Always Set

1. **Image alt text** — every image needs `alt` for accessibility. Describe what's in the image.
2. **Heading hierarchy** — H1 is the page title (usually one per page). Use H2 for sections, H3 for subsections. Never skip levels.
3. **Button links** — every button needs a `url`. Don't create buttons without destinations.
4. **Featured images** — most themes expect posts to have featured images. Set them via post meta, not as the first image block.

## What To Never Do

1. **Never write raw HTML** to create layouts, columns, buttons, accordions, or any UI element that has a block equivalent.
2. **Never hardcode post lists** — use Query Loop so content stays dynamic.
3. **Never use Classic Editor block** (`core/freeform`) in new content.
4. **Never skip the Group wrapper** — when sections need shared styling (background, padding), wrap blocks in a Group.
5. **Never assume block availability** — new blocks (accordion, tabs, icon) require WordPress 7.0+. Check the site's WP version if using these.

---

## Available Abilities for Content

| Ability | What it does |
|---------|-------------|
| `content/list` | List posts, pages, or any post type |
| `content/get` | Get a single post's full content (block markup) |
| `content/create` | Create a new post/page with block content |
| `content/update` | Update content, title, status, featured image |
| `content/delete` | Trash or permanently delete content |
| `content/find-by-url` | Find content by its URL |
| `content/get-by-slug` | Find content by its slug |
| `content/discover-types` | See all registered post types on the site |
| `media/upload` | Upload images/files to use in blocks |
| `media/list` | Browse the media library |

---

*Source: WordPress/gutenberg trunk (2026-03). Block names and attributes from block.json schema files.*
