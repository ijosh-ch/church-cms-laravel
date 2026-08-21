<?php

namespace Tests\Feature\Regression;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Characterization — the `protected $dates` upgrade regression.
 * WP 0A item 6 / gate 5. Cross-cutting: it belongs to no single TESTING_PLAN.md suite.
 *
 * CAPTURES WHAT IS. Every assertion here pins BROKEN behaviour. A green run means
 * "this has not changed", never "this is correct".
 *
 * ══ WHAT HAPPENED ══
 *
 * Eloquent's `protected $dates` property was deprecated in Laravel 8, and REMOVED in
 * LARAVEL 10. Its replacement is `protected $casts = ['col' => 'datetime']`. This
 * application declares `$dates` on 37 models and was carried from Laravel 10 to 13 by
 * WP 0B — so on HEAD the property is inert. Illuminate\Database\Eloquent\Model no
 * longer defines it, nothing reads it, and no error is raised: the models simply stop
 * casting and every listed column comes back as a plain STRING.
 *
 * MEASURED 2026-08-21 on Laravel 13.24.0: 21 columns across 9 models are affected.
 *
 * ══ WHY THIS MATTERS MORE THAN A TYPE ANNOYANCE ══
 *
 * 1. Any ->format(), ->diffInDays(), ->isPast() or Carbon comparison on these columns
 *    is a FATAL Error, not a warning. EventAttendanceController::export() line 188 is
 *    one such call site and the attendance CSV export has been dead since the upgrade
 *    — pinned by ExportCharacterizationTest::
 *    test_documents_defect_attendance_export_fatals_on_the_removed_dates_property.
 * 2. Comparisons that DON'T fatal silently compare strings instead of instants. That is
 *    the dangerous half: '2026-8-1' < '2026-12-1' is false as a string comparison.
 * 3. `Userprofile::date_of_birth` is in the broken set, and the birthday routes
 *    (TESTING_PLAN.md suite 8) derive from it.
 * 4. It interacts directly with the settled UTC-at-rest timestamp contract
 *    (CONTEXT.md): a column that is never cast is never converted either, so the rule
 *    "any code deriving a calendar day from an instant must convert to the branch
 *    timezone first" cannot even be applied to these 21 columns.
 *
 * ══ WHY IT IS RECORDED AS A REGRESSION, NOT AN UPSTREAM DEFECT ══
 *
 * This code was CORRECT on Laravel 9. It was broken by WP 0B's 10 -> 13 traversal,
 * which was performed with no behavioural baseline by explicit owner directive
 * (MEMORY.md 2026-08-10). It is the first hard evidence that that traversal broke
 * working behaviour, which is precisely what exit-gate criterion 2 exists to surface.
 * It is NOT fixed here: fixing and characterizing in one pass destroys the baseline.
 *
 * ══ WHEN THE FIX LANDS ══
 *
 * Migrating a model to $casts turns its rows in BROKEN_COLUMNS red. That is the fix
 * being noticed. Move the row to CAST_COLUMNS rather than deleting the test, so the
 * remaining count keeps working as a countdown. app/Models/Post.php already carries
 * the correct pattern and is asserted below as the worked example.
 */
class DateCastRegressionCharacterizationTest extends TestCase
{
    /**
     * Columns declared in `protected $dates` that are NOT cast on HEAD.
     * Measured 2026-08-21, Laravel 13.24.0.
     */
    private const BROKEN_COLUMNS = [
        [\App\Models\Attendance::class, 'date'],
        [\App\Models\Attendance::class, 'present_at'],
        [\App\Models\Authentication::class, 'expires_on'],
        [\App\Models\Contact::class, 'date_of_submission'],
        [\App\Models\EventAttendanceSession::class, 'attendance_date'],
        [\App\Models\EventAttendanceSession::class, 'locked_at'],
        [\App\Models\EventAttendee::class, 'scanned_at'],
        [\App\Models\MailQueue::class, 'scheduled_at'],
        [\App\Models\MailQueue::class, 'fired_at'],
        [\App\Models\MailQueue::class, 'failed_at'],
        [\App\Models\MailQueue::class, 'rule_checked_at'],
        [\App\Models\Quote::class, 'publish_on'],
        [\App\Models\Quote::class, 'published_at'],
        [\App\Models\SendMail::class, 'executed_at'],
        [\App\Models\SendMail::class, 'fired_at'],
        [\App\Models\SendMail::class, 'read_at'],
        [\App\Models\SendMail::class, 'deleted_at'],
        [\App\Models\Userprofile::class, 'date_of_birth'],
        [\App\Models\Userprofile::class, 'baptism_date'],
        [\App\Models\Userprofile::class, 'membership_start_date'],
        [\App\Models\Userprofile::class, 'marriage_start_date'],
    ];

