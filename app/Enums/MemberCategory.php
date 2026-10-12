<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Enums;

use DateTimeImmutable;
use DateTimeInterface;

enum MemberCategory: string
{
    case Kids = 'kids';
    case TeensYouth = 'teens_youth';
    case College = 'college';
    case Adult = 'adult';

    /** The occupation value the formula tests for; every other occupation is an adult. */
    public const STUDENT = 'student';

    /** A family member with no education level is a kid below this age. */
    public const KIDS_BELOW_AGE = 13;

    /**
     * PRD FR-5 — the workbook's `Kategori` formula, unchanged. First match wins.
     */
    public static function for(
        ?string $occupation,
        ?EducationLevel $education,
        ?DateTimeInterface $birthday = null,
        ?DateTimeInterface $on = null,
    ): self {
        if ($education === EducationLevel::Sd) {
            return self::Kids;
        }

        if ($education === null && $birthday !== null) {
            $age = $birthday->diff($on ?? new DateTimeImmutable('today'))->y;

            if ($age < self::KIDS_BELOW_AGE) {
                return self::Kids;
            }
        }

        if ($occupation === self::STUDENT) {
            return match ($education) {
                EducationLevel::Smp, EducationLevel::Sma => self::TeensYouth,
                EducationLevel::S1, EducationLevel::Diploma => self::College,
                default => self::Adult,
            };
        }

        return self::Adult;
    }

    public function label(): string
    {
        return match ($this) {
            self::Kids => 'Kids',
            self::TeensYouth => 'Teens and Youth',
            self::College => 'College',
            self::Adult => 'Adult',
        };
    }
}
