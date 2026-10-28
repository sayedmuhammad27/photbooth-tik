@if ($paginator->hasPages())
    <nav class="pager" role="navigation" aria-label="Navigasi Halaman">
        {{-- Previous Page Link --}}
        @if ($paginator->onFirstPage())
            <span class="is-disabled" aria-disabled="true" aria-label="Sebelumnya">&lsaquo;</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Sebelumnya">&lsaquo;</a>
        @endif

        {{-- Pagination Elements --}}
        @foreach ($elements as $element)
            {{-- "Three Dots" Separator --}}
            @if (is_string($element))
                <span class="is-disabled" aria-disabled="true">{{ $element }}</span>
            @endif

            {{-- Array Of Links --}}
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="is-current" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next Page Link --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Berikutnya">&rsaquo;</a>
        @else
            <span class="is-disabled" aria-disabled="true" aria-label="Berikutnya">&rsaquo;</span>
        @endif
    </nav>
@endif
