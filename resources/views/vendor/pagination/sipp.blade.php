@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="sipp-pagination">
        {{-- Info hasil --}}
        <div class="sipp-page-info">
            @if ($paginator->firstItem())
                Menampilkan <strong>{{ $paginator->firstItem() }}</strong>–<strong>{{ $paginator->lastItem() }}</strong>
                dari <strong>{{ $paginator->total() }}</strong> data
            @else
                {{ $paginator->count() }} data
            @endif
        </div>

        <div class="sipp-page-links">
            {{-- Previous --}}
            @if ($paginator->onFirstPage())
                <span class="sipp-page sipp-disabled" aria-disabled="true">‹</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="sipp-page" aria-label="Sebelumnya">‹</a>
            @endif

            {{-- Page elements --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="sipp-page sipp-dots">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="sipp-page sipp-active" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="sipp-page" aria-label="Halaman {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="sipp-page" aria-label="Berikutnya">›</a>
            @else
                <span class="sipp-page sipp-disabled" aria-disabled="true">›</span>
            @endif
        </div>
    </nav>
@endif
