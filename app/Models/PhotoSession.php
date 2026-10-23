<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PhotoSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'session_token',
        'final_image_path',
        'raw_photos',
    ];

    protected $casts = [
        'raw_photos' => 'array',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /** URL relatif ke strip hasil akhir. */
    protected function finalUrl(): Attribute
    {
        return Attribute::get(fn () => route('media', ['path' => $this->final_image_path], false));
    }

    /** URL relatif ke tiap foto satuan. */
    protected function rawUrls(): Attribute
    {
        return Attribute::get(function () {
            $paths = is_array($this->raw_photos) ? $this->raw_photos : [];

            return array_values(array_map(
                fn ($p) => route('media', ['path' => $p], false),
                $paths
            ));
        });
    }
}
