<?php

/*
 * Copyright (C) 2026 IFGF Taipei Zhongli
 *
 * This file is part of the IFGF church operations system.
 *
 * It is free software: you may redistribute it and/or modify it under the terms of
 * the GNU Affero General Public License as published by the Free Software Foundation,
 * either version 3 of the License, or (at your option) any later version.
 *
 * It is distributed in the hope that it will be useful to other churches and
 * ministries, but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Affero General
 * Public License for more details: <https://www.gnu.org/licenses/>.
 *
 * See NOTICE.md for how this relates to the MIT-licensed upstream it builds on.
 */

namespace Tests\Feature\Ifgf;

use Ifgf\ChurchOperations\Models\Branch;
use Ifgf\ChurchOperations\Models\CalendarLink;
use Ifgf\ChurchOperations\Models\ContactPoint;
use Ifgf\ChurchOperations\Models\IcareGroup;
use Ifgf\ChurchOperations\Models\Member;
use Ifgf\ChurchOperations\Models\MemberCategory;
use Ifgf\ChurchOperations\Models\MemberCredential;
use Tests\IfgfTestCase;

/**
 * Feature 1: member registration.
 *
 * Replaces the Google registration Form and onFormSubmit. These go through the real HTTP
 * route, so they cover the FormRequest, the service, the credential issue and the calendar
 * sync as one flow — which is how a member actually experiences it.
 */
