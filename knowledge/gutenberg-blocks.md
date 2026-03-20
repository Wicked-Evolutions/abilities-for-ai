# WordPress Block Editor Reference

> WordPress has a native block editor with 100+ blocks. This reference helps you use them effectively when building pages and posts.

---

## How Blocks Work

Every piece of content in WordPress is a **block**. A paragraph is a block. An image is a block. A full page layout is blocks nested inside blocks.

Blocks are stored as HTML comments in post content:
```
<!-- wp:paragraph -->
<p>Hello world</p>
<!-- /wp:paragraph -->
```

You create and modify content by composing blocks via the content abilities (`content/create`, `content/update`, `content/append`). The block markup is the content body.

### Key Concepts

- **Block name**: `core/paragraph`, `core/heading`, etc. Third-party blocks use their plugin namespace.
- **Attributes**: Configuration stored in the block comment as JSON. E.g., `<!-- wp:heading {"level":3} -->`
- **Inner blocks**: Some blocks contain other blocks (group, columns, cover, query). These are nested in the markup.
- **Block styles**: Named visual variants. E.g., `core/button` has `fill` and `outline` styles.
- **Supports**: What the block can do — alignment, colors, typography, spacing, borders. Theme controls what's available.
- **Patterns**: Pre-composed block arrangements. The site may have registered patterns.

### Blocks First

WordPress provides blocks for most common UI patterns — columns, accordions, hero sections, FAQ layouts, dynamic post lists, buttons, tables, and more. Before writing custom HTML, check whether a core block already handles what the human is asking for. The block approach gives the human visual editing control in the editor, which custom markup doesn't.

That said, there are legitimate cases where custom code is the right choice — complex interactivity, integrations with external APIs, or patterns that blocks genuinely don't cover. Use your judgment, and when in doubt, ask the human.

---

## Core Blocks Reference

### Layout and Structure

| Block | Name | Use for | Key attributes |
|-------|------|---------|---------------|
| Group | `core/group` | Container — wraps blocks for shared styling, background, spacing | `tagName` (div/section/article/aside/main/header/footer), `layout` (constrained/flex/grid) |
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
| Details | `core/details` | Accordion/disclosure — collapsible content | `showContent` (default open/closed), `summary` (the clickable title). Works well for FAQ sections. |
| Table | `core/table` | Data tables | `hasFixedLayout`, `head`, `body`, `foot` arrays. Styles: `default`, `stripes` |
| Code | `core/code` | Code snippets | Monospaced, no syntax highlighting by default |
| Preformatted | `core/preformatted` | Whitespace-preserved text | Like Code but for non-code |

### Media

| Block | Name | Use for | Key attributes |
|-------|------|---------|---------------|
| Image | `core/image` | Single image | `url`, `alt` (important for accessibility), `caption`, `sizeSlug` (thumbnail/medium/large/full), `linkDestination`. Styles: `default`, `rounded` |
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

The Query Loop is how you display posts, pages, or any post type dynamically. It keeps content fresh — when new posts are published, they appear automatically.

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
| Table of Contents | `core/table-of-contents` | Auto-generated from headings on the page |
| Search | `core/search` | Search form |
| Social Links | `core/social-links` | Row of social media icons |

### Widget Blocks

| Block | Name | Use for |
|-------|------|---------|
| Latest Posts | `core/latest-posts` | Simple recent posts list (for sidebars — Query Loop is better for main content areas) |
| Categories | `core/categories` | Category list/dropdown |
| Archives | `core/archives` | Monthly archive links |
| Calendar | `core/calendar` | Post calendar |
| RSS | `core/rss` | External RSS feed |

---

## Common Page Compositions

### Landing Page
```
Group (constrained) -> Cover (hero) -> Heading + Paragraph + Buttons
Group (constrained) -> Columns (3) -> [Image + Heading + Paragraph] x 3
Group (constrained) -> Heading + Query Loop (latest posts, grid 3)
Group (constrained) -> Heading + Paragraph + Buttons (final CTA)
```

### Blog Archive
```
Query Loop (post type: post, per_page: 10) ->
  Post Template (grid or list) ->
    Post Featured Image + Post Title + Post Excerpt + Post Date
  Query Pagination
```

### FAQ Page
```
Group (constrained) -> Heading "Frequently Asked Questions"
  Details (summary: "Question 1") -> Paragraph (answer)
  Details (summary: "Question 2") -> Paragraph (answer)
  Details (summary: "Question 3") -> Paragraph (answer)
```

---

## Good Practices

1. **Image alt text** — describe what's in the image for accessibility
2. **Heading hierarchy** — H1 for the page title (usually one per page), H2 for sections, H3 for subsections
3. **Button destinations** — buttons work best when they link somewhere
4. **Featured images** — most themes expect posts to have featured images, set via post meta
5. **Group wrappers** — when sections need shared styling (background, padding), wrapping blocks in a Group keeps things organized

---

## Content Abilities

| Ability | What it does |
|---------|-------------|
| `content/list` | List posts, pages, or any post type |
| `content/get` | Get a single post's full content (block markup) |
| `content/get-text` | Get readable text without block markup |
| `content/create` | Create a new post/page with block content |
| `content/update` | Update content, title, status, featured image |
| `content/append` | Append blocks to existing content without reading the full post |
| `content/delete` | Trash or permanently delete content |
| `content/find-by-url` | Find content by its URL |
| `content/get-by-slug` | Find content by its slug |
| `content/discover-types` | See all registered post types on the site |
| `blocks/parse` | Parse block markup into structured data |
| `blocks/find-in-post` | Find specific blocks within a post |
| `blocks/insert` | Insert blocks at a specific position |
| `media/upload` | Upload images/files to use in blocks |
| `media/list` | Browse the media library |

---

*Source: WordPress/gutenberg trunk (2026-03). Block names and attributes from block.json schema files. This is a seed document — it can be updated through the Knowledge Layer admin UI. More knowledge, skills, and agent patterns at [knowledge.wickedevolutions.com](https://knowledge.wickedevolutions.com).*
