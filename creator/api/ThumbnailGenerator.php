<?php
/**
 * creator/api/ThumbnailGenerator.php
 * ------------------------------------------------------------------
 * Generates the two auto-generated assets required by the spec:
 *
 *   thumbnail.png   ~300 px tall — frame with coloured placeholder tiles
 *                   (no real photos needed — fast, always available)
 *
 *   preview.png     same size as the template output — frame composited
 *                   over the 4 sample "guest" photos so the gallery card
 *                   shows a realistic example of the finished strip
 *
 * Both are written into the template's folder.  The Compositor class
 * (includes/Compositor.php) handles the real-session compositing; this
 * class re-implements a lightweight subset of the same logic so the
 * creator module doesn't pull in the full session machinery.
 * ------------------------------------------------------------------
 */
class ThumbnailGenerator
{
    /**
     * Regenerate thumbnail.png and (optionally) preview.png for a template.
     *
     * @param string $templateDir  Absolute path to the template folder
     * @param array  $config       The template's config array
     * @param bool   $withPreview  Also generate preview.png using sample photos
     */
    public static function generate(string $templateDir, array $config, bool $withPreview = true): void
    {
        $frameFile = $templateDir . '/' . ($config['frame'] ?? 'frame.png');
        if (!is_file($frameFile)) return;

        $outW = (int)($config['output']['width']  ?? $config['width']  ?? 0);
        $outH = (int)($config['output']['height'] ?? $config['height'] ?? 0);
        if ($outW < 1 || $outH < 1) return;

        $photos = $config['photos'] ?? [];

        // ---- thumbnail.png (placeholder tiles, no real photos) ---------------
        self::makeThumbnail($templateDir, $frameFile, $outW, $outH, $photos);

        // ---- preview.png (composited with sample images) ---------------------
        if ($withPreview) {
            self::makePreview($templateDir, $frameFile, $outW, $outH, $photos);
        }
    }

    // -----------------------------------------------------------------------
    private static function makeThumbnail(
        string $dir, string $frameFile,
        int $outW, int $outH, array $photos
    ): void {
        $canvas = self::blankCanvas($outW, $outH);

        // Fill the thumbnail background white so the card reads cleanly.
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $outW, $outH, $white);

        // Draw soft coloured placeholder tiles for each slot.
        $colours = [[180, 210, 255], [255, 200, 170], [170, 240, 200], [255, 230, 150]];
        foreach ($photos as $i => $slot) {
            [$r, $g, $b] = $colours[$i % count($colours)];
            $fill = imagecolorallocate($canvas, $r, $g, $b);
            imagefilledrectangle(
                $canvas,
                (int)$slot['x'], (int)$slot['y'],
                (int)$slot['x'] + (int)$slot['width'] - 1,
                (int)$slot['y'] + (int)$slot['height'] - 1,
                $fill
            );
        }

        // Composite the frame overlay on top.
        self::overlayFrame($canvas, $frameFile, $outW, $outH);

        // Downscale to ~300 px tall and write.
        $thumbH = 300;
        $thumbW = (int)round($outW * $thumbH / $outH);
        $thumb  = imagecreatetruecolor($thumbW, $thumbH);
        imagesavealpha($thumb, true);
        imagealphablending($thumb, false);
        imagefill($thumb, 0, 0, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
        imagecopyresampled($thumb, $canvas, 0, 0, 0, 0, $thumbW, $thumbH, $outW, $outH);
        imagepng($thumb, $dir . '/thumbnail.png', 6);
        imagedestroy($thumb);
        imagedestroy($canvas);
    }

    private static function makePreview(
        string $dir, string $frameFile,
        int $outW, int $outH, array $photos
    ): void {
        $canvas = self::blankCanvas($outW, $outH);
        $white  = imagecolorallocate($canvas, 255, 255, 255);
        imagefilledrectangle($canvas, 0, 0, $outW, $outH, $white);

        $samplesDir = ROOT_PATH . '/assets/img/samples';
        foreach ($photos as $i => $slot) {
            $samplePath = $samplesDir . '/sample' . (($i % 4) + 1) . '.jpg';
            if (!is_file($samplePath)) continue;
            self::stampPhoto($canvas, $samplePath, $slot);
        }

        self::overlayFrame($canvas, $frameFile, $outW, $outH);
        imagepng($canvas, $dir . '/preview.png', 6);
        imagedestroy($canvas);
    }

    // ---- Shared helpers ---------------------------------------------------

    private static function blankCanvas(int $w, int $h)
    {
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, true);
        imagesavealpha($img, true);
        return $img;
    }

    private static function overlayFrame($canvas, string $frameFile, int $outW, int $outH): void
    {
        $frame = self::loadImage($frameFile);
        if (!$frame) return;
        imagealphablending($canvas, true);
        imagecopyresampled($canvas, $frame, 0, 0, 0, 0, $outW, $outH, imagesx($frame), imagesy($frame));
        imagedestroy($frame);
    }

    private static function stampPhoto($canvas, string $path, array $slot): void
    {
        $src = self::loadImage($path);
        if (!$src) return;

        $boxW = (int)$slot['width'];
        $boxH = (int)$slot['height'];
        $srcW = imagesx($src);
        $srcH = imagesy($src);

        $scale = max($boxW / $srcW, $boxH / $srcH);
        $cropW = (int)round($boxW / $scale);
        $cropH = (int)round($boxH / $scale);
        $cropX = (int)round(($srcW - $cropW) / 2);
        $cropY = (int)round(($srcH - $cropH) / 2);

        $fitted = imagecreatetruecolor($boxW, $boxH);
        imagecopyresampled($fitted, $src, 0, 0, $cropX, $cropY, $boxW, $boxH, $cropW, $cropH);
        imagedestroy($src);

        $rotation = (float)($slot['rotation'] ?? 0);
        if (abs($rotation) > 0.01) {
            imagealphablending($fitted, false);
            imagesavealpha($fitted, true);
            $transp   = imagecolorallocatealpha($fitted, 0, 0, 0, 127);
            $rotated  = imagerotate($fitted, -$rotation, $transp);
            imagesavealpha($rotated, true);
            imagedestroy($fitted);

            // Centre the rotated image over the slot.
            $rx = (int)$slot['x'] - (int)round((imagesx($rotated) - $boxW) / 2);
            $ry = (int)$slot['y'] - (int)round((imagesy($rotated) - $boxH) / 2);
            imagealphablending($canvas, true);
            imagecopy($canvas, $rotated, $rx, $ry, 0, 0, imagesx($rotated), imagesy($rotated));
            imagedestroy($rotated);
            return;
        }

        imagealphablending($canvas, true);
        imagecopy($canvas, $fitted, (int)$slot['x'], (int)$slot['y'], 0, 0, $boxW, $boxH);
        imagedestroy($fitted);
    }

    private static function loadImage(string $path)
    {
        $info = @getimagesize($path);
        if (!$info) return null;
        switch ($info[2]) {
            case IMAGETYPE_JPEG: return @imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:
                $img = @imagecreatefrompng($path);
                if ($img) imagesavealpha($img, true);
                return $img;
            case IMAGETYPE_WEBP: return @imagecreatefromwebp($path);
            default: return null;
        }
    }
}
