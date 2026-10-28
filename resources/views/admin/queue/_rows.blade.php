@forelse($tickets as $t)
    <tr class="queue-row queue-row--{{ $t->status }}" data-ticket-id="{{ $t->id }}" data-queue-number="{{ $t->queue_number }}" style="{{ $t->status === 'in_session' ? 'background:rgba(234, 132, 169, 0.15);' : ($t->status === 'called' ? 'background:rgba(224, 211, 38, 0.12);' : '') }}">
        <td>
            <span style="font-family:var(--font-display);font-size:var(--fs-d-md);color:{{ $t->status === 'in_session' ? 'var(--pink-400)' : ($t->status === 'called' ? 'var(--lamp-300)' : 'var(--text)') }}">
                #{{ $t->queue_number }}
            </span>
        </td>
        <td>
            <div style="font-weight:bold">{{ $t->name }}</div>
            @if($t->template)
                <div style="font-size:var(--fs-xs);color:var(--peri-200)">Template: {{ $t->template->name }}</div>
            @endif
        </td>
        <td>
            <span class="badge badge--{{ $t->status_badge }}">
                {{ $t->status_label }}
            </span>
        </td>
        <td>
            <span style="font-size:var(--fs-sm)">{{ $t->created_at->format('H:i:s') }}</span>
            <div style="font-size:var(--fs-xs);color:var(--text-dim)">{{ $t->created_at->diffForHumans() }}</div>
        </td>
        <td>
            @if($t->session_token)
                <a href="{{ route('photobooth.result', $t->session_token) }}" target="_blank" class="badge badge--green" style="text-decoration:none">
                    Lihat Foto ↗
                </a>
            @elseif($t->status === 'in_session')
                <span style="font-size:var(--fs-xs);color:var(--pink-400)">Sedang di bilik foto</span>
            @elseif($t->status === 'called')
                <span style="font-size:var(--fs-xs);color:var(--lamp-300)">Dipanggil {{ $t->called_at?->format('H:i') }}</span>
            @else
                <span style="font-size:var(--fs-xs);color:var(--text-dim)">-</span>
            @endif
        </td>
        <td class="num">
            <div class="row-actions">
                @if($t->status !== 'in_session')
                    <form action="{{ route('admin.queue.start', $t) }}" method="POST" style="margin:0">
                        @csrf
                        <button type="submit" class="btn btn--primary btn--sm" style="background:var(--pink-400);color:#fff" title="Mulai sesi foto untuk nomor ini">
                            ▶ Mulai Foto
                        </button>
                    </form>
                @endif

                @if($t->status === 'waiting')
                    <form action="{{ route('admin.queue.set-specific') }}" method="POST" style="margin:0">
                        @csrf
                        <input type="hidden" name="target_number" value="{{ $t->queue_number }}">
                        <input type="hidden" name="action" value="call">
                        <button type="submit" class="btn btn--ghost btn--sm" title="Panggil pengunjung ini">
                            Panggil
                        </button>
                    </form>
                @endif

                @if($t->status === 'in_session')
                    <form action="{{ route('admin.queue.complete', $t) }}" method="POST" style="margin:0">
                        @csrf
                        <button type="submit" class="btn btn--success btn--sm" title="Tandai selesai">
                            ✓ Selesai
                        </button>
                    </form>
                @endif

                @if(in_array($t->status, ['waiting', 'called']))
                    <form action="{{ route('admin.queue.skip', $t) }}" method="POST" style="margin:0">
                        @csrf
                        <button type="submit" class="btn btn--ghost btn--sm" title="Lewati nomor ini">
                            Lewati
                        </button>
                    </form>
                @endif

                @if(in_array($t->status, ['completed', 'skipped', 'in_session']))
                    <form action="{{ route('admin.queue.reset', $t) }}" method="POST" style="margin:0">
                        @csrf
                        <button type="submit" class="btn btn--ghost btn--sm" title="Kembalikan status ke Menunggu">
                            Reset
                        </button>
                    </form>
                @endif
            </div>
        </td>
    </tr>
@empty
    <tr>
        <td colspan="6" class="empty">
            Belum ada antrean untuk hari ini.
        </td>
    </tr>
@endforelse
