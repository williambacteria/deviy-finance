<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        $announcements = Announcement::query()->latest()->paginate(15);

        return view('admin.announcements.index', compact('announcements'));
    }

    public function create(): View
    {
        return view('admin.announcements.form', ['announcement' => new Announcement]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->slug($data['title'], $data['slug'] ?? null);

        $announcement = Announcement::create($data);
        $audit->record($request->user('admin'), 'announcement.created', '发布内容：'.$announcement->title, $announcement);

        return redirect()->route('admin.announcements.index')->with('status', '内容已保存。');
    }

    public function edit(Announcement $announcement): View
    {
        return view('admin.announcements.form', compact('announcement'));
    }

    public function update(Request $request, Announcement $announcement, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->slug($data['title'], $data['slug'] ?? null, $announcement->id);

        $announcement->update($data);
        $audit->record($request->user('admin'), 'announcement.updated', '更新内容：'.$announcement->title, $announcement);

        return redirect()->route('admin.announcements.index')->with('status', '内容已更新。');
    }

    public function destroy(Request $request, Announcement $announcement, AuditLogger $audit): RedirectResponse
    {
        $audit->record($request->user('admin'), 'announcement.deleted', '删除内容：'.$announcement->title, $announcement);
        $announcement->delete();

        return back()->with('status', '内容已删除。');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:160'],
            'type' => ['required', 'in:announcement,news,help'],
            'summary' => ['nullable', 'string', 'max:300'],
            'body' => ['required', 'string'],
            'is_published' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        $data['is_published'] = (bool) ($data['is_published'] ?? false);
        $data['published_at'] = $data['is_published'] ? ($data['published_at'] ?? now()) : null;

        return $data;
    }

    private function slug(string $title, ?string $slug, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug ?: $title) ?: 'post-'.Str::lower(Str::random(6));
        $candidate = $base;
        $counter = 2;

        while (Announcement::query()->where('slug', $candidate)->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))->exists()) {
            $candidate = $base.'-'.$counter++;
        }

        return $candidate;
    }
}
