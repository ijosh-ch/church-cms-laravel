<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Characterization test for UP-005 (UPSTREAM.md).
 *
 * Locks in the observed behaviour of ImportMemberController::importUsers()
 * at app/Http/Controllers/Admin/ImportMemberController.php:56 — the
 * Excel::import() call that is the confirmed live sink for the
 * phpoffice/phpspreadsheet advisories (DEPENDENCY_INVENTORY.md
 * "Prioritize now" row). This test must pass unchanged both before and
 * after the phpoffice/phpspreadsheet ^1.30.0 -> latest-1.30.x upgrade.
 *
 * Scope note: the fixture is a header-only CSV (the exact template
 * produced by ImportMemberController::downloadFormat()) with zero data
 * rows. app/Imports/UsersImport.php's collection() method dereferences an
 * undefined $request variable ("Attempt to assign property ... on null",
 * a PHP Error, not caught by the surrounding catch(Exception)) as soon as
 * count($rows) > 0 — a preexisting, unrelated defect in the business
 * logic that fires for ANY nonempty import, independent of which
 * phpspreadsheet version parsed the file. Reproducing it here would
 * characterize that defect, not the Excel::import() parsing boundary the
 * dependency upgrade actually touches, and it is not part of UP-005's
 * scope. Left as-is for now; worth its own characterization test and
 * possible fix when WP 0A item 6 covers member import in full.
 */
class MemberImportCharacterizationTest extends TestCase
{
    use RefreshDatabase;

    private function headerOnlyCsv(): UploadedFile
    {
        $headers = [
            'ref_name', 'firstname', 'lastname', 'birth_firstname', 'birth_lastname',
            'gender', 'date_of_birth', 'occupation', 'sub-category', 'address', 'city',
            'state', 'country', 'pincode', 'mobile_no', 'email', 'aadhar_number',
            'membership_type', 'family', 'marriage_status', 'marriage_start_date',
            'relation', 'notes',
        ];

        $content = implode(',', $headers) . "\n";

        return UploadedFile::fake()->createWithContent('import.csv', $content);
    }

    public function test_importing_a_header_only_file_reports_no_records_inserted(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withoutMiddleware()
            ->post('/admin/importUsers', [
                'import_file' => $this->headerOnlyCsv(),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('failmessage', 'No Records Inserted.');
        $response->assertSessionMissing('successmessage');

        $this->assertSame(1, User::count(), 'the header-only import must not create any member row');
    }
}
