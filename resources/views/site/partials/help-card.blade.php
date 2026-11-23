<a class="hc-card" href="{{ route('help.article', $article) }}">
    <div class="hc-card-top">
        <span class="hc-card-icon"><x-icon :name="$article->category?->icon ?: 'file'" :size="17" /></span>
        <span class="hc-card-tag">{{ $article->category?->name }}</span>
    </div>
    <h4>{{ $article->title }}</h4>
    <p>{{ $article->excerpt }}</p>
    <div class="hc-card-meta">
        <span><x-icon name="clock" :size="12" /> {{ $article->reading_minutes }} 分钟</span>
        <span><x-icon name="eye" :size="12" /> {{ number_format($article->views) }}</span>
        <span class="hc-card-more">阅读 <x-icon name="arrow-right" :size="12" /></span>
    </div>
</a>
