<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Models;

use App\Enums\EducationLevel;
use App\Enums\HouseholdRole;
use App\Enums\MemberCategory;
use App\Enums\MemberStatus;
use App\Enums\TeamAbility;
use App\Enums\TeamRole;
use App\Observers\MemberObserver;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

/**
 * Everyone is a Member, and Member is the authenticatable model — there is no users table
 * and no password. Access comes only from team membership.
 *
 * Not mass-assignable on purpose: public_ref, category (always computed), the QR and
 * calendar columns, email_verified_at and legacy_ref.
 */
#[Fillable([
    'household_id',
    'household_role',
    'branch_id',
    'icare_team_id',
    'institution_id',
    'full_name',
    'chinese_name',
    'email',
    'phone',
    'line_id',
    'birthday',
    'domicile_taiwan',
    'occupation',
    'education_level',
    'school_or_company',
    'status',
])]
#[Hidden(['qr_token_hash', 'qr_token_enc', 'remember_token'])]
#[ObservedBy(MemberObserver::class)]
class Member extends Authenticatable
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory, HasUuids, Notifiable, SoftDeletes;

    /** The choices of "Profesi saat ini"; `other` takes typed text. */
    public const OCCUPATIONS = ['worker', 'student', 'homemaker', 'other'];

    /** "Belum mengikuti" — not in an iCare group yet. */
    public const NO_ICARE = 'none';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => MemberStatus::Active->value,
    ];

    protected static function booted(): void
    {
        // PRD FR-5: the category is stored, and recomputed whenever its inputs change.
        static::saving(function (Member $member): void {
            if ($member->category === null || $member->isDirty(['occupation', 'education_level', 'birthday'])) {
                $member->category = MemberCategory::for(
                    $member->occupation,
                    $member->education_level,
                    $member->birthday,
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'household_role' => HouseholdRole::class,
            'education_level' => EducationLevel::class,
            'category' => MemberCategory::class,
            'status' => MemberStatus::class,
            'qr_token_enc' => 'encrypted',
            'calendar_synced_at' => 'datetime',
            'profile_reminded_at' => 'datetime',
            'email_verified_at' => 'datetime',
        ];
    }

    /**
     * The random public_ref is generated on create; the primary key stays an integer.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['public_ref'];
    }

    /**
     * Fully random (version 4). The framework's default is time-ordered, which would let
     * anyone holding two references tell which member registered first.
     */
    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * No sequential id in a URL — routes bind members by public_ref.
     */
    public function getRouteKeyName(): string
    {
        return 'public_ref';
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<Household, $this> */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function icareTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'icare_team_id');
    }

    /**
     * Teams the member serves in now. Rows with left_at are history and are not returned.
     *
     * @return BelongsToMany<Team, $this>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot(['role', 'left_at'])
            ->withTimestamps()
            ->wherePivotNull('left_at');
    }

    /**
     * Access comes only from team membership (PRD §2): being in an active team that carries
     * the ability — `scan` for ushers, `admin` for IT.
     *
     * Registration signs a person in without proof that the email is theirs, so a role counts
     * only once the email is verified. Otherwise whoever typed a future usher's email first
     * would hold that usher's access.
     */
    public function hasAbility(TeamAbility $ability): bool
    {
        return $this->email_verified_at !== null
            && $this->teams()->where('ability', $ability)->where('is_active', true)->exists();
    }

    /**
     * Whether the member is a leader of this one team. Needs a verified email, as above.
     */
    public function leads(Team $team): bool
    {
        return $this->email_verified_at !== null
            && $this->teams()->whereKey($team->getKey())->wherePivot('role', TeamRole::Leader->value)->exists();
    }

    /** @return HasMany<Attendance, $this> */
    /**
     * Registration asks only the basics (PRD FR-1). Someone who plans to come again is asked
     * for the rest on their QR page, and once by email, until these are filled in.
     */
    public function needsProfile(): bool
    {
        return $this->status !== MemberStatus::Visitor
            && ($this->domicile_taiwan === null || $this->occupation === null || $this->school_or_company === null);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /** @return HasMany<ChangeRequest, $this> */
    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class);
    }
}
