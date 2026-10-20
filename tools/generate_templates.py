#!/usr/bin/env python3
"""
generate_templates.py
----------------------
One-time asset generator for the Opening of Classes Photobooth example templates.

For each template this script produces, inside templates/<id>/:
  - frame.png      : RGBA overlay, same size as the final output. Fully opaque
                      everywhere EXCEPT the 4 photo windows, which are fully
                      transparent so the captured photos show through.
  - thumbnail.png  : a small preview (frame + 4 placeholder photo colors)
                      shown in the in-app template gallery.
  - config.json    : the machine-readable template definition consumed by
                      includes/TemplateManager.php and includes/Compositor.php.

Run once with:  python3 tools/generate_templates.py
Re-run any time you want to regenerate / tweak the bundled example templates.
"""
import json
import os
from PIL import Image, ImageDraw, ImageFont

ROOT = os.path.join(os.path.dirname(__file__), "..", "photobooth", "templates")


def rounded_rect(draw, box, radius, fill=None, outline=None, width=1):
    draw.rounded_rectangle(box, radius=radius, fill=fill, outline=outline, width=width)


def load_font(size):
    candidates = [
        "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
        "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
    ]
    for c in candidates:
        if os.path.exists(c):
            return ImageFont.truetype(c, size)
    return ImageFont.load_default()


def make_template(spec):
    tid = spec["id"]
    out_dir = os.path.join(ROOT, tid)
    os.makedirs(out_dir, exist_ok=True)

    w, h = spec["output"]["width"], spec["output"]["height"]
    frame = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    draw = ImageDraw.Draw(frame)

    # Background fill (fully opaque card behind everything)
    rounded_rect(draw, [0, 0, w - 1, h - 1], spec["radius"], fill=spec["bg"])

    # Decorative accent bar
    if spec.get("accent_bar"):
        bx0, by0, bx1, by1 = spec["accent_bar"]
        draw.rectangle([bx0, by0, bx1, by1], fill=spec["accent"])

    # Title text
    title_font = load_font(spec["title_size"])
    tw = draw.textlength(spec["title"], font=title_font)
    draw.text(((w - tw) / 2, spec["title_y"]), spec["title"], font=title_font,
               fill=spec["title_color"])

    # Footer text
    if spec.get("footer"):
        footer_font = load_font(spec["footer_size"])
        fw = draw.textlength(spec["footer"], font=footer_font)
        draw.text(((w - fw) / 2, spec["footer_y"]), spec["footer"],
                   font=footer_font, fill=spec.get("footer_color", spec["title_color"]))

    # Punch out the transparent photo windows + draw a thin border guide
    for p in spec["photos"]:
        x0, y0 = p["x"], p["y"]
        x1, y1 = x0 + p["width"], y0 + p["height"]
        # Cut a transparent rounded window
        mask = Image.new("L", (w, h), 0)
        mdraw = ImageDraw.Draw(mask)
        mdraw.rounded_rectangle([x0, y0, x1, y1], radius=spec["window_radius"], fill=255)
        transparent = Image.new("RGBA", (w, h), (0, 0, 0, 0))
        frame = Image.composite(transparent, frame, mask)
        draw = ImageDraw.Draw(frame)
        # Thin decorative border around the window (drawn AFTER the cut so it stays opaque)
        rounded_rect(draw, [x0 - 2, y0 - 2, x1 + 2, y1 + 2], spec["window_radius"] + 2,
                     outline=spec["accent"], width=4)

    frame.save(os.path.join(out_dir, "frame.png"))

    # ---- Thumbnail: composite frame over 4 flat placeholder colors ----
    thumb_scale = spec.get("thumb_scale", 0.22)
    tw_, th_ = max(1, int(w * thumb_scale)), max(1, int(h * thumb_scale))
    base = Image.new("RGBA", (w, h), (255, 255, 255, 255))
    bd = ImageDraw.Draw(base)
    palette = spec.get("placeholder_colors", ["#cfe8ff", "#bcd9ff", "#a9caff", "#96bbff"])
    for i, p in enumerate(spec["photos"]):
        color = palette[i % len(palette)]
        bd.rounded_rectangle(
            [p["x"], p["y"], p["x"] + p["width"], p["y"] + p["height"]],
            radius=spec["window_radius"], fill=color)
    composed = Image.alpha_composite(base, frame)
    composed.thumbnail((tw_, th_))
    composed.save(os.path.join(out_dir, "thumbnail.png"))

    # ---- config.json ----
    config = {
        "name": spec["display_name"],
        "description": spec["description"],
        "thumbnail": "thumbnail.png",
        "frame": "frame.png",
        "output": spec["output"],
        "background": spec["bg_hex"],
        "photos": [
            {
                "x": p["x"], "y": p["y"],
                "width": p["width"], "height": p["height"],
                "rotation": p.get("rotation", 0)
            } for p in spec["photos"]
        ]
    }
    with open(os.path.join(out_dir, "config.json"), "w") as f:
        json.dump(config, f, indent=2)

    print(f"Generated template '{tid}' -> {out_dir}")


TEMPLATES = []

