@extends('layouts.admin')

@section('title', 'Template : Admin Photobooth TIK')

@section('content')
<div class="adm-head">
    <div>
        <h1>TEMPLATE</h1>
        <p>Atur bingkai, tata letak foto, dan template mana yang tampil di booth.</p>
    </div>
    <a class="btn btn--primary btn--sm" href="{{ route('admin.templates.create') }}">Tambah template</a>
</div>

<div class="px-box table-wrap">
    <table class="table">
        <thead>
            <tr><th>Preview</th><th>Nama</th><th>Kategori</th><th class="ctr">Foto</th><th class="ctr">Dipakai</th><th class="ctr">Status</th><th class="num">Aksi</th></tr>
        </thead>
        <tbody>
        @forelse($templates as $t)
            <tr>
                <td>
                    @if($t->frame_url)
                        <img class="table__thumb" src="{{ $t->frame_url }}" alt="Bingkai {{ $t->name }}" loading="lazy" style="background:{{ $t->bg_color }}">
                    @else
                        <span class="badge badge--off">Tanpa bingkai</span>
                    @endif
                </td>
                <td>{{ $t->name }}</td>
                <td>{{ ucfirst($t->category) }}</td>
                <td class="ctr">{{ $t->slot_count }}</td>
                <td class="ctr">{{ $t->photo_sessions_count }}</td>
                <td class="ctr">
                    <span class="badge {{ $t->is_active ? 'badge--green' : 'badge--off' }}">{{ $t->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                </td>
                <td>
                    <div class="row-actions">
                        <form action="{{ route('admin.templates.toggle', $t) }}" method="POST">
                            @csrf @method('PATCH')
                            <button class="btn btn--ghost btn--sm" type="submit">{{ $t->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                        </form>
                        <a class="btn btn--ghost btn--sm" href="{{ route('admin.templates.edit', $t) }}">Ubah</a>
                        <form action="{{ route('admin.templates.destroy', $t) }}" method="POST" data-confirm="Hapus template “{{ $t->name }}”? Tindakan ini tidak bisa dibatalkan.">
                            @csrf @method('DELETE')
                            <button class="btn btn--danger btn--sm" type="submit">Hapus</button>
                        </form>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">Belum ada template. Tambahkan yang pertama, atau jalankan <code>php artisan db:seed</code> untuk memuat template bawaan.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
