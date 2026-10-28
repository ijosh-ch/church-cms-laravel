<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Http\Requests\Member\StoreSelfPhotoRequest;
use App\Services\AttendancePhotoStore;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * /profile/photo — the member's own photo, optional (owner, 2026-10-28). Given with consent,
 * withdrawn by deleting it; both are written to the audit log. Only the member sees it for now:
 * who else may (their iCare leaders, for recognising faces) is decided with face detection.
 */
class PhotoController extends Controller
{
    public function show(Request $request): View
    {
        return view('member.photo', ['member' => $request->user()]);
    }

    public function store(StoreSelfPhotoRequest $request, AttendancePhotoStore $photos): RedirectResponse
    {
        $member = $request->user();
        $path = $photos->storeSelf($request->file('photo'), $member);

        $member->forceFill(['photo_path' => $path, 'photo_consent_at' => now()])->save();
        Audit::record('member.photo_consent', $member, ['given' => 'yes']);

        return redirect()->route('profile.photo')->with('status', __('Your photo is saved.'));
    }

    public function image(Request $request): StreamedResponse
    {
        $path = $request->user()->photo_path;

        abort_if($path === null || ! Storage::disk('photos')->exists($path), 404);

        return Storage::disk('photos')->response($path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(Request $request, AttendancePhotoStore $photos): RedirectResponse
    {
        $member = $request->user();

        $photos->delete($member->photo_path);
        $member->forceFill(['photo_path' => null, 'photo_consent_at' => null])->save();
        Audit::record('member.photo_consent', $member, ['given' => 'no']);

        return redirect()->route('profile.photo')->with('status', __('Your photo is deleted.'));
    }
}
