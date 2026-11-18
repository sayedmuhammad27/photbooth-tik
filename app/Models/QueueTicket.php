<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'queue_number',
        'name',
        'status',
        'template_id',
        'session_token',
        'called_at',
        'session_started_at',
        'completed_at',
    ];

    protected $casts = [
        'queue_number'       => 'integer',
        'called_at'          => 'datetime',
        'session_started_at' => 'datetime',
        'completed_at'       => 'datetime',
    ];

    /* ---------- Relasi ---------- */

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function photoSession(): BelongsTo
    {
        return $this->belongsTo(PhotoSession::class, 'session_token', 'session_token');
    }

    /* ---------- Scopes ---------- */

    /** Antrian hari ini saja (rentang timestamp agar ramah index). */
    public function scopeToday($query)
    {
        $start = today()->startOfDay();

        return $query->where('created_at', '>=', $start)
            ->where('created_at', '<', $start->copy()->addDay());
    }

    /** Hanya yang masih menunggu. */
    public function scopeWaiting($query)
    {
        return $query->where('status', 'waiting');
    }

    /** Hanya yang sedang dipanggil. */
    public function scopeCalled($query)
    {
        return $query->where('status', 'called');
    }

    /** Hanya yang sedang sesi foto. */
    public function scopeInSession($query)
    {
        return $query->where('status', 'in_session');
    }

    /* ---------- Atribut ---------- */

    /** Label status dalam bahasa Indonesia. */
    protected function statusLabel(): Attribute
    {
        return Attribute::get(fn () => match ($this->status) {
            'waiting'    => 'Menunggu',
            'called'     => 'Dipanggil',
            'in_session' => 'Sesi Foto',
            'completed'  => 'Selesai',
            'skipped'    => 'Dilewati',
            default      => $this->status,
        });
    }

    /** Warna badge untuk status. */
    protected function statusBadge(): Attribute
    {
        return Attribute::get(fn () => match ($this->status) {
            'waiting'    => 'off',
            'called'     => 'lamp',
            'in_session' => 'pink',
            'completed'  => 'green',
            'skipped'    => 'off',
            default      => 'off',
        });
    }

    /* ---------- Helper Methods ---------- */

    public function markCalled(): void
    {
        $this->update([
            'status'    => 'called',
            'called_at' => now(),
        ]);
    }

    public function startSession(): void
    {
        $this->update([
            'status'             => 'in_session',
            'session_started_at' => now(),
        ]);
    }

    public function markCompleted(?string $sessionToken = null): void
    {
        $data = [
            'status'       => 'completed',
            'completed_at' => now(),
        ];
        if ($sessionToken) {
            $data['session_token'] = $sessionToken;
        }
        $this->update($data);
    }

    public function markSkipped(): void
    {
        $this->update([
            'status' => 'skipped',
        ]);
    }

    public function resetToWaiting(): void
    {
        $this->update([
            'status'             => 'waiting',
            'called_at'          => null,
            'session_started_at' => null,
            'session_token'      => null,
        ]);
    }
}
