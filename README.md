# Watermark Pro

A WordPress plugin to apply image and text watermarks to media library photos — with live preview, position control, opacity, templates and batch processing.

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue?logo=wordpress) ![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php) ![License](https://img.shields.io/badge/license-GPL--2.0-green)

---

## Features

### Image Watermark
- Select any image from the WordPress media library as a watermark
- Supported formats: **PNG** (recommended, with transparency), **JPG**, **WebP**, **GIF**
- **EPS** support via Imagick + Ghostscript
- 9-point position grid (top/middle/bottom × left/center/right)
- Pixel-precise X/Y offset from the chosen anchor
- Size control: 1–100% of the base image width
- Opacity: 10–100%

### Text Watermark
- Freely typeable text rendered directly onto the image using server-side TTF fonts
- **8 placement modes:**
  | Mode | Description |
  |------|-------------|
  | 9-point grid | Same position options as image watermark |
  | Top edge | Horizontal text along the top border |
  | Bottom edge | Horizontal text along the bottom border |
  | Left edge | Text rotated 90° CCW along the left border |
  | Right edge | Text rotated 90° CW along the right border |
- Text alignment: **Left / Center / Right**
- Font family: auto-detected from server (DejaVu, Liberation Sans/Serif/Mono, FreeSans) + custom TTF path
- Font size: 12–200 px
- Color picker + opacity slider
- Independent X/Y offset

### Both Together
Image and text watermarks can be combined freely — each with its own position and settings.

### Templates
- Save any combination of settings as a named template
- Templates store both image and text watermark settings
- Load a template in one click — watermark image, position, size, font and text are all restored
- Manage templates in a dedicated tab (view, load, delete)

### Workflow
- **Batch processing** — select multiple images at once, apply in sequence with a progress bar
- **Save as new file** (original preserved) or **overwrite original**
- New files are automatically added to the WordPress media library
- Live canvas preview updates instantly as settings change

---

## Requirements

| Requirement | Minimum |
|-------------|---------|
| WordPress | 6.0 |
| PHP | 8.0 |
| PHP extension | `gd` (required) |
| PHP extension | `imagick` + Ghostscript (optional, for EPS watermarks) |
| PHP extension | FreeType support in GD (for text watermarks) |

> **Text watermarks** require GD to be compiled with FreeType support (`imagettftext` function). This is the default on virtually all managed WordPress hosts.

> **EPS watermarks** additionally require the PHP `imagick` extension and Ghostscript installed on the server.

---

## Installation

1. Download the latest `watermark-pro.zip` from [Releases](https://github.com/BattloXX/WP-Watermark-Pro/releases)
2. In WordPress: **Plugins → Plugin installieren → ZIP hochladen**
3. Upload `watermark-pro.zip` and click **Jetzt installieren**
4. **Activate** the plugin

A custom database table (`wp_wm_templates`) is created automatically on activation.

### Manual Installation

```bash
cd wp-content/plugins
git clone https://github.com/BattloXX/WP-Watermark-Pro.git watermark-pro
```

Then activate the plugin in the WordPress admin.

---

## Usage

Navigate to **Watermark Pro** in the WordPress admin sidebar.

### Apply Watermark

1. Click **Bilder aus Mediathek wählen** — select one or more images (hold Ctrl/Cmd for multiple)
2. Enable **Bild-Wasserzeichen** and/or **Text-Wasserzeichen**
3. Configure position, size, font, color and opacity — the **preview canvas** updates live
4. Optionally load a saved **Vorlage** (template) to restore previous settings
5. Choose output mode: *neue Datei* (keep original) or *Original überschreiben*
6. Click **Wasserzeichen anwenden** — a progress bar tracks batch operations

### Save a Template

After configuring your settings, enter a name in the *Als Vorlage speichern* field and click **Speichern**. The template is immediately available in the dropdown.

### Text Position Reference

```
         [edge-top]
    ┌──────────────────┐
    │  top-left  top-center  top-right  │
[e  │                  │  e]
[d  │  middle-left  ●  middle-right  │  d]
[g  │                  │  g]
[e  │  bottom-left  bottom-center  bottom-right  │  e]
[-  └──────────────────┘  -]
[l]       [edge-bottom]          [r]
```

---

## Font Support

The plugin auto-detects available TTF fonts on the server. Common paths checked:

| Family | Linux | Windows |
|--------|-------|---------|
| DejaVu Sans | `/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf` | — |
| Liberation Sans (Arial) | `/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf` | `C:/Windows/Fonts/arial.ttf` |
| Liberation Serif (Times) | `/usr/share/fonts/truetype/liberation/LiberationSerif-Regular.ttf` | `C:/Windows/Fonts/times.ttf` |
| Liberation Mono (Courier) | `/usr/share/fonts/truetype/liberation/LiberationMono-Regular.ttf` | `C:/Windows/Fonts/cour.ttf` |
| FreeSans | `/usr/share/fonts/truetype/freefont/FreeSans.ttf` | — |

To use a custom font, select **Benutzerdefinierter Pfad…** in the font dropdown and enter the absolute path to any `.ttf` file on your server.

---

## File Structure

```
watermark-pro/
├── watermark-pro.php               # Plugin bootstrap
├── includes/
│   ├── class-wm-admin.php          # Admin UI, AJAX handlers, mime filters
│   ├── class-wm-processor.php      # GD image processing, text rendering
│   └── class-wm-templates.php      # Template CRUD (custom DB table)
├── assets/
│   ├── css/admin.css               # Admin styles
│   └── js/admin.js                 # Canvas preview, media library, controls
└── templates/
    └── admin-page.php              # Admin page HTML
```

---

## Changelog

### 1.1.0
- Added **text watermark** with full position, font, color and rotation support
- Image watermark now accepts all formats (JPG, WebP, GIF — not just PNG)
- Templates extended to store text watermark settings
- Templates table auto-migrated on plugin update (`maybe_upgrade()`)

### 1.0.0
- Initial release
- PNG/EPS image watermarks
- 9-point positioning, opacity, size, offset
- Template system
- Batch processing with progress bar

---

## Author

**Johannes Battlogg**

---

## License

GPL-2.0 — see [WordPress Plugin License](https://www.gnu.org/licenses/gpl-2.0.html)
