@if (session('status'))
    <div class="flash success">
        <x-icon name="check" :size="16" />
        <span>{{ str_replace(['魔方上游', '魔方', '上游'], '', (string) session('status')) }}</span>
        <button class="close" type="button" data-dismiss aria-label="关闭">×</button>
    </div>
@endif

@if ($errors->any())
    <div class="flash error">
        <x-icon name="alert" :size="16" />
        <span>{{ str_replace(['魔方上游', '魔方', '上游'], '', (string) $errors->first()) }}</span>
        <button class="close" type="button" data-dismiss aria-label="关闭">×</button>
    </div>
@endif
