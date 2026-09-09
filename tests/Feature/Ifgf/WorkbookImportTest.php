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

use Ifgf\ChurchOperations\Models\AttendanceRecord;
use Ifgf\ChurchOperations\Models\Branch;
use Ifgf\ChurchOperations\Models\CalendarLink;
use Ifgf\ChurchOperations\Models\ContactPoint;
use Ifgf\ChurchOperations\Models\FormIngestion;
use Ifgf\ChurchOperations\Models\IcareGroup;
use Ifgf\ChurchOperations\Models\ImportBatch;
use Ifgf\ChurchOperations\Models\ImportConflict;
use Ifgf\ChurchOperations\Models\Member;
use Ifgf\ChurchOperations\Models\ServiceOccurrence;
use Ifgf\ChurchOperations\Services\WorkbookImporter;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\IfgfTestCase;

/**
 * The legacy workbook import.
 *
 * 🔴 Built against a SYNTHETIC workbook written by this test, never the real file. The
 * real 'Jemaat & Absensi' workbook holds 217 real members' names, emails, phone numbers and
 * birthdays; it is not committed and a test must not depend on it existing.
 *
 * The fixture is deliberately shaped to contain every defect the real file has, verified
 * against it beforehand:
 *   - a Kategori column that is a FORMULA, not a literal
 *   - a birthday that cannot be parsed ('5/11/0005' in the real file)
 *   - two members whose phones differ in format but are the same number
 *   - members with 'Belum mengikuti' instead of an iCare
 *   - an attendance grid with merged date headers over Onsite/Online pairs
 */
