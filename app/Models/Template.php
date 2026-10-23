<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    use HasFactory;

    public const CANVAS_WIDTH = 1200;
    public const CANVAS_HEIGHT = 1800;

    protected $fillable = [
        'name',
        'description',
        'frame_asset',
        'slot_count',
        'layout_config',
        'is_active',
        'category',
        'tags',
        'bg_color',
        'sort_order',
    ];

    protected $casts = [
        'layout_config' => 'array',
        'tags'          => 'array',
        'is_active'     => 'boolean',
        'slot_count'    => 'integer',
        'sort_order'    => 'integer',
    ];

    public function photoSessions(): HasMany
    {
        return $this->hasMany(PhotoSession::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** URL relatif ke file bingkai (tidak bergantung pada APP_URL / symlink storage). */
    protected function frameUrl(): Attribute
    {
        return Attribute::get(function () {
            return $this->frame_asset
                ? route('media', ['path' => $this->frame_asset], false)
                : null;
        });
    }

    /**
     * Daftar slot yang sudah dinormalkan: [x, y, width, height, photo].
     * Menangani data lama yang tersimpan sebagai string JSON (double-encoded)
     * dan memakai tata letak tumpuk otomatis bila konfigurasi kosong/rusak.
     */
    public function normalizedSlots(): array
    {
        $raw = $this->layout_config;
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
        }

        $count = max(1, (int) $this->slot_count);
        $slots = [];

        if (is_array($raw)) {
            foreach (array_values($raw) as $i => $s) {
                if (! is_array($s)) {
                    continue;
                }
                $slot = [
                    'x'      => (int) round((float) ($s['x'] ?? 0)),
                    'y'      => (int) round((float) ($s['y'] ?? 0)),
                    'width'  => (int) round((float) ($s['width'] ?? 0)),
                    'height' => (int) round((float) ($s['height'] ?? 0)),
                    'photo'  => (int) ($s['photo'] ?? $i),
                ];
                if ($slot['width'] > 0 && $slot['height'] > 0 && $slot['photo'] >= 0 && $slot['photo'] < $count) {
                    $slots[] = $slot;
                }
            }
        }

        return $slots !== [] ? $slots : $this->defaultSlots($count);
    }

    /** Tata letak tumpuk vertikal: margin atas/bawah 260 px, jarak 40 px. */
    public function defaultSlots(int $count): array
    {
        $count = max(1, min(6, $count));
        $top = 260;
        $bottom = 260;
        $gap = 40;
        $height = intdiv(self::CANVAS_HEIGHT - $top - $bottom - $gap * ($count - 1), $count);

        $slots = [];
        for ($i = 0; $i < $count; $i++) {
            $slots[] = [
                'x'      => 50,
                'y'      => $top + $i * ($height + $gap),
                'width'  => 1100,
                'height' => $height,
                'photo'  => $i,
            ];
        }

        return $slots;
    }

    /** Data ringkas untuk galeri dan halaman kamera (dibaca JavaScript). */
    public function toBoothArray(): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'category'    => $this->category ?: 'umum',
            'tags'        => is_array($this->tags) ? array_values($this->tags) : [],
            'slot_count'  => (int) $this->slot_count,
            'layout'      => $this->normalizedSlots(),
            'frame_url'   => $this->frame_url,
            'bg_color'    => $this->bg_color ?: '#131947',
            'session_url' => route('photobooth.session', $this),
        ];
    }
}
