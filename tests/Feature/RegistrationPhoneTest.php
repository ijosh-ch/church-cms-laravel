<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature;

use App\Enums\PhoneCountry;
use App\Models\Member;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * PRD FR-1 field 7 — the WhatsApp number: a country code chosen first, then a number that has
 * the shape of a mobile number in that country.
 */
class RegistrationPhoneTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Mail::fake();
    }

    public function test_the_form_offers_the_country_codes_with_taiwan_chosen(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('name="phone_country"', false)
            ->assertSee('<option value="886" data-example="0912 345 678" selected', false)
            ->assertSee('+62 Indonesia')
            ->assertSee('Other country')
            ->assertSee('placeholder="0912 345 678"', false);
    }

    public function test_the_number_is_stored_with_the_chosen_country_code(): void
    {
        $cases = [
            ['886', '0912 345 678', '886912345678'],
            ['886', '987-654-321', '886987654321'],
            ['62', '0812 3456 7890', '6281234567890'],
            ['60', '012 345 6789', '60123456789'],
            ['65', '8123 4567', '6581234567'],
            ['852', '5123 4567', '85251234567'],
            ['other', '84 912 345 678', '84912345678'],
            ['other', '+63 917 123 4567', '639171234567'],
            // A number typed with its own code keeps it, whatever was chosen.
            ['886', '+62 813 1111 2222', '6281311112222'],
        ];

        foreach ($cases as $i => [$country, $typed, $stored]) {
            $this->post('/register', $this->stepOne([
                'email' => "phone{$i}@example.com",
                'phone_country' => $country,
                'phone' => $typed,
            ]))->assertSessionHasNoErrors()->assertRedirect();

            $this->assertSame($stored, Member::where('email', "phone{$i}@example.com")->value('phone'), "$country $typed");

            auth()->logout();
        }
    }

    public function test_a_number_that_does_not_fit_the_country_is_refused_and_says_how_to_write_it(): void
    {
        foreach ([
            ['886', '0212 345 678'],   // a Taipei landline, not a mobile
            ['886', '0912 345 67'],    // one digit short
            ['62', '0912 345 678'],    // a Taiwanese number with Indonesia chosen
            ['65', '1234 5678'],
            ['852', '1234 5678'],
            ['other', '123'],
        ] as [$country, $typed]) {
            $this->post('/register', $this->stepOne(['phone_country' => $country, 'phone' => $typed]))
                ->assertSessionHasErrors('phone');
        }

        $this->post('/register', $this->stepOne(['phone_country' => '62', 'phone' => '0912 345 678']))
            ->assertInvalid(['phone' => 'Write it like 0812 3456 7890'])
            ->assertInvalid(['phone' => '+62 Indonesia']);

        $this->assertSame(0, Member::count());
    }

    public function test_an_unknown_country_code_is_refused(): void
    {
        $this->post('/register', $this->stepOne(['phone_country' => '999']))->assertSessionHasErrors('phone_country');
    }

    public function test_a_form_sent_back_shows_the_number_as_it_was_typed(): void
    {
        $this->from('/register')
            ->post('/register', $this->stepOne(['phone_country' => '62', 'phone' => '0912 345 678']))
            ->assertRedirect('/register');

        $this->get('/register')
            ->assertSee('value="0912 345 678"', false)
            ->assertSee('<option value="62" data-example="0812 3456 7890" selected', false);
    }

    public function test_a_number_posted_without_a_country_code_is_read_as_taiwanese(): void
    {
        $this->post('/register', $this->stepOne(['phone' => '0987 654 321']))->assertSessionHasNoErrors();

        $this->assertSame('886987654321', Member::sole()->phone);
    }

    public function test_the_country_of_an_international_number_is_found_from_its_code(): void
    {
        $this->assertSame(PhoneCountry::Taiwan, PhoneCountry::fromNumber('886912345678'));
        $this->assertSame(PhoneCountry::HongKong, PhoneCountry::fromNumber('85251234567'));
        $this->assertSame(PhoneCountry::Indonesia, PhoneCountry::fromNumber('6281234567890'));
        $this->assertNull(PhoneCountry::fromNumber('639171234567'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function stepOne(array $overrides = []): array
    {
        return [
            'email' => 'new.person@example.com',
            'full_name' => 'New Person',
            'chinese_name' => '',
            'birthday' => '2001-07-09',
            'domicile_taiwan' => 'Zhongli',
            'line_id' => '',
            'phone_country' => '886',
            'phone' => '0912 345 678',
            'branch' => 'ZL',
            'icare' => 'none',
            'attend_again' => 'no',
            ...$overrides,
        ];
    }
}
