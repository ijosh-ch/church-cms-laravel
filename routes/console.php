<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

use Illuminate\Support\Facades\Schedule;

// The birthday calendar is not scheduled: it is written only when a member's record changes
// (MemberObserver), and `php artisan ifgf:calendar:sync` reconciles it by hand.

// PRD FR-1 — one reminder, a week after registering, to complete the profile.
Schedule::command('ifgf:profile:remind')->dailyAt('19:00')->timezone('Asia/Taipei');
