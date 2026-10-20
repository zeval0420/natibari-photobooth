# Opening of Classes Photobooth

A complete, production-quality, standalone kiosk photobooth web app — built with
plain PHP, HTML, CSS and JavaScript (Canvas API + WebRTC `getUserMedia`).
No database. Everything is stored on the filesystem as JSON + images.

---

## 1. Quick Start

**Requirements:** PHP 7.4+ (tested on 8.3) with the **GD** extension enabled.
A webcam-equipped device with a modern browser (Chrome, Edge, Safari).

```bash
cd photobooth
php -S 0.0.0.0:8000
```

Open **http://localhost:8000/index.php** on the kiosk display (use Chrome's
kiosk mode: `chrome --kiosk http://localhost:8000/index.php`).

Open the **hidden admin Settings page** at `http://localhost:8000/admin/settings.php`
(or tap the small logo on the welcome screen 5 times). Default passcode: **`1234`**
— change it immediately from the Settings → Admin Access section.

The **admin Gallery** lives at `http://localhost:8000/admin/gallery.php`.

> Deploying on Apache instead of the PHP dev server? Point the document root at
> this `photobooth/` folder and make sure `AllowOverride All` is set so the
> bundled `.htaccess` files take effect (they hide directory listings and block
> direct access to `includes/` and `config/`).

---

## 2. Folder Structure

```
photobooth/
├── index.php                 Kiosk app shell (single page, all workflow screens)
├── admin/
│   ├── settings.php          Hidden, passcode-gated settings page
│   └── gallery.php           Admin gallery of past sessions + reprint
├── api/                      JSON endpoints called by the frontend (fetch)
├── includes/                 PHP "backend" classes (one responsibility each)
│   ├── bootstrap.php         Paths, autoloading, app constants
│   ├── helpers.php           Small shared functions + admin-auth gate
│   ├── Settings.php          Reads/writes config/settings.json
│   ├── TemplateManager.php   Scans templates/ on every request
│   ├── SessionManager.php    Creates/reads sessions/<id>/ folders
│   ├── Compositor.php        GD-based final strip.png composition
│   └── PrintManager.php      Shells out to `lp` (CUPS) or simulates
├── templates/                 5 example templates (see below)
├── sessions/                  Created at runtime — one folder per photo session
├── config/
│   ├── settings.default.json  Shipped defaults
│   └── settings.json          Live overrides (admin-editable)
├── assets/
│   ├── css/                   variables / main / components / animations / dark-mode / admin
│   ├── js/                    ES modules — camera, countdown, capture flow, etc.
│   └── sounds/                beep.wav, shutter.wav (synthesized, see tools/)
└── tools/                     One-off asset generator scripts (Python)
```

---

## 3. The Workflow (matches the brief exactly)

```
Welcome → Template Gallery → Camera Preview → 3s Countdown → Capture
   → (repeat x4) → Review (retake any photo) → Generate Strip → Preview
   → Accept → Auto-save (already done) → Auto-print (if enabled) → Welcome
```

Every step is its own `<section class="screen">` in `index.php`; `assets/js/app.js`
is the state machine that shows/hides them and calls the right module at each step.

---

## 4. Templates — fully modular, zero code changes

Drop a new folder into `templates/`:

```
templates/my-new-template/
├── config.json
├── frame.png        (RGBA, same size as output — transparent over photo windows)
└── thumbnail.png    (small gallery preview)
```

`config.json` schema:

```json
{
  "name": "My New Template",
  "description": "Optional short description",
  "thumbnail": "thumbnail.png",
  "frame": "frame.png",
  "background": "#FFFFFF",
  "output": { "width": 600, "height": 1800 },
  "photos": [
    { "x": 40, "y": 90,   "width": 520, "height": 380, "rotation": 0 },
    { "x": 40, "y": 490,  "width": 520, "height": 380, "rotation": 0 },
    { "x": 40, "y": 890,  "width": 520, "height": 380, "rotation": 0 },
    { "x": 40, "y": 1290, "width": 520, "height": 380, "rotation": 0 }
  ]
}
```

`TemplateManager::getAll()` re-scans the directory on every page load — **no code
changes, no restart needed.** Invalid folders (missing files/fields) are skipped
and logged, never crash the app.

5 ready-made templates are included: **Classic Strip, Modern Grid, School
Spirit, Polaroid Fun** (demonstrates the `rotation` field), and **Elegant Gold**.
Regenerate or design new ones with `tools/generate_templates.py` (Python +
Pillow) — it's a convenience generator, not a runtime dependency.

---

## 5. Storage layout (no database, ever)

```
sessions/2026-06-28_14-05-02/
├── raw/photo1.jpg .. photo4.jpg     (only when Photo Storage = "Raw photos + final strip")
├── final/strip.png  (+ strip_a5.png when printing "two strips side by side")
└── metadata.json
```

**Settings → Photo Storage** chooses what is kept:

- **Raw photos + final strip** — everything stays on disk.
- **Final strip only** — raw photos are deleted the moment the guest accepts
  their strip. Sessions abandoned for 2+ hours are cleaned up on the next new
  session. A button there also deletes raw photos from existing sessions.

The Gallery shows each session's size, lets you view/download the strip and
raw photos, print, delete raw photos, or delete the session.

---

## 6. Printing

**Settings → Printing**: pick an installed printer (the list is read from the
OS), press **Test print**, choose the layout (one strip, or two side by side
for cutting), paper size, copies, margin and scale, then turn on **Print
automatically**. With auto-print off, strips are only saved and can be printed
from the Gallery.

