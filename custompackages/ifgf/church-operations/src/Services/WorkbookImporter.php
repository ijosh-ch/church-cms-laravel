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
use Ifgf\ChurchOperations\Models\CalendarLink;
use Ifgf\ChurchOperations\Models\ContactPoint;
use Ifgf\ChurchOperations\Models\FormIngestion;
use Ifgf\ChurchOperations\Models\GroupMembership;
use Ifgf\ChurchOperations\Models\IcareGroup;
use Ifgf\ChurchOperations\Models\ImportBatch;
use Ifgf\ChurchOperations\Models\ImportConflict;
use Ifgf\ChurchOperations\Models\Member;
use Ifgf\ChurchOperations\Models\MemberCategory;
use Ifgf\ChurchOperations\Models\ServiceOccurrence;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

/**
 * Imports the legacy 'Jemaat & Absensi' workbook.
 *
 * ─── Ground rules ───────────────────────────────────────────────────────────────────
 *
 * 1. **The workbook is never modified and never committed.** It holds real member data.
 *    Only its filename and a hash are recorded.
 *
 * 2. **Idempotent.** Identity is the normalised email, which was verified to be present
 *    and unique on all 217 roster rows and to match the attendance grids exactly. Running
 *    twice updates; it does not duplicate.
 *
 * 3. **Nothing is silently fixed.** A malformed birthday, an unknown iCare, a phone that
 *    normalises to another member's — each becomes an ifgf_import_conflicts row and the
 *    import carries on. Quietly "correcting" pastoral data is worse than importing 216 of
 *    217 rows and saying which one needs a human.
 *
 * 4. **Imported rows are never is_demo.** `ifgf:demo:purge` must not be able to delete
 *    real members.
 *
 * ─── Where attendance comes from, and why ───────────────────────────────────────────
 *
 * 🔴 From the GRIDS (Absen-TPE, Absen-ZL), never from the 'Daftar Absensi' scan log.
 *
 * The log has 3,516 rows but its `Lokasi` column is filled on only 40 of them (1.1%),
 * because the legacy QR deliberately left location blank for the member to choose and
 * members did not. Per-branch history is simply not recoverable from it. The grids are
 * per-branch by construction — the sheet IS the branch.
 *
 * The log is still imported, verbatim, into ifgf_form_ingestions as JSONB, so nothing is
 * lost even where it cannot be attributed.
 *
 * 🔴 Absen-TPE_ZL is NOT imported. The Apps Script never maintained it — the weekly job
 * iterates only ['Absen-TPE','Absen-ZL'] — and it stalled at 2026-04-26 while the
 * per-branch sheets run to 2026-09-13.
 */
final class WorkbookImporter
{
    private const SHEET_ROSTER = 'Daftar Jemaat';
    private const SHEET_LOG = 'Daftar Absensi';

    /** Sheet name => branch code. The sheet is the branch; that is the whole point. */
    private const GRIDS = ['Absen-TPE' => 'TPE', 'Absen-ZL' => 'ZL'];

    /** Roster branch values, verified against the file: exactly these two. */
    private const BRANCH_MAP = [
        'IFGF Taipei / 台北' => 'TPE',
        'IFGF Zhongli / 中壢' => 'ZL',
    ];

    /** The roster's own way of writing "no iCare". Never becomes a group. */
    private const NO_GROUP = 'belum mengikuti';

    /** Grid layout, verified against the file. */
    private const GRID_HEADER_ROW = 6;   // merged date, sits in the Onsite column
    private const GRID_MODE_ROW = 7;     // 'Onsite' / 'Online'
    private const GRID_FIRST_DATA_ROW = 8;
    private const GRID_FIRST_DATE_COL = 6;

    /** @var list<array<string, mixed>> */
    private array $conflicts = [];

    /** @var array<string, int> */
    private array $counts = [];

    /** Formula cells read with no cached result. See cell(). */
    private int $uncachedFormulas = 0;

    /** Cap on how many uncached-formula conflicts get recorded; the count is still exact. */
    private const MAX_UNCACHED_FORMULA_CONFLICTS = 10;

    public function __construct(private readonly PhoneNormalizer $phones)
    {
    }

