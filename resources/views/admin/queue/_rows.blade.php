@forelse($tickets as $t)
    <tr class="queue-row queue-row--{{ $t->status }}" data-ticket-id="{{ $t->id }}" data-queue-number="{{ $t->queue_number }}" style="{{ $t->status === 'in_session' ? 'background:rgba(127, 5, 8, 0.2);' : ($t->status === 'called' ? 'background:rgba(236, 230, 223, 0.1);' : '') }}">
        <td>
            <span style="font-family:var(--font-display);font-size:var(--fs-d-md);font-weight:700;color:{{ $t->status === 'in_session' ? 'var(--burgundy-400)' : 'var(--concrete)' }}">
                #{{ $t->queue_number }}
            </span>
        </td>
        <td>
            <div style="font-weight:bold;color:var(--concrete)">{{ $t->name }}</div>
            @if($t->template)
                <div style="font-size:var(--fs-xs);color:var(--concrete-dim)">Template: {{ $t->template->name }}</div>
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
                <span style="font-size:var(--fs-xs);color:var(--burgundy-400);font-weight:700">Sedang di bilik foto</span>
            @elseif($t->status === 'called')
                <span style="font-size:var(--fs-xs);color:var(--concrete);font-weight:700">Dipanggil {{ $t->called_at?->format('H:i') }}</span>
            @else
                <span style="font-size:var(--fs-xs);color:var(--text-dim)">-</span>
            @endif
        </td>
        <td class="num">
            <div class="row-actions">
                @if($t->status !== 'in_session')
                    <form action="{{ route('admin.queue.start', $t) }}" method="POST" style="margin:0">
                        @csrf
                        <button type="submit" class="btn btn--danger btn--sm" title="Mulai sesi foto untuk nomor ini">
                            @include('partials.icon', ['name' => 'play']) Mulai Foto
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
                            @include('partials.icon', ['name' => 'check']) Selesai
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
