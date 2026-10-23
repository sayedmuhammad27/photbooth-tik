@extends('layouts.admin')

@section('title', 'Tambah Template — Admin Photobooth TIK')
@section('inline_errors', '1')

@section('content')
<div class="adm-head">
    <div><h1>TEMPLATE BARU</h1><p>Unggah bingkai dan tentukan posisi tiap foto.</p></div>
</div>
@if($errors->any())<div class="alert alert--error" role="alert">Periksa kembali isian yang ditandai.</div>@endif
<div class="px-box px-box--pad-lg" style="max-width:860px">
    @include('admin.templates._form', ['action' => route('admin.templates.store')])
</div>
@endsection