- **Windows** (XAMPP): `tools/print_image.ps1` prints through the Windows
  spooler using System.Drawing — no extra software. Needs PHP `exec()` and
  PowerShell (both default on Windows).
- **Linux/macOS**: CUPS `lp`.
- No printer selected → prints are *simulated* and logged in `metadata.json`.

If the printer is offline or misnamed, the guest still gets their saved strip
and the failure reason is stored on the session (visible in the Gallery).

---

## 7. Settings

`config/settings.json` (merged over `settings.default.json`), edited from the
tabbed Settings page: **Printing, Photo Storage, Camera & Capture, Templates,
Security**. The passcode hash never leaves the server.

---

## 8. Architecture notes / extending the app

The codebase is deliberately split by responsibility so new features are
additive, not invasive:

| Want to add...        | Touch only...                                                   |
|------------------------|------------------------------------------------------------------|
| A new template         | `templates/<id>/` — zero code                                   |
| GIF mode                | new `assets/js/gifFlow.js` reusing `CameraController`/`CaptureFlow`, new `api/gif_generate.php` using GD frame capture |
| AI background removal   | a step inside `CaptureFlow.captureOne()` before upload, or a new `api/remove_bg.php` the compositor calls before `Compositor::stampPhoto()` |
| QR code download         | new `api/qr.php` (render a QR pointing at a public session URL) + a button on the Final screen |
| Stickers                  | extend `config.json` with a `stickers` array + a few lines in `Compositor.php` |
| New event theme           | a new template + new CSS variable palette (everything already reads CSS vars) |

Frontend modules (`assets/js/*.js`) are plain ES modules with single
responsibilities (`camera.js`, `countdown.js`, `captureFlow.js`, `review.js`,
`templateGallery.js`, `sessionClient.js`) orchestrated by `app.js`. Backend
classes (`includes/*.php`) mirror that same separation. Both sides talk only
through the small, documented JSON contracts in `api/*.php`.

---

## 9. Security notes for real deployments

- Change the default admin passcode (`1234`) immediately.
- Set `APP_DEBUG = false` in `includes/bootstrap.php` before going live.
- Serve over HTTPS if the kiosk is reachable beyond a local network — webcam
  access (`getUserMedia`) requires a secure context on most browsers anyway
  (localhost is exempt).
- `config/` and `includes/` are blocked from direct HTTP access via the
  bundled `.htaccess` files (Apache only — see deployment note above).

---

## 10. Visual Template Creator

The photobooth ships with a fully standalone **WYSIWYG Template Creator** module at `creator/`.

**Access it** at `http://localhost:8000/creator/gallery.php` (same admin passcode as Settings).

### Workflow

1. **Gallery** — lists every installed template with Edit / Preview / Duplicate / Rename / Export (ZIP) / Delete actions.
2. **New Template** → Upload a frame image (PNG / JPG / WEBP). The editor opens with the image on the canvas.
3. **Place slots** — click "＋" in the toolbar to add photo-slot boxes. Drag to move, drag the corner/edge handles to resize, drag the ↻ handle above a box to rotate.
4. **Align** — toolbar buttons for left/centre/right, top/centre/bottom, distribute vertically/horizontally, match width, match height, bring forward/backward, reset rotation.
5. **Snap** — slots snap to the frame edges and centrelines automatically (toggle the ⊞ button).
6. **Properties panel** — right sidebar shows numeric X/Y/W/H/rotation for the selected slot; type exact values for pixel-perfect layouts.
7. **Preview** — composites the 4 bundled sample images into the current layout and shows the result in a modal.
8. **Save** — writes `config.json`, regenerates `thumbnail.png` and `preview.png`, and returns to the gallery.

### Import / Export

- **Export** — any gallery card has an ⬇ Export button that downloads the template as a self-contained ZIP (`frame.png`, `thumbnail.png`, `preview.png`, `config.json`).
- **Import** — the gallery's ⬆ Import ZIP button accepts any compliant ZIP and installs it under `templates/`, regenerating thumbnails with the installed sample photos.

### Module files

```
creator/
├── gallery.php          Gallery listing page (admin-gated)
├── editor.php           WYSIWYG editor page (admin-gated)
├── creator.css          All creator-specific styles (shares main app CSS tokens)
├── api/
│   ├── bootstrap.php    Loads main bootstrap + creator classes
│   ├── CreatorTemplateManager.php  Gallery CRUD (list, duplicate, rename, delete)
│   ├── ThumbnailGenerator.php      GD-based thumbnail + preview generation
│   ├── ImportExportManager.php     ZIP import / export
│   ├── gallery_list.php, template_duplicate.php, template_rename.php,
│   │   template_delete.php, frame_upload.php, save.php,
│   │   preview_generate.php, export.php, import.php
└── js/
    ├── editor.js          Main editor controller
    ├── SlotManager.js     Drag / resize / rotate / align / validate slots
    ├── CanvasRenderer.js  Zoom, frame image, snap guides
    ├── PropsPanel.js      Right-hand numeric properties panel
    ├── CreatorAPI.js      Fetch wrappers for all creator API endpoints
    ├── gallery.js         Gallery page controller
    └── utils.js           Toasts, promptModal, confirmModal
```

### Sample photos for preview generation

`assets/img/samples/sample1–4.jpg` are the placeholder images composited into previews. Regenerate them with:

```bash
python3 tools/generate_samples.py
```

Or replace them with real event photos (800×600 JPG, named `sample1.jpg`–`sample4.jpg`) for more realistic previews.
