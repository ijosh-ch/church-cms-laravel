<?php

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace App\Console\Commands;

use App\Enums\TeamAbility;
use App\Enums\TeamRole;
use App\Models\Member;
use App\Models\Team;
use App\Support\Audit;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * PRD §2 — the first IT admin is created here, never through a web form.
 */
#[Signature('ifgf:admin:grant {email : The registered email of the member to make an IT admin}')]
#[Description('Put a registered member into the IT team, which makes them an IT admin')]
class GrantAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $member = Member::where('email', mb_strtolower(trim($this->argument('email'))))->first();

        if ($member === null) {
            $this->error('No member has that email. They must register first.');

            return self::FAILURE;
        }

        $team = Team::where('ability', TeamAbility::Admin)->where('is_active', true)->orderBy('id')->first();

        if ($team === null) {
            $this->error('There is no active team with the admin ability. Run the seeders first.');

            return self::FAILURE;
        }

        if ($member->hasAbility(TeamAbility::Admin)) {
            $this->info("Member #{$member->id} is already an IT admin.");

            return self::SUCCESS;
        }

        $member->teams()->attach($team, ['role' => TeamRole::Member->value]);

        Audit::record('team.joined', $member, ['team' => $team->id, 'role' => TeamRole::Member->value]);

        $this->info("Member #{$member->id} is now an IT admin (team: {$team->name}).");

        return self::SUCCESS;
    }
}
