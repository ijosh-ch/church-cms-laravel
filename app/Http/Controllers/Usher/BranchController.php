<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers\Usher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Usher\ChooseBranchRequest;
use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PRD FR-7 — the usher picks the branch they are standing in; it is remembered for the
 * session and decides which Sunday Service every scan belongs to.
 */
class BranchController extends Controller
{
    public const SESSION_KEY = 'usher.branch';

    public function show(): View
    {
        return view('usher.branch', [
            'branches' => Branch::where('is_active', true)->orderBy('id')->get(),
        ]);
    }

    public function store(ChooseBranchRequest $request): RedirectResponse
    {
        $request->session()->put(self::SESSION_KEY, $request->validated('branch'));

        return redirect()->route('usher.scan');
    }

    /**
     * The branch chosen for this session, or null when none has been chosen yet.
     */
    public static function chosen(Request $request): ?Branch
    {
        $code = $request->session()->get(self::SESSION_KEY);

        return is_string($code) ? Branch::where('code', $code)->where('is_active', true)->first() : null;
    }
}
