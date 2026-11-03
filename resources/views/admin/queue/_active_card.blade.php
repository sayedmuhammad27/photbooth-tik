<div>
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <h2 class="section-title" style="margin:0;font-size:var(--fs-d-sm)">STATUS ANTREAN AKTIF</h2>
        @if($setting->is_open)
            <span class="badge badge--green">Pendaftaran Dibuka</span>
        @else
            <span class="badge badge--off">Pendaftaran Ditutup</span>
        @endif
    </div>

    @if($currentTicket)
        <div style="background:var(--night-950);padding:var(--sp-4);margin-bottom:16px;border:1px solid var(--outline)">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
                <span class="badge badge--{{ $currentTicket->status_badge }}" style="font-size:var(--fs-d-xs)">
                    {{ strtoupper($currentTicket->status_label) }}
                </span>
                <span style="font-size:var(--fs-xs);color:var(--text-dim)">
                    Diambil: {{ $currentTicket->created_at->format('H:i') }}
                </span>
            </div>
            
            <div style="font-family:var(--font-display);font-size:clamp(1.6rem, 3.5vw, 2.4rem);color:var(--lamp-300);line-height:1.2;margin:8px 0">
                #{{ $currentTicket->queue_number }}
            </div>
            
            <div style="font-size:var(--fs-md);color:var(--text);font-weight:bold">
                {{ $currentTicket->name }}
            </div>

            @if($currentTicket->template)
                <div style="font-size:var(--fs-sm);color:var(--peri-200);margin-top:6px">
                    Template: {{ $currentTicket->template->name }}
                </div>
            @endif

            @if($currentTicket->status === 'in_session')
                <p style="font-size:var(--fs-xs);color:var(--pink-400);margin-top:10px;animation:blink 1.2s steps(2) infinite">
                    <span aria-hidden="true">★</span> Layar depan booth saat ini siap berfoto untuk nomor ini!
                </p>
            @endif
        </div>

        <div style="display:flex;gap:8px;flex-wrap:wrap">
            @if($currentTicket->status === 'called')
                <form action="{{ route('admin.queue.start', $currentTicket) }}" method="POST" style="margin:0;flex:1">
                    @csrf
                    <button type="submit" class="btn btn--primary btn--sm" style="width:100%">
                        <span aria-hidden="true">▶</span> Mulai Sesi Foto Sekarang
                    </button>
                </form>
            @endif

            @if($currentTicket->status === 'in_session')
                <form action="{{ route('admin.queue.complete', $currentTicket) }}" method="POST" style="margin:0;flex:1">
                    @csrf
                    <button type="submit" class="btn btn--success btn--sm" style="width:100%">
                        <span aria-hidden="true">✓</span> Selesaikan Sesi
                    </button>
                </form>
            @endif

            <form action="{{ route('admin.queue.skip', $currentTicket) }}" method="POST" style="margin:0">
                @csrf
                <button type="submit" class="btn btn--ghost btn--sm" title="Lewati antrean ini">
                    Lewati
                </button>
            </form>
        </div>
    @else
        <div style="background:var(--night-950);padding:var(--sp-5);text-align:center;color:var(--text-muted);border:1px dashed var(--outline);margin-bottom:16px">
            <p style="font-size:var(--fs-md);margin-bottom:8px">Tidak ada antrean yang sedang aktif.</p>
            <span style="font-size:var(--fs-sm);color:var(--text-dim)">Panggil antrean berikutnya atau tentukan nomor di samping.</span>
        </div>

        <form action="{{ route('admin.queue.call-next') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn--primary btn--block" {{ $stats['waiting'] == 0 ? 'disabled' : '' }}>
                <span aria-hidden="true">📢</span> Panggil Antrean Berikutnya (Next)
            </button>
        </form>
    @endif
</div>

@if($currentTicket && $currentTicket->status !== 'in_session')
    <div style="margin-top:16px;padding-top:12px;border-top:1px dashed var(--outline)">
        <form action="{{ route('admin.queue.call-next') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn--ghost btn--sm btn--block" {{ $stats['waiting'] == 0 ? 'disabled' : '' }}>
                Panggil Berikutnya Setelah Ini
            </button>
        </form>
    </div>
@endif
