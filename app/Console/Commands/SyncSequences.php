<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncSequences extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sequences:sync {--tables=* : Tables to sync, defaults to products,product_variants}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Postgres serial sequences to the current max(id) for specified tables.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tables = $this->option('tables') ?: ['products', 'product_variants'];

        foreach ($tables as $table) {
            try {
                $seq = DB::selectOne("SELECT pg_get_serial_sequence(?, 'id') AS seq", [$table]);
                if (empty($seq) || empty($seq->seq)) {
                    $this->warn("No serial sequence found for table: {$table}");
                    continue;
                }

                // Use direct SQL to set sequence and mark it as called so nextval will return a value > max(id)
                $seqName = $seq->seq;
                $sql = "SELECT setval('" . $seqName . "', COALESCE((SELECT MAX(id) FROM \"" . $table . "\"), 0), true)";
                DB::statement($sql);

                $this->info("Synced sequence for {$table}: {$seqName}");
            } catch (\Exception $e) {
                $this->error("Failed to sync sequence for {$table}: " . $e->getMessage());
            }
        }

        return 0;
    }
}
