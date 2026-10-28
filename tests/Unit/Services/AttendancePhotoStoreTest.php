<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Unit\Services;

use App\Services\AttendancePhotoStore;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

/**
 * The size rule of the iCare meeting photo (PRD FR-9): at most 4096 px on the long side and at
 * most 3 MB, close to 3 MB when the photo has the detail for it. The quality between 90 and 80 is
 * calculated from two measurements; below 80 the size is shrunk by the square-root rule.
 * No framework, no disk.
 */
class AttendancePhotoStoreTest extends TestCase
{
    public function test_between_quality_90_and_80_the_quality_is_calculated_and_the_size_kept(): void
    {
        $file = $this->noise(2100, 1900);
        $this->assertGreaterThan(AttendancePhotoStore::MAX_BYTES, $this->jpegSize($file, 90));
        $this->assertLessThanOrEqual(AttendancePhotoStore::MAX_BYTES, $this->jpegSize($file, 80));

        $jpeg = (new AttendancePhotoStore)->toJpeg($file);

        $this->assertLessThanOrEqual(AttendancePhotoStore::MAX_BYTES, strlen($jpeg));
        // A better quality than 80 was chosen, and the file is close to the 3 MB the owner asked for.
        $this->assertGreaterThan($this->jpegSize($file, 80), strlen($jpeg));
        $this->assertGreaterThan(0.9 * AttendancePhotoStore::MAX_BYTES, strlen($jpeg));
        $this->assertSame([2100, 1900, IMAGETYPE_JPEG], array_slice(getimagesizefromstring($jpeg), 0, 3));
    }

    public function test_too_big_even_at_quality_80_shrinks_once_by_the_square_root_rule(): void
    {
        $file = $this->noise(2400, 2200);
        $this->assertGreaterThan(AttendancePhotoStore::MAX_BYTES, $this->jpegSize($file, 80));

        $jpeg = (new AttendancePhotoStore)->toJpeg($file);
        [$width, $height] = getimagesizefromstring($jpeg);

        $this->assertLessThanOrEqual(AttendancePhotoStore::MAX_BYTES, strlen($jpeg));
        $this->assertGreaterThan(0.9 * AttendancePhotoStore::MAX_BYTES, strlen($jpeg));
        $this->assertLessThan(2400, $width);
        $this->assertEqualsWithDelta(2400 / 2200, $width / $height, 0.01);
    }

    public function test_a_small_photo_keeps_its_size(): void
    {
        $jpeg = (new AttendancePhotoStore)->toJpeg($this->noise(320, 200));

        $this->assertSame([320, 200], array_slice(getimagesizefromstring($jpeg), 0, 2));
    }

    public function test_a_tall_photo_is_fitted_by_its_long_side(): void
    {
        $image = imagecreatetruecolor(2500, 5000);
        $path = tempnam(sys_get_temp_dir(), 'tall').'.png';
        imagepng($image, $path);

        $jpeg = (new AttendancePhotoStore)->toJpeg(new UploadedFile($path, 'tall.png', 'image/png', null, true));

        $this->assertSame([2048, 4096], array_slice(getimagesizefromstring($jpeg), 0, 2));
    }

    /** Random pixels: the worst case for JPEG, so the first encoding is far over 3 MB. */
    private function noise(int $width, int $height): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        mt_srand(7);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                imagesetpixel($image, $x, $y, mt_rand(0, 0xFFFFFF));
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'noise').'.png';
        imagepng($image, $path, 1);

        return new UploadedFile($path, 'noise.png', 'image/png', null, true);
    }

    private function jpegSize(UploadedFile $file, int $quality): int
    {
        ob_start();
        imagejpeg(imagecreatefrompng($file->getRealPath()), null, $quality);

        return strlen((string) ob_get_clean());
    }
}
