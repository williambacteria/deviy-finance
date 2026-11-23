<figure class="sky-testimonial-card">
    <div class="sky-testimonial-rating" aria-label="{{ $review['rating'] }} 星评价"><span aria-hidden="true">{{ str_repeat('★', $review['rating']) }}<span>{{ str_repeat('☆', 5 - $review['rating']) }}</span></span><span class="sky-testimonial-quote" aria-hidden="true">“</span></div>
    <blockquote>{{ $review['content'] }}</blockquote>
    <figcaption><span class="sky-testimonial-avatar" aria-hidden="true">@if (! empty($review['avatar']))<img src="{{ asset('storage/'.$review['avatar']) }}" alt="" loading="lazy" decoding="async" width="39" height="39">@else{{ mb_substr($review['name'], 0, 1) }}@endif</span><span><b>{{ $review['name'] }}</b><small>{{ $review['role'] ?: '云服务用户' }}</small></span><x-icon name="cloud" :size="20" /></figcaption>
</figure>
