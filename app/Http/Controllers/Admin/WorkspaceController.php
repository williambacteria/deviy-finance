<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class WorkspaceController extends Controller
{
    /** @return array<string, array<string, mixed>> */
    public static function areas(): array
    {
        return [
            'customers' => ['title' => '客户中心', 'description' => '客户资料与实名认证集中管理。', 'tabs' => [
                'list' => ['客户资料', CustomerController::class, ['super_admin', 'finance', 'support']],
                'real-names' => ['实名认证', RealNameReviewController::class, ['super_admin', 'support']],
                'resellers' => ['代理与 API', ResellerController::class, ['super_admin']],
            ]],
            'catalog' => ['title' => '商品中心', 'description' => '商品、分类及资源选品统一管理，存量隐藏商品保留原业务关联。', 'tabs' => [
                'products' => ['商品管理', ProductController::class, ['super_admin']],
                'groups' => ['商品分组', ProductGroupController::class, ['super_admin']],
                'sourcing' => ['资源选品', MofangController::class, ['super_admin']],
                'panel' => ['游戏面板选品', PterodactylController::class, ['super_admin']],
            ]],
            'finance' => ['title' => '订单财务', 'description' => '订单、支付与经营报表。', 'tabs' => [
                'orders' => ['订单管理', OrderController::class, ['super_admin', 'finance', 'support']],
                'reports' => ['财务报表', ReportController::class, ['super_admin', 'finance']],
            ]],
            'website' => ['title' => '网站内容', 'description' => '公告、帮助文档、首页推荐、用户评价与友情链接集中维护。', 'tabs' => [
                'announcements' => ['新闻公告', AnnouncementController::class, ['super_admin', 'support']],
                'docs' => ['帮助文档', HelpArticleController::class, ['super_admin', 'support']],
                'featured' => ['首页商品', HomepageFeaturedController::class, ['super_admin']],
                'reviews' => ['用户评价', HomepageTestimonialsController::class, ['super_admin']],
                'friend-links' => ['友情链接', HomepageFriendLinksController::class, ['super_admin']],
            ]],
            'messages' => ['title' => '消息与告警', 'description' => '业务消息、故障告警和人员值班。', 'tabs' => [
                'inbox' => ['管理员消息', InboxController::class, ['super_admin', 'finance', 'support']],
                'alerts' => ['服务告警', OpsAlertController::class, ['super_admin', 'support']],
                'oncall' => ['值班安排', OncallController::class, ['super_admin']],
            ]],
        ];
    }

    public function show(Request $request, string $area): Response
    {
        $definition = self::areas()[$area] ?? null;
        abort_unless($definition, 404);
        $role = $request->user('admin')->role;
        $tabs = array_filter($definition['tabs'], fn (array $tab): bool => in_array($role, $tab[2], true));
        abort_unless($tabs !== [], 403);
        $tab = $request->string('tab', array_key_first($tabs))->toString();
        abort_unless(isset($tabs[$tab]), 403);
        $delegate = app($tabs[$tab][1]);
        $content = app()->call([$delegate, 'index']);
        if ($content instanceof Response) {
            $content = $content->getOriginalContent();
        }
        abort_unless($content instanceof View, 500);
        $viewName = $content->getName();
        $workspacePartial = str_starts_with($viewName, 'admin.merged.')
            ? $viewName
            : 'admin.merged.'.str_replace('.', '-', $viewName);

        return response()->view('admin.workspace', [
            'workspaceArea' => $area, 'workspaceTab' => $tab, 'workspaceTitle' => $definition['title'],
            'workspaceDescription' => $definition['description'], 'workspaceTabs' => $tabs,
            'workspacePartial' => $workspacePartial,
            'workspaceData' => $content->getData(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }
}
