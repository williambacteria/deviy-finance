<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SliderCaptchaController extends Controller
{
    private const WIDTH = 320;

    private const HEIGHT = 120;

    private const PIECE = 46;

    private const SHAPES = ['circle', 'diamond', 'triangle', 'hexagon', 'star'];

    public function image(Request $request): JsonResponse
    {
        $width = self::WIDTH;
        $height = self::HEIGHT;
        $pieceSize = self::PIECE;
        $image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; $y++) {
            $ratio = $y / $height;
            $color = imagecolorallocate(
                $image,
                (int) (16 + (1 - $ratio) * 20 + random_int(0, 3)),
                (int) (42 + (1 - $ratio) * 30 + random_int(0, 3)),
                (int) (82 + (1 - $ratio) * 56 + random_int(0, 3)),
            );
            imagefilledrectangle($image, 0, $y, $width, $y, $color);
        }

        for ($i = 0; $i < 14; $i++) {
            $color = imagecolorallocatealpha($image, random_int(60, 220), random_int(120, 240), random_int(180, 255), random_int(70, 112));
            imagefilledellipse($image, random_int(0, $width), random_int(0, $height), random_int(20, 90), random_int(14, 70), $color);
        }

        for ($i = 0; $i < 22; $i++) {
            $color = imagecolorallocatealpha($image, 220, 240, 255, random_int(20, 80));
            imageline($image, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $color);
        }

        $shape = self::SHAPES[array_rand(self::SHAPES)];
        $targetX = random_int(80, $width - $pieceSize - 10);
        $targetY = random_int(8, $height - $pieceSize - 8);

        $piece = imagecreatetruecolor($pieceSize, $pieceSize);
        imagealphablending($piece, false);
        imagesavealpha($piece, true);
        imagefilledrectangle($piece, 0, 0, $pieceSize, $pieceSize, imagecolorallocatealpha($piece, 0, 0, 0, 127));
        imagealphablending($piece, true);
        imagecopy($piece, $image, 0, 0, $targetX, $targetY, $pieceSize, $pieceSize);

        $mask = imagecreatetruecolor($pieceSize, $pieceSize);
        imagealphablending($mask, false);
        imagesavealpha($mask, true);
        imagefilledrectangle($mask, 0, 0, $pieceSize, $pieceSize, imagecolorallocatealpha($mask, 0, 0, 0, 127));
        imagealphablending($mask, true);
        $this->drawShape($mask, $shape, 0, 0, $pieceSize, imagecolorallocate($mask, 255, 255, 255), true);

        $clipped = imagecreatetruecolor($pieceSize, $pieceSize);
        imagealphablending($clipped, false);
        imagesavealpha($clipped, true);
        imagefilledrectangle($clipped, 0, 0, $pieceSize, $pieceSize, imagecolorallocatealpha($clipped, 0, 0, 0, 127));
        for ($y = 0; $y < $pieceSize; $y++) {
            for ($x = 0; $x < $pieceSize; $x++) {
                if ((imagecolorat($mask, $x, $y) & 0x7F) === 0) {
                    imagesetpixel($clipped, $x, $y, imagecolorat($piece, $x, $y));
                }
            }
        }
        imagealphablending($clipped, true);
        $this->drawShape($clipped, $shape, 0, 0, $pieceSize, imagecolorallocate($clipped, 255, 255, 255), false);

        $hole = imagecolorallocatealpha($image, 8, 20, 38, 78);
        $this->drawShape($image, $shape, $targetX, $targetY, $pieceSize, $hole, true);
        $this->drawShape($image, $shape, $targetX, $targetY, $pieceSize, imagecolorallocate($image, 255, 255, 255), false);

        $token = Str::random(32);
        $request->session()->put('slider_captcha', [
            'token' => $token,
            'x' => $targetX,
            'y' => $targetY,
            'shape' => $shape,
            'created_at' => now()->timestamp,
            'attempts' => 0,
        ]);

        return response()->json([
            'bg' => 'data:image/png;base64,'.base64_encode($this->png($image)),
            'piece' => 'data:image/png;base64,'.base64_encode($this->png($clipped)),
            'token' => $token,
            'width' => $width,
            'height' => $height,
            'piece_size' => $pieceSize,
            'y' => $targetY,
            'shape' => $shape,
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function verifyImage(Request $request): JsonResponse
    {
        $captcha = $request->session()->get('slider_captcha');
        if (! is_array($captcha) || (time() - (int) ($captcha['created_at'] ?? 0)) > 300) {
            $request->session()->forget('slider_captcha');

            return response()->json(['ok' => false, 'message' => '验证已过期，请刷新后重试。']);
        }

        $attempts = (int) ($captcha['attempts'] ?? 0) + 1;
        $captcha['attempts'] = $attempts;
        $request->session()->put('slider_captcha', $captcha);
        if ($attempts > 5) {
            $request->session()->forget('slider_captcha');

            return response()->json(['ok' => false, 'message' => '尝试次数过多，请刷新验证码。']);
        }

        $token = (string) $request->input('captcha_token', '');
        $x = (int) $request->input('captcha_x', -1);
        if ($token === '' || ! hash_equals((string) ($captcha['token'] ?? ''), $token) || $x < 0) {
            return response()->json(['ok' => false, 'message' => '验证失败，请重新拖动。']);
        }

        if (abs($x - (int) ($captcha['x'] ?? -999)) > 8) {
            return response()->json(['ok' => false, 'message' => '位置不准确，请重新拖动。']);
        }

        $request->session()->put('slider_captcha_verified', [
            'token' => $token,
            'created_at' => now()->timestamp,
        ]);
        $request->session()->forget('slider_captcha');

        return response()->json(['ok' => true, 'message' => '验证通过。']);
    }

    public static function isVerified(Request $request): bool
    {
        $verified = $request->session()->get('slider_captcha_verified');
        if (! is_array($verified) || (time() - (int) ($verified['created_at'] ?? 0)) > 300) {
            $request->session()->forget('slider_captcha_verified');

            return false;
        }

        $token = (string) $request->input('captcha_token', '');
        $request->session()->forget('slider_captcha_verified');

        return $token !== '' && hash_equals((string) ($verified['token'] ?? ''), $token);
    }

    private function drawShape(\GdImage $image, string $shape, int $x, int $y, int $size, int $color, bool $filled): void
    {
        $half = $size / 2;
        switch ($shape) {
            case 'circle':
                $filled ? imagefilledellipse($image, (int) ($x + $half), (int) ($y + $half), $size, $size, $color) : imageellipse($image, (int) ($x + $half), (int) ($y + $half), $size, $size, $color);
                break;
            case 'diamond':
                $points = [$x + $half, $y, $x + $size, $y + $half, $x + $half, $y + $size, $x, $y + $half];
                $this->polygon($image, $points, $color, $filled);
                break;
            case 'triangle':
                $points = [$x + $half, $y, $x + $size, $y + $size, $x, $y + $size];
                $this->polygon($image, $points, $color, $filled);
                break;
            case 'hexagon':
                $points = [];
                for ($i = 0; $i < 6; $i++) {
                    $angle = M_PI / 3 * $i - M_PI / 6;
                    $points[] = (int) ($x + $half + cos($angle) * $half);
                    $points[] = (int) ($y + $half + sin($angle) * $half);
                }
                $this->polygon($image, $points, $color, $filled);
                break;
            default:
                $points = [];
                for ($i = 0; $i < 10; $i++) {
                    $angle = -M_PI / 2 + M_PI / 5 * $i;
                    $radius = $i % 2 === 0 ? $half : $half * 0.45;
                    $points[] = (int) ($x + $half + cos($angle) * $radius);
                    $points[] = (int) ($y + $half + sin($angle) * $radius);
                }
                $this->polygon($image, $points, $color, $filled);
        }
    }

    /** @param array<int, int|float> $points */
    private function polygon(\GdImage $image, array $points, int $color, bool $filled): void
    {
        if ($filled) {
            imagefilledpolygon($image, $points, $color);
        } else {
            imagepolygon($image, $points, $color);
        }
    }

    private function png(\GdImage $image): string
    {
        ob_start();
        imagepng($image);
        $data = (string) ob_get_clean();
        imagedestroy($image);

        return $data;
    }
}
