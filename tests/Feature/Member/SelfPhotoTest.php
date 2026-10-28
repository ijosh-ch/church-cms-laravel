<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\Member;

use App\Models\Member;
use App\Services\AttendancePhotoStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A member's own photo (owner, 2026-10-28): optional, only with consent (STANDARDS §4.5),
 * a JPEG of at most 1 MB and 1600 px without metadata on the photos disk, seen only by the
 * member, and deleting it withdraws the consent. Both are written to the audit log.
 */
class SelfPhotoTest extends TestCase
{
    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('photos');
        $this->member = Member::factory()->create();
    }

    public function test_signed_out_the_photo_pages_go_to_sign_in(): void
    {
        $this->get('/profile/photo')->assertRedirect('/sign-in');
        $this->post('/profile/photo')->assertRedirect('/sign-in');
        $this->get('/profile/photo/image')->assertRedirect('/sign-in');
        $this->delete('/profile/photo')->assertRedirect('/sign-in');
    }

    public function test_the_qr_page_offers_the_photo_until_there_is_one(): void
    {
        $this->actingAs($this->member)->get('/me')->assertSee('Tambah foto diri (opsional)');

        $this->member->forceFill(['photo_path' => 'members/x.jpg', 'photo_consent_at' => now()])->save();

        $this->actingAs($this->member)->get('/me')->assertDontSee('Tambah foto diri (opsional)');
    }

    public function test_the_page_says_what_is_kept_and_why(): void
    {
        $this->actingAs($this->member)->get('/profile/photo')
            ->assertOk()
            ->assertSee('Apa yang kami simpan, dan untuk apa')
            ->assertSee('Saya setuju');
    }

    public function test_without_consent_nothing_is_kept(): void
    {
        $this->actingAs($this->member)->from('/profile/photo')
            ->post('/profile/photo', ['photo' => UploadedFile::fake()->image('me.jpg', 900, 1200)])
            ->assertSessionHasErrors('consent');

        $this->assertNull($this->member->fresh()->photo_path);
        $this->assertSame([], Storage::disk('photos')->allFiles());
    }

    public function test_with_consent_the_photo_is_kept_small_and_private_and_logged(): void
    {
        Log::shouldReceive('channel')->once()->with('audit')->andReturnSelf();
        Log::shouldReceive('info')->once()->with('member.photo_consent', [
            'actor' => $this->member->id,
            'member' => $this->member->id,
            'given' => 'yes',
        ]);

        $this->actingAs($this->member)
            ->post('/profile/photo', ['photo' => UploadedFile::fake()->image('me.png', 3000, 4000), 'consent' => '1'])
            ->assertRedirect('/profile/photo');

        $member = $this->member->fresh();
        $this->assertSame('members/'.$member->public_ref.'.jpg', $member->photo_path);
        $this->assertNotNull($member->photo_consent_at);
        $this->assertArrayNotHasKey('photo_path', $member->toArray());

        $bytes = Storage::disk('photos')->get($member->photo_path);
        [$width, $height, $type] = getimagesizefromstring($bytes);
        $this->assertSame([1200, 1600, IMAGETYPE_JPEG], [$width, $height, $type]);
        $this->assertLessThanOrEqual(AttendancePhotoStore::SELF_MAX_BYTES, strlen($bytes));

        $response = $this->actingAs($member)->get('/profile/photo/image')->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_a_file_that_is_not_an_image_is_refused(): void
    {
        $this->actingAs($this->member)->from('/profile/photo')
            ->post('/profile/photo', ['photo' => UploadedFile::fake()->createWithContent('me.jpg', 'not a photo'), 'consent' => '1'])
            ->assertSessionHasErrors('photo');

        $this->assertNull($this->member->fresh()->photo_path);
    }

    public function test_deleting_the_photo_withdraws_the_consent(): void
    {
        $this->actingAs($this->member)->post('/profile/photo', ['photo' => UploadedFile::fake()->image('me.jpg', 600, 800), 'consent' => '1']);
        $path = $this->member->fresh()->photo_path;

        $this->actingAs($this->member)->delete('/profile/photo')->assertRedirect('/profile/photo');

        Storage::disk('photos')->assertMissing($path);
        $this->assertNull($this->member->fresh()->photo_path);
        $this->assertNull($this->member->fresh()->photo_consent_at);
        $this->actingAs($this->member)->get('/profile/photo/image')->assertNotFound();
    }

    public function test_each_member_only_ever_gets_their_own_photo(): void
    {
        $other = Member::factory()->create();
        $this->actingAs($other)->post('/profile/photo', ['photo' => UploadedFile::fake()->image('o.jpg', 600, 800), 'consent' => '1']);

        // The image address names nobody: it always answers with the signed-in member's own photo.
        $this->actingAs($this->member)->get('/profile/photo/image')->assertNotFound();
    }
}
