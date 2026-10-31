<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers\CellGroup;

use App\Http\Controllers\Controller;
use App\Http\Requests\CellGroup\SearchPeopleRequest;
use App\Models\CellGroup;
use Illuminate\Http\RedirectResponse;

/**
 * Edit iCare's "find a church member" box (PRD FR-9). The typed name is kept in the session for
 * the next page only, so it never appears in a URL or the access log.
 */
class PeopleSearchController extends Controller
{
    private const SECTIONS = ['leader' => 'leader', 'co_leader' => 'co-leaders', 'member' => 'members'];

    public function __invoke(SearchPeopleRequest $request, CellGroup $cellGroup): RedirectResponse
    {
        $for = $request->validated('for');

        return redirect()->to(route('icare.edit', $cellGroup).'#'.self::SECTIONS[$for])
            ->with('people_search', ['for' => $for, 'q' => $request->validated('q')]);
    }
}
