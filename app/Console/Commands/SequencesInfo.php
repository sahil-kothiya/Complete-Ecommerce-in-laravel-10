<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SequencesInfo extends Command
{
    protected $signature = 'sequences:info {--tables=* : Tables to inspect, defaults to products,product_variants}';
    protected $description = 'Show MAX(id) and sequence last_value for given tables (Postgres).';

    public function handle()
    {
        $tables = $this->option('tables') ?: ['products', 'product_variants'];

        foreach ($tables as $table) {
            try {
                $max = DB::selectOne("SELECT COALESCE(MAX(id),0) AS max_id FROM \"" . $table . "\"");
                $seqRow = DB::selectOne("SELECT pg_get_serial_sequence(?, 'id') AS seq", [$table]);
                $seqName = $seqRow->seq ?? null;

                if (!$seqName) {
                    $this->line("Table: {$table} - seq: none, max_id: {$max->max_id}");
                    continue;
                }

                // seqName may be schema-qualified (eg. public.products_id_seq). Do not wrap in quotes.
                $last = DB::selectOne("SELECT last_value, is_called FROM " . $seqName);

                $this->line("Table: {$table}");
                $this->line("  seq: {$seqName}");
                $this->line("  max_id: {$max->max_id}");
                $this->line("  seq_last_value: " . ($last->last_value ?? 'null') . ", is_called: " . ($last->is_called ?? 'null'));
            } catch (\Exception $e) {
                $this->error("Failed to inspect {$table}: " . $e->getMessage());
            }
        }

        return 0;
    }
}
