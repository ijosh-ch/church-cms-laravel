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

namespace App\Http\Controllers\Ifgf;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request)
    {
        $locale = (string) $request->query('locale');

        if (in_array($locale, config('ifgf-sites.locales', []), true)) {
            $request->session()->put('ifgf_locale', $locale);
        }

        // Only ever return to a path on THIS host. 'to' arrives from a query string, so
        // redirecting to it blindly would be an open redirect.
        $to = (string) $request->query('to');

        if ($to !== '' && str_starts_with($to, $request->getSchemeAndHttpHost())) {
            return redirect($to);
        }

        return back();
    }
}
