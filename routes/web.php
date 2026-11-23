<?php

use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Admin\AuditController as AdminAuditController;
use App\Http\Controllers\Admin\Auth\LoginController as AdminLoginController;
use App\Http\Controllers\Admin\Auth\TwoFactorChallengeController as AdminTwoFactorChallengeController;
use App\Http\Controllers\Admin\ColocationServiceController;
use App\Http\Controllers\Admin\ControllerIntegrationController as AdminControllerIntegrationController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\HelpArticleController as AdminHelpArticleController;
use App\Http\Controllers\Admin\HomepageFeaturedController as AdminHomepageFeaturedController;
use App\Http\Controllers\Admin\HomepageFriendLinksController as AdminHomepageFriendLinksController;
use App\Http\Controllers\Admin\HomepageTestimonialsController as AdminHomepageTestimonialsController;
use App\Http\Controllers\Admin\InboxController as AdminInboxController;
use App\Http\Controllers\Admin\MailPluginController as AdminMailPluginController;
use App\Http\Controllers\Admin\MofangController as AdminMofangController;
use App\Http\Controllers\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Admin\OncallController as AdminOncallController;
use App\Http\Controllers\Admin\OpsAlertController as AdminOpsAlertController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\PaymentSettingsController as AdminPaymentSettingsController;
use App\Http\Controllers\Admin\PluginController as AdminPluginController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\ProductGroupController as AdminProductGroupController;
use App\Http\Controllers\Admin\PterodactylController as AdminPterodactylController;
use App\Http\Controllers\Admin\QuarantineController as AdminQuarantineController;
use App\Http\Controllers\Admin\RealNameReviewController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\SecurityController as AdminSecurityController;
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Admin\SettingChangeController as AdminSettingChangeController;
use App\Http\Controllers\Admin\SettingController as AdminSettingController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\WechatPaymentSettingsController;
use App\Http\Controllers\Admin\WorkspaceController;
use App\Http\Controllers\Api\LegacyFinanceController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordRecoveryController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Console\BareMetalMaintenanceController;
use App\Http\Controllers\Console\CdnModuleController;
use App\Http\Controllers\Console\DashboardController as ConsoleDashboardController;
use App\Http\Controllers\Console\DomainAllowlistController;
use App\Http\Controllers\Console\InvoiceController as ConsoleInvoiceController;
use App\Http\Controllers\Console\NoticeController as ConsoleNoticeController;
use App\Http\Controllers\Console\OrderCashierController;
use App\Http\Controllers\Console\OrderController as ConsoleOrderController;
use App\Http\Controllers\Console\RealNameController;
use App\Http\Controllers\Console\RechargeController as ConsoleRechargeController;
use App\Http\Controllers\Console\ResellerController;
use App\Http\Controllers\Console\ServiceController as ConsoleServiceController;
use App\Http\Controllers\Console\SettingsController as ConsoleSettingsController;
use App\Http\Controllers\Console\TicketController as ConsoleTicketController;
use App\Http\Controllers\HelpCenterController;
use App\Http\Controllers\Payment\AlipayNotifyController;
use App\Http\Controllers\Payment\WechatNotifyController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\UnsubscribeController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::match(['GET', 'POST', 'PUT', 'DELETE'], '/{legacyPath}', LegacyFinanceController::class)
    ->where('legacyPath', '(?:zjmf_api_login|apply_credit|cart/(?:get_product_config|add_to_shop|settle|add_promo)|api/(?:product(?:/[a-zA-Z0-9_-]+(?:/resource)?)?|host/sync)|host/(?:list|header|renew|cancel|dedicatedserver|trafficusage)|provision/(?:default|chart/[0-9]+|custom/(?:[0-9]+|content))|zjmf_api/provision/custom/content|dcim/(?:on|off|reboot|bmc|novnc|rescue|reinstall|crack_pass|traffic_usage|traffic_package|buy_flow_packet|resintall_status|refresh_power_status|detail))/?')
    ->middleware('throttle:180,1')
    ->withoutMiddleware(PreventRequestForgery::class)
    ->name('legacy-finance.compat');

