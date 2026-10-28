<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services;

use App\Models\Branch;
use App\Models\Member;
use App\Support\PhoneNormalizer;

/**
 * Turns the validated fields of a profile form into member attributes. Shared by the member's
 * own profile and the IT admin's member page, so both store a value the same way.
 */
class MemberEditor
{
    public function __construct(private readonly PhoneNormalizer $normalizer) {}

    /**
     * @param  array<string, mixed>  $data  validated form fields
     * @return array<string, mixed> attributes for Member::update()
     */
    public function attributes(array $data): array
    {
        $occupation = $data['occupation'] ?? null;
        $icare = $data['icare'] ?? null;
        $phone = $this->normalizer->normalize($data['phone'] ?? null);

        return [
            'full_name' => trim($data['full_name']),
            'chinese_name' => $this->blankToNull($data['chinese_name'] ?? null),
            'birthday' => $this->blankToNull($data['birthday'] ?? null),
            'domicile_taiwan' => $this->blankToNull($data['domicile_taiwan'] ?? null),
            'line_id' => $this->blankToNull($data['line_id'] ?? null),
            'phone' => $phone === '' ? null : $phone,
            'branch_id' => Branch::where('code', $data['branch'])->value('id'),
            'occupation' => $occupation === 'other'
                ? trim((string) $data['occupation_other'])
                : $this->blankToNull($occupation),
            'education_level' => $this->blankToNull($data['education_level'] ?? null),
            'school_or_company' => $this->blankToNull($data['school_or_company'] ?? null),
            'cell_group_id' => in_array($icare, [null, '', Member::NO_ICARE], true) ? null : (int) $icare,
        ];
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
