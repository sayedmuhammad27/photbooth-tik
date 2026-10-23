@extends('layouts.admin')

@section('title', 'Ringkasan — Admin Photobooth TIK')

@section('content')
<div class="adm-head">
    <div>
        <h1>RINGKASAN</h1>
        <p>Aktivitas photobooth Expo Wisuda Jurusan TIK 2026.</p>
    </div>
    <a class="btn btn--primary btn--sm" href="{{ route('admin.templates.create') }}">Tambah template</a>
</div>

<div class="stats">
    <div class="px-box stat reveal"><div class="stat__label">Total sesi foto</div><div class="stat__value">{{ number_format($stats['total_sesi'], 0, ',', '.') }}</div></div>
    <div class="px-box stat reveal"><div class="stat__label">Sesi hari ini</div><div class="stat__value">{{ number_format($stats['sesi_hari_ini'], 0, ',', '.') }}</div></div>
    <div class="px-box stat reveal"><div class="stat__label">Foto dihasilkan</div><div class="stat__value">{{ number_format($stats['total_foto'], 0, ',', '.') }}</div></div>
    <div class="px-box stat reveal"><div class="stat__label">Template aktif</div><div class="stat__value">{{ $stats['template_aktif'] }}</div></div>
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
</div>
@endsection
