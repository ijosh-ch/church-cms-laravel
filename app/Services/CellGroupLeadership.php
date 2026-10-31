<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Services;

use App\Enums\CellGroupRole;
use App\Models\CellGroup;
use App\Models\CellGroupLeader;
use App\Models\Member;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Who leads and who belongs to an iCare group (PRD FR-9, "Managing iCare groups").
 *
 * Leader rows are never deleted: stepping down sets left_at, so who led which group when stays
 * answerable. Every change is written to the audit log with ids only.
 */
class CellGroupLeadership
{
    /**
     * A new iCare. Its creator leads it at once, unless the creator is the IT admin: an admin's
     * group waits for the admin to assign a leader (owner, 2026-10-28).
     *
     * @param  array<string, mixed>  $data  validated: name, branch_id, location, gmaps_url, meeting_weekday
     */
    public function create(array $data, Member $creator): CellGroup
    {
        return DB::transaction(function () use ($data, $creator): CellGroup {
            $group = CellGroup::create([...$data, 'is_active' => true]);

            Audit::recordGroup('cell_group.created', $group);

            if (! $creator->can('admin')) {
                $this->startRole($group, $creator, CellGroupRole::Leader);
            }

            return $group;
        });
    }

    /**
     * IT admin only: the group's leader becomes $member. The previous leader steps down (left_at);
     * if $member was a co-leader of this group, that role ends too.
     */
    public function assignLeader(CellGroup $group, Member $member): void
    {
        DB::transaction(function () use ($group, $member): void {
            CellGroup::whereKey($group->getKey())->lockForUpdate()->first();

            $current = $this->currentRows($group)->get();

            if ($current->contains(fn (CellGroupLeader $row) => $row->member_id === $member->id && $row->role === CellGroupRole::Leader)) {
                return;
            }

            foreach ($current as $row) {
                if ($row->role === CellGroupRole::Leader || $row->member_id === $member->id) {
                    $row->forceFill(['left_at' => now()])->save();
                    Audit::record('cell_group.leader_left', $row->member, ['cell_group' => $group->id, 'role' => $row->role->value]);
                }
            }

            $this->startRole($group, $member, CellGroupRole::Leader);
        });
    }

    /** Adds a co-leader. Someone who already leads or co-leads this group is left as they are. */
    public function addCoLeader(CellGroup $group, Member $member): bool
    {
        return DB::transaction(function () use ($group, $member): bool {
            CellGroup::whereKey($group->getKey())->lockForUpdate()->first();

            if ($this->currentRows($group)->where('member_id', $member->id)->exists()) {
                return false;
            }

            $this->startRole($group, $member, CellGroupRole::CoLeader);

            return true;
        });
    }

    /** Ends a co-leader's role. False when $member is not a current co-leader of this group. */
    public function removeCoLeader(CellGroup $group, Member $member): bool
    {
        $row = $this->currentRows($group)
            ->where('member_id', $member->id)
            ->where('role', CellGroupRole::CoLeader->value)
            ->first();

        if ($row === null) {
            return false;
        }

        $row->forceFill(['left_at' => now()])->save();
        Audit::record('cell_group.leader_left', $member, ['cell_group' => $group->id, 'role' => CellGroupRole::CoLeader->value]);

        return true;
    }

    /**
     * Makes this group $member's main iCare, active. Someone whose main iCare was another group
     * moves here. Saved through the model, so the birthday calendar follows (MemberObserver).
     */
    public function addMember(CellGroup $group, Member $member): bool
    {
        if ($member->cell_group_id === $group->id) {
            return false;
        }

        $from = $member->cell_group_id;
        $member->forceFill(['cell_group_id' => $group->id, 'cell_group_active' => true])->save();

        Audit::record('cell_group.member_added', $member, ['cell_group' => $group->id, 'from_cell_group' => $from]);

        return true;
    }

    /** $member no longer has a main iCare ("Belum join iCare"). False when not in this group. */
    public function removeMember(CellGroup $group, Member $member): bool
    {
        if ($member->cell_group_id !== $group->id) {
            return false;
        }

        $member->forceFill(['cell_group_id' => null, 'cell_group_active' => true])->save();

        Audit::record('cell_group.member_removed', $member, ['cell_group' => $group->id]);

        return true;
    }

    /** @return Builder<CellGroupLeader> */
    private function currentRows(CellGroup $group): Builder
    {
        return CellGroupLeader::query()->where('cell_group_id', $group->id)->whereNull('left_at');
    }

    private function startRole(CellGroup $group, Member $member, CellGroupRole $role): void
    {
        CellGroupLeader::create([
            'cell_group_id' => $group->id,
            'member_id' => $member->id,
            'role' => $role,
        ]);

        Audit::record('cell_group.leader_started', $member, ['cell_group' => $group->id, 'role' => $role->value]);
    }
}
