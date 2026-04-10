=== Stitch to UX Builder ===
Contributors: tuendwork
Tags: flatsome, ux-builder, html-import, stitch, dynamic-content
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import HTML from AI tools (Stitch, v0, Bolt…) into Flatsome UX Builder with slot-based templates and dynamic data support.

== Description ==

**Stitch to UX Builder** bridges the gap between AI-generated HTML and Flatsome's UX Builder. It provides:

* **Ultimate Section** — A container element with an HTML template that uses `{{slot_name}}` placeholders
* **Field Text** — Text element with tag selection (p, h1-h6, span, div) and dynamic data
* **Field Image** — Image element with upload, dynamic source from WooCommerce/ACF/Post
* **Field Link** — Link element with dynamic URL and label sources
* **Import Tool** — Admin meta box to paste/upload HTML → auto-parse → generate shortcodes

= Dynamic Data Sources =

| Prefix | Example | Source |
|--------|---------|--------|
| post:  | post:title, post:excerpt, post:thumbnail | Post built-in fields |
| acf:   | acf:summary, acf:btn_url | ACF field name |
| woo:   | woo:price, woo:thumbnail, woo:sku | WooCommerce product data |
| meta:  | meta:_custom_key | Raw post meta |

= Slot System =

* Last child element with the same slot wins (override, not concatenate)
* Dynamic source empty → falls back to static value
* Static value empty → replaced with empty string
* No `{{slot}}` placeholder left on frontend

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`
2. Activate via **Plugins** menu in WordPress
3. Requires **Flatsome** theme for UX Builder integration
4. Shortcodes work on frontend even without Flatsome

== Frequently Asked Questions ==

= Do child elements require Ultimate Section? =

No. All child elements (Field Text, Field Image, Field Link) can be used standalone anywhere in UX Builder or in regular post content as shortcodes.

= Does the import tool replace existing content? =

No. Import always **appends** to existing post content. Duplicate detection prevents importing the same HTML twice.

= How does dynamic data work in loops? =

Dynamic sources use `get_the_ID()` internally, so they automatically resolve to the current post/product in WooCommerce or post loops.

== Changelog ==

= 1.0.1 =
* Added support for ZIP file imports.
* Added auto-splitting logic to decompose complex HTML into multiple sections.
* Improved AJAX file handling using FormData.
* Refined preview UI to display multi-section tables.

= 1.0.0 =
* Initial release
* Ultimate Section container with slot-based templates
* Field Text, Field Image, Field Link child elements
* Dynamic data resolver (post, ACF, WooCommerce, meta)
* HTML Import Tool with preview and dynamic source assignment
* Duplicate import detection
