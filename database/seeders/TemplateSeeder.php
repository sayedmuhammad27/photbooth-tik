<?php

namespace Database\Seeders;

use App\Models\Template;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Memuat 5 template bawaan bertema "Night City" (bingkai PNG transparan 1200×1800).
 * Aman dijalankan berulang: template dengan nama yang sama tidak ditimpa.
 */
class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        $json = database_path('seeders/data/templates.json');
        if (! is_file($json)) {
            $this->command?->warn('templates.json tidak ditemukan, template bawaan dilewati.');

            return;
        }

        $rows = json_decode((string) file_get_contents($json), true) ?: [];
        $disk = Storage::disk('public');

        foreach ($rows as $row) {
            if (Template::where('name', $row['name'])->exists()) {
                continue;
            }

            $frame = database_path('seeders/data/frames/' . $row['frame_file']);
            $stored = null;
            if (is_file($frame)) {
                $stored = str_starts_with($row['slug'], 'snaptik-')
                    ? 'templates/' . $row['slug'] . '.png'
                    : 'templates/seed-' . $row['slug'] . '.png';
                $disk->put($stored, (string) file_get_contents($frame));
            }

            Template::create([
                'name'          => $row['name'],
                'description'   => $row['description'],
                'category'      => $row['category'],
                'tags'          => $row['tags'],
                'frame_asset'   => $stored,
                'bg_color'      => $row['bg_color'],
                'slot_count'    => $row['slot_count'],
                'layout_config' => $row['layout_config'],
                'is_active'     => true,
                'sort_order'    => $row['sort_order'],
            ]);
        }
    }
}