Route::get('/', [SiteController::class, 'home'])->name('home');
Route::get('/products', [SiteController::class, 'products'])->name('products.index');
Route::get('/products/{product:code}/details', [SiteController::class, 'productDetails'])->name('products.details');
Route::get('/products/{product:code}', [SiteController::class, 'product'])->name('products.show');
Route::get('/news', [SiteController::class, 'news'])->name('news.index');
Route::get('/news/{announcement:slug}', [SiteController::class, 'announcement'])->name('news.show');
Route::get('/help', [HelpCenterController::class, 'index'])->name('help');
Route::get('/help/{article:slug}', [HelpCenterController::class, 'show'])->name('help.article');
Route::view('/terms', 'site.terms')->name('terms');
Route::view('/privacy', 'site.privacy')->name('privacy');
Route::get('/notifications/unsubscribe/{user}/{type}/{channel}', UnsubscribeController::class)
    ->middleware('signed')
    ->name('notifications.unsubscribe');

Route::post('/payments/alipay/notify', [AlipayNotifyController::class, 'notify'])
    ->middleware(['payment.ip', 'throttle:60,1'])
    ->withoutMiddleware(PreventRequestForgery::class)
    ->name('payments.alipay.notify');

Route::get('/payments/alipay/return', [AlipayNotifyController::class, 'return'])
    ->name('payments.alipay.return');

Route::get('/payments/alipay/qr/{paymentNo}', [AlipayNotifyController::class, 'redirectToCashier'])
    ->name('payments.alipay.qr');

