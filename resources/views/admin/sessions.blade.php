@extends('layouts.admin')

@section('title', 'Riwayat Sesi — Admin Photobooth TIK')

@section('content')
<div class="adm-head">
    <div>
        <h1>RIWAYAT SESI</h1>
        <p>Lihat, unduh, dan cetak hasil foto pengunjung (4R).</p>
    </div>
</div>

<div class="px-box table-wrap">
    <table class="table">
        <thead><tr><th>ID</th><th>Strip</th><th>Template</th><th>Waktu</th><th class="num">Aksi</th></tr></thead>
        <tbody>
        @forelse($sessions as $s)
            <tr>
                <td>#{{ $s->id }}</td>
                <td><img class="table__thumb" src="{{ $s->final_url }}" alt="Strip sesi {{ $s->id }}" loading="lazy"></td>
                <td>{{ $s->template->name ?? 'Template dihapus' }}</td>
                <td>{{ $s->created_at->format('d/m/Y H:i') }}</td>
                <td>
                    <div class="row-actions">
                        <a class="btn btn--ghost btn--sm" href="{{ route('photobooth.result', $s->session_token) }}" target="_blank" rel="noopener">Buka</a>
                        <a class="btn btn--ghost btn--sm" href="{{ route('photobooth.download', [$s->session_token, 'strip']) }}" download data-no-transition>Unduh</a>
                        @if($s->isSyncedToGoogleDrive())
                            <a class="btn btn--sm btn--primary" href="{{ $s->google_drive_url }}" target="_blank" rel="noopener" title="Buka di Google Drive">☁️ Drive</a>
                        @else
                            <button type="button" class="btn btn--ghost btn--sm btn-admin-sync-drive" data-token="{{ $s->session_token }}" title="Sinkronkan ke Google Drive">☁️ Sync</button>
                        @endif
                        <button type="button" class="btn btn--success btn--sm" data-print="{{ $s->final_url }}">Cetak 4R</button>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">Belum ada sesi foto yang tersimpan.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

{{ $sessions->links('vendor.pagination.pixel') }}

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-admin-sync-drive').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var token = btn.getAttribute('data-token');
            btn.disabled = true;
            btn.textContent = '⏳';

            fetch('/api/sessions/' + encodeURIComponent(token) + '/upload-drive', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok && data.google_drive_url) {
                    btn.outerHTML = '<a class="btn btn--sm btn--primary" href="' + data.google_drive_url + '" target="_blank" rel="noopener">☁️ Drive</a>';
                    if (window.PB && PB.toast) PB.toast('Tersimpan di Google Drive!', 'success', 3000);
                } else {
                    btn.disabled = false;
                    btn.textContent = '☁️ Sync';
                    alert(data.message || 'Gagal mengunggah ke Google Drive.');
                }
            })
            .catch(function(err) {
                btn.disabled = false;
                btn.textContent = '☁️ Sync';
                alert('Gagal: ' + (err.message || err));
            });
        });
    });
});
</script>
@endpush
@endsection
