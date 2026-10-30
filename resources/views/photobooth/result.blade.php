@extends('layouts.app')

@section('title', 'Hasil Foto — Photobooth TIK')
@section('body_class', 'has-stars')

@section('content')
<div class="result">
    <div class="result__head reveal">
        <h1>SESI SELESAI</h1>
        <p>Fotomu sudah jadi. Pindai QR di samping atau unduh langsung.</p>
    </div>

    <div class="result__grid">
        <div class="px-box px-box--raised result__strip is-printing">
            <img src="{{ $session->final_url }}" alt="Strip foto hasil sesi {{ $session->id }}" width="1200" height="1800">
        </div>

        <div class="result__side">
            <div class="px-box qr reveal">
                <div class="qr__code" role="img" aria-label="QR code untuk membuka hasil foto">{!! $qrCode !!}</div>
                <div>
                    <h2>Bawa pulang lewat ponsel</h2>
                    <p>Arahkan kamera ponsel ke kode ini untuk membuka dan mengunduh fotomu.</p>
                    @if(!empty($shareUrl))
                        <p style="margin-top:8px;font-size:0.75rem;color:var(--lamp-400);word-break:break-all">
                            <a href="{{ $shareUrl }}" target="_blank" rel="noopener" style="color:var(--lamp-400);text-decoration:underline">{{ $shareUrl }}</a>
                        </p>
                    @endif
                    @auth
                        @if($qrIsLocal)
                            <p style="margin-top:8px;color:var(--pink-400)">Catatan: QR masih mengarah ke alamat lokal. Jalankan Cloudflare Tunnel agar bisa diakses dari HP.</p>
                        @endif
                    @endauth
                </div>
            </div>

            <div class="result__actions">
                <a class="btn btn--primary btn--block" href="{{ route('photobooth.download', [$session->session_token, 'strip']) }}" download data-no-transition>UNDUH STRIP</a>
                @if($session->isSyncedToGoogleDrive())
                    <a class="btn btn--success btn--block" id="btn-gdrive-link" href="{{ $session->google_drive_url }}" target="_blank" rel="noopener">☁️ BUKA DI GOOGLE DRIVE</a>
                @else
                    <button type="button" class="btn btn--ghost btn--block" id="btn-sync-gdrive" data-token="{{ $session->session_token }}">☁️ SIMPAN KE GOOGLE DRIVE</button>
                    <a class="btn btn--success btn--block" id="btn-gdrive-link" href="#" target="_blank" rel="noopener" style="display:none">☁️ BUKA DI GOOGLE DRIVE</a>
                @endif
                <a class="btn btn--ghost btn--block" href="{{ route('home') }}">SELESAI</a>
            </div>
        </div>
    </div>

    @if(count($session->raw_urls) > 0)
        <section class="raw reveal" aria-labelledby="raw-title">
            <h2 id="raw-title">FOTO SATUAN</h2>
            <div class="raw__grid">
                @foreach($session->raw_urls as $i => $url)
                    <div class="px-box raw__item">
                        <img src="{{ $url }}" alt="Foto ke-{{ $i + 1 }}" loading="lazy">
                        <a class="btn btn--sm btn--block" href="{{ route('photobooth.download', [$session->session_token, $i + 1]) }}" download data-no-transition>Unduh foto {{ $i + 1 }}</a>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection

@push('scripts')
<script>
(function() {
    var btnSync = document.getElementById('btn-sync-gdrive');
    var linkDrive = document.getElementById('btn-gdrive-link');
    if (!btnSync) return;

    btnSync.addEventListener('click', function() {
        var token = btnSync.getAttribute('data-token');
        btnSync.disabled = true;
        btnSync.textContent = '⏳ Menyimpan ke Drive…';

        fetch('/session/' + encodeURIComponent(token) + '/upload-drive', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
            }
        })
        .then(function(res) {
            return res.json().then(function(data) {
                if (!res.ok) throw new Error(data.message || ('HTTP ' + res.status));
                return data;
            });
        })
        .then(function(data) {
            if (data.ok && data.google_drive_url) {
                if (window.PB && PB.toast) {
                    PB.toast('Foto berhasil tersimpan di Google Drive!', 'success', 3500);
                }
                btnSync.style.display = 'none';
                if (linkDrive) {
                    linkDrive.href = data.google_drive_url;
                    linkDrive.style.display = 'flex';
                }
            } else {
                throw new Error(data.message || 'Gagal menyimpan.');
            }
        })
        .catch(function(err) {
            btnSync.disabled = false;
            btnSync.textContent = '☁️ Simpan ke Google Drive';
            if (window.PB && PB.toast) {
                PB.toast(err.message || 'Gagal menyimpan ke Google Drive. Periksa koneksi atau kredensial Google Drive.', 'error', 6000);
            } else {
                alert(err.message || 'Gagal menyimpan ke Google Drive.');
            }
        });
    });
})();
</script>
@endpush
