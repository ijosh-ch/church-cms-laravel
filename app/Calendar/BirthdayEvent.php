<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Calendar;

use App\Models\Member;

/**
 * What a member's birthday looks like in the calendar (PRD FR-6).
 *
 *   Title        🎂 Alicia Ardella (林淑愛)'s Birthday
 *   Description  English Name: Alicia Ardella
 *                Chinese Name: 林淑愛
 *                Year of Birth: 2002
 *                iCare: iCare Hsinchu
 *
 * An all-day event on the day of birth, repeating every year until the birth year + 99,
 * with reminders one week and one day before. Nothing in it changes from year to year, so
 * the calendar is only written when the member's record changes.
 */
final readonly class BirthdayEvent
{
    /** The series ends this many years after the birth. */
    public const YEARS = 99;

    /** Popup reminders: one week and one day before, in minutes. */
    public const REMINDER_MINUTES = [10080, 1440];

    public function __construct(
        public string $title,
        public string $description,
        /** The day of birth, Y-m-d: the first occurrence of the series. */
        public string $date,
        /** The last day the series may occur on, Y-m-d. */
        public string $until,
    ) {}

    public static function forMember(Member $member): self
    {
        $birthday = $member->birthday;
        $icare = $member->icareTeam?->name;
        $name = trim(preg_replace('/\s+/', ' ', $member->full_name));
        $chineseName = trim((string) $member->chinese_name);

        return new self(
            title: $chineseName === ''
                ? "🎂 {$name}'s Birthday"
                : "🎂 {$name} ({$chineseName})'s Birthday",
            description: implode("\n", [
                'English Name: '.$name,
                'Chinese Name: '.($chineseName === '' ? '-' : $chineseName),
                'Year of Birth: '.$birthday->year,
                'iCare: '.($icare === null ? '-' : 'iCare '.$icare),
            ]),
            date: $birthday->toDateString(),
            until: $birthday->copy()->addYears(self::YEARS)->toDateString(),
        );
    }
}
