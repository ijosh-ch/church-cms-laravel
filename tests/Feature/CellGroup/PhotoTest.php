<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\CellGroup;

use App\Models\CellGroup;
use App\Models\CellGroupAttendance;
use App\Models\CellGroupLeader;
use App\Models\Member;
use App\Services\AttendancePhotoStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PRD FR-9 — the meeting photo: any image becomes a JPEG of at most 3 MB and 4096 px, without
 * metadata, on the photos disk at icare/{public_ref}/{date}.jpg; only the group's
 * leaders and the admin see or delete it; a second save keeps it.
 */
class PhotoTest extends TestCase
{
    private CellGroup $group;

    private Member $leader;

    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('photos');
        Carbon::setTestNow(Carbon::parse('2026-10-28 20:00', 'Asia/Taipei'));

        $this->group = CellGroup::factory()->create(['meeting_weekday' => 4]);
        $this->leader = Member::factory()->create();
        CellGroupLeader::factory()->create(['cell_group_id' => $this->group->id, 'member_id' => $this->leader->id]);
        $this->member = Member::factory()->create(['cell_group_id' => $this->group->id]);
    }

    public function test_a_large_png_is_stored_as_a_jpeg_on_the_photos_disk(): void
    {
        $photo = UploadedFile::fake()->image('meeting.png', 4800, 3200);

        $this->actingAs($this->leader)->put($this->url(), $this->form(['photo' => $photo]))->assertSessionHasNoErrors();

        $path = 'icare/'.$this->group->public_ref.'/2026-10-22.jpg';
        $this->assertSame($path, CellGroupAttendance::sole()->photo_path);
        Storage::disk('photos')->assertExists($path);

        $bytes = Storage::disk('photos')->get($path);
        [$width, $height, $type] = getimagesizefromstring($bytes);
        $this->assertSame(IMAGETYPE_JPEG, $type);
        $this->assertSame([4096, 2731], [$width, $height]);
        $this->assertLessThanOrEqual(AttendancePhotoStore::MAX_BYTES, strlen($bytes));
        $this->assertStringNotContainsString('Exif', substr($bytes, 0, 64));
    }

    public function test_a_file_that_is_not_an_image_is_refused(): void
    {
        $fake = UploadedFile::fake()->createWithContent('meeting.jpg', '<?php echo "not a photo";');

        $this->actingAs($this->leader)->from($this->url())
            ->put($this->url(), $this->form(['photo' => $fake]))
            ->assertSessionHasErrors('photo');

        $this->assertSame(0, CellGroupAttendance::count());
        $this->assertSame([], Storage::disk('photos')->allFiles());
    }

    public function test_saving_again_keeps_the_photo_without_asking_for_it(): void
    {
        $this->actingAs($this->leader)->put($this->url(), $this->form(['photo' => UploadedFile::fake()->image('a.jpg', 800, 600)]));
        $this->actingAs($this->leader)->put($this->url(), $this->form())->assertSessionHasNoErrors();

        $this->assertNotNull(CellGroupAttendance::sole()->photo_path);
    }

    public function test_the_leader_sees_the_photo_privately_and_others_do_not(): void
    {
        $this->actingAs($this->leader)->put($this->url(), $this->form(['photo' => UploadedFile::fake()->image('a.jpg', 800, 600)]));
        $photoUrl = '/icare/'.$this->group->public_ref.'/attendance/2026-10-22/photo';

        $response = $this->actingAs($this->leader)->get($photoUrl)->assertOk();
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->actingAs($this->member)->get($photoUrl)->assertForbidden();
        $this->actingAs($this->leader)->get('/icare/'.$this->group->public_ref.'/attendance/2026-10-15/photo')->assertNotFound();
    }

    public function test_deleting_the_photo_removes_the_file(): void
    {
        $this->actingAs($this->leader)->put($this->url(), $this->form(['photo' => UploadedFile::fake()->image('a.jpg', 800, 600)]));
        $path = CellGroupAttendance::sole()->photo_path;

        $this->actingAs($this->leader)
            ->delete('/icare/'.$this->group->public_ref.'/attendance/2026-10-22/photo')
            ->assertRedirect($this->url().'?date=2026-10-22');

        Storage::disk('photos')->assertMissing($path);
        $this->assertNull(CellGroupAttendance::sole()->photo_path);
    }

    private function url(): string
    {
        return '/icare/'.$this->group->public_ref.'/attendance';
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function form(array $extra = []): array
    {
        return [
            'met_on' => '2026-10-22',
            'members' => [$this->member->public_ref => ['seen' => '1', 'came' => '1']],
            ...$extra,
        ];
    }
}
