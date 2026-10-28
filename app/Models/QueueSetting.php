<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QueueSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'active_date',
        'last_number',
        'current_serving',
        'is_open',
    ];

    protected $casts = [
        'active_date' => 'date:Y-m-d',
        'is_open'     => 'boolean',
    ];

    /**
     * Ambil atau buat pengaturan antrian untuk hari ini.
     */
    public static function forToday(): self
    {
        $today = today()->format('Y-m-d');

        $setting = self::whereDate('active_date', $today)->first();

        if (! $setting) {
            try {
                $setting = self::create([
                    'active_date'     => $today,
                    'last_number'     => 0,
                    'current_serving' => 0,
                    'is_open'         => false,
                ]);
            } catch (\Throwable $e) {
                $setting = self::whereDate('active_date', $today)->first();
            }
        }

        return $setting;
    }

    /**
     * Ambil nomor antrian berikutnya (increment atomik).
     */
    public function nextNumber(): int
    {
        $this->increment('last_number');
        $this->refresh();

        return $this->last_number;
    }
}
