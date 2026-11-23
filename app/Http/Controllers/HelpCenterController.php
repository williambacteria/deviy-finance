<?php

namespace App\Http\Controllers;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HelpCenterController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->trim()->toString();

        $categories = HelpCategory::query()
            ->visible()
            ->with(['articles' => fn ($query) => $query->published()->orderBy('sort_order')->latest('published_at')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $articles = HelpArticle::query()
            ->published()
            ->with('category')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', '%'.$search.'%')
                        ->orWhere('summary', 'like', '%'.$search.'%')
                        ->orWhere('body', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('sort_order')
            ->latest('published_at')
            ->paginate(12)
            ->withQueryString();

        return view('site.help', compact('categories', 'articles', 'search'));
    }

    public function show(HelpArticle $article): View
    {
        abort_unless($article->is_published && $article->published_at?->isPast(), 404);

        $article->load('category');
        $article->increment('views');

        $siblings = HelpArticle::query()
            ->published()
            ->where('help_category_id', $article->help_category_id)
            ->orderBy('sort_order')
            ->orderBy('published_at')
            ->get();

        $currentIndex = $siblings->search(fn (HelpArticle $item): bool => $item->is($article));
        $previous = $currentIndex !== false && $currentIndex > 0 ? $siblings[$currentIndex - 1] : null;
        $next = $currentIndex !== false && $currentIndex < $siblings->count() - 1 ? $siblings[$currentIndex + 1] : null;

        $categories = HelpCategory::query()
            ->visible()
            ->with(['articles' => fn ($query) => $query->published()->orderBy('sort_order')->latest('published_at')])
            ->orderBy('sort_order')
            ->get();

        return view('site.help-article', compact('article', 'categories', 'previous', 'next'));
    }
}