# 1. Classic vertical photo-strip (2x6in @ 300dpi = 600x1800)
TEMPLATES.append(dict(
    id="01-classic-strip", display_name="Classic Strip",
    description="The timeless 4-photo vertical strip with a clean white border.",
    output={"width": 600, "height": 1800}, radius=18,
    bg=(255, 255, 255, 255), bg_hex="#FFFFFF",
    accent=(37, 99, 235, 255), accent_bar=None,
    title="OPENING OF CLASSES", title_size=26, title_y=28, title_color=(37, 99, 235, 255),
    footer="2026", footer_size=18, footer_y=1755, footer_color=(120, 120, 120, 255),
    window_radius=14, thumb_scale=0.3,
    photos=[
        {"x": 40, "y": 90, "width": 520, "height": 380, "rotation": 0},
        {"x": 40, "y": 490, "width": 520, "height": 380, "rotation": 0},
        {"x": 40, "y": 890, "width": 520, "height": 380, "rotation": 0},
        {"x": 40, "y": 1290, "width": 520, "height": 380, "rotation": 0},
    ],
))

# 2. Modern 2x2 grid, square format
TEMPLATES.append(dict(
    id="02-modern-grid", display_name="Modern Grid",
    description="Minimalist 2x2 square grid with thin geometric accents.",
    output={"width": 1600, "height": 1600}, radius=24,
    bg=(250, 250, 252, 255), bg_hex="#FAFAFC",
    accent=(15, 23, 42, 255), accent_bar=None,
    title="OPENING OF CLASSES 2026", title_size=34, title_y=30, title_color=(15, 23, 42, 255),
    footer=None, footer_size=0, footer_y=0,
    window_radius=10, thumb_scale=0.22,
    placeholder_colors=["#e2e8f0", "#cbd5e1", "#cbd5e1", "#e2e8f0"],
    photos=[
        {"x": 60, "y": 110, "width": 715, "height": 715, "rotation": 0},
        {"x": 825, "y": 110, "width": 715, "height": 715, "rotation": 0},
        {"x": 60, "y": 875, "width": 715, "height": 715, "rotation": 0},
        {"x": 825, "y": 875, "width": 715, "height": 715, "rotation": 0},
    ],
))

# 3. School spirit vertical strip (maroon / gold)
TEMPLATES.append(dict(
    id="03-school-spirit", display_name="School Spirit",
    description="Bold maroon-and-gold strip for school pride at Opening of Classes.",
    output={"width": 600, "height": 1800}, radius=18,
    bg=(91, 18, 30, 255), bg_hex="#5B121E",
    accent=(245, 197, 66, 255), accent_bar=[0, 1740, 600, 1800],
    title="OPENING OF CLASSES", title_size=26, title_y=26, title_color=(245, 197, 66, 255),
    footer="GO TIGERS! \u2022 2026", footer_size=16, footer_y=1755, footer_color=(91, 18, 30, 255),
    window_radius=12, thumb_scale=0.3,
    placeholder_colors=["#f5c542", "#f0b429", "#f5c542", "#f0b429"],
    photos=[
        {"x": 40, "y": 90, "width": 520, "height": 380, "rotation": 0},
        {"x": 40, "y": 490, "width": 520, "height": 380, "rotation": 0},
        {"x": 40, "y": 890, "width": 520, "height": 380, "rotation": 0},
        {"x": 40, "y": 1290, "width": 520, "height": 380, "rotation": 0},
    ],
))

# 4. Polaroid fun grid - each window tilted slightly, demonstrates "rotation"
TEMPLATES.append(dict(
    id="04-polaroid-fun", display_name="Polaroid Fun",
    description="Playful scattered polaroid-style layout with tilted photo frames.",
    output={"width": 1400, "height": 1400}, radius=20,
    bg=(255, 250, 235, 255), bg_hex="#FFFAEB",
    accent=(234, 88, 12, 255), accent_bar=None,
    title="OPENING OF CLASSES", title_size=30, title_y=24, title_color=(234, 88, 12, 255),
    footer="smile! \u2022 2026", footer_size=18, footer_y=1355, footer_color=(234, 88, 12, 255),
    window_radius=6, thumb_scale=0.24,
    placeholder_colors=["#fed7aa", "#fdba74", "#fb923c", "#f97316"],
    photos=[
        {"x": 90, "y": 130, "width": 560, "height": 420, "rotation": -6},
        {"x": 740, "y": 200, "width": 560, "height": 420, "rotation": 5},
        {"x": 100, "y": 760, "width": 560, "height": 420, "rotation": 4},
        {"x": 730, "y": 820, "width": 560, "height": 420, "rotation": -5},
    ],
))

# 5. Elegant gold vertical strip - black + gold formal look
TEMPLATES.append(dict(
    id="05-elegant-gold", display_name="Elegant Gold",
    description="Formal black-and-gold strip for an elegant Opening of Classes ceremony.",
    output={"width": 600, "height": 1800}, radius=12,
    bg=(10, 10, 12, 255), bg_hex="#0A0A0C",
    accent=(212, 175, 55, 255), accent_bar=[0, 0, 600, 8],
    title="OPENING  OF  CLASSES", title_size=22, title_y=30, title_color=(212, 175, 55, 255),
    footer="EST. 2026", footer_size=15, footer_y=1758, footer_color=(212, 175, 55, 255),
    window_radius=4, thumb_scale=0.3,
    placeholder_colors=["#3f3f46", "#52525b", "#3f3f46", "#52525b"],
    photos=[
        {"x": 45, "y": 95, "width": 510, "height": 375, "rotation": 0},
        {"x": 45, "y": 490, "width": 510, "height": 375, "rotation": 0},
        {"x": 45, "y": 885, "width": 510, "height": 375, "rotation": 0},
        {"x": 45, "y": 1280, "width": 510, "height": 375, "rotation": 0},
    ],
))

if __name__ == "__main__":
    for spec in TEMPLATES:
        make_template(spec)
    print("All templates generated.")
