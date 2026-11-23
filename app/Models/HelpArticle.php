<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'help_category_id',
    'title',
    'slug',
    'summary',
    'body',
    'cover_image',
    'sort_order',
    'is_published',
    'published_at',
    'views',
])]
class HelpArticle extends Model
{
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
            'sort_order' => 'integer',
            'views' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(HelpCategory::class, 'help_category_id');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    protected function readingMinutes(): Attribute
    {
        return Attribute::get(function (): int {
            $text = trim(strip_tags((string) $this->body));
            $characters = mb_strlen(preg_replace('/\s+/u', '', $text) ?? '');

            return max(1, (int) ceil($characters / 420));
        });
    }

    protected function excerpt(): Attribute
    {
        return Attribute::get(fn (): string => $this->summary ?: Str::limit(strip_tags((string) $this->body), 120));
    }
}
