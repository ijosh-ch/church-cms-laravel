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

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the UI language: session → Accept-Language → configured default.
 *
 * ⚠ This switches LABELS only. It must never touch stored values — 'iCare Linkou',
 * 'Belum mengikuti', 'Adult', 'College', 'Teens and Youth' and 'Kids' are data, and the
 * import identity map matches on them exactly. Translating a stored value would silently
 * break member-to-row matching.
 */
class SetIfgfLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = config('ifgf-sites.locales', ['id', 'en', 'zh_TW']);

        $locale = $request->session()->get('ifgf_locale')
            ?? $this->fromHeader($request, $supported)
            ?? config('ifgf-sites.default_locale', 'id');

        if (in_array($locale, $supported, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }

    private function fromHeader(Request $request, array $supported): ?string
    {
        foreach ($request->getLanguages() as $language) {
            // getLanguages() yields 'zh_TW' style tags already; also accept a bare 'zh'
            // and a regionless 'id'/'en'.
            if (in_array($language, $supported, true)) {
                return $language;
            }

            $base = explode('_', $language)[0];

            if ($base === 'zh') {
                return 'zh_TW';
            }

            if (in_array($base, $supported, true)) {
                return $base;
            }
        }

        return null;
    }
}
