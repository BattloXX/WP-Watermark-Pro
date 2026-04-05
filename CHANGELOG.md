# Changelog – Watermark Pro

## 1.2.0 — 2026-04-05

### Bug Fixes
- **Text watermark not rendered (critical):** Plugin now bundles `fonts/DejaVuSans.ttf` (SIL Open Font License). This font is used automatically when no system TTF is found, eliminating silent failures on servers without pre-installed fonts.
- **Silent font failure replaced by proper error:** When no TTF font is available at all (not bundled, not system, no custom path), the AJAX handler now returns an explicit error message instead of returning `success` with no text applied.
- **JS state not initialized from DOM on page load (Bug 2):** `admin.js` now reads all form field values into the state object on init, so default values (checkboxes, sliders, selects) always match the state correctly — even after a hard page reload.

### New Features
- **No-font warning in UI (Feature 2):** When no real TTF font is available (`wmPro.fonts` contains only `auto`/`custom` entries) and the user activates the Text Watermark checkbox, a WordPress admin notice is shown explaining that text watermarks are unavailable.
- **Last-used state persisted in localStorage (Feature 3):** After a watermark is successfully applied, the current settings (excluding the image selection) are saved to `localStorage`. On the next page visit, these settings are automatically restored into the form so the user can continue working immediately.

### Internal
- `WM_Processor::resolve_font()` now checks the plugin-bundled `fonts/DejaVuSans.ttf` before system paths.
- `WM_Processor::detect_available_fonts()` always lists DejaVu Sans as available when the bundled font is present.

---

## 1.1.0

- Added **text watermark** with full position, font, color and rotation support
- Image watermark now accepts all formats (JPG, WebP, GIF — not just PNG)
- Templates extended to store text watermark settings
- Templates table auto-migrated on plugin update (`maybe_upgrade()`)

## 1.0.0

- Initial release
- PNG/EPS image watermarks
- 9-point positioning, opacity, size, offset
- Template system
- Batch processing with progress bar