    /**
     * @param  bool  $dryRun  when true every write is rolled back; the batch and its
     *                        conflicts are still recorded, so the report survives.
     */
    public function import(string $path, bool $dryRun = true): ImportBatch
    {
        if (! is_readable($path)) {
            throw new RuntimeException("Workbook not readable at [{$path}].");
        }

        $this->conflicts = [];
        $this->counts = [];
        $this->uncachedFormulas = 0;

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);

        $startedAt = now();
        $error = null;

        try {
            DB::beginTransaction();

            $branches = $this->importBranches();
            $categories = $this->importCategories($spreadsheet->getSheetByName(self::SHEET_ROSTER));
            $groups = $this->importGroups($spreadsheet->getSheetByName(self::SHEET_ROSTER), $branches);

            $membersByEmail = $this->importMembers(
                $spreadsheet->getSheetByName(self::SHEET_ROSTER),
                $branches,
                $categories,
                $groups,
            );

            $occurrences = $this->importOccurrences($spreadsheet, $branches);
            $this->importAttendance($spreadsheet, $occurrences, $membersByEmail);
            $this->importRawLog($spreadsheet->getSheetByName(self::SHEET_LOG), $membersByEmail);

            $this->assertResultIsPlausible($membersByEmail, $occurrences);

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $error = $e->getMessage();
        }

        $spreadsheet->disconnectWorksheets();

