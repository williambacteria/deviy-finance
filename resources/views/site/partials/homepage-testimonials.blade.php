<section class="sky-section sky-testimonials-section" id="testimonials" aria-labelledby="sky-testimonials-title">
    <div class="dv-container"><div class="sky-section-head"><div><div class="sky-eyebrow">VOICES FROM THE CLOUD</div><h2 id="sky-testimonials-title">每一份信任，都值得认真回应。</h2><p>听见用户的声音，把服务做得更好。</p></div>@if (count($testimonials) > 1)<button class="sky-testimonial-toggle" type="button" data-testimonial-toggle aria-pressed="false" aria-label="暂停评价滚动">Ⅱ 暂停滚动</button>@endif</div>
        @if (count($testimonials))
            <div class="sky-testimonial-window {{ count($testimonials) === 1 ? 'is-single' : '' }}" data-testimonials-carousel role="region" aria-label="用户评价" tabindex="0"><div class="sky-testimonial-track"><div class="sky-testimonial-group">@foreach ($testimonials as $review)@include('site.partials.testimonial-card', ['review' => $review])@endforeach</div>@if (count($testimonials) > 1)<div class="sky-testimonial-group sky-testimonial-duplicate" aria-hidden="true" inert>@foreach ($testimonials as $review)@include('site.partials.testimonial-card', ['review' => $review])@endforeach</div>@endif</div></div>
        @else
            <div class="sky-testimonial-empty"><span><x-icon name="users" :size="26" /></span><div><h3>把每一份信任，化为更好的服务。</h3><p>用户评价正在整理中，后续将在这里展示。</p></div></div>
        @endif
    </div>
</section>
