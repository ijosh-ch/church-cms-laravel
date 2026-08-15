<?php

namespace Ifgf\ChurchOperations\Console\Commands;

use Illuminate\Console\Command;

/**
 * A no-op command whose only purpose is to prove the package registers commands.
 *
 * WP 0A item 14. It has no product behaviour and must not acquire any. Replace it
 * with a real command when one exists, and update the smoke test in the same commit.
 */
class ChurchOperationsPing extends Command
{
    protected $signature = 'ifgf:ping';

    protected $description = 'Prove the IFGF church-operations package is loaded (no-op).';

    public function handle(): int
    {
        $this->line('church-operations '.config('church-operations.version', 'unknown'));

        return self::SUCCESS;
    }
}
