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

namespace Ifgf\ChurchOperations\Support;

/**
 * The single dummy dataset, and the contract for removing it.
 *
 * ─── Why a fixed fixture rather than random factories ───────────────────────────────
 *
 * Every run produces THE SAME rows: the same eight members, the same two branches, the
 * same four iCare groups, the same six weeks of services. That matters because:
 *
 *   - A test can assert an exact number ("Taipei had 3 present on 2026-09-06") instead of
 *     a tautology derived from whatever was generated.
 *   - A failure is reproducible. Random fixtures make a flake indistinguishable from a bug.
 *   - A human demoing the prototype sees a coherent church, not lorem ipsum.
 *
 * ─── Why everything is tagged ───────────────────────────────────────────────────────
 *
 * 🔴 The purge must delete exactly what the seeder made, and nothing else. A development
 * database will contain real imported members alongside demo ones, and "delete everything"
 * is not an acceptable teardown there.
 *
 *   - Database: rows carry is_demo = true. Children (contact points, credentials,
 *     memberships, attendance, calendar links) cascade from the member, so tagging the
 *     graph roots is sufficient.
 *   - Google Calendar: a calendar is SHARED and cannot be truncated. Demo events are
 *     written with extendedProperties.private[DEMO_CALENDAR_TAG] = '1', and the purge
 *     lists by that property and deletes only those. Nothing untagged is ever touched.
 *
 * Names are obviously fictional on purpose. Nobody should ever wonder whether a demo row
 * is a real member of the church.
 */
final class DemoData
{
    /**
     * Google Calendar extendedProperties.private key marking an event as demo data.
     * The purge deletes by this and only this.
     */
    public const CALENDAR_TAG = 'ifgf_demo';

    /** Prefix on every demo member's legacy_row_ref, for eyeballing a table dump. */
    public const REF_PREFIX = 'DEMO-';

    /** Fixed anchor date so "six weeks of Sundays" is identical on every run. */
    public const ANCHOR_SUNDAY = '2026-09-06';

    public const WEEKS = 6;

    /** @return array<int, array<string, string>> */
    public static function branches(): array
    {
        return [
            ['code' => 'TPE', 'name' => 'Taipei', 'name_zh' => '台北', 'gmaps_url' => 'https://g.co/kgs/ue5CEtv'],
            ['code' => 'ZL', 'name' => 'Zhongli', 'name_zh' => '中壢', 'gmaps_url' => 'https://g.co/kgs/xKRLyXC'],
        ];
    }

    /** The four categories the quarterly report groups by, in workbook order. */
    public static function categories(): array
    {
        return [
            ['code' => 'adult', 'name' => 'Adult', 'sort_order' => 1],
            ['code' => 'college', 'name' => 'College', 'sort_order' => 2],
            ['code' => 'teens_youth', 'name' => 'Teens and Youth', 'sort_order' => 3],
            ['code' => 'kids', 'name' => 'Kids', 'sort_order' => 4],
        ];
    }

    /** Four groups, named after real ones so the UI looks right, but demo-tagged. */
    public static function groups(): array
    {
        return [
            ['name' => 'Demo iCare Linkou', 'slug' => 'demo-icare-linkou', 'branch' => 'TPE'],
            ['name' => 'Demo iCare Tamkang', 'slug' => 'demo-icare-tamkang', 'branch' => 'TPE'],
            ['name' => 'Demo iCare Immanuel', 'slug' => 'demo-icare-immanuel', 'branch' => 'ZL'],
            ['name' => 'Demo iCare Joys', 'slug' => 'demo-icare-joys', 'branch' => 'ZL'],
        ];
    }

