<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\CloudNode;
use App\Models\Product;
use App\Models\ProductGroup;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use App\Support\HomepageFriendLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function home(): View
    {
        $featuredConfig = json_decode((string) Setting::value('homepage_featured_products', '[]'), true);
        $featuredConfig = is_array($featuredConfig) ? $featuredConfig : [];
        $featuredMeta = [];
        $featuredIds = [];

        foreach ($featuredConfig as $item) {
            if (! is_array($item) || empty($item['product_id'])) {
                continue;
            }
            $productId = (int) $item['product_id'];
            if (in_array($productId, $featuredIds, true)) {
                continue;
            }
            $featuredIds[] = $productId;
            $featuredMeta[$productId] = [
                'label' => (string) ($item['label'] ?? ''),
                'icon' => (string) ($item['icon'] ?? 'fire'),
            ];
        }

        if ($featuredIds !== []) {
            $featuredProducts = Product::query()->catalogVisible()
                ->with(['group', 'prices' => fn ($query) => $query->where('is_active', true)->orderBy('price')])
                ->where('status', 'active')
                ->whereIn('id', $featuredIds)
                ->get()
                ->sortBy(fn (Product $product): int => (int) array_search($product->id, $featuredIds, true))
                ->values();
        } else {
            $featuredProducts = Product::query()->catalogVisible()
                ->with(['group', 'prices' => fn ($query) => $query->where('is_active', true)->orderBy('price')])
                ->where('status', 'active')
                ->inRandomOrder()
                ->limit(6)
                ->get();
        }

        $testimonialConfig = json_decode((string) Setting::value('homepage_testimonials', '[]'), true);
        $testimonials = [];
        foreach (is_array($testimonialConfig) ? $testimonialConfig : [] as $review) {
            if (! is_array($review) || ($review['enabled'] ?? false) !== true || ! is_string($review['name'] ?? null) || ! is_string($review['content'] ?? null) || trim($review['name']) === '' || trim($review['content']) === '') {
                continue;
            }
            $testimonials[] = [
                'name' => $review['name'],
                'role' => is_string($review['role'] ?? null) ? $review['role'] : '',
                'content' => $review['content'],
                'avatar' => is_string($review['avatar'] ?? null) && preg_match('#^testimonials/avatars/[a-f0-9-]{36}\.png$#D', $review['avatar']) ? $review['avatar'] : '',
                'rating' => max(1, min(5, (int) ($review['rating'] ?? 5))),
            ];
        }

        $announcements = Announcement::query()
            ->published()
            ->latest('published_at')
            ->limit(3)
            ->get();

        $stats = [
            'customers' => 500 + User::query()->where('status', 'active')->count(),
            'services' => 1000 + Product::query()->catalogVisible()->where('status', 'active')->count() + Service::withTrashed()->count(),
            'nodes' => CloudNode::query()->where('status', 'online')->count(),
            'uptime' => '99.98%',
        ];

        return view('site.home', [
            'settings' => Setting::query()->pluck('value', 'key'),
            'featuredProducts' => $featuredProducts,
            'featuredMeta' => $featuredMeta,
            'testimonials' => $testimonials,
            'friendLinks' => HomepageFriendLinks::forHomepage(),
            'announcements' => $announcements,
            'stats' => $stats,
        ]);
    }

    public function products(Request $request): View|RedirectResponse
    {
        $groupSlug = $request->string('group')->toString();
        $search = $request->string('q')->trim()->toString();

        $groups = ProductGroup::query()
            ->where('is_visible', true)
            ->withCount(['products' => fn ($query) => $query->catalogVisible()->where('status', 'active')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $roots = $groups->whereNull('parent_id')->values();
        $childGroups = $roots->flatMap(
            fn (ProductGroup $root) => $groups->where('parent_id', $root->id)->values(),
        )->values();

        $defaultGroup = $childGroups->firstWhere('slug', 'c6a') ?? $childGroups->first();
        $selectedGroup = $groupSlug !== '' ? $groups->firstWhere('slug', $groupSlug) : null;

        if (! $selectedGroup) {
            if ($defaultGroup) {
                return redirect()->route('products.index', array_filter([
                    'group' => $defaultGroup->slug,
                    'q' => $search !== '' ? $search : null,
                ]));
            }
        } elseif ($selectedGroup->parent_id === null) {
            $rootChildren = $childGroups->where('parent_id', $selectedGroup->id);
            if ($rootChildren->isNotEmpty() || $selectedGroup->products_count === 0) {
                if ($defaultGroup) {
                    return redirect()->route('products.index', array_filter([
                        'group' => $defaultGroup->slug,
                        'q' => $search !== '' ? $search : null,
                    ]));
                }
                $selectedGroup = null;
            }
        }

        $groupSlug = $selectedGroup?->slug ?? '';

        $productsQuery = Product::query()->catalogVisible()
            ->with(['group.parent', 'prices' => fn ($query) => $query->where('is_active', true)->orderBy('price')])
            ->where('status', 'active')
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('short_description', 'like', "%{$search}%")
                ->orWhere('region', 'like', "%{$search}%")))
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($selectedGroup) {
            $productsQuery->where('product_group_id', $selectedGroup->id);
        } else {
            $productsQuery->whereRaw('1 = 0');
        }

        $products = $productsQuery->get();

        $sections = $selectedGroup
            ? [['group' => $selectedGroup, 'products' => $products]]
            : [];

        return view('site.products', [
            'groups' => $groups,
            'roots' => $roots,
            'childGroups' => $childGroups,
            'sections' => $sections,
            'selectedGroup' => $selectedGroup,
            'groupSlug' => $groupSlug,
            'search' => $search,
        ]);
    }

    public function product(Product $product): View
    {
        if (\App\Support\Colocation::product($product)) {
            abort_unless($product->status === 'active',404);
            return view('site.colocation', ['product'=>$product->load('group','prices')]);
        }
        abort_unless($product->isPurchasable(), 404);

        $product->load([
            'group',
            'prices' => fn ($query) => $query->where('is_active', true)->orderBy('price'),
        ]);

        $relatedProducts = Product::query()->catalogVisible()
            ->with('prices')
            ->where('status', 'active')
            ->whereKeyNot($product->id)
            ->orderBy('sort_order')
            ->limit(3)
            ->get();

        return view('site.product', compact('product', 'relatedProducts'));
    }

    public function productDetails(Product $product): View
    {
        abort_unless($product->status === 'active', 404);
        $product->load('group');

        return view('site.product-details', compact('product'));
    }

    public function news(): View
    {
        $announcements = Announcement::query()
            ->published()
            ->latest('published_at')
            ->paginate(9);

        return view('site.news', compact('announcements'));
    }

    public function announcement(Announcement $announcement): View
    {
        abort_unless($announcement->is_published && $announcement->published_at?->isPast(), 404);

        return view('site.announcement', compact('announcement'));
    }

    public function help(): View
    {
        return view('site.help');
    }
}
