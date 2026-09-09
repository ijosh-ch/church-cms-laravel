<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Test accounts for walking the prototype by hand.
 *
 * Creates the minimum upstream rows the usher login needs — a church, a user group, the
 * 'usher' role, and two accounts — then leaves the ifgf_ demo fixture to
 * `ifgf:demo:seed`, which owns it.
 *
 * 🔴 SAFETY. Refuses to run outside APP_ENV=local, and refuses on any database whose name
 * is not the local development one. These accounts have known passwords printed to the
 * console; they must never exist anywhere reachable.
 *
 * 🔴 usergroup_id is deliberately NOT 3. That is the value Gate::before grants every
 * ability to (SEC-001) and the value Auth\RegisterController hands to anyone who
 * self-registers. An usher test account set to 3 would be a superuser and would make every
 * authorisation check in the prototype meaningless — the opposite of what a test account
 * is for.
 */
class IfgfDevArenaSeeder extends Seeder
{
    /** Ordinary, non-privileged user group. Anything but 3. */
    private const USERGROUP_MEMBER = 2;

    public const USHER_EMAIL = 'usher@ifgf.test';
    public const USHER_PASSWORD = 'usher-password';

    public const MEMBER_EMAIL = 'member@ifgf.test';
    public const MEMBER_PASSWORD = 'member-password';

    public function run(): void
    {
        $this->guard();

        $churchId = $this->church();
        $this->userGroup();

        $usherRoleId = $this->role('usher', 'Usher', 'Records attendance at a service');

        $usherId = $this->user($churchId, self::USHER_EMAIL, 'Test Usher', self::USHER_PASSWORD);
        $this->attachRole($usherId, $usherRoleId);

        $memberId = $this->user($churchId, self::MEMBER_EMAIL, 'Test Member', self::MEMBER_PASSWORD);

        $this->command?->newLine();
        $this->command?->info('Dev arena accounts ready:');
        $this->command?->table(
            ['role', 'email', 'password', 'where'],
            [
                ['usher', self::USHER_EMAIL, self::USHER_PASSWORD, '/usher/login'],
                ['member', self::MEMBER_EMAIL, self::MEMBER_PASSWORD, '(no login yet - P2)'],
            ]
        );
        $this->command?->warn('Local development only. Never seed these anywhere reachable.');
    }

    /** @throws \RuntimeException */
    private function guard(): void
    {
        $env = app()->environment();
        $database = DB::connection()->getDatabaseName();
        $driver = DB::connection()->getDriverName();

        $this->command?->line("  environment: {$env}");
        $this->command?->line("  driver:      {$driver}");
        $this->command?->line("  database:    {$database}");

        if ($env !== 'local' && $env !== 'testing') {
            throw new \RuntimeException("Refusing to seed test accounts in environment [{$env}].");
        }

        if (! in_array($database, ['ifgf_cms', 'ifgf_cms_test', 'churchcms_test_disposable'], true)) {
            throw new \RuntimeException(
                "Refusing to seed test accounts into database [{$database}] — it is not a known "
                . 'local development database.'
            );
        }
    }

    private function church(): int
    {
        $existing = DB::table('church')->first();

        if ($existing) {
            return $existing->id;
        }

        // name, address, pincode and slug are all NOT NULL with no default in the
        // upstream schema — checked against information_schema rather than guessed.
        return DB::table('church')->insertGetId([
            'name' => 'IFGF Taipei Zhongli',
            'address' => 'Taipei, Taiwan',
            'pincode' => '10000',
            'slug' => 'ifgf-taipei-zhongli',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function userGroup(): void
    {
        if (! DB::table('user_group')->where('id', self::USERGROUP_MEMBER)->exists()) {
            DB::table('user_group')->insert([
                'id' => self::USERGROUP_MEMBER,
                'name' => 'Member',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function role(string $name, string $display, string $description): int
    {
        $existing = DB::table('roles')->where('name', $name)->first();

        if ($existing) {
            return $existing->id;
        }

        return DB::table('roles')->insertGetId([
            'name' => $name,
            'display_name' => $display,
            'description' => $description,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function user(int $churchId, string $email, string $name, string $password): int
    {
        $existing = DB::table('users')->where('email', $email)->first();

        if ($existing) {
            // Reset the password so a re-run always leaves a usable account.
            DB::table('users')->where('id', $existing->id)->update([
                'password' => Hash::make($password),
                'updated_at' => now(),
            ]);

            return $existing->id;
        }

        return DB::table('users')->insertGetId([
            'church_id' => $churchId,
            'usergroup_id' => self::USERGROUP_MEMBER,
            'name' => $name,
            'email' => $email,
            'mobile_no' => '0900000000',
            'password' => Hash::make($password),
            'email_verified' => true,
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function attachRole(int $userId, int $roleId): void
    {
        $exists = DB::table('role_user')
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->exists();

        if ($exists) {
            return;
        }

        // Laratrust's pivot carries the model type alongside the id.
        DB::table('role_user')->insert([
            'user_id' => $userId,
            'role_id' => $roleId,
            'user_type' => \App\Models\User::class,
        ]);
    }
}