class RegistrationTest extends IfgfTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedDemoData();
    }

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Nadia Kusuma',
            'chinese_name' => '林娜迪',
            'email' => 'nadia.kusuma@example.invalid',
            'phone' => '0912777888',
            'birthday' => '1997-04-12',
            'gender' => 'female',
            'branch_id' => Branch::query()->where('code', 'TPE')->value('id'),
            'category_id' => MemberCategory::query()->where('code', 'college')->value('id'),
            'group_id' => null,
            'domicile_taiwan' => 'Taipei - Da an',
        ], $overrides);
    }

    public function test_the_registration_form_renders(): void
    {
        $response = $this->get('/app/register');

        $response->assertOk();
        $response->assertSee('Taipei');
        $response->assertSee('Zhongli');
        // The four categories the quarterly report groups by must all be offerable.
        $response->assertSee('Adult');
        $response->assertSee('College');
    }

    public function test_a_member_can_register_and_is_given_a_qr_immediately(): void
    {
        $response = $this->post('/app/register', $this->validPayload());

        $member = Member::query()->where('full_name', 'Nadia Kusuma')->firstOrFail();

        $response->assertRedirect(route('member.registered', ['member' => $member->public_ref]));

        $this->assertSame('active', $member->status);
        $this->assertSame('林娜迪', $member->chinese_name);
        $this->assertSame('1997-04-12', $member->birthday->toDateString());

        // The whole point of registering: they leave with a working credential.
        $credential = $member->credentials()->active()->firstOrFail();
        $this->assertSame(MemberCredential::TYPE_QR, $credential->type);
        $this->assertStringStartsWith('IFGF1:', $credential->payload());
    }

    /**
     * 🔴 The fix for main.js:590 cleanPhoneNumber(), whose leading-zero branch is a literal
     * no-op. Whatever the member types is stored verbatim; what duplicate detection matches
     * on is the E.164 form.
     */
    public function test_the_phone_is_stored_as_typed_and_matched_as_e164(): void
    {
        $this->post('/app/register', $this->validPayload(['phone' => '0912 777 888']));

        $member = Member::query()->where('full_name', 'Nadia Kusuma')->firstOrFail();

        $phone = $member->contactPoints()->where('type', ContactPoint::TYPE_WHATSAPP)->firstOrFail();

        $this->assertSame('0912 777 888', $phone->value, 'The member sees what they typed.');
        $this->assertSame('886912777888', $phone->normalized_value);
    }

    public function test_the_email_is_normalised_for_matching(): void
    {
        $this->post('/app/register', $this->validPayload(['email' => '  Nadia.Kusuma@Example.INVALID ']));

        $member = Member::query()->where('full_name', 'Nadia Kusuma')->firstOrFail();
        $email = $member->contactPoints()->where('type', ContactPoint::TYPE_EMAIL)->firstOrFail();

        $this->assertSame('nadia.kusuma@example.invalid', $email->normalized_value);
    }

    public function test_registering_twice_with_the_same_email_is_refused(): void
    {
        $this->post('/app/register', $this->validPayload());

        $second = $this->post('/app/register', $this->validPayload([
            'full_name' => 'Someone Else',
            'phone' => '0900111222',
        ]));

        $second->assertSessionHasErrors('email');
        $this->assertSame(0, Member::query()->where('full_name', 'Someone Else')->count());
    }

    /**
     * 🔴 The duplicate the LEGACY system could never catch.
     *
     * checkIfMemberExists() matched on email OR phone, but the phone half compared raw
     * strings — so the same Taiwanese mobile written three ways was three people. Here the
     * second registration uses a DIFFERENT email and the same number in a different format,
     * and must still be refused.
     */
    public function test_a_cross_format_duplicate_phone_is_caught(): void
    {
        $this->post('/app/register', $this->validPayload(['phone' => '0912777888']));

        $second = $this->post('/app/register', $this->validPayload([
            'full_name' => 'Different Person',
            'email' => 'someone.else@example.invalid',
            'phone' => '+886912777888',
        ]));

        $second->assertSessionHasErrors();
        $this->assertSame(0, Member::query()->where('full_name', 'Different Person')->count());
    }

    public function test_registration_requires_a_name_a_branch_and_some_way_to_be_contacted(): void
    {
        $this->post('/app/register', [])->assertSessionHasErrors(['full_name', 'branch_id']);

        $this->post('/app/register', $this->validPayload(['email' => null, 'phone' => null]))
            ->assertSessionHasErrors(['email', 'phone']);
    }

    public function test_a_future_birthday_is_rejected(): void
    {
        $this->post('/app/register', $this->validPayload([
            'birthday' => now()->addDay()->toDateString(),
        ]))->assertSessionHasErrors('birthday');
    }

    /**
     * No iCare is a real answer, not a missing one — 62 of 217 legacy members are in this
     * state. It must produce NO membership row rather than a row pointing at a sentinel
     * group called "Belum mengikuti".
     */
    public function test_a_member_may_register_without_an_icare(): void
    {
        $this->post('/app/register', $this->validPayload(['group_id' => null]));

        $member = Member::query()->where('full_name', 'Nadia Kusuma')->firstOrFail();

        $this->assertSame(0, $member->groupMemberships()->count());
        $this->assertNull($member->currentGroup());
        $this->assertTrue(Member::query()->withoutIcare()->whereKey($member->id)->exists());
    }

    public function test_choosing_an_icare_creates_an_effective_dated_membership(): void
    {
        $group = IcareGroup::query()->where('slug', 'demo-icare-linkou')->firstOrFail();

        $this->post('/app/register', $this->validPayload(['group_id' => $group->id]));

        $member = Member::query()->where('full_name', 'Nadia Kusuma')->firstOrFail();
        $membership = $member->groupMemberships()->firstOrFail();

        $this->assertSame($group->id, $membership->group_id);
        $this->assertSame(now()->toDateString(), $membership->effective_from->toDateString());
        $this->assertNull($membership->effective_to, 'A current membership is an open row.');
    }

    public function test_registering_puts_the_birthday_on_the_church_calendar(): void
    {
        $this->post('/app/register', $this->validPayload());

        $member = Member::query()->where('full_name', 'Nadia Kusuma')->firstOrFail();
        $link = CalendarLink::query()->where('member_id', $member->id)->firstOrFail();

        $this->assertSame(CalendarLink::STATUS_OK, $link->status);
        $this->assertNotNull($link->event_series_id);

        $event = $this->calendar()->event(config('church-operations.calendar.birthday_id'), $link->event_series_id);

        // Name only — never the iCare, branch or contact details (build.md SECURITY 8).
        $this->assertSame('Nadia Kusuma', $event['summary']);
        $this->assertSame('1997-04-12', $event['date']);
    }

    public function test_a_member_without_a_birthday_still_registers(): void
    {
        $this->post('/app/register', $this->validPayload(['birthday' => null]));

        $member = Member::query()->where('full_name', 'Nadia Kusuma')->firstOrFail();

        $this->assertNull($member->birthday);
        $this->assertSame(0, CalendarLink::query()->where('member_id', $member->id)->count());
        $this->assertSame(1, $member->credentials()->count(), 'They still get a QR.');
    }

    public function test_the_welcome_page_shows_the_new_code(): void
    {
        $this->post('/app/register', $this->validPayload());

        $member = Member::query()->where('full_name', 'Nadia Kusuma')->firstOrFail();
        $credential = $member->credentials()->firstOrFail();

        $response = $this->get(route('member.registered', ['member' => $member->public_ref]));

        $response->assertOk();
        $response->assertSee('<svg', false);
        $response->assertSee($credential->short_code);

        // The credential itself must not be recoverable from the page.
        $response->assertDontSee($credential->token_hash);
        $response->assertDontSee('nadia.kusuma@example.invalid');
    }

    public function test_the_welcome_page_404s_on_a_malformed_reference(): void
    {
        // A route pattern rejects a non-UUID before any query runs — on PostgreSQL a
        // uuid-vs-text comparison is a type error that would abort the transaction.
        $this->get('/app/welcome/1')->assertNotFound();
        $this->get('/app/welcome/not-a-uuid')->assertNotFound();
    }

    public function test_a_new_member_is_not_marked_as_demo_data(): void
    {
        $this->post('/app/register', $this->validPayload());

        $member = Member::query()->where('full_name', 'Nadia Kusuma')->firstOrFail();

        // Otherwise the next `ifgf:demo:purge` would delete a real registration.
        $this->assertFalse($member->is_demo);
    }
}
