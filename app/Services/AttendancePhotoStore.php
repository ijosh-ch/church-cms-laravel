<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services;

use App\Models\CellGroup;
use App\Models\Member;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The photo of an iCare meeting (PRD FR-9). Whatever image arrives — the browser has usually
 * made it a JPEG already — is decoded, turned upright, fitted to 4096 px on its long side and
 * written again as the best-quality JPEG that stays under 3 MB (owner, 2026-10-28: large enough
 * for face detection later). Re-encoding drops every bit of metadata, the GPS position included,
 * and anything that is not really an image fails to decode.
 *
 * Stored on the `photos` disk: outside the code, outside public/, readable only by the web-server
 * user on the server (config/filesystems.php, docs/DEPLOY.md §8).
 */
class AttendancePhotoStore
{
    public const MAX_BYTES = 3_000_000;

    public const MAX_SIDE = 4096;

    /** The quality range: start at 90, never go below 80 (encode() calculates where to land). */
    public const QUALITY_HIGH = 90;

    public const QUALITY_LOW = 80;

    /** Aim 3 % under the cap when shrinking, so the estimate lands under it at the first try. */
    public const SAFETY = 0.97;

    /** A shrunk photo is good enough once it reaches 90 % of the cap. */
    public const CLOSE = 0.9;

    public const SELF_MAX_BYTES = 1_000_000;

    public const SELF_MAX_SIDE = 1600;

    /** Refuse to decode anything larger: a 20-megapixel image already needs ~80 MB of memory. */
    public const MAX_PIXELS = 20_000_000;

    public function store(UploadedFile $file, CellGroup $group, string $metOn): string
    {
        $jpeg = $this->toJpeg($file);
        $path = self::pathFor($group, $metOn);

        Storage::disk('photos')->put($path, $jpeg);

        return $path;
    }

    /**
     * A member's own photo (owner, 2026-10-28): a face filling most of the picture needs far less
     * than a group photo, so 1600 px and 1 MB. members/{public_ref}.jpg on the photos disk.
     */
    public function storeSelf(UploadedFile $file, Member $member): string
    {
        $jpeg = $this->toJpeg($file, self::SELF_MAX_BYTES, self::SELF_MAX_SIDE);
        $path = "members/{$member->public_ref}.jpg";

        Storage::disk('photos')->put($path, $jpeg);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('photos')->delete($path);
        }
    }

    /** icare/{public_ref}/{date}.jpg on the photos disk — a random reference and a date, nothing personal. */
    public static function pathFor(CellGroup $group, string $metOn): string
    {
        return "icare/{$group->public_ref}/{$metOn}.jpg";
    }

    public function toJpeg(UploadedFile $file, int $maxBytes = self::MAX_BYTES, int $maxSide = self::MAX_SIDE): string
    {
        $data = (string) file_get_contents($file->getRealPath());
        $size = @getimagesizefromstring($data);

        if ($size === false) {
            $this->fail(__('This file is not a photo we can read. Save it as JPG and try again.'));
        }

        if ($size[0] * $size[1] > self::MAX_PIXELS) {
            $this->fail(__('This photo is too large. Take it again with a lower resolution.'));
        }

        $image = @imagecreatefromstring($data);

        if (! $image instanceof GdImage) {
            $this->fail(__('This file is not a photo we can read. Save it as JPG and try again.'));
        }

        $image = $this->upright($image, $file, $size[2] ?? null);
        $image = $this->fit($image, $maxSide);

        return $this->encode($image, $maxBytes);
    }

    /** Phone cameras store a JPEG on its side and say so in EXIF; turn it the right way up. */
    private function upright(GdImage $image, UploadedFile $file, ?int $type): GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($file->getRealPath())['Orientation'] ?? 1);
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }

    private function fit(GdImage $image, int $maxSide): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $maxSide / max($width, $height));

        if ($scale >= 1) {
            return $image;
        }

        return imagescale($image, (int) round($width * $scale), (int) round($height * $scale), IMG_BICUBIC) ?: $image;
    }

    /**
     * The best JPEG under 3 MB, calculated from two measurements instead of trial and error.
     *
     * 1. Encode at quality 90. Under 3 MB: done (one encoding).
     * 2. Encode at quality 80. Between the two, JPEG size falls about exponentially with the
     *    quality, so the quality that lands on 3 MB is
     *        q = 80 + 10 · ln(3 MB / size80) / ln(size90 / size80)
     *    Encode at that quality (rounded down; one step lower in the rare case it is still over).
     * 3. Still over at quality 80: at a fixed quality the size grows with the pixel count, so
     *    shrink both sides by √(3 MB · 0.97 / size80), measure, and correct the scale the same way
     *    (at most three tries) until the file is between 2.7 and 3 MB.
     *
     * Usually one to four encodings, keeping the resolution — what face detection needs most —
     * and lowering the quality only as far as 80.
     */
    private function encode(GdImage $image, int $maxBytes): string
    {
        $high = $this->jpeg($image, self::QUALITY_HIGH);

        if (strlen($high) <= $maxBytes) {
            return $high;
        }

        $low = $this->jpeg($image, self::QUALITY_LOW);

        if (strlen($low) <= $maxBytes) {
            $quality = self::QUALITY_LOW + (int) floor(
                (self::QUALITY_HIGH - self::QUALITY_LOW)
                * log($maxBytes / strlen($low)) / log(strlen($high) / strlen($low))
            );

            for (; $quality > self::QUALITY_LOW; $quality--) {
                $jpeg = $this->jpeg($image, $quality);

                if (strlen($jpeg) <= $maxBytes) {
                    return $jpeg;
                }
            }

            return $low;
        }

        // Each try measures again and corrects the scale from the original, so a photo whose
        // bytes per pixel change when shrunk still settles between 90 % and 100 % of the cap.
        $original = $image;
        $scale = 1.0;
        $jpeg = $low;
        $best = null;

        for ($try = 0; $try < 3; $try++) {
            $scale = min(1.0, $scale * sqrt($maxBytes * self::SAFETY / strlen($jpeg)));
            $jpeg = $this->jpeg($this->scaled($original, $scale), self::QUALITY_LOW);

            if (strlen($jpeg) <= $maxBytes) {
                $best = $jpeg;

                if (strlen($jpeg) >= self::CLOSE * $maxBytes) {
                    break;
                }
            }
        }

        while ($best === null) {
            $scale *= 0.8;
            $jpeg = $this->jpeg($this->scaled($original, $scale), self::QUALITY_LOW);
            $best = strlen($jpeg) <= $maxBytes ? $jpeg : null;
        }

        return $best;
    }

    private function scaled(GdImage $image, float $scale): GdImage
    {
        if ($scale >= 1.0) {
            return $image;
        }

        $width = max(1, (int) round(imagesx($image) * $scale));
        $height = max(1, (int) round(imagesy($image) * $scale));

        return imagescale($image, $width, $height, IMG_BICUBIC) ?: $image;
    }

    private function jpeg(GdImage $image, int $quality): string
    {
        ob_start();
        imagejpeg($image, null, $quality);

        return (string) ob_get_clean();
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['photo' => $message]);
    }
}
