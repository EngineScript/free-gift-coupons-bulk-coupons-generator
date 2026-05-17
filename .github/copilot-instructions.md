---
applyTo: '**'
---

# WordPress Plugin Development Standards

## Compatibility

- WordPress 6.8+, PHP 8.2+, WooCommerce 5.0+
- Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/) for PHP, JS, CSS, HTML, and accessibility
- Never use deprecated WordPress or PHP functions; this is a modern plugin (2026)
- Use ES6+ JavaScript (WordPress 6.8+ targets modern browsers)

## Environment

- Use WordPress APIs, hooks, and standards exclusively - no non-WP frameworks

## Security

All input must be sanitized. All output must be escaped. No exceptions.

- **Input:** `sanitize_text_field()`, `sanitize_email()`, `wp_kses()`, `absint()`, `wp_unslash()`
- **Output:** `esc_html()`, `esc_attr()`, `esc_url()`, `esc_js()`, `wp_kses_post()`
- **Forms:** `wp_nonce_field()` + `wp_verify_nonce()` for CSRF protection
- **Permissions:** `current_user_can()` before any sensitive operation
- **Database:** `$wpdb->prepare()` for all direct queries; prefer WooCommerce/WordPress APIs over raw SQL
- **Vulnerabilities to prevent:** SQL injection, XSS, CSRF, LFI, path traversal
- Auto-identify and fix security issues when found

## Code Quality

- Use WordPress APIs instead of raw PHP equivalents (e.g., `wc_get_products()` over `get_posts()` for products)
- Use WooCommerce HPOS-compatible APIs (no direct `wp_posts`/`wp_postmeta` queries for orders or products)
- Use `add_action()` / `add_filter()` with named methods (not anonymous closures) so hooks can be unhooked
- No inline styles in PHP or JS; use dedicated CSS files with proper classes
- Define magic numbers as named constants
- Use `WP_Error` for error handling; log errors via `wc_get_logger()` without exposing sensitive data
- PHPDoc all functions: `@param`, `@return`, `@since` tags
- Remove unused code; don't leave dead code behind

## Internationalization (i18n)

- Text domain: `free-gift-coupons-bulk-coupons-generator`
- All user-facing strings must use `__()`, `_e()`, `esc_html__()`, `esc_attr__()`, etc.
- Update `.pot` language files when adding or modifying translatable strings
- JS strings must be passed via `wp_add_inline_script()` data, never hardcoded

## Performance

- Avoid N+1 queries - use batch-fetching APIs
- Enqueue assets with `wp_enqueue_script()` / `wp_enqueue_style()` — load only on relevant admin pages
- Prefer `wc_get_products()` over `get_posts()` + `wc_get_product()` loops

## Documentation & Versioning

- Always update both CHANGELOG.md and readme.txt changelog section — keep them in sync
- Use an "Unreleased" section for ongoing changes
- **Version releases (only when explicitly instructed):**
  - Follow semantic versioning (MAJOR.MINOR.PATCH)
  - Update version in: plugin header, README.md, readme.txt, CHANGELOG.md, GEMINI.md, `.pot` files, constants, and composer.json
  - Move "Unreleased" entries to new version section in both changelogs
  - Never auto-update versions

## Workflow

- Read complete files before modifying them — understand context first
- Edit files in place; create new files only when necessary
- Proceed automatically unless the action is destructive (data loss, deletion)
- Provide concise, actionable responses — no separate summary files
- Auto-identify and fix bugs when encountered
