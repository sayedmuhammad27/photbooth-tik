@extends('layouts.admin')

@section('title', 'Ringkasan : Admin SnapTik')

@section('content')
<div class="adm-head">
    <div>
        <h1>RINGKASAN</h1>
        <p>Aktivitas bilik foto SnapTik Expo Wisuda Jurusan TIK 2026.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
        <a class="btn btn--primary btn--sm" href="{{ route('admin.queue.index') }}">@include('partials.icon', ['name' => 'ticket']) Kelola Antrean</a>
        <a class="btn btn--ghost btn--sm" href="{{ route('admin.templates.create') }}">Tambah template</a>
    </div>
</div>

<div class="stats">
    <div class="px-box stat reveal"><div class="stat__label">Total sesi foto</div><div class="stat__value">{{ number_format($stats['total_sesi'], 0, ',', '.') }}</div></div>
    <div class="px-box stat reveal"><div class="stat__label">Sesi hari ini</div><div class="stat__value">{{ number_format($stats['sesi_hari_ini'], 0, ',', '.') }}</div></div>
    <div class="px-box stat reveal" style="border-left:4px solid var(--concrete)">
        <div class="stat__label">Antrean Menunggu</div>
        <div class="stat__value" style="color:var(--concrete)">{{ $stats['antrean_menunggu'] }}</div>
    </div>
    <div class="px-box stat reveal" style="border-left:4px solid var(--burgundy-700)">
        <div class="stat__label">Antrean Aktif</div>
        <div class="stat__value" style="color:var(--concrete);font-size:clamp(1.1rem, 2.5vw, 1.5rem)">
            {{ $stats['antrean_aktif'] ? '#' . $stats['antrean_aktif']->queue_number : 'Kosong' }}
        </div>
    </div>
</div>

<h2 class="section-title">Sesi terakhir</h2>
<div class="px-box table-wrap">
    <table class="table">
        <thead><tr><th>Strip</th><th>Template</th><th>Waktu</th><th class="num">Aksi</th></tr></thead>
        <tbody>
        @forelse($recent as $s)
            <tr>
                <td><img class="table__thumb" src="{{ $s->final_url }}" alt="Strip sesi {{ $s->id }}" loading="lazy"></td>
                <td>{{ $s->template->name ?? 'Template dihapus' }}</td>
                <td>{{ $s->created_at->format('d/m/Y H:i') }}</td>
                <td class="num"><a class="btn btn--ghost btn--sm" href="{{ route('photobooth.result', $s->session_token) }}" target="_blank" rel="noopener">Buka</a></td>
            </tr>
        @empty
            <tr><td colspan="4" class="empty">Belum ada sesi. Hasil foto pengunjung akan muncul di sini.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 18px;border-top:1px solid var(--border-subtle);background:var(--black-surface);border-radius:0 0 var(--radius-lg) var(--radius-lg)">
        <span style="font-size:var(--fs-xs);color:var(--white-muted)">Menampilkan {{ count($recent) }} sesi foto terbaru</span>
        <a class="btn btn--ghost btn--sm" href="{{ route('admin.sessions') }}">Lihat Semua Sesi & Paginasi &rarr;</a>
    </div>
</div>

<h2 class="section-title" style="margin-top:48px">Template terpopuler</h2>
<div class="px-box table-wrap">
    <table class="table">
        <thead><tr><th>Template</th><th>Kategori</th><th class="num">Dipakai</th></tr></thead>
        <tbody>
        @forelse($popular as $t)
            <tr><td>{{ $t->name }}</td><td>{{ ucfirst($t->category) }}</td><td class="num">{{ $t->photo_sessions_count }} sesi</td></tr>
        @empty
            <tr><td colspan="3" class="empty">Belum ada template.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 18px;border-top:1px solid var(--border-subtle);background:var(--black-surface);border-radius:0 0 var(--radius-lg) var(--radius-lg)">
        <span style="font-size:var(--fs-xs);color:var(--white-muted)">Peringkat berdasarkan frekuensi pemakaian</span>
        <a class="btn btn--ghost btn--sm" href="{{ route('admin.templates.index') }}">Kelola Semua Template &rarr;</a>
    </div>
</div>
@endsection
