@php $t = $template ?? null; @endphp
<form class="form-grid" action="{{ $action }}" method="POST" enctype="multipart/form-data" novalidate>
    @csrf
    @if($t) @method('PUT') @endif

    <div class="field">
        <label class="field__label" for="name">Nama template</label>
        <input class="input" id="name" type="text" name="name" value="{{ old('name', $t->name ?? '') }}" maxlength="80" required @error('name') aria-invalid="true" @enderror>
        @error('name')<p class="field__error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label class="field__label" for="description">Deskripsi (opsional)</label>
        <textarea class="textarea" id="description" name="description" rows="2" maxlength="300" style="font-family:var(--font-body);font-size:var(--fs-md)">{{ old('description', $t->description ?? '') }}</textarea>
        @error('description')<p class="field__error">{{ $message }}</p>@enderror
    </div>

    <div class="form-grid form-grid--2" style="max-width:none">
        <div class="field">
            <label class="field__label" for="category">Kategori</label>
            <select class="select" id="category" name="category">
                @foreach($categories as $key => $label)
                    <option value="{{ $key }}" @selected(old('category', $t->category ?? 'umum') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            @error('category')<p class="field__error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label class="field__label" for="slot_count">Jumlah foto (1–4)</label>
            <input class="input" id="slot_count" type="number" name="slot_count" min="1" max="4" value="{{ old('slot_count', $t->slot_count ?? 3) }}" required>
            @error('slot_count')<p class="field__error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label class="field__label" for="tags">Tag (pisahkan dengan koma)</label>
            <input class="input" id="tags" type="text" name="tags" value="{{ old('tags', isset($t) && is_array($t->tags) ? implode(', ', $t->tags) : '') }}" maxlength="200" placeholder="strip, malam, pixel">
            @error('tags')<p class="field__error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label class="field__label" for="bg_color">Warna latar</label>
            <input class="input" id="bg_color" type="text" name="bg_color" value="{{ old('bg_color', $t->bg_color ?? '#131947') }}" maxlength="7" pattern="#[0-9A-Fa-f]{6}" placeholder="#131947">
            @error('bg_color')<p class="field__error">{{ $message }}</p>@enderror
        </div>
        <div class="field">
            <label class="field__label" for="sort_order">Urutan tampil</label>
            <input class="input" id="sort_order" type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order', $t->sort_order ?? 0) }}">
            @error('sort_order')<p class="field__error">{{ $message }}</p>@enderror
        </div>
        <div class="field" style="align-content:end">
            <input type="hidden" name="is_active" value="0">
            <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $t->is_active ?? true))> Tampilkan di booth</label>
        </div>
    </div>

    <div class="field">
        <label class="field__label" for="frame_asset">File bingkai (PNG transparan disarankan, 1200×1800 px)</label>
        <input class="file" id="frame_asset" type="file" name="frame_asset" accept="image/png,image/jpeg">
        <p class="field__hint">Bingkai ditempel di atas foto. Beri jendela transparan di posisi foto.@if($t && $t->frame_asset) Kosongkan untuk tetap memakai bingkai saat ini.@endif</p>
        @error('frame_asset')<p class="field__error">{{ $message }}</p>@enderror
    </div>

    <div class="field">
        <label class="field__label" for="layout_config">Tata letak foto (JSON)</label>
        <div class="layout-prev">
            <canvas id="layout-canvas" aria-label="Pratinjau tata letak" @if($t && $t->frame_url) data-frame="{{ $t->frame_url }}" @endif></canvas>
            <div>
                <textarea class="textarea" id="layout_config" name="layout_config" rows="8" spellcheck="false" placeholder='[{"x":50,"y":260,"width":1100,"height":620,"photo":0}]'>{{ old('layout_config', $layoutJson ?? '') }}</textarea>
                <p class="layout-prev__msg" id="layout-msg" aria-live="polite"></p>
                <p class="field__hint">Kanvas 1200×1800 px (cetak 4R). <code>photo</code> menunjuk foto ke-berapa (mulai 0), jadi satu foto bisa dipakai di dua slot untuk strip ganda. Foto otomatis dipotong sesuai rasio slot tanpa gepeng.</p>
                <button type="button" class="btn btn--ghost btn--sm" id="btn-autolayout">Isi tata letak otomatis</button>
            </div>
        </div>
        @error('layout_config')<p class="field__error">{{ $message }}</p>@enderror
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn--primary">{{ $t ? 'SIMPAN PERUBAHAN' : 'SIMPAN TEMPLATE' }}</button>
        <a class="btn btn--ghost" href="{{ route('admin.templates.index') }}">Batal</a>
    </div>
</form>