    /**
     * Eight members. Deliberately shaped to exercise the edges:
     *
     *   - two with NO iCare        → the "Belum mengikuti" left-join path (62/217 in reality)
     *   - one with no Chinese name → 17 of 217 are missing it
     *   - one with no birthday     → the calendar sync must skip, not crash
     *   - both branches, all four categories
     *   - one born 29 February     → the birthday recurrence edge nobody tests
     */
    public static function members(): array
    {
        return [
            ['ref' => 'M01', 'name' => 'Demo Andi Wijaya', 'zh' => '黃安迪', 'birthday' => '1996-03-14',
             'branch' => 'TPE', 'category' => 'adult', 'group' => 'demo-icare-linkou',
             'email' => 'demo.andi@example.invalid', 'phone' => '0912345001', 'gender' => 'male'],

            ['ref' => 'M02', 'name' => 'Demo Sinta Halim', 'zh' => '林欣婷', 'birthday' => '2003-07-22',
             'branch' => 'TPE', 'category' => 'college', 'group' => 'demo-icare-linkou',
             'email' => 'demo.sinta@example.invalid', 'phone' => '+886912345002', 'gender' => 'female'],

            ['ref' => 'M03', 'name' => 'Demo Budi Santoso', 'zh' => '陳文德', 'birthday' => '2000-02-29',
             'branch' => 'TPE', 'category' => 'college', 'group' => 'demo-icare-tamkang',
             'email' => 'demo.budi@example.invalid', 'phone' => '886912345003', 'gender' => 'male'],

            // No Chinese name.
            ['ref' => 'M04', 'name' => 'Demo Grace Tanuwijaya', 'zh' => null, 'birthday' => '2009-11-02',
             'branch' => 'TPE', 'category' => 'teens_youth', 'group' => null,
             'email' => 'demo.grace@example.invalid', 'phone' => '0912345004', 'gender' => 'female'],

            ['ref' => 'M05', 'name' => 'Demo Hendra Kusuma', 'zh' => '吳建豪', 'birthday' => '1988-05-30',
             'branch' => 'ZL', 'category' => 'adult', 'group' => 'demo-icare-immanuel',
             'email' => 'demo.hendra@example.invalid', 'phone' => '0912345005', 'gender' => 'male'],

            ['ref' => 'M06', 'name' => 'Demo Melisa Chandra', 'zh' => '張美莉', 'birthday' => '2002-09-18',
             'branch' => 'ZL', 'category' => 'college', 'group' => 'demo-icare-joys',
             'email' => 'demo.melisa@example.invalid', 'phone' => '0912345006', 'gender' => 'female'],

            // No birthday — the calendar sync must skip this member, not fail on it.
            ['ref' => 'M07', 'name' => 'Demo Rio Pratama', 'zh' => '李瑞歐', 'birthday' => null,
             'branch' => 'ZL', 'category' => 'teens_youth', 'group' => 'demo-icare-joys',
             'email' => 'demo.rio@example.invalid', 'phone' => '0912345007', 'gender' => 'male'],

            // No iCare, youngest category.
            ['ref' => 'M08', 'name' => 'Demo Keira Lim', 'zh' => '林凱拉', 'birthday' => '2018-01-08',
             'branch' => 'ZL', 'category' => 'kids', 'group' => null,
             'email' => 'demo.keira@example.invalid', 'phone' => '0912345008', 'gender' => 'female'],
        ];
    }

    /**
     * Who attended which week, as an index into members() per Sunday offset.
     *
     * Week 0 is ANCHOR_SUNDAY, week 5 is five Sundays earlier. Fixed, so a report test can
     * assert real totals: e.g. Taipei week 0 has exactly M01, M02, M04 present.
     *
     * @return array<int, array<string, list<string>>>
     */
    public static function attendance(): array
    {
        return [
            0 => ['TPE' => ['M01', 'M02', 'M04'], 'ZL' => ['M05', 'M06', 'M08']],
            1 => ['TPE' => ['M01', 'M03'],        'ZL' => ['M05', 'M07']],
            2 => ['TPE' => ['M01', 'M02', 'M03'], 'ZL' => ['M05', 'M06', 'M07', 'M08']],
            3 => ['TPE' => ['M02'],               'ZL' => ['M06']],
            4 => ['TPE' => ['M01', 'M02', 'M03', 'M04'], 'ZL' => ['M05', 'M06']],
            5 => ['TPE' => ['M01'],               'ZL' => ['M05', 'M07', 'M08']],
        ];
    }

    /** Members marked online rather than onsite, so both modes appear in reports. */
    public static function onlineRefs(): array
    {
        return ['M04', 'M08'];
    }
}
