<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Unit\Rules;

use App\Rules\GoogleMapsUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** PRD FR-9: an iCare's Maps link is an https link to Google Maps, never anywhere else. */
class GoogleMapsUrlTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function accepted(): array
    {
        return [
            'short link' => ['https://maps.app.goo.gl/AbC123xyz'],
            'old short link' => ['https://goo.gl/maps/AbC123'],
            'google.com/maps' => ['https://www.google.com/maps/place/NTUST/@25.01,121.54,17z'],
            'bare google.com/maps' => ['https://google.com/maps?q=25.01,121.54'],
            'maps.google.com' => ['https://maps.google.com/?q=Zhongli'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function refused(): array
    {
        return [
            'plain http' => ['http://maps.app.goo.gl/AbC123'],
            'another site' => ['https://example.com/maps'],
            'look-alike host' => ['https://maps.app.goo.gl.example.com/x'],
            'google search, not maps' => ['https://www.google.com/search?q=maps'],
            'goo.gl not maps' => ['https://goo.gl/abc'],
            'path prefix trick' => ['https://www.google.com/mapsevil'],
            'credentials' => ['https://user@maps.app.goo.gl/x'],
            'port' => ['https://maps.app.goo.gl:8443/x'],
            'javascript' => ['javascript:alert(1)'],
            'space' => ['https://maps.app.goo.gl/a b'],
            'not a url' => ['Taipei 101'],
        ];
    }

    #[DataProvider('accepted')]
    public function test_google_maps_links_are_accepted(string $url): void
    {
        $this->assertTrue(GoogleMapsUrl::accepts($url));
    }

    #[DataProvider('refused')]
    public function test_other_links_are_refused(string $url): void
    {
        $this->assertFalse(GoogleMapsUrl::accepts($url));
    }
}
