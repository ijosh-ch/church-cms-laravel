<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use Database\Factories\InstitutionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['country', 'code', 'name_en', 'name_zh'])]
class Institution extends Model
{
    /** @use HasFactory<InstitutionFactory> */
    use HasFactory;

    /** @return HasMany<Member, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
