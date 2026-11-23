@if ($friendLinks !== [])
    <section class="sky-section sky-friend-links" id="friend-links" aria-labelledby="friend-links-title">
        <div class="dv-container">
            <div class="sky-section-head"><div><h2 id="friend-links-title">友情链接</h2></div></div>
            <ul class="sky-friend-links-grid">
                @foreach ($friendLinks as $link)
                    <li>
                        <a class="sky-friend-link" href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer" title="在新标签页打开">
                            <span class="sky-friend-link-logo">
                                @if ($link['logo'])
                                    <img src="{{ asset('storage/'.$link['logo']) }}" alt="" width="48" height="48" loading="lazy" decoding="async">
                                @else
                                    <x-icon name="globe" :size="23" />
                                @endif
                            </span>
                            <span class="sky-friend-link-content">
                                <strong>{{ $link['name'] }}</strong>
                                @if ($link['description'] !== '')
                                    <span>{{ $link['description'] }}</span>
                                @endif
                            </span>
                            <span class="sky-friend-link-arrow" aria-hidden="true">↗</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
