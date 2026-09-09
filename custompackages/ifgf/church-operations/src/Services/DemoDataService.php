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

namespace Ifgf\ChurchOperations\Services;

use Ifgf\ChurchOperations\Models\AttendanceRecord;
use Ifgf\ChurchOperations\Models\Branch;
use Ifgf\ChurchOperations\Models\ContactPoint;
use Ifgf\ChurchOperations\Models\GroupMembership;
use Ifgf\ChurchOperations\Models\IcareGroup;
use Ifgf\ChurchOperations\Models\Member;
use Ifgf\ChurchOperations\Models\MemberCategory;
use Ifgf\ChurchOperations\Models\MemberCredential;
use Ifgf\ChurchOperations\Models\ServiceOccurrence;
use Ifgf\ChurchOperations\Support\DemoData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Builds and destroys the demo dataset.
 *
 * 🔴 purge() ALWAYS runs before seed(). Not as a convenience — as the contract. Re-running
 * a seeder that appends rather than replaces is how a fixture silently drifts until the
 * numbers a test asserts stop describing anything.
 */
final class DemoDataService
{
    public function __construct(
        private readonly MemberCredentialService $credentials,
        private readonly PhoneNormalizer $phones,
    ) {
    }

    /**
     * Remove every demo row. Safe to run against a database holding real data.
     *
     * @return array<string, int> rows deleted, per table
     */
    public function purge(): array
    {
        return DB::transaction(function () {
            $deleted = [];

            // Occurrences first: attendance cascades from them, and from members. Deleting
            // occurrences up front means the member delete has less to cascade through.
            $deleted['attendance'] = AttendanceRecord::query()
                ->whereHas('occurrence', fn ($q) => $q->where('is_demo', true))
                ->delete();

            $deleted['occurrences'] = ServiceOccurrence::query()->where('is_demo', true)->delete();

            // forceDelete, not delete: Member uses SoftDeletes, and a soft-deleted demo row
            // would keep its unique public_ref and collide on the next seed.
            $demoMembers = Member::withTrashed()->where('is_demo', true)->get();
            $deleted['members'] = $demoMembers->count();

            foreach ($demoMembers as $member) {
                $member->forceDelete();   // contact points, credentials, memberships,
                                          // calendar links and attendance all cascade
            }

            $deleted['groups'] = IcareGroup::query()->where('is_demo', true)->delete();

            return $deleted;
        });
    }

    /**
     * Purge, then build the fixture.
     *
     * 🔴 Refuses to run when the database already holds REAL members, unless forced.
     *
     * The fixture deliberately uses the real branches (TPE, ZL) and real Sunday dates, so
     * it looks like a plausible church. That is exactly what makes it collide once the
     * legacy workbook is imported: a Sunday service at Taipei on 2026-09-06 is ONE
     * occurrence — the unique index says so, correctly — so the import claims the demo row,
     * flips it to is_demo = false, and the demo members' attendance silently becomes part
     * of the real quarterly report.
     *
     * That happened once, on 2026-09-08: 29 fixture rows landed in the real Q3 figures.
     * Demo data and imported data are not meant to coexist; the fixture is for an empty
     * development database.
     *
     * @param  bool  $force  seed anyway, accepting that reports will mix fixture with real
     * @return array<string, mixed> a summary the command prints
     *
     * @throws \RuntimeException
     */
    public function seed(bool $force = false): array
    {
        $realMembers = Member::query()->where('is_demo', false)->count();

        if ($realMembers > 0 && ! $force) {
            throw new \RuntimeException(
                "Refusing to seed demo data: this database already holds {$realMembers} real "
                . 'member(s). The fixture shares branches and Sunday dates with the imported '
                . 'workbook, so seeding would mix invented attendance into the real quarterly '
                . 'report. Run `ifgf:demo:purge` if you want the fixture gone, or pass --force '
                . 'if you accept the mixing.'
            );
        }

        $this->purge();

        return DB::transaction(function () {
            $branches = $this->seedBranches();
            $categories = $this->seedCategories();
            $groups = $this->seedGroups($branches);
            [$members, $credentials] = $this->seedMembers($branches, $categories, $groups);
            $occurrences = $this->seedOccurrences($branches);
            $attendance = $this->seedAttendance($members, $occurrences);

            return [
                'branches' => count($branches),
                'categories' => count($categories),
                'groups' => count($groups),
                'members' => count($members),
                'credentials' => count($credentials),
                'occurrences' => count($occurrences),
                'attendance' => $attendance,
                'sample_payload' => reset($credentials)['payload'] ?? null,
            ];
        });
    }

    /**
     * Branches and categories are REFERENCE data, not demo data — a real import needs them
     * too. They are upserted by code and never carry is_demo, so purge leaves them alone.
     *
     * @return array<string, Branch>
     */
    private function seedBranches(): array
    {
        $out = [];

        foreach (DemoData::branches() as $row) {
            $out[$row['code']] = Branch::query()->updateOrCreate(
                ['code' => $row['code']],
                $row + ['timezone' => 'Asia/Taipei', 'is_active' => true],
            );
        }

        return $out;
    }

