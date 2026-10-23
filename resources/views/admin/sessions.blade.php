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
@endsection
