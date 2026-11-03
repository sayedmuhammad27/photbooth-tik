@extends('layouts.admin')

@section('title', 'Ubah Template : Admin Photobooth TIK')
@section('inline_errors', '1')

@section('content')
<div class="adm-head">
    <div><h1>UBAH TEMPLATE</h1><p>{{ $template->name }}</p></div>
</div>
@if($errors->any())<div class="alert alert--error" role="alert">Periksa kembali isian yang ditandai.</div>@endif
<div class="px-box px-box--pad-lg" style="max-width:860px">
    @include('admin.templates._form', ['action' => route('admin.templates.update', $template)])
</div>
@endsection
