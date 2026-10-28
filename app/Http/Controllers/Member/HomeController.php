<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Services\QrIssuer;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in member's own page. It takes no member from the URL, so it can only ever show
 * the person who is signed in (PRD §5.3).
 */
class HomeController extends Controller
{
    public function __invoke(Request $request, QrIssuer $qr): View
    {
        $member = $request->user()->load(['branch', 'cellGroup']);

        return view('member.home', [
            'member' => $member,
            'qrSvg' => $member->qr_token_enc ? $qr->svg($member->qr_token_enc) : null,
        ]);
    }
}
