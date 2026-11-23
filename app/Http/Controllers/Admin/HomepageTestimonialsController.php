<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HomepageTestimonialsController extends Controller
{
    public function index(): Response
    {
        $decoded = json_decode((string) Setting::value('homepage_testimonials', '[]'), true);
        $items = is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];

        return response()
            ->view('admin.homepage-testimonials.index', compact('items'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['nullable', 'array', 'max:30'],
            'items.*.name' => ['required', 'string', 'max:40'],
            'items.*.role' => ['nullable', 'string', 'max:80'],
            'items.*.content' => ['required', 'string', 'max:500'],
            'items.*.rating' => ['required', 'integer', 'between:1,5'],
            'items.*.enabled' => ['required', 'boolean'],
            'items.*.avatar' => ['nullable', 'string', 'max:120'],
            'items.*.remove_avatar' => ['nullable', 'boolean'],
            'items.*.avatar_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=32,min_height=32,max_width=2048,max_height=2048'],
        ], [
            'items.*.avatar_file.max' => '每张头像不能超过 2 MB。',
            'items.*.avatar_file.mimes' => '头像仅支持 JPG、PNG 或 WebP 格式。',
            'items.*.avatar_file.dimensions' => '头像尺寸须介于 32 × 32 和 2048 × 2048 像素之间。',
            'items.max' => '最多保存 30 条用户评价。',
            'items.*.name.required' => '请填写评价用户的昵称。',
            'items.*.name.max' => '昵称不能超过 40 个字符。',
            'items.*.content.required' => '请填写评价正文，或移除该条空白评价。',
            'items.*.content.max' => '每条评价正文不能超过 500 个字符。',
            'items.*.rating.between' => '评分须为 1 至 5 星。',
        ]);

        $previous = json_decode((string) Setting::value('homepage_testimonials', '[]'), true);
        $allowedAvatars = [];
        foreach (is_array($previous) ? $previous : [] as $old) {
            $path = is_array($old) ? ($old['avatar'] ?? '') : '';
            if (is_string($path) && preg_match('#^testimonials/avatars/[a-f0-9-]{36}\.png$#D', $path)) {
                $allowedAvatars[] = $path;
            }
        }
        $items = [];
        $uploads = [];
        try {
            foreach (($data['items'] ?? []) as $key => $item) {
                $avatar = in_array($item['avatar'] ?? '', $allowedAvatars, true) ? $item['avatar'] : '';
                if ((bool) ($item['remove_avatar'] ?? false)) {
                    $avatar = '';
                }
                $file = $request->file('items.'.$key.'.avatar_file');
                if ($file) {
                    $avatar = $this->storeAvatar($file);
                    $uploads[] = $avatar;
                }
                $items[] = [
                    'name' => trim($item['name']),
                    'role' => trim((string) ($item['role'] ?? '')),
                    'content' => trim($item['content']),
                    'rating' => (int) $item['rating'],
                    'enabled' => (bool) $item['enabled'],
                    'avatar' => $avatar,
                ];
            }

            Setting::query()->updateOrCreate(
                ['key' => 'homepage_testimonials'],
                ['group_name' => 'homepage', 'value' => json_encode($items, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 'type' => 'json'],
            );

        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($uploads);
            throw $exception;
        }

        return back()->with('status', '首页用户评价已保存，启用的评价将按当前顺序展示。');
    }

    /** Decode and re-encode a bounded raster image, discarding metadata and active content. */
    private function storeAvatar(\Illuminate\Http\UploadedFile $file): string
    {
        $source = @imagecreatefromstring($file->get());
        if ($source === false) {
            throw \Illuminate\Validation\ValidationException::withMessages(['items' => '头像图片无法读取，请重新选择。']);
        }
        $canvas = imagecreatetruecolor(256, 256);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        $edge = min(imagesx($source), imagesy($source));
        imagecopyresampled($canvas, $source, 0, 0, (int) ((imagesx($source) - $edge) / 2), (int) ((imagesy($source) - $edge) / 2), 256, 256, $edge, $edge);
        ob_start();
        imagepng($canvas);
        $data = (string) ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);
        $path = 'testimonials/avatars/'.\Illuminate\Support\Str::uuid().'.png';
        if (! \Illuminate\Support\Facades\Storage::disk('public')->put($path, $data)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['items' => '头像保存失败，请稍后重试。']);
        }

        return $path;
    }
}
