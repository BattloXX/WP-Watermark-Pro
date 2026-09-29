# Changelog – Watermark Pro

## 1.3.1 — 2026-09-29

### Fixes
- **Release-Paketierung automatisiert:** Releases werden jetzt per GitHub Actions gebaut und veröffentlicht, statt manuell lokal gepackt zu werden. Der Build bricht ab, falls Git-Tag, `Version:`-Header und `WM_VERSION`-Konstante nicht exakt übereinstimmen, und prüft zusätzlich, dass das gebaute ZIP an der erwarteten Stelle (`watermark-pro/watermark-pro.php`) einen gültigen Plugin-Header enthält. Das behebt die Ursache dafür, dass Version 1.2.1 nie in den Plugin-Header übernommen wurde (siehe unten) und künftige Releases nicht mehr aktivierbar sein könnten.

## 1.3.0 — 2026-09-29

### Bug Fixes
- **Overwrite result is now verifiable:** Overwrite mode returns a cache-busted result link so the newly watermarked image is immediately visible.
- **Confirmation before re-applying:** Re-applying a watermark to an already-watermarked image in overwrite mode now asks for confirmation instead of silently double-stamping it.
- **Double-submit protection:** Added a short-lived per-image processing lock to prevent concurrent processing races.

### Changes
- **Explicit overwrite warning:** Overwrite mode is no longer silently restored across page loads and now shows a warning plus confirmation before processing.

## 1.2.1 — 2026-04-11

### Bug Fixes
- **Text-Wasserzeichen auf FreeBSD:** Imagick-Fallback für Text-Rendering schlug auf FreeBSD/PHP-FPM fehl, obwohl GD/FreeType und Imagick laut `phpinfo()` aktiv waren. `resolve_font_for_imagick()` validiert Fonts jetzt mit einem echten `queryFontMetrics()`-Test statt nur `setFont()`, und `apply_text_watermark_imagick()` wiederholt bei einem font-bedingten Fehler automatisch ohne expliziten Font.

*Hinweis: Dieser Eintrag wurde nachträglich ergänzt — das damalige Release trug zwar den Tag `v1.2.1`, der `Version:`-Header in `watermark-pro.php` wurde aber nicht mit hochgezählt und zeigte weiterhin `1.2.0`. Ab 1.3.1 verhindert die automatisierte Release-Pipeline (siehe oben) genau diese Inkonsistenz.*

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