    /** @return array<string, MemberCategory> */
    private function seedCategories(): array
    {
        $out = [];

        foreach (DemoData::categories() as $row) {
            $out[$row['code']] = MemberCategory::query()->updateOrCreate(
                ['code' => $row['code']],
                $row + ['is_active' => true],
            );
        }

        return $out;
    }

    /**
     * @param  array<string, Branch>  $branches
     * @return array<string, IcareGroup>
     */
    private function seedGroups(array $branches): array
    {
        $out = [];

        foreach (DemoData::groups() as $row) {
            $out[$row['slug']] = IcareGroup::query()->create([
                'branch_id' => $branches[$row['branch']]->id,
                'name' => $row['name'],
                'slug' => $row['slug'],
                'is_active' => true,
                'is_demo' => true,
            ]);
        }

        return $out;
    }

    /**
     * @return array{0: array<string, Member>, 1: array<string, array>}
     */
    private function seedMembers(array $branches, array $categories, array $groups): array
    {
        $members = [];
        $credentials = [];

        foreach (DemoData::members() as $row) {
            $member = Member::query()->create([
                'branch_id' => $branches[$row['branch']]->id,
                'category_id' => $categories[$row['category']]->id,
                'full_name' => $row['name'],
                'chinese_name' => $row['zh'],
                'birthday' => $row['birthday'],
                'gender' => $row['gender'],
                'status' => 'active',
                'legacy_row_ref' => DemoData::REF_PREFIX . $row['ref'],
                'is_demo' => true,
            ]);

            ContactPoint::query()->create([
                'member_id' => $member->id,
                'type' => ContactPoint::TYPE_EMAIL,
                'value' => $row['email'],
                'normalized_value' => mb_strtolower(trim($row['email'])),
                'is_primary' => true,
            ]);

            // The three phone spellings in members() normalise to three DISTINCT E.164
            // numbers here. That is intentional — it proves the normaliser ran, and it is
            // the exact case the legacy cleanPhoneNumber() no-op got wrong.
            ContactPoint::query()->create([
                'member_id' => $member->id,
                'type' => ContactPoint::TYPE_WHATSAPP,
                'value' => $row['phone'],
                'normalized_value' => $this->phones->normalize($row['phone']),
                'is_primary' => true,
            ]);

            if ($row['group'] !== null) {
                GroupMembership::query()->create([
                    'member_id' => $member->id,
                    'group_id' => $groups[$row['group']]->id,
                    'role' => 'member',
                    'effective_from' => Carbon::parse(DemoData::ANCHOR_SUNDAY)->subMonths(6)->toDateString(),
                ]);
            }

            // Every demo member gets a QR. Two also get an NFC tag, so the usher scanner
            // has both credential types to resolve without anyone provisioning hardware.
            $credentials[$row['ref']] = $this->credentials->issue(
                $member->id,
                MemberCredential::TYPE_QR,
                'demo phone QR',
            );

            if (in_array($row['ref'], ['M01', 'M05'], true)) {
                $this->credentials->issue(
                    $member->id,
                    MemberCredential::TYPE_NFC_TAG,
                    'demo NFC card',
                    null,
                    '04DEM0' . $row['ref'],
                );
            }

            $members[$row['ref']] = $member;
        }

        return [$members, $credentials];
    }

    /** @return array<string, ServiceOccurrence> keyed "TPE:0" */
    private function seedOccurrences(array $branches): array
    {
        $anchor = Carbon::parse(DemoData::ANCHOR_SUNDAY);
        $out = [];

        for ($week = 0; $week < DemoData::WEEKS; $week++) {
            $date = $anchor->copy()->subWeeks($week);

            foreach ($branches as $code => $branch) {
                $out["{$code}:{$week}"] = ServiceOccurrence::query()->create([
                    'branch_id' => $branch->id,
                    'group_id' => null,
                    'kind' => ServiceOccurrence::KIND_SUNDAY,
                    'service_date' => $date->toDateString(),
                    // 10:00 local. Stored UTC; service_date stays the branch-local day.
                    'starts_at' => $date->copy()->setTime(10, 0)->setTimezone('UTC'),
                    'label' => 'Sunday Service',
                    'is_demo' => true,
                ]);
            }
        }

        return $out;
    }

    private function seedAttendance(array $members, array $occurrences): int
    {
        $online = DemoData::onlineRefs();
        $count = 0;

        foreach (DemoData::attendance() as $week => $byBranch) {
            foreach ($byBranch as $branchCode => $refs) {
                $occurrence = $occurrences["{$branchCode}:{$week}"];

                foreach ($refs as $ref) {
                    AttendanceRecord::query()->create([
                        'occurrence_id' => $occurrence->id,
                        'member_id' => $members[$ref]->id,
                        'status' => AttendanceRecord::STATUS_PRESENT,
                        'mode' => in_array($ref, $online, true)
                            ? AttendanceRecord::MODE_ONLINE
                            : AttendanceRecord::MODE_ONSITE,
                        'method' => AttendanceRecord::METHOD_IMPORT,
                        'recorded_at' => $occurrence->starts_at,
                    ]);

                    $count++;
                }
            }
        }

        return $count;
    }
}
