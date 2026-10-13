<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * A session row holds personal data for a while — page one of the registration form, an
 * admin's search — so it is never stored readable, whatever a machine's .env says.
 */
class SessionConfigTest extends TestCase
{
    public function test_session_rows_are_always_encrypted(): void
    {
        $this->assertTrue(config('session.encrypt'));
    }

    public function test_a_stored_session_cannot_be_read_without_the_key(): void
    {
        $this->seed();
        config(['session.driver' => 'database']);

        $this->post('/register', [
            'email' => 'demo.student@example.com',
            'full_name' => 'Readable Name',
            'birthday' => '2004-03-15',
            'domicile_taiwan' => 'Taipei',
            'phone' => '0912 345 678',
            'branch' => 'TPE',
            'attend_again' => 'yes',
        ]);

        $payloads = $this->app['db']->table('sessions')->pluck('payload');

        $this->assertNotEmpty($payloads);

        foreach ($payloads as $payload) {
            $this->assertStringNotContainsString('Readable Name', (string) base64_decode($payload));
        }
    }
}
