<?php
/**
 * includes/Compositor.php
 * ------------------------------------------------------------------
 * Turns 4 raw JPEG photos + a template definition into the final
 * strip.png. Everything about the layout (canvas size, where each
 * photo goes, its size, its rotation) comes from the template's
 * config.json — this class contains no hard-coded positions at all,
 * satisfying "image composition driven entirely from config files".
 *
 * Composition order:
 *   1. Create a blank canvas at output width/height.
 *   2. For each of the 4 photos: cover-fit crop it into its target
 *      box, rotate it if the template asks for it, stamp it onto
 *      the canvas.
 *   3. Draw frame.png on top. Because frame.png is transparent over
 *      the photo windows and opaque everywhere else, this single step
 *      adds the border/decoration and hides any sloppy photo edges.
 * ------------------------------------------------------------------
 */
class Compositor
{
    /**
     * @param string $sessionId   the session folder name
     * @param array  $template    a template definition from TemplateManager
     * @return string             relative path (within the session folder) to the final strip
     */
    public static function composeStrip(string $sessionId, array $template): string
    {
        $outW = (int)$template['output']['width'];
        $outH = (int)$template['output']['height'];

        $canvas = imagecreatetruecolor($outW, $outH);
        imagesavealpha($canvas, true);
        imagealphablending($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);

        // Fill with the template's declared background colour first (in case
        // a photo fails to load we still get a sane-looking strip).
        $bg = self::hexToRgb($template['background'] ?? '#FFFFFF');
        $bgColor = imagecolorallocate($canvas, $bg[0], $bg[1], $bg[2]);
        imagefilledrectangle($canvas, 0, 0, $outW, $outH, $bgColor);

        $rawDir = SessionManager::rawDir($sessionId);

        foreach ($template['photos'] as $i => $slot) {
            $photoPath = $rawDir . '/photo' . ($i + 1) . '.jpg';
            if (!is_file($photoPath)) {
                continue; // missing photo -> leave the background showing through
            }
            self::stampPhoto($canvas, $photoPath, $slot);
        }

        // Lay the frame (with its transparent cut-out windows) on top.
        $framePath = $template['frame_path'];
        if (is_file($framePath)) {
            $frame = self::loadAnyImage($framePath);
            if ($frame !== null) {
                imagealphablending($canvas, true);
                imagecopy($canvas, $frame, 0, 0, 0, 0, imagesx($frame), imagesy($frame));
                imagedestroy($frame);
            }
        }

        if (!is_dir(FINAL_PATH)) {
            mkdir(FINAL_PATH, 0775, true);
        }
        imagepng($canvas, SessionManager::finalFile($sessionId), 6);
        imagedestroy($canvas);

        return SessionManager::finalRel($sessionId);
    }

    /** Cover-fit crop + optional rotation, then stamp a single photo onto the canvas. */
    private static function stampPhoto($canvas, string $photoPath, array $slot): void
    {
        $src = self::loadAnyImage($photoPath);
        if ($src === null) {
            return;
        }

        $boxW = (int)$slot['width'];
        $boxH = (int)$slot['height'];
        $srcW = imagesx($src);
        $srcH = imagesy($src);

        // "Cover" fit: scale so the photo fully fills the box, cropping overflow.
        $scale = max($boxW / $srcW, $boxH / $srcH);
        $cropW = (int)round($boxW / $scale);
        $cropH = (int)round($boxH / $scale);
        $cropX = (int)round(($srcW - $cropW) / 2);
        $cropY = (int)round(($srcH - $cropH) / 2);

        $fitted = imagecreatetruecolor($boxW, $boxH);
        imagecopyresampled($fitted, $src, 0, 0, $cropX, $cropY, $boxW, $boxH, $cropW, $cropH);

        $rotation = (float)($slot['rotation'] ?? 0);
        if (abs($rotation) > 0.01) {
            // Rotate with a true transparent background (not a solid color) so
            // any corners exposed by the rotation cleanly reveal whatever is
            // already on the canvas underneath (the template's background)
            // instead of a harsh black or mismatched-color box.
            imagealphablending($fitted, false);
            imagesavealpha($fitted, true);
            $transparent = imagecolorallocatealpha($fitted, 0, 0, 0, 127);
            $rotated = imagerotate($fitted, -$rotation, $transparent);
            imagesavealpha($rotated, true);
            imagedestroy($fitted);
            $fitted = $rotated;
        }
        imagedestroy($src);

        // Center the (possibly now-larger, post-rotation) image over the
        // original slot position so the rotated photo stays visually
        // anchored to its window.
        $destX = (int)$slot['x'] - (int)round((imagesx($fitted) - $boxW) / 2);
        $destY = (int)$slot['y'] - (int)round((imagesy($fitted) - $boxH) / 2);

        imagealphablending($canvas, true);
        imagecopy($canvas, $fitted, $destX, $destY, 0, 0, imagesx($fitted), imagesy($fitted));
        imagedestroy($fitted);
    }

    /** Load a JPEG or PNG into a GD image resource regardless of extension. */
    private static function loadAnyImage(string $path)
    {
        $info = @getimagesize($path);
        if ($info === false) {
            return null;
        }
        switch ($info[2]) {
            case IMAGETYPE_JPEG:
                return @imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:
                $img = @imagecreatefrompng($path);
                if ($img) {
                    imagesavealpha($img, true);
                }
                return $img;
            default:
                return null;
        }
    }

    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6) {
            return [255, 255, 255];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    /**
     * Creates a side-by-side version of the final strip and saves it.
     * @param string $sessionId
     * @param string $relativeStripPath
     * @return string relative path to the side-by-side image
     */
    public static function generateSideBySide(string $sessionId, string $relativeStripPath): string
    {
        $stripPath = SessionManager::abs($relativeStripPath);
        
        $src = self::loadAnyImage($stripPath);
        if ($src === null) {
            throw new Exception("Failed to load strip image for side-by-side composition.");
        }
        
        $w = imagesx($src);
        $h = imagesy($src);
        
        // Two strips side-by-side
        $outW = $w * 2;
        $outH = $h;
        
        $canvas = imagecreatetruecolor($outW, $outH);
        imagesavealpha($canvas, true);
        imagealphablending($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        
        // Copy the strip twice
        imagecopy($canvas, $src, 0, 0, 0, 0, $w, $h);
        imagecopy($canvas, $src, $w, 0, 0, 0, $w, $h);
        
        if (!is_dir(FINAL_PATH)) {
            mkdir(FINAL_PATH, 0775, true);
        }

        imagepng($canvas, SessionManager::finalFile($sessionId, 'print'), 6);
        imagedestroy($canvas);
        imagedestroy($src);
        
        return SessionManager::finalRel($sessionId, 'print');
    }

    /**
     * The file that actually goes to the printer, per printing.layout:
     *   single -> the strip as-is
     *   two_up -> two copies side by side (print on 5R, cut in half)
     * @return string relative path inside the session folder
     */
    public static function printableFor(string $sessionId, array $meta): string
    {
        if (Settings::get('printing.layout', 'single') === 'two_up') {
            return self::generateSideBySide($sessionId, $meta['strip']);
        }
        return $meta['strip'];
    }
}
