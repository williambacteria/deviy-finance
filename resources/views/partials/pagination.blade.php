@if ($paginator->hasPages())
    <div class="pagination">
        <span class="small muted">第 {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} 条，共 {{ $paginator->total() }} 条</span>
        <div class="flex gap-2">
            @if ($paginator->onFirstPage())
                <span class="btn btn-ghost btn-sm" style="opacity:.45">上一页</span>
            @else
                <a class="btn btn-ghost btn-sm" href="{{ $paginator->previousPageUrl() }}">上一页</a>
            @endif
            @if ($paginator->hasMorePages())
                <a class="btn btn-ghost btn-sm" href="{{ $paginator->nextPageUrl() }}">下一页</a>
            @else
                <span class="btn btn-ghost btn-sm" style="opacity:.45">下一页</span>
            @endif
        </div>
    </div>
@endif
