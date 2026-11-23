<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HelpArticle;
use App\Models\HelpCategory;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HelpArticleController extends Controller
{
    public function index(): View
    {
        $articles = HelpArticle::query()
            ->with('category')
            ->orderBy('sort_order')
            ->latest('updated_at')
            ->paginate(20);

        return view('admin.merged.admin-help-articles-index', compact('articles'));
    }

    public function create(): View
    {
        return view('admin.help-articles.form', [
            'article' => new HelpArticle(['is_published' => true]),
            'categories' => $this->categories(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->slug($data['title'], $data['slug'] ?? null);
        $data = $this->storeCover($request, $data);

        $article = HelpArticle::create($data);
        $audit->record($request->user('admin'), 'help_article.created', '发布帮助文档：'.$article->title, $article);

        return redirect()->route('admin.workspace', ['area' => 'website', 'tab' => 'docs'])
            ->with('status', '帮助文档已保存。');
    }

    public function edit(HelpArticle $helpArticle): View
    {
        return view('admin.help-articles.form', [
            'article' => $helpArticle,
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, HelpArticle $helpArticle, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->slug($data['title'], $data['slug'] ?? null, $helpArticle->id);
        $data = $this->storeCover($request, $data);

        $helpArticle->update($data);
        $audit->record($request->user('admin'), 'help_article.updated', '更新帮助文档：'.$helpArticle->title, $helpArticle);

        return redirect()->route('admin.workspace', ['area' => 'website', 'tab' => 'docs'])
            ->with('status', '帮助文档已更新。');
    }

    public function destroy(Request $request, HelpArticle $helpArticle, AuditLogger $audit): RedirectResponse
    {
        $audit->record($request->user('admin'), 'help_article.deleted', '删除帮助文档：'.$helpArticle->title, $helpArticle);
        $helpArticle->delete();

        return back()->with('status', '帮助文档已删除。');
    }

    /** @return Collection<int, HelpCategory> */
    private function categories(): Collection
    {
        return HelpCategory::query()->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'help_category_id' => ['required', 'integer', 'exists:help_categories,id'],
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:160'],
            'summary' => ['nullable', 'string', 'max:400'],
            'body' => ['required', 'string'],
            'cover_image' => ['nullable', 'string', 'max:255'],
            'cover_image_file' => ['nullable', 'image', 'max:4096'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_published' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        unset($data['cover_image_file']);

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_published'] = (bool) ($data['is_published'] ?? false);
        $data['published_at'] = $data['is_published'] ? ($data['published_at'] ?? now()) : null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function storeCover(Request $request, array $data): array
    {
        if (! $request->hasFile('cover_image_file')) {
            return $data;
        }

        $file = $request->file('cover_image_file');
        $directory = public_path('assets/images/help/uploads');
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $filename = Str::slug($data['title']).'-'.time().'.'.$file->extension();
        $file->move($directory, $filename);
        $data['cover_image'] = 'assets/images/help/uploads/'.$filename;

        return $data;
    }

    private function slug(string $title, ?string $slug, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug ?: $title) ?: 'article-'.Str::lower(Str::random(6));
        $candidate = $base;
        $counter = 2;

        while (HelpArticle::query()->where('slug', $candidate)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $candidate = $base.'-'.$counter++;
        }

        return $candidate;
    }
}
