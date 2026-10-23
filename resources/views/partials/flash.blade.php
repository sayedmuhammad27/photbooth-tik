@if(session('success'))<div data-toast="success" hidden>{{ session('success') }}</div>@endif
@if(session('error'))<div data-toast="error" hidden>{{ session('error') }}</div>@endif
@if($errors->any())
    @hasSection('inline_errors')
    @else
        <div data-toast="error" hidden>{{ $errors->first() }}</div>
    @endif
@endif
