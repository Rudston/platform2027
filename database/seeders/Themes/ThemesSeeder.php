<?php

namespace Database\Seeders\Themes;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ThemesSeeder extends Seeder
{
    /**
     * Seed the `themes` table from storage/app/initial_themes.csv.
     *
     * The CSV is a two-column `parent,child` file:
     *   - A row with an empty child (e.g. "Education,") marks a top-level theme.
     *   - A row with a child (e.g. "Education,Early Childhood Development")
     *     places that child under its parent.
     *
     * NOTE: the file is NOT quoted, and one parent label contains a comma
     * ("Culture, Heritage and Sport"). So the LAST field is always the child
     * and everything before it is re-joined to form the parent label.
     */
    public function run(): void
    {
        $csvPath = storage_path('app/initial_themes.csv');

        if (! is_readable($csvPath)) {
            $this->command->error("Themes CSV not found or unreadable at: {$csvPath}");

            return;
        }

        $parents = [];         // [label => true] distinct top-level themes, first-seen order
        $childRelations = [];  // [ [childLabel, parentLabel], ... ]

        $handle = fopen($csvPath, 'r');
        fgetcsv($handle); // skip header row: parent,child

        while (($row = fgetcsv($handle)) !== false) {
            $fields = array_map(fn ($v) => trim((string) $v), $row);
            $child = array_pop($fields);
            // Re-join any leading fields so an unquoted comma in the parent
            // label (e.g. "Culture, Heritage and Sport") survives intact.
            $parent = implode(', ', array_filter($fields, fn ($v) => $v !== ''));

            if ($parent === '') {
                continue; // blank / separator line
            }

            $parents[$parent] = true;

            if ($child !== '') {
                $childRelations[] = [$child, $parent];
            }
        }

        fclose($handle);

        DB::transaction(function () use ($parents, $childRelations) {
            $now = now();

            // Pass 1 — insert top-level themes (parent_id = null).
            $parentRows = [];
            foreach (array_keys($parents) as $label) {
                $parentRows[] = [
                    'label' => $label,
                    'slug' => Str::slug($label),
                    'parent_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('themes')->insert($parentRows);

            // Resolve label -> id so children can point at their parent.
            $idByLabel = DB::table('themes')->pluck('id', 'label')->all();

            // Pass 2 — insert child themes linked to their parent.
            $childRows = [];
            foreach ($childRelations as [$childLabel, $parentLabel]) {
                $childRows[] = [
                    'label' => $childLabel,
                    'slug' => Str::slug($childLabel),
                    'parent_id' => $idByLabel[$parentLabel] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('themes')->insert($childRows);

            $this->command->info(sprintf(
                'Seeded %d top-level themes and %d child themes (%d total).',
                count($parentRows),
                count($childRows),
                count($parentRows) + count($childRows),
            ));
        });
    }
}
