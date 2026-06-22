<?php

namespace Database\Seeders\Demography;

namespace Database\Seeders\Demography;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MainPlacesSeeder extends Seeder
{
    private array $prefixToMdbMap = [];
    private array $unmappedPrefixes = [];

    private function loadMdbLookup(): void
    {
        $lookupPath = storage_path('app/mainplaces_prefixes.csv');

        if (!file_exists($lookupPath)) {
            throw new \RuntimeException("Lookup file not found: {$lookupPath}");
        }

        $file    = fopen($lookupPath, 'r');
        $headers = fgetcsv($file); // skip header row

        while (($row = fgetcsv($file)) !== false) {
            [$prefix, $exampleCode, $count, $mdbCode] = $row;
            if (!empty(trim($mdbCode))) {
                $this->prefixToMdbMap[trim($prefix)] = trim($mdbCode);
            }
        }

        fclose($file);
        $this->command->info("Loaded " . count($this->prefixToMdbMap) . " MDB code mappings.");
    }

    private function convertToMdbCode(string $statsSaCode): ?string
    {
        $prefix = substr($statsSaCode, 0, 3);

        if (isset($this->prefixToMdbMap[$prefix])) {
            return $this->prefixToMdbMap[$prefix];
        }

        // Track unmapped prefixes without stopping the import
        if (!in_array($prefix, $this->unmappedPrefixes)) {
            $this->unmappedPrefixes[] = $prefix;
            $this->command->warn("Unmapped prefix: {$prefix} (from code: {$statsSaCode})");
        }

        return null; // skip this row
    }

    public function run(): void
    {
        $this->loadMdbLookup();

        $csvPath = storage_path('app/mainplaces-2011.csv');

        if (!file_exists($csvPath)) {
            $this->command->error("CSV file not found at: {$csvPath}");
            return;
        }

        $file      = fopen($csvPath, 'r');
        $headers   = fgetcsv($file);
        $headerMap = array_flip(array_map('strtolower', $headers));
        $codeIdx   = $headerMap['code']       ?? null;
        $nameIdx   = $headerMap['name']       ?? null;
        $popIdx    = $headerMap['population'] ?? null;

        if ($codeIdx === null || $nameIdx === null || $popIdx === null) {
            $this->command->error("CSV must contain 'Code', 'Name', and 'Population' columns.");
            fclose($file);
            return;
        }

        $this->command->info('Seeding main_places table...');

        $batch     = [];
        $batchSize = 1000;
        $rowCount  = 0;
        $skipped   = 0;

        DB::beginTransaction();

        try {
            while (($row = fgetcsv($file)) !== false) {
                $statsSaCode = trim($row[$codeIdx]);
                $name        = trim($row[$nameIdx]);
                $population  = (int) trim($row[$popIdx]);

                if (empty($statsSaCode) || !is_numeric($statsSaCode)) {
                    continue;
                }

                $mdbCode = $this->convertToMdbCode($statsSaCode);

                // Skip rows with no mapping rather than inserting bad data
                if ($mdbCode === null) {
                    $skipped++;
                    continue;
                }

                $batch[] = [
                    'code'          => $mdbCode,
                    'stats_sa_code' => $statsSaCode,
                    'name'          => $name,
                    'population'    => $population,
                ];

                $rowCount++;

                if (count($batch) >= $batchSize) {
                    DB::table('main_places')->insert($batch);
                    $batch = [];
                }
            }

            if (count($batch) > 0) {
                DB::table('main_places')->insert($batch);
            }

            DB::commit();

            $this->command->info("Successfully seeded {$rowCount} main places.");

            if ($skipped > 0) {
                $this->command->warn("{$skipped} rows skipped due to unmapped prefixes.");
                $this->command->warn("Unmapped prefixes: " . implode(', ', $this->unmappedPrefixes));
            }

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("Seeding failed: " . $e->getMessage());
        } finally {
            fclose($file);
        }
    }
}
