@if ($paginator->total() > 0)
    <nav class="pager" role="navigation" aria-label="Navigasi Halaman">
        <div class="pager__info">
            Halaman <strong>{{ $paginator->currentPage() }}</strong> dari <strong>{{ $paginator->lastPage() }}</strong>
            <span style="opacity:.6;margin:0 4px">•</span>
            Total <strong>{{ $paginator->total() }}</strong> data
        </div>
        <div class="pager__list">
            {{-- Previous Page Link --}}
            @if ($paginator->onFirstPage())
                <span class="pager__btn is-disabled" aria-disabled="true" aria-label="Sebelumnya">‹</span>
            @else
                <a class="pager__btn" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Sebelumnya">‹</a>
            @endif

            {{-- Pagination Elements --}}
            @if ($paginator->lastPage() > 1)
                @foreach ($elements as $element)
                    {{-- "Three Dots" Separator --}}
                    @if (is_string($element))
                        <span class="pager__btn pager__btn--dots is-disabled" aria-disabled="true">{{ $element }}</span>
                    @endif

                    {{-- Array Of Links --}}
                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="pager__btn is-current" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="pager__btn" href="{{ $url }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            @else
                <span class="pager__btn is-current" aria-current="page">1</span>
            @endif

            {{-- Next Page Link --}}
            @if ($paginator->hasMorePages())
                <a class="pager__btn" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Berikutnya">›</a>
            @else
                <span class="pager__btn is-disabled" aria-disabled="true" aria-label="Berikutnya">›</span>
            @endif
        </div>
    </nav>
@endif