Route::post('/payments/wechat/notify', [WechatNotifyController::class, 'notify'])
    ->middleware(['payment.ip', 'throttle:60,1'])
    ->withoutMiddleware(PreventRequestForgery::class)
    ->name('payments.wechat.notify');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
    Route::get('/forgot-password', [PasswordRecoveryController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordRecoveryController::class, 'store'])->middleware('throttle:8,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordRecoveryController::class, 'edit'])->where('token', '[A-Za-z0-9]{64}')->name('password.reset');
    Route::post('/reset-password', [PasswordRecoveryController::class, 'update'])->middleware('throttle:8,1')->name('password.update');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:6,1')->name('register.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::post('/email/verify/code', [EmailVerificationController::class, 'verifyCode'])->middleware('throttle:10,1')->name('verification.code');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware('signed')->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])->middleware('throttle:6,1')->name('verification.send');

    Route::post('/impersonate/leave', [AdminCustomerController::class, 'leaveImpersonation'])->name('impersonate.leave');

    Route::get('/checkout/{product:code}', [CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout/{product:code}', [CheckoutController::class, 'store'])->name('checkout.store');

    Route::prefix('console')->name('console.')->middleware('verified')->group(function () {
        Route::get('/', ConsoleDashboardController::class)->name('dashboard');
        Route::get('/domain-allowlist', [DomainAllowlistController::class, 'index'])->name('domain-allowlist.index');
        Route::post('/domain-allowlist', [DomainAllowlistController::class, 'store'])->middleware('throttle:6,1')->name('domain-allowlist.store');
        Route::get('/reseller', [ResellerController::class, 'index'])->name('reseller.index');
        Route::post('/reseller', [ResellerController::class, 'store'])->middleware('throttle:3,60')->name('reseller.store');
        Route::put('/reseller', [ResellerController::class, 'update'])->middleware('throttle:6,1')->name('reseller.update');
        Route::get('/reseller/docs', [ResellerController::class, 'docs'])->name('reseller.docs');
        Route::get('/real-name', [RealNameController::class, 'show'])->name('real-name');
        Route::post('/real-name', [RealNameController::class, 'store'])->name('real-name.store');

        Route::get('/services', [ConsoleServiceController::class, 'index'])->name('services.index');
        Route::get('/services/{service}', [ConsoleServiceController::class, 'show'])->name('services.show');
        Route::get('/services/{service}/vnc', [ConsoleServiceController::class, 'vnc'])->name('services.vnc');
        Route::get('/services/{service}/monitor', [ConsoleServiceController::class, 'monitor'])->name('services.monitor');
        Route::get('/services/{service}/rdp', [ConsoleServiceController::class, 'rdpFile'])->name('services.rdp');
        Route::post('/services/{service}/action', [ConsoleServiceController::class, 'action'])->name('services.action');
        Route::post('/services/{service}/bare/maintenance', [BareMetalMaintenanceController::class, 'store'])->middleware('throttle:6,1')->name('services.bare.maintenance');
        Route::post('/services/{service}/cdn/whitelist', [CdnModuleController::class, 'store'])->middleware('throttle:10,1')->name('services.cdn.whitelist');
        Route::post('/services/{service}/renew', [ConsoleServiceController::class, 'renew'])->name('services.renew');
        Route::post('/services/{service}/auto-renew', [ConsoleServiceController::class, 'autoRenew'])->name('services.auto-renew');
        Route::post('/services/{service}/password', [ConsoleServiceController::class, 'changePassword'])->name('services.password');
        Route::post('/services/{service}/traffic', [ConsoleServiceController::class, 'buyTraffic'])->name('services.traffic');

        Route::post('/services/{service}/mappings', [ConsoleServiceController::class, 'storeMapping'])->name('services.mappings.store');
        Route::delete('/services/{service}/mappings/{mapping}', [ConsoleServiceController::class, 'destroyMapping'])->name('services.mappings.destroy');

        Route::get('/orders', [ConsoleOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [ConsoleOrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/cashier', [OrderCashierController::class, 'store'])->middleware('throttle:12,1')->name('orders.cashier');
        Route::get('/orders/{order}/cashier/status', [OrderCashierController::class, 'show'])->middleware('throttle:60,1')->name('orders.cashier.status');
        Route::post('/orders/{order}/cashier/close', [OrderCashierController::class, 'destroy'])->middleware('throttle:12,1')->name('orders.cashier.close');
        Route::post('/orders/{order}/pay', [ConsoleOrderController::class, 'pay'])->name('orders.pay');

        Route::get('/invoices', [ConsoleInvoiceController::class, 'index'])->name('invoices.index');
        Route::get('/invoices/{invoice}', [ConsoleInvoiceController::class, 'show'])->name('invoices.show');

        Route::get('/recharge', [ConsoleRechargeController::class, 'index'])->name('recharge.index');
        Route::post('/recharge', [ConsoleRechargeController::class, 'store'])->name('recharge.store');
        Route::post('/recharge/payments/{payment}/resume', [ConsoleRechargeController::class, 'resume'])->middleware('throttle:12,1')->name('recharge.resume');
        Route::get('/recharge/payments/{payment}/status', [ConsoleRechargeController::class, 'status'])->name('recharge.status');

        Route::get('/notices', [ConsoleNoticeController::class, 'index'])->name('notices.index');
        Route::post('/notices/read-all', [ConsoleNoticeController::class, 'readAll'])->name('notices.read-all');
        Route::post('/notices/{notice}/read', [ConsoleNoticeController::class, 'read'])->name('notices.read');

        Route::get('/tickets', [ConsoleTicketController::class, 'index'])->name('tickets.index');
        Route::get('/tickets/create', [ConsoleTicketController::class, 'create'])->name('tickets.create');
        Route::post('/tickets', [ConsoleTicketController::class, 'store'])->name('tickets.store');
        Route::get('/tickets/attachments/{attachment}/preview', [ConsoleTicketController::class, 'preview'])->middleware('throttle:60,1')->name('tickets.attachments.preview');
        Route::get('/tickets/attachments/{attachment}', [ConsoleTicketController::class, 'download'])->middleware('throttle:30,1')->name('tickets.attachments.download');
        Route::get('/tickets/{ticket}', [ConsoleTicketController::class, 'show'])->name('tickets.show');
        Route::post('/tickets/{ticket}/reply', [ConsoleTicketController::class, 'reply'])->name('tickets.reply');

        Route::get('/settings', [ConsoleSettingsController::class, 'show'])->name('settings');
        Route::put('/settings', [ConsoleSettingsController::class, 'update'])->name('settings.update');
        Route::put('/settings/password', [ConsoleSettingsController::class, 'password'])->name('settings.password');
        Route::put('/settings/notifications', [ConsoleSettingsController::class, 'notifications'])->name('settings.notifications');
    });
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AdminLoginController::class, 'create'])->name('login');
    Route::post('/login', [AdminLoginController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
    Route::get('/two-factor', [AdminTwoFactorChallengeController::class, 'create'])->name('two-factor.create');
    Route::post('/two-factor', [AdminTwoFactorChallengeController::class, 'store'])->middleware('throttle:6,1')->name('two-factor.store');
    Route::post('/logout', [AdminLoginController::class, 'destroy'])->name('logout');

    Route::middleware(['auth:admin', 'admin.2fa'])->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');

        Route::get('/inbox', fn (Request $request) => redirect()->route('admin.workspace', array_merge($request->query(), ['area' => 'messages', 'tab' => 'inbox'])))->name('inbox.index');
        Route::post('/inbox/{notice}/read', [AdminInboxController::class, 'read'])->name('inbox.read');
        Route::post('/inbox/read-all', [AdminInboxController::class, 'readAll'])->name('inbox.read-all');

        Route::get('/ops', fn (Request $request) => redirect()->route('admin.workspace', array_merge($request->query(), ['area' => 'messages', 'tab' => 'alerts'])))->middleware('admin.role:super_admin,support')->name('ops.index');
        Route::post('/ops/scan', [AdminOpsAlertController::class, 'scan'])->middleware('admin.role:super_admin,support')->name('ops.scan');
        Route::post('/ops/{alert}/ack', [AdminOpsAlertController::class, 'acknowledge'])->middleware('admin.role:super_admin,support')->name('ops.ack');
        Route::post('/ops/{alert}/silence', [AdminOpsAlertController::class, 'silence'])->middleware('admin.role:super_admin,support')->name('ops.silence');
        Route::post('/ops/{alert}/resolve', [AdminOpsAlertController::class, 'resolve'])->middleware('admin.role:super_admin,support')->name('ops.resolve');
        Route::get('/oncall', fn (Request $request) => redirect()->route('admin.workspace', array_merge($request->query(), ['area' => 'messages', 'tab' => 'oncall'])))->middleware('admin.role:super_admin')->name('oncall.index');
        Route::post('/oncall', [AdminOncallController::class, 'store'])->middleware('admin.role:super_admin')->name('oncall.store');
        Route::delete('/oncall/{shift}', [AdminOncallController::class, 'destroy'])->middleware('admin.role:super_admin')->name('oncall.destroy');

        Route::get('/security', fn (Request $request) => $request->user('admin')->role === 'super_admin' ? redirect()->route('admin.settings.index', array_merge($request->query(), ['section' => 'security'])) : app()->call([app(AdminSecurityController::class), 'show']))->name('security.show');
        Route::post('/security/two-factor', [AdminSecurityController::class, 'enable'])->name('security.enable');
        Route::delete('/security/two-factor', [AdminSecurityController::class, 'disable'])->name('security.disable');

        Route::get('/real-names', fn (Request $request) => redirect()->route('admin.workspace', array_merge($request->query(), ['area' => 'customers', 'tab' => 'real-names'])))->middleware('admin.role:super_admin,support')->name('real-names.index');
        Route::get('/real-names/{user}/edit', [RealNameReviewController::class, 'edit'])->middleware('admin.role:super_admin,support')->name('real-names.edit');
        Route::put('/real-names/{user}', [RealNameReviewController::class, 'update'])->middleware('admin.role:super_admin,support')->name('real-names.update');
        Route::get('/customers', fn (Request $request) => redirect()->route('admin.workspace', array_merge($request->query(), ['area' => 'customers', 'tab' => 'list'])))->name('customers.index');
        Route::get('/customers/create', [AdminCustomerController::class, 'create'])->middleware('admin.role:super_admin,support')->name('customers.create');
        Route::post('/customers', [AdminCustomerController::class, 'store'])->middleware('admin.role:super_admin,support')->name('customers.store');
        Route::get('/customers/{customer}', [AdminCustomerController::class, 'show'])->name('customers.show');
        Route::post('/customers/{customer}/real-name/verify', [AdminCustomerController::class, 'verifyRealName'])->middleware('admin.role:super_admin,support')->name('customers.real-name.verify');
        Route::post('/customers/{customer}/real-name/reject', [AdminCustomerController::class, 'rejectRealName'])->middleware('admin.role:super_admin,support')->name('customers.real-name.reject');
        Route::get('/customers/{customer}/real-name/file/{type}', [AdminCustomerController::class, 'realNameFile'])->middleware('admin.role:super_admin,support')->name('customers.real-name.file');
        Route::post('/customers/{customer}/impersonate', [AdminCustomerController::class, 'impersonate'])->middleware('admin.role:super_admin,support')->name('customers.impersonate');
        Route::post('/customers/{customer}/services', [AdminCustomerController::class, 'storeService'])->middleware('admin.role:super_admin,support')->name('customers.services.store');
        Route::put('/customers/{customer}/services/{service}', [AdminCustomerController::class, 'updateService'])->middleware('admin.role:super_admin,support')->name('customers.services.update');
        Route::delete('/customers/{customer}/services/{service}', [AdminCustomerController::class, 'deleteService'])->middleware('admin.role:super_admin,support')->name('customers.services.destroy');
        Route::put('/customers/{customer}', [AdminCustomerController::class, 'update'])->middleware('admin.role:super_admin,support')->name('customers.update');
        Route::delete('/customers/{customer}', [AdminCustomerController::class, 'destroy'])->middleware('admin.role:super_admin')->name('customers.destroy');
        Route::post('/customers/{customer}/balance', [AdminCustomerController::class, 'adjustBalance'])->middleware('admin.role:super_admin,finance')->name('customers.balance');

        Route::get('/services', [AdminServiceController::class, 'index'])->name('services.index');
        Route::put('/services/{service}/colocation', [ColocationServiceController::class, 'update'])->middleware('admin.role:super_admin,support')->name('services.colocation.update');
        Route::get('/services/{service}', [AdminServiceController::class, 'show'])->name('services.show');
        Route::get('/services/{service}/vnc', [AdminServiceController::class, 'vnc'])->middleware('admin.role:super_admin,support')->name('services.vnc');
        Route::post('/services/{service}/traffic', [AdminServiceController::class, 'traffic'])->middleware('admin.role:super_admin,support')->name('services.traffic');
        Route::post('/services/{service}/bare/maintenance', [BareMetalMaintenanceController::class, 'store'])->middleware(['admin.role:super_admin,support', 'throttle:6,1'])->name('services.bare.maintenance');
        Route::post('/services/{service}/cdn/whitelist', [CdnModuleController::class, 'store'])->middleware(['admin.role:super_admin,support', 'throttle:10,1'])->name('services.cdn.whitelist');
        Route::post('/services/{service}/password', [AdminServiceController::class, 'resetPassword'])->middleware('admin.role:super_admin,support')->name('services.password');
        Route::post('/services/{service}/extend', [AdminServiceController::class, 'extend'])->middleware('admin.role:super_admin,support')->name('services.extend');
        Route::post('/services/{service}/auto-renew', [AdminServiceController::class, 'toggleAutoRenew'])->middleware('admin.role:super_admin,support')->name('services.auto-renew');
        Route::post('/services/{service}/action', [AdminServiceController::class, 'action'])->middleware('admin.role:super_admin,support')->name('services.action');
        Route::post('/services/{service}/remote', [AdminServiceController::class, 'updateRemote'])->middleware('admin.role:super_admin,support')->name('services.remote');

        Route::get('/orders', fn (Request $request) => redirect()->route('admin.workspace', array_merge($request->query(), ['area' => 'finance', 'tab' => 'orders'])))->name('orders.index');
        Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/refund', [AdminOrderController::class, 'refund'])->middleware('admin.role:super_admin,finance')->name('orders.refund');

        Route::get('/reports', fn (Request $request) => redirect()->route('admin.workspace', array_merge($request->query(), ['area' => 'finance', 'tab' => 'reports'])))->middleware('admin.role:super_admin,finance')->name('reports.index');
        Route::get('/reports/export/{type}', [AdminReportController::class, 'export'])->middleware('admin.role:super_admin,finance')->name('reports.export');

        Route::get('/tickets', [AdminTicketController::class, 'index'])->name('tickets.index');
        Route::get('/tickets/attachments/{attachment}/preview', [AdminTicketController::class, 'preview'])->middleware('throttle:60,1')->name('tickets.attachments.preview');
        Route::get('/tickets/attachments/{attachment}', [AdminTicketController::class, 'download'])->middleware('throttle:30,1')->name('tickets.attachments.download');
        Route::get('/tickets/{ticket}', [AdminTicketController::class, 'show'])->name('tickets.show');
        Route::post('/tickets/{ticket}/reply', [AdminTicketController::class, 'reply'])->name('tickets.reply');
        Route::put('/tickets/{ticket}/status', [AdminTicketController::class, 'status'])->name('tickets.status');

        Route::get('/announcements', fn (Request $request) => redirect()->route('admin.workspace', array_merge($request->query(), ['area' => 'website', 'tab' => 'announcements'])))->middleware('admin.role:super_admin,support')->name('announcements.index');
        Route::resource('announcements', AdminAnnouncementController::class)->except(['show', 'index'])->middleware('admin.role:super_admin,support');
        Route::get('/help-articles', fn (Request $request) => redirect()->route('admin.workspace', array_merge($request->query(), ['area' => 'website', 'tab' => 'docs'])))->middleware('admin.role:super_admin,support')->name('help-articles.index');
        Route::resource('help-articles', AdminHelpArticleController::class)->except(['show', 'index'])->middleware('admin.role:super_admin,support');
        Route::get('/plugins/mail', fn (Request $request) => redirect()->route('admin.settings.index', array_merge($request->query(), ['section' => 'mail'])))->middleware('admin.role:super_admin')->name('mail.index');
        Route::post('/plugins/mail', [AdminMailPluginController::class, 'update'])->middleware('admin.role:super_admin')->name('mail.update');
        Route::post('/plugins/mail/test', [AdminMailPluginController::class, 'test'])->middleware('admin.role:super_admin')->name('mail.test');

        Route::get('/plugins/pterodactyl', fn (Request $request) => $request->user('admin')->role === 'super_admin' ? redirect()->route('admin.workspace', array_merge($request->query(), ['area' => 'catalog', 'tab' => 'panel'])) : app()->call([app(AdminPterodactylController::class), 'index']))->middleware('admin.role:super_admin,support')->name('pterodactyl.index');
        Route::post('/plugins/pterodactyl', [AdminPterodactylController::class, 'update'])->middleware('admin.role:super_admin')->name('pterodactyl.update');
        Route::get('/plugins/pterodactyl/eggs', [AdminPterodactylController::class, 'eggs'])->middleware('admin.role:super_admin,support')->name('pterodactyl.eggs');
        Route::post('/plugins/pterodactyl/import', [AdminPterodactylController::class, 'import'])->middleware('admin.role:super_admin')->name('pterodactyl.import');

        Route::get('/plugins', fn (Request $request) => $request->user('admin')->role === 'super_admin' ? redirect()->route('admin.settings.index', array_merge($request->query(), ['section' => 'plugins'])) : app()->call([app(AdminPluginController::class), 'index']))->name('plugins.index');
        Route::post('/plugins/{plugin}/toggle', [AdminPluginController::class, 'toggle'])->middleware('admin.role:super_admin')->name('plugins.toggle');

        Route::get('/product-groups', fn (Request $request) => redirect()->route('admin.workspace', array_merge($request->query(), ['area' => 'catalog', 'tab' => 'groups'])))->middleware('admin.role:super_admin')->name('product-groups.index');
        Route::get('/homepage-featured', fn (Request $request) => redirect()->route('admin.workspace', array_merge($request->query(), ['area' => 'website', 'tab' => 'featured'])))->middleware('admin.role:super_admin')->name('homepage-featured.index');
        Route::put('/homepage-featured', [AdminHomepageFeaturedController::class, 'update'])->middleware('admin.role:super_admin')->name('homepage-featured.update');
        Route::get('/homepage-testimonials', fn (Request $request) => redirect()->route('admin.workspace', array_merge($request->query(), ['area' => 'website', 'tab' => 'reviews'])))->middleware('admin.role:super_admin')->name('homepage-testimonials.index');
        Route::put('/homepage-testimonials', [AdminHomepageTestimonialsController::class, 'update'])->middleware('admin.role:super_admin')->name('homepage-testimonials.update');
        Route::middleware('admin.role:super_admin')->prefix('homepage-friend-links')->name('homepage-friend-links.')->group(function (): void {
            Route::post('/', [AdminHomepageFriendLinksController::class, 'save'])->name('store');
            Route::put('/{link}', [AdminHomepageFriendLinksController::class, 'save'])->whereUuid('link')->name('update');
            Route::delete('/{link}', [AdminHomepageFriendLinksController::class, 'destroy'])->whereUuid('link')->name('destroy');
        });
        Route::post('/product-groups/products/sort', [AdminProductGroupController::class, 'sortProducts'])->middleware('admin.role:super_admin')->name('product-groups.products.sort');
        Route::post('/product-groups', [AdminProductGroupController::class, 'store'])->middleware('admin.role:super_admin')->name('product-groups.store');
        Route::put('/product-groups/bulk', [AdminProductGroupController::class, 'bulkUpdate'])->middleware('admin.role:super_admin')->name('product-groups.bulk-update');
        Route::put('/product-groups/{group}', [AdminProductGroupController::class, 'update'])->middleware('admin.role:super_admin')->name('product-groups.update');
        Route::delete('/product-groups/{group}', [AdminProductGroupController::class, 'destroy'])->middleware('admin.role:super_admin')->name('product-groups.destroy');

        Route::post('/products/{product}/duplicate', [AdminProductController::class, 'duplicate'])->middleware('admin.role:super_admin')->name('products.duplicate');
        Route::get('/products/{product}/sold', [AdminProductController::class, 'sold'])->middleware('admin.role:super_admin')->name('products.sold');
        Route::get('/products', fn (Request $request) => redirect()->route('admin.workspace', array_merge($request->query(), ['area' => 'catalog', 'tab' => 'products'])))->middleware('admin.role:super_admin')->name('products.index');
        Route::resource('products', AdminProductController::class)->except(['show', 'index'])->middleware('admin.role:super_admin');

        Route::get('/mofang', fn (Request $request) => redirect()->route('admin.workspace', array_merge($request->query(), ['area' => 'catalog', 'tab' => 'sourcing'])))->middleware('admin.role:super_admin')->name('mofang.index');
        Route::post('/mofang/suppliers', [AdminMofangController::class, 'storeSupplier'])->middleware('admin.role:super_admin')->name('mofang.suppliers.store');
        Route::post('/mofang/suppliers/{supplier}/test', [AdminMofangController::class, 'testSupplier'])->middleware('admin.role:super_admin')->name('mofang.suppliers.test');
        Route::delete('/mofang/suppliers/{supplier}', [AdminMofangController::class, 'destroySupplier'])->middleware('admin.role:super_admin')->name('mofang.suppliers.destroy');
        Route::post('/mofang/sync', [AdminMofangController::class, 'sync'])->middleware('admin.role:super_admin')->name('mofang.sync');
        Route::post('/mofang/bulk-import', [AdminMofangController::class, 'bulkImport'])->middleware('admin.role:super_admin')->name('mofang.bulk-import');
        Route::post('/mofang/{upstream}/import', [AdminMofangController::class, 'import'])->middleware('admin.role:super_admin')->name('mofang.import');
        Route::post('/mofang/{upstream}/ignore', [AdminMofangController::class, 'ignore'])->middleware('admin.role:super_admin')->name('mofang.ignore');
        Route::post('/mofang/orders/{upstreamOrder}/retry', [AdminMofangController::class, 'retry'])->middleware('admin.role:super_admin')->name('mofang.retry');

        Route::put('/resellers/{reseller}', [App\Http\Controllers\Admin\ResellerController::class, 'update'])->middleware('admin.role:super_admin')->name('resellers.update');
        Route::get('/workspace/{area}', [WorkspaceController::class, 'show'])->whereIn('area', ['customers', 'catalog', 'finance', 'website', 'messages'])->name('workspace');

        Route::get('/settings', [AdminSettingController::class, 'index'])->middleware('admin.role:super_admin')->name('settings.index');
        Route::put('/settings', [AdminSettingController::class, 'update'])->middleware('admin.role:super_admin')->name('settings.update');
        Route::get('/payments', fn (Request $request) => redirect()->route('admin.settings.index', array_merge($request->query(), ['section' => 'payment'])))->middleware('admin.role:super_admin')->name('payments.index');
        Route::put('/payments/wechat', [WechatPaymentSettingsController::class, 'update'])->middleware(['admin.role:super_admin', 'throttle:10,1'])->name('payments.wechat.update');
        Route::put('/payments', [AdminPaymentSettingsController::class, 'update'])->middleware('admin.role:super_admin')->name('payments.update');
        Route::get('/settings/changes', fn (Request $request) => redirect()->route('admin.settings.index', array_merge($request->query(), ['section' => 'changes'])))->middleware('admin.role:super_admin')->name('settings.changes.index');
        Route::post('/settings/changes/{change}/approve', [AdminSettingChangeController::class, 'approve'])->middleware('admin.role:super_admin')->name('settings.changes.approve');
        Route::post('/settings/changes/{change}/reject', [AdminSettingChangeController::class, 'reject'])->middleware('admin.role:super_admin')->name('settings.changes.reject');

        Route::get('/notifications', fn (Request $request) => redirect()->route('admin.settings.index', array_merge($request->query(), ['section' => 'notification'])))->middleware('admin.role:super_admin')->name('notifications.index');
        Route::get('/notifications/templates/{template}/preview', [AdminNotificationController::class, 'previewTemplate'])->middleware('admin.role:super_admin')->name('notifications.templates.preview');
        Route::get('/notifications/templates/{template}/edit', [AdminNotificationController::class, 'editTemplate'])->middleware('admin.role:super_admin')->name('notifications.templates.edit');
        Route::put('/notifications/templates/{template}', [AdminNotificationController::class, 'updateTemplate'])->middleware('admin.role:super_admin')->name('notifications.templates.update');
        Route::post('/notifications/{log}/retry', [AdminNotificationController::class, 'retry'])->middleware('admin.role:super_admin')->name('notifications.retry');

        Route::get('/controller', fn (Request $request) => $request->user('admin')->role === 'super_admin' ? redirect()->route('admin.settings.index', array_merge($request->query(), ['section' => 'controller'])) : app()->call([app(AdminControllerIntegrationController::class), 'index']))->middleware('admin.role:super_admin,finance,support')->name('controller.index');
        Route::post('/controller/hosts', [AdminControllerIntegrationController::class, 'storeNode'])->middleware('admin.role:super_admin,finance')->name('controller.hosts.store');
        Route::get('/controller/{node}', [AdminControllerIntegrationController::class, 'show'])->middleware('admin.role:super_admin,finance,support')->name('controller.show');
        Route::post('/controller/{node}/create', [AdminControllerIntegrationController::class, 'create'])->middleware('admin.role:super_admin,finance')->name('controller.create');
        Route::post('/controller/{node}/baremetal-service', [AdminControllerIntegrationController::class, 'createBareMetalService'])->middleware('admin.role:super_admin,finance')->name('controller.baremetal-service');

        Route::post('/controller/{node}/vms/{vmid}/action', [AdminControllerIntegrationController::class, 'action'])->middleware('admin.role:super_admin,finance,support')->name('controller.action');

        Route::get('/audit', fn (Request $request) => $request->user('admin')->role === 'super_admin' ? redirect()->route('admin.settings.index', array_merge($request->query(), ['section' => 'audit'])) : app()->call([app(AdminAuditController::class), 'index']))->name('audit.index');
        Route::get('/storage', fn (Request $request) => redirect()->route('admin.settings.index', array_merge($request->query(), ['section' => 'attachment'])))->middleware('admin.role:super_admin')->name('storage.index');
        Route::get('/quarantine', fn (Request $request) => redirect()->route('admin.settings.index', array_merge($request->query(), ['section' => 'quarantine'])))->middleware('admin.role:super_admin')->name('quarantine.index');
        Route::delete('/quarantine/{file}', [AdminQuarantineController::class, 'destroy'])->middleware('admin.role:super_admin')->name('quarantine.destroy');
    });
});