    /**
     * Columns ALSO listed in `protected $dates` that survive — because the model
     * declares them in $casts as well, or because SoftDeletes supplies the cast.
     */
    private const CAST_COLUMNS = [
        [\App\Models\Post::class, 'post_created_at'],
        [\App\Models\Post::class, 'posted_at'],
        [\App\Models\User::class, 'email_verified_at'],
        [\App\Models\User::class, 'last_login_at'],
    ];

    /**
     * The ROOT CAUSE, asserted against the framework rather than inferred.
     *
     * If this ever fails, Laravel has reintroduced the property and every other test
     * in this file is meaningless — read the upgrade notes before touching them.
     */
    public function test_documents_the_dates_property_no_longer_exists_on_the_base_model(): void
    {
        $this->assertFalse(
            (new \ReflectionClass(Model::class))->hasProperty('dates'),
            'Eloquent\Model declares $dates again. The regression this whole file '
            .'documents may have evaporated — re-measure before deleting anything.'
        );
    }

    /**
     * DOCUMENTS DEFECT — 21 date columns silently return strings.
     *
     * Asserted per column so a partial fix reports exactly which model was migrated
     * rather than one opaque failure.
     */
    public function test_documents_defect_dates_columns_are_not_cast(): void
    {
        foreach (self::BROKEN_COLUMNS as [$class, $column]) {
            $model = new $class;
            $model->setRawAttributes([$column => '2026-08-16 01:00:00'], true);

            $this->assertIsString(
                $model->{$column},
                class_basename($class)."::{$column} IS NOW CAST. That is the FIX landing. "
                .'Move this row from BROKEN_COLUMNS to CAST_COLUMNS (do not delete it), '
                .'and check every ->format()/->diff* call site on the column — they were '
                .'fatal before and are live again now.'
            );
            $this->assertArrayNotHasKey(
                $column,
                $model->getCasts(),
                class_basename($class)."::{$column} appears in \$casts but still reads as a "
                .'string. Something else is overriding the cast — investigate before '
                .'trusting any date on this model.'
            );
        }
    }

    /**
     * The countdown. Deliberately brittle: it is SUPPOSED to go red when someone
     * fixes a model, so the remaining scope is re-stated rather than drifting.
     */
    public function test_documents_the_size_of_the_uncast_date_surface(): void
    {
        $stillBroken = 0;
        foreach (self::BROKEN_COLUMNS as [$class, $column]) {
            $model = new $class;
            $model->setRawAttributes([$column => '2026-08-16 01:00:00'], true);
            if (is_string($model->{$column})) {
                $stillBroken++;
            }
        }

        $this->assertSame(
            21,
            $stillBroken,
            'The number of uncast date columns has changed from the 2026-08-21 baseline '
            .'of 21. If it went DOWN, a model was migrated to $casts — update this '
            .'number and move the row. If it went UP, a new model was written using the '
            .'removed $dates property; that is a code-review gap worth closing.'
        );
    }

    /**
     * The fix pattern already present in the codebase, asserted as the worked example
     * so the remedy is not guesswork. app/Models/Post.php declares BOTH $dates and
     * $casts; only $casts does anything.
     */
    public function test_models_declaring_casts_still_return_carbon(): void
    {
        foreach (self::CAST_COLUMNS as [$class, $column]) {
            $model = new $class;
            $model->setRawAttributes([$column => '2026-08-16 01:00:00'], true);

            $this->assertInstanceOf(
                Carbon::class,
                $model->{$column},
                class_basename($class)."::{$column} has STOPPED casting. This is a "
                .'regression on top of the regression — the $casts declaration was '
                .'probably removed with the $dates cleanup.'
            );
        }
    }

    /**
     * DOCUMENTS DEFECT — SendMail lists deleted_at in $dates but does not use
     * SoftDeletes, so it is the ONE deleted_at column left uncast.
     *
     * Every other model in this application is rescued on deleted_at by the
     * SoftDeletes trait, which registers the cast itself and does not depend on
     * $dates. SendMail is not, which means its $dates entry was the only thing
     * describing that column and the model has no soft-delete behaviour at all —
     * a separate question from the cast, and one for WP 0C: either the column is
     * vestigial and should go, or the trait is missing and deletes are not being
     * retained.
     */
    public function test_documents_defect_sendmail_declares_deleted_at_without_soft_deletes(): void
    {
        $this->assertNotContains(
            SoftDeletes::class,
            class_uses_recursive(\App\Models\SendMail::class),
            'SendMail now uses SoftDeletes, so deleted_at is cast and retained. Move its '
            .'row out of BROKEN_COLUMNS and confirm what happened to rows deleted while '
            .'the trait was absent.'
        );

        $this->assertContains(
            SoftDeletes::class,
            class_uses_recursive(\App\Models\Userprofile::class),
            'Userprofile has LOST SoftDeletes. Member deletes would become permanent — '
            .'treat as urgent.'
        );
    }
}