        // Outside the transaction, so a dry run still leaves a readable report.
        return $this->recordBatch($path, $dryRun, $startedAt, $error);
    }

    // ── Stage 1: lookups ────────────────────────────────────────────────────────

    /** @return array<string, Branch> keyed by code */
    private function importBranches(): array
    {
        $rows = [
            ['code' => 'TPE', 'name' => 'Taipei', 'name_zh' => '台北', 'sort_order' => 1],
            ['code' => 'ZL', 'name' => 'Zhongli', 'name_zh' => '中壢', 'sort_order' => 2],
        ];

        $out = [];

        foreach ($rows as $row) {
            $out[$row['code']] = Branch::query()->updateOrCreate(
                ['code' => $row['code']],
                $row + ['timezone' => 'Asia/Taipei', 'is_active' => true],
            );
            $this->bump('branches');
        }

        return $out;
    }

    /** @return array<string, MemberCategory> keyed by the roster's own spelling */
    private function importCategories(Worksheet $roster): array
    {
        $seen = $this->distinctValues($roster, 'Kategori');

        // Fixed order, matching the workbook's own summary block.
        $known = [
            'Adult' => ['adult', 1],
            'College' => ['college', 2],
            'Teens and Youth' => ['teens_youth', 3],
            'Kids' => ['kids', 4],
        ];

        $out = [];

        foreach ($known as $name => [$code, $order]) {
            $out[$name] = MemberCategory::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'sort_order' => $order, 'is_active' => true],
            );
            $this->bump('categories');
        }

        // Anything the roster contains that we did not expect is flagged, never invented.
        foreach ($seen as $value) {
            if (! isset($known[$value])) {
                $this->conflict('lookups', null, ImportConflict::KIND_UNKNOWN_CATEGORY,
                    "Roster contains an unrecognised Kategori [{$value}].", ['value' => $value]);
            }
        }

        return $out;
    }

    /** @return array<string, IcareGroup> keyed by lowercased group name */
    private function importGroups(Worksheet $roster, array $branches): array
    {
        $out = [];

        foreach ($this->distinctValues($roster, 'iCare') as $name) {
            // 🔴 "Belum mengikuti" is the ABSENCE of a group, not a group. Creating it
            // would make "not in a cell group" indistinguishable from "in the cell group
            // called 'not in a cell group'".
            if (mb_strtolower($name) === self::NO_GROUP) {
                continue;
            }

            $group = IcareGroup::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true, 'is_demo' => false],
            );

            $out[mb_strtolower($name)] = $group;
            $this->bump('groups');
        }

        return $out;
    }

    // ── Stage 2: members ────────────────────────────────────────────────────────

    /** @return array<string, Member> keyed by normalised email */
    private function importMembers(Worksheet $roster, array $branches, array $categories, array $groups): array
    {
        $columns = $this->headerMap($roster);
        $out = [];
        $seenPhones = [];

        foreach ($this->dataRows($roster, 2, $columns['Full Name']) as $r) {
            $ref = self::SHEET_ROSTER . "!{$r}";

            $name = $this->str($roster, $r, $columns['Full Name']);
            $email = $this->phones->normalizeEmail($this->str($roster, $r, $columns['Email Address']));

            if ($email === '') {
                $this->conflict('members', $ref, ImportConflict::KIND_MISSING_CONTACT,
                    "Row has no email; it cannot be identified or re-matched.", ['name' => $name]);

                continue;
            }

            if (isset($out[$email])) {
                $this->conflict('members', $ref, ImportConflict::KIND_DUPLICATE_EMAIL,
                    "Email [{$email}] appears more than once in the roster.", ['name' => $name]);

                continue;
            }

            $branchValue = $this->str($roster, $r, $columns['Domisili Gereja IFGF'] ?? null);
            $branchCode = self::BRANCH_MAP[$branchValue] ?? null;

            if ($branchCode === null) {
                $this->conflict('members', $ref, ImportConflict::KIND_UNKNOWN_BRANCH,
                    "Unrecognised branch [{$branchValue}]; defaulted to Taipei.",
                    ['value' => $branchValue, 'name' => $name]);
                $branchCode = 'TPE';
            }

            $categoryName = $this->str($roster, $r, $columns['Kategori'] ?? null);
            $birthday = $this->date($roster, $r, $columns['Tanggal Lahir'] ?? null, $ref, 'Tanggal Lahir');

            $member = Member::query()->updateOrCreate(
                // Matched through the contact point below on re-runs; on a first run this
                // creates. legacy_row_ref keeps the tie back to the sheet either way.
                ['legacy_row_ref' => 'ROSTER-' . $email],
                [
                    'branch_id' => $branches[$branchCode]->id,
                    'category_id' => $categories[$categoryName]->id ?? null,
                    'full_name' => $name,
                    'chinese_name' => $this->str($roster, $r, $columns['Chinese Name'] ?? null) ?: null,
                    'birthday' => $birthday,
                    'domicile_taiwan' => $this->str($roster, $r, $columns['Domisili Taiwan'] ?? null) ?: null,
                    'occupation' => $this->str($roster, $r, $columns['Profesi saat ini'] ?? null) ?: null,
                    'education_level' => $this->str($roster, $r, $columns['Tingkat Pendidikan'] ?? null) ?: null,
                    'school_or_company' => $this->str($roster, $r, $columns['Sekolah / Kampus / Perusahaan'] ?? null) ?: null,
                    'status' => 'active',
                    'is_demo' => false,
                ],
            );

            $this->bump($member->wasRecentlyCreated ? 'members_created' : 'members_updated');

            $this->contact($member, ContactPoint::TYPE_EMAIL, $email, $email);

            $phoneRaw = $this->str($roster, $r, $columns['WhatsApp Number'] ?? null);
            if ($phoneRaw !== '') {
                $e164 = $this->phones->normalize($phoneRaw);

                // 🔴 The duplicate the legacy system could never see. cleanPhoneNumber()
                // compared raw strings, so one number written three ways was three people.
                if ($e164 !== '' && isset($seenPhones[$e164]) && $seenPhones[$e164] !== $email) {
                    $this->conflict('contacts', $ref, ImportConflict::KIND_DUPLICATE_PHONE,
                        "Phone normalises to [{$e164}], already used by [{$seenPhones[$e164]}]. "
                        . 'The legacy system could not detect this.',
                        ['raw' => $phoneRaw, 'normalized' => $e164, 'other_email' => $seenPhones[$e164]]);
                } elseif ($e164 !== '') {
                    $seenPhones[$e164] = $email;
                }

                $this->contact($member, ContactPoint::TYPE_WHATSAPP, $phoneRaw, $e164);
            }

            $lineRaw = $this->str($roster, $r, $columns['Line ID '] ?? $columns['Line ID'] ?? null);
            if ($lineRaw !== '') {
                $this->contact($member, ContactPoint::TYPE_LINE, $lineRaw, mb_strtolower($lineRaw));
            }

            $this->calendarLink($member, $this->str($roster, $r, $columns['Birthday ID'] ?? null), $birthday);
            $this->groupMembership($member, $this->str($roster, $r, $columns['iCare'] ?? null), $groups, $ref);

            $out[$email] = $member;
        }

        return $out;
    }

    private function contact(Member $member, string $type, string $value, string $normalized): void
    {
        if ($normalized === '') {
            return;
        }

        ContactPoint::query()->updateOrCreate(
            ['member_id' => $member->id, 'type' => $type, 'normalized_value' => $normalized],
            ['value' => $value, 'is_primary' => true],
        );

        $this->bump('contact_points');
    }

    /**
     * 🔴 The single most valuable column in the whole import.
     *
     * "Birthday ID" is the Google Calendar recurring event series id, and it is the ONLY
     * 100%-filled column in the roster (217 of 217). Carrying it verbatim is what lets the
     * new system ADOPT the existing calendar events instead of deleting 217 and recreating
     * them — which every subscribed member would see happen.
     */
    private function calendarLink(Member $member, string $seriesId, ?string $birthday): void
    {
        if ($seriesId === '') {
            return;
        }

        CalendarLink::query()->updateOrCreate(
            [
                'member_id' => $member->id,
                'google_calendar_id' => (string) config('church-operations.calendar.birthday_id'),
            ],
            [
                'event_series_id' => $seriesId,
                // 'pending', not 'ok': the event is believed to exist but this process has
                // not verified it against Calendar. `ifgf:calendar:sync` confirms and
                // promotes it. Claiming 'ok' here would be asserting something unchecked.
                'status' => CalendarLink::STATUS_PENDING,
                'synced_birthday' => $birthday,
                'synced_title' => $member->full_name,
            ],
        );

        $this->bump('calendar_links');
    }

    private function groupMembership(Member $member, string $groupName, array $groups, string $ref): void
    {
        $key = mb_strtolower(trim($groupName));

        // No group, or the roster's own "not yet" value: leave NO row. That absence is the
        // "Belum mengikuti" state — 62 of 217 members.
        if ($key === '' || $key === self::NO_GROUP) {
            return;
        }

        if (! isset($groups[$key])) {
            $this->conflict('groups', $ref, ImportConflict::KIND_UNKNOWN_GROUP,
                "Member references an iCare [{$groupName}] that is not in the group list.",
                ['value' => $groupName]);

            return;
        }

        GroupMembership::query()->updateOrCreate(
            ['member_id' => $member->id, 'group_id' => $groups[$key]->id, 'effective_to' => null],
            [
                'role' => 'member',
                // The workbook records only the CURRENT group with no start date. Dating
                // it to the earliest attendance week is the least-wrong choice available,
                // and it is honest that this is an assumption rather than a record.
                'effective_from' => '2025-09-28',
                'reason' => 'imported from spreadsheet; original start date unknown',
            ],
        );

        $this->bump('group_memberships');
    }

    // ── Stage 3: occurrences ────────────────────────────────────────────────────

    /** @return array<string, ServiceOccurrence> keyed "TPE|2026-09-06" */
    private function importOccurrences($spreadsheet, array $branches): array
    {
        $out = [];

        foreach (self::GRIDS as $sheetName => $branchCode) {
            $sheet = $spreadsheet->getSheetByName($sheetName);

            if ($sheet === null) {
                continue;
            }

            foreach ($this->gridDates($sheet) as $col => $date) {
                $key = "{$branchCode}|{$date}";

                if (isset($out[$key])) {
                    continue;
                }

                $out[$key] = ServiceOccurrence::query()->updateOrCreate(
                    [
                        'branch_id' => $branches[$branchCode]->id,
                        'group_id' => null,
                        'kind' => ServiceOccurrence::KIND_SUNDAY,
                        'service_date' => $date,
                    ],
                    [
                        'label' => 'Sunday Service',
                        // 10:00 local, converted to UTC. service_date stays the
                        // branch-local calendar day — the report groups on that, never on
                        // the instant.
                        'starts_at' => Carbon::parse($date, 'Asia/Taipei')->setTime(10, 0)->utc(),
                        'is_demo' => false,
                    ],
                );

                $this->bump('occurrences');
            }
        }

        return $out;
    }

    /**
     * Date columns in a grid.
     *
     * The date sits in the Onsite column of each Onsite/Online pair because row 6 is
     * merged across the pair; reading the Online column returns null. Carrying the last
     * seen date forward is what pairs them.
     *
     * @return array<int, string> column index => Y-m-d
     */
    private function gridDates(Worksheet $sheet): array
    {
        $out = [];
        $last = null;

        for ($col = self::GRID_FIRST_DATE_COL; $col <= $this->highestColumnIndex($sheet); $col++) {
            $raw = $this->cell($sheet, $col, self::GRID_HEADER_ROW);
            $parsed = $this->toDate($raw);

            if ($parsed !== null) {
                $last = $parsed;
            }

            if ($last !== null) {
                $out[$col] = $last;
            }
        }

        return $out;
    }

    // ── Stage 4: attendance ─────────────────────────────────────────────────────

    private function importAttendance($spreadsheet, array $occurrences, array $membersByEmail): void
    {
        foreach (self::GRIDS as $sheetName => $branchCode) {
            $sheet = $spreadsheet->getSheetByName($sheetName);

            if ($sheet === null) {
                continue;
            }

            $dates = $this->gridDates($sheet);
            $highestRow = $sheet->getHighestDataRow();

            // (occurrence, member) already written during THIS run, with the mode used.
            // A member can legitimately attend both branches in a week — that is two
            // occurrences — but cannot be both onsite and online at ONE of them.
            $written = [];

            for ($r = self::GRID_FIRST_DATA_ROW; $r <= $highestRow; $r++) {
                $email = $this->phones->normalizeEmail((string) $this->cell($sheet, 3, $r));

                if ($email === '') {
                    continue;
                }

                $member = $membersByEmail[$email] ?? null;

                if ($member === null) {
                    $this->conflict('attendance', "{$sheetName}!{$r}", ImportConflict::KIND_UNMATCHED_MEMBER,
                        "Grid row for [{$email}] matches no roster member; its attendance is not imported.",
                        ['email' => $email, 'sheet' => $sheetName]);

                    continue;
                }

                foreach ($dates as $col => $date) {
                    $mode = trim((string) $this->cell($sheet, $col, self::GRID_MODE_ROW));
                    $value = $this->cell($sheet, $col, $r);

                    // Present is "anything but blank or zero". The cell holds the scan
                    // time when present, e.g. '09:59' — the exact value is not modelled,
                    // only the fact of presence, because the grid's times are not reliable
                    // enough to be treated as timestamps.
                    if (! $this->isPresent($value)) {
                        continue;
                    }

                    $occurrence = $occurrences["{$branchCode}|{$date}"] ?? null;

                    if ($occurrence === null) {
                        continue;
                    }

                    $resolvedMode = strcasecmp($mode, 'Online') === 0
                        ? AttendanceRecord::MODE_ONLINE
                        : AttendanceRecord::MODE_ONSITE;

                    $slot = $occurrence->id . ':' . $member->id;

                    // 🔴 Marked in BOTH the Onsite and Online column of the same week.
                    //
                    // One row does this in the real workbook (Absen-TPE row 213,
                    // 2026-08-02, '14:26' in both columns) — plainly a data-entry
                    // artifact. Letting the later column silently overwrite the earlier
                    // one is exactly the quiet correction this importer is not allowed to
                    // make, and it made the imported count disagree with the row count by
                    // exactly one.
                    //
                    // ONSITE wins, because it is the first column and the more
                    // consequential claim: someone physically present. The conflict is
                    // recorded either way.
                    if (isset($written[$slot])) {
                        $this->conflict('attendance', "{$sheetName}!{$r}",
                            ImportConflict::KIND_COERCED_TYPE,
                            "Marked both {$written[$slot]} and {$resolvedMode} for {$date}; "
                            . "kept {$written[$slot]}.",
                            [
                                'email' => $email,
                                'date' => $date,
                                'branch' => $branchCode,
                                'kept' => $written[$slot],
                                'discarded' => $resolvedMode,
                            ]);

                        continue;
                    }

                    $written[$slot] = $resolvedMode;

                    AttendanceRecord::query()->updateOrCreate(
                        ['occurrence_id' => $occurrence->id, 'member_id' => $member->id],
                        [
                            'status' => AttendanceRecord::STATUS_PRESENT,
                            'mode' => $resolvedMode,
                            'method' => AttendanceRecord::METHOD_IMPORT,
                            'recorded_at' => $occurrence->starts_at,
                        ],
                    );

                    $this->bump('attendance');
                }
            }
        }
    }

    private function isPresent(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        $s = trim((string) $value);

        return $s !== '' && $s !== '0' && $s !== '0.0';
    }

    // ── Stage 5: the raw scan log ───────────────────────────────────────────────

    /**
     * The 'Daftar Absensi' log, verbatim, into JSONB.
     *
     * Not authoritative — its Lokasi is filled on 40 of 3,516 rows, so it cannot say which
     * branch a scan happened at. Kept because it is the only record of the actual scan
     * TIMES, and because throwing away source data during a migration is irreversible.
     */
    private function importRawLog(?Worksheet $log, array $membersByEmail): void
    {
        if ($log === null) {
            return;
        }

        $columns = $this->headerMap($log);
        $highestRow = $log->getHighestDataRow();

        for ($r = 2; $r <= $highestRow; $r++) {
            $payload = [];

            foreach ($columns as $header => $col) {
                $value = $this->cell($log, $col, $r);

                if ($value === null || $value === '') {
                    continue;
                }

                $payload[$header] = $value instanceof \DateTimeInterface
                    ? $value->format('c')
                    : (is_scalar($value) ? $value : (string) $value);
            }

            if ($payload === []) {
                continue;
            }

            $email = $this->phones->normalizeEmail((string) ($payload['Email'] ?? $payload['Email Address'] ?? ''));

            FormIngestion::query()->updateOrCreate(
                // Synthetic but stable: the sheet and row. Re-running updates rather than
                // duplicating, which is what makes the whole import re-runnable.
                ['response_id' => 'LEGACY-LOG-' . $r],
                [
                    'source' => FormIngestion::SOURCE_LEGACY_LOG,
                    'payload' => $payload,
                    'mapped_member_id' => $membersByEmail[$email]->id ?? null,
                    'status' => isset($membersByEmail[$email])
                        ? FormIngestion::STATUS_MAPPED
                        : FormIngestion::STATUS_UNMAPPED,
                    'submitted_at' => $this->toDateTime($payload['Timestamp'] ?? null),
                    'ingested_at' => now(),
                ],
            );

            $this->bump('raw_log');
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────────────────

    /**
     * 🔴 Catches the failure mode a per-cell check cannot see: a workbook whose formula
     * results were never written.
     *
     * Almost everything this importer reads is a formula — Kategori, the grid date headers
     * (an =H6+7 chain), and the ~40,000 attendance cells per sheet. It works only because
     * Excel/Sheets stores each formula's answer in the file alongside it.
     *
     * If a tool re-saves the workbook without evaluating formulas, those answers come back
     * as **0**, not as empty. So every attendance cell reads as "absent" and every category
     * as unknown, and nothing looks wrong cell by cell: 0 is a perfectly legitimate value
     * for an attendance cell. The import would complete, report thousands of rows, and be
     * silently worthless.
     *
     * What DOES give it away is the shape of the result. A roster with members but not one
     * resolved category, or a grid with dated services but not one attendance mark, is not
     * a church — it is a file whose formulas were not evaluated.
     *
     * @param  array<string, Member>  $membersByEmail
     * @param  array<string, ServiceOccurrence>  $occurrences
     */
    private function assertResultIsPlausible(array $membersByEmail, array $occurrences): void
    {
        $members = count($membersByEmail);

        if ($members > 0) {
            $categorised = collect($membersByEmail)->filter(fn ($m) => $m->category_id !== null)->count();

            if ($categorised === 0) {
                $this->conflict('cells', self::SHEET_ROSTER, ImportConflict::KIND_UNCACHED_FORMULA,
                    "Read {$members} member(s) but not one resolved a category. Kategori is a "
                    . 'formula column, so this almost certainly means the workbook was saved '
                    . 'without its formula results. Open it in Excel or Google Sheets, re-save, '
                    . 'and import again.',
                    ['members' => $members, 'categorised' => 0]);
            }
        }

        $attendance = $this->counts['attendance'] ?? 0;

        if (count($occurrences) > 0 && $attendance === 0) {
            $this->conflict('cells', 'Absen-*', ImportConflict::KIND_UNCACHED_FORMULA,
                'Found ' . count($occurrences) . ' service date(s) but not one attendance mark. '
                . 'The attendance cells are formulas; an unevaluated workbook reads every one '
                . 'of them as 0, which is indistinguishable from "absent". Re-save the workbook '
                . 'with its formula results and import again.',
                ['occurrences' => count($occurrences), 'attendance' => 0]);
        }
    }

    /**
     * One cell's VALUE, resolving formulas to their cached result.
     *
     * 🔴 The roster's Kategori column is a formula (=IF(D14="","",IF(...))), and with
     * setReadDataOnly(true) PhpSpreadsheet hands back the formula TEXT, not the answer.
     * Reading it raw produced 300+ bogus "unrecognised Kategori [=IF(..." conflicts.
     *
     * getOldCalculatedValue() returns the result Excel/Sheets last computed and stored in
     * the file — the same thing openpyxl's data_only=True reads. It is used in preference
     * to getCalculatedValue(), which would re-evaluate every formula across ~3,500 rows
     * and can fail on functions PhpSpreadsheet does not implement.
     */
    private function cell(Worksheet $sheet, int $col, int $row): mixed
    {
        $cell = $sheet->getCell([$col, $row]);
        $value = $cell->getValue();

        if (! is_string($value) || ! str_starts_with($value, '=')) {
            return $value;
        }

        $cached = $cell->getOldCalculatedValue();

        if ($cached !== null && $cached !== '') {
            return $cached;
        }

        /*
         | 🔴 A formula with NO cached result.
         |
         | This does not happen in the current workbook — every formula that the importer
         | actually reads carries a cached value, which is why the imported figures
         | reconcile. But the dependency is far heavier than it looks: the Kategori column
         | is a formula, the grid DATE HEADERS are an =H6+7 chain, and the attendance cells
         | themselves are formulas — roughly 40,000 per grid sheet. The entire import rests
         | on Excel/Sheets having written its answers into the file.
         |
         | If the workbook is ever re-saved by a tool that drops the formula cache, every
         | one of those reads would come back null. Returning null quietly would import a
         | member with no category, or a week with no date, and say nothing at all.
         |
         | So it is recorded. Capped, because a cache-less file would otherwise produce
         | tens of thousands of identical rows — the first few are enough to diagnose it,
         | and the count in the message says how bad it is.
        */
        $this->uncachedFormulas++;

        if ($this->uncachedFormulas <= self::MAX_UNCACHED_FORMULA_CONFLICTS) {
            $this->conflict(
                'cells',
                $sheet->getTitle() . '!' . Coordinate::stringFromColumnIndex($col) . $row,
                ImportConflict::KIND_UNCACHED_FORMULA,
                'Formula has no cached value, so it was read as empty. Open the workbook in '
                . 'Excel or Google Sheets and re-save it so the results are stored.',
                ['formula' => mb_substr($value, 0, 120)],
            );
        }

        return null;
    }

    /**
     * The last column as a NUMBER.
     *
     * getHighestColumn() returns a spreadsheet column LETTER ('DV'), and using it in a
     * numeric loop produces coordinates like 'AAAA1'. getHighestColumnIndex() does not
     * exist on Worksheet in this PhpSpreadsheet version, so convert explicitly.
     */
    private function highestColumnIndex(Worksheet $sheet): int
    {
        return Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
    }

    /** @return array<string, int> header text => column index */
    private function headerMap(Worksheet $sheet, int $row = 1): array
    {
        $out = [];

        for ($col = 1; $col <= $this->highestColumnIndex($sheet); $col++) {
            $header = $this->cell($sheet, $col, $row);

            if (is_string($header) && trim($header) !== '' && ! isset($out[trim($header)])) {
                $out[trim($header)] = $col;
            }
        }

        return $out;
    }

    /** @return list<int> row numbers that have a value in $keyColumn */
    private function dataRows(Worksheet $sheet, int $firstRow, int $keyColumn): array
    {
        $out = [];
        $highest = $sheet->getHighestDataRow();

        for ($r = $firstRow; $r <= $highest; $r++) {
            if (trim((string) $this->cell($sheet, $keyColumn, $r)) !== '') {
                $out[] = $r;
            }
        }

        return $out;
    }

    /**
     * Distinct values in a column, over POPULATED rows only.
     *
     * 🔴 The row guard is not an optimisation. The roster sheet carries ~100 empty template
     * rows below the 217 members, and their Kategori formulas have no cached value —
     * correctly, because the formula returns "" for a blank row and a spreadsheet does not
     * cache an empty result. Scanning them produced 100 "formula has no cached value"
     * conflicts describing rows that contain nobody.
     *
     * @param  string  $guardHeader  a row counts only if this column has a value
     * @return list<string>
     */
    private function distinctValues(Worksheet $sheet, string $header, string $guardHeader = 'Full Name'): array
    {
        $columns = $this->headerMap($sheet);

        if (! isset($columns[$header])) {
            return [];
        }

        $col = $columns[$header];
        $guard = $columns[$guardHeader] ?? null;
        $seen = [];
        $highest = $sheet->getHighestDataRow();

        for ($r = 2; $r <= $highest; $r++) {
            if ($guard !== null && trim((string) $this->cell($sheet, $guard, $r)) === '') {
                continue;
            }

            $value = trim((string) $this->cell($sheet, $col, $r));

            if ($value !== '') {
                $seen[$value] = true;
            }
        }

        return array_keys($seen);
    }

    private function str(Worksheet $sheet, int $row, ?int $col): string
    {
        if ($col === null) {
            return '';
        }

        $value = $this->cell($sheet, $col, $row);

        // LINE ids arrive as floats for three members — a spreadsheet guessing at types.
        if (is_float($value) && floor($value) === $value) {
            $value = (string) (int) $value;
        }

        return trim((string) $value);
    }

    private function date(Worksheet $sheet, int $row, ?int $col, string $ref, string $label): ?string
    {
        if ($col === null) {
            return null;
        }

        $raw = $this->cell($sheet, $col, $row);

        if ($raw === null || $raw === '') {
            return null;
        }

        $parsed = $this->toDate($raw);

        if ($parsed === null) {
            // One roster row holds the literal text '5/11/0005'. Guessing at it would be
            // inventing a member's birthday; it is flagged and left null.
            $this->conflict('members', $ref, ImportConflict::KIND_UNPARSEABLE_DATE,
                "Could not read [{$label}] value; left empty for a human to fix.",
                ['value' => (string) $raw]);

            return null;
        }

        return $parsed;
    }

    private function toDate(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if ($raw instanceof \DateTimeInterface) {
            return $raw->format('Y-m-d');
        }

        // With readDataOnly, Excel dates arrive as serial numbers.
        if (is_numeric($raw)) {
            $serial = (float) $raw;

            // Serial 1 is 1900-01-01; anything below ~1000 is not a plausible birthday or
            // service date and is almost certainly a count that landed in a date column.
            if ($serial < 1000 || $serial > 80000) {
                return null;
            }

            try {
                return ExcelDate::excelToDateTimeObject($serial)->format('Y-m-d');
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            $parsed = Carbon::parse((string) $raw);

            // Rejects '5/11/0005' and similar: no member of this church was born before
            // 1900 or will be born after today.
            if ($parsed->year < 1900 || $parsed->year > (int) date('Y') + 1) {
                return null;
            }

            return $parsed->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function toDateTime(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $raw)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            return Carbon::parse((string) $raw)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function bump(string $key, int $by = 1): void
    {
        $this->counts[$key] = ($this->counts[$key] ?? 0) + $by;
    }

    private function conflict(string $stage, ?string $ref, string $kind, string $message, array $context = []): void
    {
        $this->conflicts[] = compact('stage', 'kind', 'message', 'context') + ['row_ref' => $ref];
    }

    private function recordBatch(string $path, bool $dryRun, Carbon $startedAt, ?string $error): ImportBatch
    {
        $batch = ImportBatch::query()->create([
            // Filename only. The full path is a detail of this machine, and the workbook
            // itself is never stored.
            'source_name' => basename($path),
            'source_hash' => hash_file('sha256', $path),
            'source_bytes' => filesize($path) ?: null,
            'mode' => $dryRun ? ImportBatch::MODE_DRY_RUN : ImportBatch::MODE_COMMITTED,
            'status' => $error === null ? ImportBatch::STATUS_COMPLETED : ImportBatch::STATUS_FAILED,
            'started_at' => $startedAt,
            'finished_at' => now(),
            'summary' => $this->counts + ($this->uncachedFormulas > 0
                ? ['uncached_formulas' => $this->uncachedFormulas]
                : []),
            'error' => $error,
        ]);

        foreach ($this->conflicts as $conflict) {
            $batch->conflicts()->create($conflict);
        }

        return $batch->fresh('conflicts');
    }
}
