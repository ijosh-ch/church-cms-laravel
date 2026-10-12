<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace Tests\Unit;

use App\Enums\EducationLevel;
use App\Enums\MemberCategory;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PRD FR-5 — the workbook's `Kategori` formula. First match wins:
 *   1. education sd                              → Kids
 *   2. student and education smp or sma          → Teens and Youth
 *   3. student and education s1 or diploma       → College
 *   4. otherwise                                 → Adult
 * plus: no education level and age under 13      → Kids
 *
 * A pure unit test: no database, no framework.
 */
class MemberCategoryTest extends TestCase
{
    #[DataProvider('formula')]
    public function test_the_formula(?string $occupation, ?EducationLevel $education, MemberCategory $expected): void
    {
        $this->assertSame($expected, MemberCategory::for($occupation, $education));
    }

    /**
     * @return array<string, array{?string, ?EducationLevel, MemberCategory}>
     */
    public static function formula(): array
    {
        return [
            // Rule 1 — sd wins whatever the occupation is.
            'sd student' => ['student', EducationLevel::Sd, MemberCategory::Kids],
            'sd with no occupation' => [null, EducationLevel::Sd, MemberCategory::Kids],
            'sd worker (first match wins)' => ['worker', EducationLevel::Sd, MemberCategory::Kids],

            // Rule 2
            'smp student' => ['student', EducationLevel::Smp, MemberCategory::TeensYouth],
            'sma student' => ['student', EducationLevel::Sma, MemberCategory::TeensYouth],

            // Rule 3
            's1 student' => ['student', EducationLevel::S1, MemberCategory::College],
            'diploma student' => ['student', EducationLevel::Diploma, MemberCategory::College],

            // Rule 4 — a student above s1 is an adult, as in the workbook.
            's2 student' => ['student', EducationLevel::S2, MemberCategory::Adult],
            's3 student' => ['student', EducationLevel::S3, MemberCategory::Adult],
            'student with no education level' => ['student', null, MemberCategory::Adult],

            // Rule 4 — rules 2 and 3 need the occupation to be student.
            'sma worker' => ['worker', EducationLevel::Sma, MemberCategory::Adult],
            'smp homemaker' => ['homemaker', EducationLevel::Smp, MemberCategory::Adult],
            's1 worker' => ['worker', EducationLevel::S1, MemberCategory::Adult],
            'diploma homemaker' => ['homemaker', EducationLevel::Diploma, MemberCategory::Adult],
            'typed occupation' => ['Pastor', EducationLevel::S1, MemberCategory::Adult],
            'occupation is matched exactly' => ['Student', EducationLevel::S1, MemberCategory::Adult],
            'nothing known (a visitor)' => [null, null, MemberCategory::Adult],
        ];
    }

    public function test_a_family_member_without_an_education_level_is_a_kid_under_13(): void
    {
        $today = new DateTimeImmutable('2026-10-04');

        $this->assertSame(
            MemberCategory::Kids,
            MemberCategory::for(null, null, new DateTimeImmutable('2020-01-15'), $today),
        );

        // The day before the 13th birthday is still 12.
        $this->assertSame(
            MemberCategory::Kids,
            MemberCategory::for(null, null, new DateTimeImmutable('2013-10-05'), $today),
        );
    }

    public function test_the_age_rule_stops_at_13(): void
    {
        $today = new DateTimeImmutable('2026-10-04');

        // 13 today.
        $this->assertSame(
            MemberCategory::Adult,
            MemberCategory::for(null, null, new DateTimeImmutable('2013-10-04'), $today),
        );

        $this->assertSame(
            MemberCategory::Adult,
            MemberCategory::for('worker', null, new DateTimeImmutable('1990-06-01'), $today),
        );
    }

    public function test_the_age_rule_applies_only_when_there_is_no_education_level(): void
    {
        $today = new DateTimeImmutable('2026-10-04');
        $twelve = new DateTimeImmutable('2014-03-01');

        // A 12-year-old in smp is placed by the formula, not by age.
        $this->assertSame(
            MemberCategory::TeensYouth,
            MemberCategory::for('student', EducationLevel::Smp, $twelve, $today),
        );

        // And a student under 13 with no level recorded is still a kid.
        $this->assertSame(
            MemberCategory::Kids,
            MemberCategory::for('student', null, $twelve, $today),
        );
    }

    public function test_age_defaults_to_today(): void
    {
        $this->assertSame(
            MemberCategory::Kids,
            MemberCategory::for(null, null, new DateTimeImmutable('-5 years')),
        );
    }

    public function test_every_category_has_the_workbook_label(): void
    {
        $this->assertSame(
            ['Kids', 'Teens and Youth', 'College', 'Adult'],
            array_map(fn (MemberCategory $category) => $category->label(), MemberCategory::cases()),
        );
    }
}
