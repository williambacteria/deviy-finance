<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Support\HomepageFriendLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class HomepageFriendLinksController extends Controller
{
    public function index(Request $request): View
    {
        $friendLinks = HomepageFriendLinks::ordered(Setting::value(HomepageFriendLinks::KEY));
        $editId = $request->string('edit')->toString();
        abort_if($editId !== '' && ! isset($friendLinks[$editId]), 404);
        $editedLink = $friendLinks[$editId] ?? null;

        return view('admin.merged.admin-homepage-friend-links-index', compact('friendLinks', 'editId', 'editedLink'));
    }

    public function save(Request $request, AuditLogger $audit, ?string $link = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'url' => ['required', 'url:http,https', 'max:2048'],
            'description' => ['nullable', 'string', 'max:160'],
            'position' => ['required', 'integer', 'between:0,9999'],
            'enabled' => ['required', 'boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:2048', 'dimensions:max_width=2048,max_height=2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ], [], [
            'name' => '网站名称', 'url' => '链接地址', 'description' => '网站介绍',
            'position' => '排序', 'logo' => 'Logo',
        ]);

        $file = $request->file('logo');
        $logo = $file ? $this->storeLogo($file) : null;
        $linkId = $link ?? (string) Str::uuid();

        try {
            $oldLogo = DB::transaction(function () use ($request, $audit, $link, $linkId, $data, $logo): ?string {
                Setting::query()->firstOrCreate(
                    ['key' => HomepageFriendLinks::KEY],
                    ['group_name' => 'homepage', 'type' => 'json', 'value' => '{}'],
                );
                $setting = Setting::query()->where('key', HomepageFriendLinks::KEY)->lockForUpdate()->firstOrFail();
                $links = HomepageFriendLinks::ordered($setting->value);
                abort_if($link !== null && ! isset($links[$linkId]), 404);
                if ($link === null && count($links) >= 48) {
                    throw ValidationException::withMessages(['name' => '最多可保存 48 条友情链接。']);
                }
                $previousLogo = $links[$linkId]['logo'] ?? null;
                $newLogo = $logo ?? ($request->boolean('remove_logo') ? null : $previousLogo);
                $links[$linkId] = [
                    'name' => $data['name'],
                    'url' => $data['url'],
                    'description' => $data['description'] ?? '',
                    'logo' => $newLogo,
                    'position' => (int) $data['position'],
                    'enabled' => (bool) $data['enabled'],
                ];
                $setting->update(['value' => json_encode($links, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
                $audit->record($request->user('admin'), 'homepage.friend-link.saved', '保存首页友情链接', $setting, ['link_id' => $linkId]);

                return $newLogo !== $previousLogo ? $previousLogo : null;
            });
        } catch (Throwable $exception) {
            $this->deleteLogo($logo);
            throw $exception;
        }

        $this->deleteLogo($oldLogo);

        return $this->indexRedirect()->with('status', '友情链接已保存。');
    }

    public function destroy(Request $request, AuditLogger $audit, string $link): RedirectResponse
    {
        $logo = DB::transaction(function () use ($request, $audit, $link): ?string {
            $setting = Setting::query()->where('key', HomepageFriendLinks::KEY)->lockForUpdate()->firstOrFail();
            $links = HomepageFriendLinks::ordered($setting->value);
            abort_unless(isset($links[$link]), 404);
            $logo = $links[$link]['logo'];
            unset($links[$link]);
            $setting->update(['value' => json_encode($links, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
            $audit->record($request->user('admin'), 'homepage.friend-link.deleted', '删除首页友情链接', $setting, ['link_id' => $link]);

            return $logo;
        });
        $this->deleteLogo($logo);

        return $this->indexRedirect()->with('status', '友情链接已删除。');
    }

    private function indexRedirect(): RedirectResponse
    {
        return redirect()->route('admin.workspace', ['area' => 'website', 'tab' => 'friend-links']);
    }

    private function storeLogo(UploadedFile $file): string
    {
        $source = @imagecreatefromstring($file->get());
        if ($source === false) {
            throw ValidationException::withMessages(['logo' => '无法读取 Logo，请重新选择图片。']);
        }

        $scale = min(1, 320 / max(imagesx($source), imagesy($source)));
        $width = max(1, (int) round(imagesx($source) * $scale));
        $height = max(1, (int) round(imagesy($source) * $scale));
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, imagesx($source), imagesy($source));
        ob_start();
        $encoded = imagepng($canvas);
        $png = (string) ob_get_clean();

        $path = 'friend-links/'.Str::uuid().'.png';
        if (! $encoded || ! Storage::disk('public')->put($path, $png)) {
            throw ValidationException::withMessages(['logo' => 'Logo 保存失败，请稍后重试。']);
        }

        return $path;
    }

    private function deleteLogo(?string $path): void
    {
        if ($path !== null && preg_match('#^friend-links/[a-f0-9-]{36}\.png$#D', $path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