class WorkbookImportTest extends IfgfTestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = $this->buildWorkbook();
    }

    protected function tearDown(): void
    {
        if (isset($this->path) && is_file($this->path)) {
            @unlink($this->path);
        }

        parent::tearDown();
    }

    private function importer(): WorkbookImporter
    {
        return app(WorkbookImporter::class);
    }

    // ── Dry run ─────────────────────────────────────────────────────────────────

    public function test_a_dry_run_writes_nothing_but_still_reports(): void
    {
        $batch = $this->importer()->import($this->path, dryRun: true);

        $this->assertSame(ImportBatch::MODE_DRY_RUN, $batch->mode);
        $this->assertSame(ImportBatch::STATUS_COMPLETED, $batch->status);

        // The counts describe what WOULD have happened...
        $this->assertSame(4, $batch->summary['members_created']);

        // ...but nothing was kept.
        $this->assertSame(0, Member::query()->where('is_demo', false)->count());
        $this->assertSame(0, AttendanceRecord::query()->count());

        // The report itself survives the rollback — that is the whole point of a dry run.
        $this->assertDatabaseHas('ifgf_import_batches', ['id' => $batch->id]);
        $this->assertGreaterThan(0, $batch->conflicts->count());
    }

    // ── Committed ───────────────────────────────────────────────────────────────

    public function test_a_committed_import_creates_the_expected_rows(): void
    {
        $batch = $this->importer()->import($this->path, dryRun: false);

        $this->assertSame(ImportBatch::MODE_COMMITTED, $batch->mode);
        $this->assertSame(ImportBatch::STATUS_COMPLETED, $batch->status);

        $this->assertSame(4, Member::query()->where('is_demo', false)->count());
        $this->assertSame(2, Branch::query()->count());

        // 'Belum mengikuti' is NOT a group — only the two real iCare names become rows.
        $this->assertSame(2, IcareGroup::query()->where('is_demo', false)->count());
        $this->assertSame(0, IcareGroup::query()->where('name', 'Belum mengikuti')->count());
    }

    public function test_the_kategori_formula_is_read_as_its_value_not_its_text(): void
    {
        $this->importer()->import($this->path, dryRun: false);

        $member = Member::query()->where('full_name', 'Alpha Tester')->firstOrFail();

        // Reading the formula text instead of the cached result produced 300+ bogus
        // "unrecognised Kategori [=IF(..." conflicts against the real file.
        $this->assertSame('College', $member->category?->name);
        $this->assertSame(0, ImportConflict::query()
            ->where('kind', ImportConflict::KIND_UNKNOWN_CATEGORY)->count());
    }

    /**
     * 🔴 A workbook saved without its formula results must be REPORTED, not imported as
     * a church where nobody has a category and nobody ever attended.
     *
     * Almost everything read here is a formula: Kategori, the grid date headers (an =H6+7
     * chain), and the ~40,000 attendance cells per sheet. It works only because Excel and
     * Sheets store each answer in the file.
     *
     * A tool that re-saves without evaluating writes **0** rather than nothing, so every
     * attendance cell reads as "absent" and nothing looks wrong cell by cell — 0 is a
     * legitimate value there. Only the SHAPE of the result gives it away.
     */
    public function test_a_workbook_without_formula_results_is_reported_not_imported_silently(): void
    {
        $path = $this->buildWorkbook(precalculate: false);

        try {
            $batch = $this->importer()->import($path, dryRun: false);

            $conflicts = $batch->conflicts->where('kind', ImportConflict::KIND_UNCACHED_FORMULA);

            $this->assertGreaterThanOrEqual(2, $conflicts->count(),
                'Both the category and the attendance shape checks should fire.');

            $this->assertTrue($conflicts->contains(
                fn ($c) => str_contains($c->message, 'not one resolved a category')
            ));

            $this->assertTrue($conflicts->contains(
                fn ($c) => str_contains($c->message, 'not one attendance mark')
            ));

            // The rows still import — the import is not lost, it is flagged as suspect.
            $this->assertSame(4, Member::query()->count());
            $this->assertSame(0, AttendanceRecord::query()->count());
        } finally {
            @unlink($path);
        }
    }

    /** The healthy fixture must NOT trip the shape checks. */
    public function test_a_normal_workbook_raises_no_uncached_formula_conflict(): void
    {
        $batch = $this->importer()->import($this->path, dryRun: false);

        $this->assertSame(0, $batch->conflicts
            ->where('kind', ImportConflict::KIND_UNCACHED_FORMULA)->count());
    }

    public function test_members_without_an_icare_get_no_membership_row(): void
    {
        $this->importer()->import($this->path, dryRun: false);

        $delta = Member::query()->where('full_name', 'Delta Tester')->firstOrFail();

        $this->assertSame(0, $delta->groupMemberships()->count());
        $this->assertNull($delta->currentGroup());
        $this->assertTrue(Member::query()->withoutIcare()->whereKey($delta->id)->exists());
    }

    /**
     * 🔴 The single most valuable column in the import: the Google Calendar series id.
     *
     * It is the only 100%-filled column in the real roster (217 of 217). Carrying it
     * verbatim is what lets the new system adopt the existing events instead of deleting
     * and recreating 217 of them, which every subscribed member would see.
     */
    public function test_the_google_calendar_series_ids_are_carried_verbatim(): void
    {
        $this->importer()->import($this->path, dryRun: false);

        $member = Member::query()->where('full_name', 'Alpha Tester')->firstOrFail();
        $link = CalendarLink::query()->where('member_id', $member->id)->firstOrFail();

        $this->assertSame('abc123seriesalpha', $link->event_series_id);

        // 'pending', not 'ok': the event is believed to exist but has not been verified
        // against Calendar by this process. Claiming 'ok' would assert something unchecked.
        $this->assertSame(CalendarLink::STATUS_PENDING, $link->status);
    }

    public function test_phones_are_stored_as_typed_and_normalised_for_matching(): void
    {
        $this->importer()->import($this->path, dryRun: false);

        $member = Member::query()->where('full_name', 'Alpha Tester')->firstOrFail();
        $phone = $member->contactPoints()->where('type', ContactPoint::TYPE_WHATSAPP)->firstOrFail();

        $this->assertSame('0912345678', $phone->value);
        $this->assertSame('886912345678', $phone->normalized_value);
    }

    /** The duplicate the legacy system could never see, because it compared raw strings. */
    public function test_a_cross_format_duplicate_phone_is_flagged_not_merged(): void
    {
        $batch = $this->importer()->import($this->path, dryRun: false);

        $conflict = $batch->conflicts
            ->firstWhere('kind', ImportConflict::KIND_DUPLICATE_PHONE);

        $this->assertNotNull($conflict, 'Beta and Gamma share one number in two formats.');
        $this->assertSame('886912345679', $conflict->context['normalized']);

        // Flagged, NOT merged — both members are still imported. Deciding they are the
        // same person is a pastoral judgement, not an import one.
        $this->assertSame(1, Member::query()->where('full_name', 'Beta Tester')->count());
        $this->assertSame(1, Member::query()->where('full_name', 'Gamma Tester')->count());
    }

    public function test_an_unparseable_birthday_is_flagged_and_left_empty(): void
    {
        $batch = $this->importer()->import($this->path, dryRun: false);

        $conflict = $batch->conflicts->firstWhere('kind', ImportConflict::KIND_UNPARSEABLE_DATE);

        $this->assertNotNull($conflict);
        $this->assertSame('5/11/0005', $conflict->context['value']);

        // Guessing at it would be inventing a member's birthday.
        $this->assertNull(Member::query()->where('full_name', 'Gamma Tester')->value('birthday'));
    }

    // ── Attendance ──────────────────────────────────────────────────────────────

    public function test_occurrences_come_from_the_grid_headers_one_per_branch_per_week(): void
    {
        $this->importer()->import($this->path, dryRun: false);

        // 2 weeks x 2 branches.
        $this->assertSame(4, ServiceOccurrence::query()->where('is_demo', false)->count());

        $tpe = Branch::query()->where('code', 'TPE')->firstOrFail();

        $this->assertSame(2, ServiceOccurrence::query()->where('branch_id', $tpe->id)->count());
        $this->assertTrue(ServiceOccurrence::query()
            ->where('branch_id', $tpe->id)->where('service_date', '2026-09-06')->exists());
    }

    /**
     * 🔴 The branch comes from the SHEET, never from the member.
     *
     * The legacy scan log records location on 40 of 3,516 rows, so per-branch history is
     * unrecoverable from it. The grids are per-branch by construction, which is why they
     * are the source.
     */
    public function test_attendance_is_attributed_to_the_branch_of_the_sheet(): void
    {
        $this->importer()->import($this->path, dryRun: false);

        $alpha = Member::query()->where('full_name', 'Alpha Tester')->firstOrFail();

        $records = AttendanceRecord::query()
            ->with('occurrence.branch')
            ->where('member_id', $alpha->id)
            ->get();

        // Alpha is present in BOTH sheets on 2026-09-06 — a member can attend either
        // branch, and the sheet decides which.
        $this->assertSame(2, $records->count());
        $this->assertEqualsCanonicalizing(
            ['TPE', 'ZL'],
            $records->pluck('occurrence.branch.code')->all()
        );
    }

    public function test_a_zero_cell_is_absence_and_a_time_cell_is_presence(): void
    {
        $this->importer()->import($this->path, dryRun: false);

        $beta = Member::query()->where('full_name', 'Beta Tester')->firstOrFail();

        // Beta has a time on the second TPE week only; zeros everywhere else.
        $this->assertSame(1, AttendanceRecord::query()->where('member_id', $beta->id)->count());
    }

    /**
     * 🔴 Marked in both the Onsite and Online column of the same week.
     *
     * Letting the later column silently overwrite the earlier one is exactly the quiet
     * correction this importer forbids — and it made the reported count disagree with the
     * stored row count by exactly one against the real file.
     */
    public function test_a_double_marked_week_keeps_onsite_and_records_a_conflict(): void
    {
        $batch = $this->importer()->import($this->path, dryRun: false);

        $alpha = Member::query()->where('full_name', 'Alpha Tester')->firstOrFail();

        $tpe = AttendanceRecord::query()
            ->with('occurrence')
            ->where('member_id', $alpha->id)
            ->get()
            ->first(fn ($r) => $r->occurrence->branch_id === Branch::query()->where('code', 'TPE')->value('id'));

        // Onsite wins: it is the first column and the more consequential claim.
        $this->assertSame(AttendanceRecord::MODE_ONSITE, $tpe->mode);

        $conflict = $batch->conflicts->firstWhere('kind', ImportConflict::KIND_COERCED_TYPE);

        $this->assertNotNull($conflict);
        $this->assertSame('onsite', $conflict->context['kept']);
        $this->assertSame('online', $conflict->context['discarded']);
    }

    /**
     * The reported count and the stored row count must agree. They did not, by exactly
     * one, until the double-mark above was handled explicitly.
     */
    public function test_the_reported_attendance_count_matches_the_rows_written(): void
    {
        $batch = $this->importer()->import($this->path, dryRun: false);

        $this->assertSame(
            AttendanceRecord::query()->count(),
            $batch->summary['attendance'],
            'A silent overwrite makes the summary lie about what was written.'
        );
    }

    public function test_the_online_column_is_recorded_as_online_mode(): void
    {
        $this->importer()->import($this->path, dryRun: false);

        $gamma = Member::query()->where('full_name', 'Gamma Tester')->firstOrFail();

        $record = AttendanceRecord::query()->where('member_id', $gamma->id)->firstOrFail();

        $this->assertSame(AttendanceRecord::MODE_ONLINE, $record->mode);
        $this->assertSame(AttendanceRecord::METHOD_IMPORT, $record->method);
    }

    // ── The raw log ─────────────────────────────────────────────────────────────

    public function test_the_raw_scan_log_is_kept_verbatim_as_jsonb(): void
    {
        $this->importer()->import($this->path, dryRun: false);

        $rows = FormIngestion::query()->where('source', FormIngestion::SOURCE_LEGACY_LOG)->get();

        $this->assertSame(2, $rows->count());

        $mapped = $rows->firstWhere('status', FormIngestion::STATUS_MAPPED);
        $this->assertNotNull($mapped);

        // Columns the schema never modelled remain queryable rather than being discarded.
        $this->assertSame('Taipei', $mapped->field('Lokasi'));
        $this->assertNotNull($mapped->mapped_member_id);

        // A log row whose email matches nobody is kept, flagged unmapped, not dropped.
        $this->assertSame(1, $rows->where('status', FormIngestion::STATUS_UNMAPPED)->count());
    }

    // ── Re-runnability ──────────────────────────────────────────────────────────

    /** The property that makes a migration survivable: run it twice, get the same rows. */
    public function test_importing_twice_updates_rather_than_duplicating(): void
    {
        $this->importer()->import($this->path, dryRun: false);

        $members = Member::query()->where('is_demo', false)->count();
        $attendance = AttendanceRecord::query()->count();
        $occurrences = ServiceOccurrence::query()->where('is_demo', false)->count();
        $contacts = ContactPoint::query()->count();

        $this->importer()->import($this->path, dryRun: false);

        $this->assertSame($members, Member::query()->where('is_demo', false)->count());
        $this->assertSame($attendance, AttendanceRecord::query()->count());
        $this->assertSame($occurrences, ServiceOccurrence::query()->where('is_demo', false)->count());
        $this->assertSame($contacts, ContactPoint::query()->count());
    }

    public function test_imported_members_are_never_demo_data(): void
    {
        $this->importer()->import($this->path, dryRun: false);

        // Otherwise `ifgf:demo:purge` could delete the entire real congregation.
        $this->assertSame(0, Member::query()->where('is_demo', true)->count());
        $this->assertSame(0, ServiceOccurrence::query()->where('is_demo', true)->count());
    }

    public function test_the_batch_records_the_source_file_but_not_its_path(): void
    {
        $batch = $this->importer()->import($this->path, dryRun: false);

        $this->assertSame(basename($this->path), $batch->source_name);
        $this->assertSame(hash_file('sha256', $this->path), $batch->source_hash);

        // The workbook holds real member data; its location on disk is not recorded.
        $this->assertStringNotContainsString(DIRECTORY_SEPARATOR, $batch->source_name);
    }

    public function test_a_missing_workbook_is_refused_clearly(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->importer()->import($this->path . '.nope', dryRun: true);
    }

    // ── The synthetic workbook ──────────────────────────────────────────────────

    /** @return string path to a temporary .xlsx that mirrors the real file's shape */
    /**
     * A "present" cell, written as a FORMULA with a cached result — which is what the real
     * grids contain. Roughly 40,000 per sheet there; every attendance mark the importer
     * reads is a formula's cached answer, not a literal.
     */
    private function mark($grid, int $col, int $row, string $time): void
    {
        $grid->setCellValue([$col, $row], '=IF(1=1,"' . $time . '","")');
        $grid->getCell([$col, $row])->setCalculatedValue($time);
    }

    /**
     * @param  bool  $precalculate  when false the writer stores NO formula results, which
     *         is what a workbook re-saved by a tool that does not evaluate formulas looks
     *         like. PhpSpreadsheet computes them on save by default, so this flag is the
     *         only way to produce a genuinely uncached fixture.
     */
    private function buildWorkbook(bool $precalculate = true): string
    {
        $book = new Spreadsheet();

        // ── Daftar Jemaat ───────────────────────────────────────────────────────
        $roster = $book->getActiveSheet();
        $roster->setTitle('Daftar Jemaat');

        $headers = [
            'Edit URL', 'Birthday ID', 'Full Name', 'Email Address', 'iCare', 'Kategori',
            'QR Code URL', 'Timestamp', 'Chinese Name', 'Tanggal Lahir', 'Domisili Taiwan',
            'Line ID ', 'WhatsApp Number', 'Profesi saat ini', 'Tingkat Pendidikan',
            'Sekolah / Kampus / Perusahaan', 'Domisili Gereja IFGF',
        ];

        foreach ($headers as $i => $header) {
            $roster->setCellValue([$i + 1, 1], $header);
        }

        $rows = [
            // series id, name, email, iCare, kategori, chinese, birthday, line, phone, branch
            ['abc123seriesalpha', 'Alpha Tester', 'alpha@example.invalid', 'iCare Linkou',
             'College', '林阿法', '1998-03-14', 'alphaline', '0912345678', 'IFGF Taipei / 台北'],
            ['def456seriesbeta', 'Beta Tester', 'beta@example.invalid', 'iCare Immanuel',
             'Adult', '陳貝塔', '1990-07-01', 'betaline', '0912345679', 'IFGF Zhongli / 中壢'],
            // Same number as Beta, written internationally. The legacy system saw two people.
            ['ghi789seriesgamma', 'Gamma Tester', 'gamma@example.invalid', 'iCare Linkou',
             'Teens and Youth', '', '5/11/0005', '', '+886912345679', 'IFGF Taipei / 台北'],
            ['jkl012seriesdelta', 'Delta Tester', 'delta@example.invalid', 'Belum mengikuti',
             'Kids', '林德他', '2015-01-20', '', '0912345670', 'IFGF Zhongli / 中壢'],
        ];

        foreach ($rows as $i => $row) {
            $r = $i + 2;
            [$series, $name, $email, $icare, $kategori, $chinese, $birthday, $line, $phone, $branch] = $row;

            $roster->setCellValue([1, $r], 'https://docs.google.com/forms/edit');
            $roster->setCellValue([2, $r], $series);
            $roster->setCellValue([3, $r], $name);
            $roster->setCellValue([4, $r], $email);
            $roster->setCellValue([5, $r], $icare);

            // 🔴 Kategori is a FORMULA with a cached result, exactly as in the real file.
            $roster->setCellValue([6, $r], '=IF(E' . $r . '="","","' . $kategori . '")');

            $roster->getCell([6, $r])->setCalculatedValue($kategori);

            $roster->setCellValue([9, $r], $chinese);
            $roster->setCellValueExplicit([10, $r], $birthday,
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $roster->setCellValue([12, $r], $line);
            $roster->setCellValueExplicit([13, $r], $phone,
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $roster->setCellValue([17, $r], $branch);
        }

        // ── Absen-TPE / Absen-ZL ────────────────────────────────────────────────
        // Layout mirrors the real grids: identity in A-E, then Onsite/Online pairs from
        // column F with a merged date in row 6 and the mode in row 7.
        foreach (['Absen-TPE' => ['alpha', 'beta', 'gamma'], 'Absen-ZL' => ['alpha', 'delta']] as $title => $present) {
            $grid = $book->createSheet();
            $grid->setTitle($title);

            foreach (['No. ', 'Full name', 'Email', 'iCare', 'Kategori'] as $i => $header) {
                $grid->setCellValue([$i + 1, 6], $header);
            }

            // Week 1 in F/G, week 2 in H/I. The date sits only in the Onsite column,
            // because row 6 is merged across the pair.
            $grid->setCellValue([6, 6], '2026-09-06');
            $grid->setCellValue([6, 7], 'Onsite');
            $grid->setCellValue([7, 7], 'Online');
            $grid->setCellValue([8, 6], '2026-08-30');
            $grid->setCellValue([8, 7], 'Onsite');
            $grid->setCellValue([9, 7], 'Online');

            $people = [
                'alpha' => 'alpha@example.invalid',
                'beta' => 'beta@example.invalid',
                'gamma' => 'gamma@example.invalid',
                'delta' => 'delta@example.invalid',
            ];

            $r = 8;

            foreach ($people as $key => $email) {
                $grid->setCellValue([1, $r], $r - 7);
                $grid->setCellValue([2, $r], ucfirst($key) . ' Tester');
                $grid->setCellValue([3, $r], $email);

                // Everyone absent by default: 0 in every cell.
                foreach ([6, 7, 8, 9] as $col) {
                    $grid->setCellValue([$col, $r], 0);
                }

                if (in_array($key, $present, true)) {
                    if ($key === 'gamma') {
                        // Online, week 1.
                        $this->mark($grid, 7, $r, '10:05');
                    } elseif ($key === 'beta') {
                        // Onsite, week 2 only.
                        $this->mark($grid, 8, $r, '09:58');
                    } else {
                        // Onsite, week 1.
                        $this->mark($grid, 6, $r, '09:45');

                        // Alpha is ALSO marked online for the same TPE week — the
                        // double-mark that exists once in the real workbook (Absen-TPE
                        // row 213, 2026-08-02, the same time in both columns).
                        if ($title === 'Absen-TPE') {
                            $this->mark($grid, 7, $r, '09:45');
                        }
                    }
                }

                $r++;
            }
        }

        // ── Daftar Absensi (the raw scan log) ───────────────────────────────────
        $log = $book->createSheet();
        $log->setTitle('Daftar Absensi');

        foreach (['Email Address', 'Timestamp', 'Email', 'Lokasi', 'Full name', 'iCare'] as $i => $header) {
            $log->setCellValue([$i + 1, 1], $header);
        }

        $log->setCellValue([2, 2], '2026-09-06 09:45:00');
        $log->setCellValue([3, 2], 'alpha@example.invalid');
        $log->setCellValue([4, 2], 'Taipei');
        $log->setCellValue([5, 2], 'Alpha Tester');

        // A scan by somebody who is not on the roster: kept, flagged unmapped, never dropped.
        $log->setCellValue([2, 3], '2026-09-06 09:50:00');
        $log->setCellValue([3, 3], 'stranger@example.invalid');
        $log->setCellValue([5, 3], 'Not A Member');

        // Absen-TPE_ZL exists in the real file and is deliberately NOT imported.
        $book->createSheet()->setTitle('Absen-TPE_ZL');

        $path = tempnam(sys_get_temp_dir(), 'ifgf_wb_') . '.xlsx';

        $writer = new Xlsx($book);
        $writer->setPreCalculateFormulas($precalculate);
        $writer->save($path);
        $book->disconnectWorksheets();

        return $path;
    }
}
