<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DiagnoseMainPlaces extends Command
{
    protected $signature   = 'diagnose:mainplaces';
    protected $description = 'Analyse Stats SA code prefixes in the main places CSV';

    public function handle(): void
    {
        $csvPath = storage_path('app/mainplaces-2011.csv');

        if (!file_exists($csvPath)) {
            $this->error("CSV not found at: {$csvPath}");
            return;
        }

        $file      = fopen($csvPath, 'r');
        $headers   = fgetcsv($file);
        $headerMap = array_flip(array_map('strtolower', $headers));
        $codeIdx   = $headerMap['code'] ?? null;

        if ($codeIdx === null) {
            $this->error("No 'code' column found.");
            fclose($file);
            return;
        }

        $prefixes = [];

        while (($row = fgetcsv($file)) !== false) {
            $code = trim($row[$codeIdx]);
            if (empty($code) || !is_numeric($code)) continue;

            $prefix = substr($code, 0, 3);

            if (!isset($prefixes[$prefix])) {
                $prefixes[$prefix] = [
                    'prefix'        => $prefix,
                    'example_code'  => $code,
                    'count'         => 0,
                ];
            }
            $prefixes[$prefix]['count']++;
        }

        fclose($file);
        ksort($prefixes);

        $this->info("Found " . count($prefixes) . " unique prefixes:\n");
        $this->table(
            ['Prefix', 'Example Full Code', 'Row Count'],
            array_values($prefixes)
        );

        // Export to a file for easy reference
        $outputPath = storage_path('app/mainplaces_prefixes.csv');
        $out = fopen($outputPath, 'w');
        fputcsv($out, ['prefix', 'example_code', 'count', 'mdb_code']);
        foreach ($prefixes as $p) {
            fputcsv($out, [$p['prefix'], $p['example_code'], $p['count'], '']);
        }
        fclose($out);

        $this->info("\nExported to: {$outputPath}");
        $this->info("Fill in the 'mdb_code' column manually, then use it to build your lookup table.");
    }
}
