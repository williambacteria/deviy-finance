# Deviy 财务与云服务门户

德威云计算官网 **www.deviy.cn** 的应用代码仓库，包含官网、客户控制台、运营后台、订单财务、支付与云资源交付对接。

此仓库是 **2026-11-23** 从在用站点导出的版本快照。现网域名为 www.deviy.cn，应用目录仍为 /www/wwwroot/demo.deviy.cn；域名与目录名不同，不代表存在两套财务程序。

**仓库为私有仓库，保存应用代码和静态资源，不包含生产数据库、客户文件、.env、支付证书、接口密钥、会话或运行日志。克隆仓库不等于恢复完整业务数据。**

## 一、系统组成

| 部分 | 主要能力 |
| --- | --- |
| 官网 | 商品展示、新闻公告、帮助中心、用户评价、友情链接、服务条款与隐私政策 |
| 账户 | 注册、邮箱验证、登录、忘记密码、点选验证、实名资料 |
| 客户控制台 | 资源分组、实例详情、续费、订单支付、充值、工单、消息、域名过白、代理 API |
| 运营后台 | 客户资料、商品分组、资源选品、订单财务、网站内容、系统设置、支付渠道、邮件、审计 |
| 支付 | 支付宝、微信 Native、余额；回调验证、支付结果同步和账务核对 |
| 自动交付 | 订单交付、主控同步、资源生命周期、账单、通知、定时备份 |
| 多类产品 | 云服务器、NAT 云机、CDN、裸金属、游戏面板、自有设备托管 |
| 页面体验 | 白天/黑夜主题、响应式侧边栏、登录注册动态展示 |

实际启用的商品、接口和价格由数据库及后台设置决定，代码中的演示数据不代表现网商品。

### 对接关系

- **Deviy Controller**：demovps.deviy.cn，对应 [deviy-vps-controller](https://github.com/WilliamBacteria/deviy-vps-controller)。
- **轻舟**：通过 app/Services/Qzvps 中的适配逻辑接入，供应商及节点配置单独保存。
- **魔方财务上游**：app/Services/Mofang 实现选品、购买、续费、流量包、实例同步。
- **翼龙面板**：app/Services/Pterodactyl 对接游戏服务器。
- **人工托管**：记录硬件、机房、带宽、到期日与合同价，按人工托管流程维护。

CDN 不套用服务器远程登录、操作系统和重装界面；托管设备不自动调用开关机、删除或重装接口。

## 二、技术栈

依赖的准确版本以 composer.json、composer.lock 和 package.json 为准。

- PHP：项目声明 ^8.3，Composer 平台基准 8.3.36。
- Laravel：导出时现网版本 13.39.0。
- 数据库：MySQL / MariaDB。
- 页面：Blade、独立 CSS / JavaScript；构建入口使用 Vite 8、Tailwind CSS 4。
- PHP 扩展：PDO/MySQL、mbstring、openssl、curl、fileinfo、bcmath、GD，以及 Composer 检查要求的其他扩展。
- Node.js：使用满足当前 Vite 版本引擎要求的版本。
- 生产服务：Nginx、PHP-FPM、队列工作进程、Laravel 调度器。

## 三、目录结构

~~~text
app/Console/Commands/       定时任务和运维命令
app/Http/Controllers/       官网、认证、客户、运营与接口
app/Models/                 业务模型
app/Services/Billing/       续费计价
app/Services/Commerce/      交易流程
app/Services/Finance/       财务核对
app/Services/Mofang/        魔方财务上游
app/Services/Payments/      支付渠道与确认
app/Services/Provisioning/  主控操作及交付
app/Services/Pterodactyl/   翼龙面板
app/Services/Qzvps/         轻舟接入
app/Services/Reseller/      下游代理
app/Services/Mail/          邮件配置和模板触发
app/Support/               展示、托管、金额等公共逻辑
bootstrap/                 应用引导
config/                    配置与环境变量
database/                  迁移、工厂与演示 Seeder
lang/                      语言资源
public/assets/             现网独立样式、字体、图片与脚本
resources/views/           Blade 页面和组件
routes/web.php             Web、后台、回调及代理路由
routes/console.php         任务调度
deploy/                    部署配置示例
~~~

public/assets 中的文件由 Nginx 直接提供，修改后需同步发布；修改 resources/css、resources/js 等构建入口后需执行 Vite 构建。

## 四、新环境安装

以下步骤用于**全新独立环境**。恢复现网请先阅读“备份与恢复”，不要对现网执行清库或演示初始化。

~~~bash
git clone https://github.com/WilliamBacteria/deviy-finance.git
cd deviy-finance
composer install --no-dev --prefer-dist --optimize-autoloader
cp .env.example .env
php artisan key:generate
~~~

编辑 .env，至少设置：

~~~dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://www.deviy.cn
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=deviy_finance
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
~~~

然后执行：

~~~bash
php artisan migrate --force
php artisan storage:link
npm install --ignore-scripts
npm run build
php artisan view:cache
~~~

为 PHP-FPM 账户授予 storage/、bootstrap/cache/ 所需写权限。Nginx 根目录必须指向 public/，参考 deploy/nginx.example.conf；证书和 PHP-FPM socket 按实际服务器调整。

### 初始管理员

DatabaseSeeder 包含测试账户和模拟业务，**不用于生产初始化，不要在现网执行 db:seed 或 migrate:fresh**。

全新生产环境可运行 php artisan tinker，然后交互创建管理员：

~~~php
App\Models\Admin::create([
    'name' => '运营管理员',
    'email' => '替换为管理员邮箱',
    'password' => '替换为独立的高强度密码',
    'role' => 'super_admin',
    'is_active' => true,
]);
~~~

密码由模型 hashed cast 处理。恢复既有数据库不需要重新创建管理员。

## 五、后台首次配置

管理员入口 /admin/login。

1. 系统设置：确认域名、站点名称、联系信息和邮件。
2. 支付渠道：配置支付宝、微信应用与商户、公钥、证书和回调。
3. 商品中心：确认供应商、节点、类型、地域、价格和隐藏商品。
4. 邮件：配置 SMTP，验证投递和到期提醒。
5. 网站内容：配置首页商品、公告、用户评价和友情链接。
6. 安全：确认管理员角色、二次验证、代理权限和审计。
7. 完成隔离验证后，再启用真实支付、交付和自动任务。

配置可能保存于 Setting、供应商或节点相关表，不仅存在于 .env。

## 六、支付与上游交付

| 渠道 | 异步通知地址 |
| --- | --- |
| 支付宝 | https://www.deviy.cn/payments/alipay/notify |
| 微信支付 | https://www.deviy.cn/payments/wechat/notify |

微信 Native 请求中的 notify_url 是交易结果通知地址，不等同于老式扫码模式的商户平台回调设置。

发布或变更支付配置后，应验证订单创建、扫码、异步通知、本站确认、充值入账以及购买/续费交付；浏览器跳转成功不能单独证明收款成功。

魔方交付先根据本站已支付记录生成上游订单，再创建账单、付款并核实资源结果。流量包使用上游 fid 参数；确认交付要检查套餐或额度到账，不能把使用量变化当成购买成功。结果不明确的订单应人工核对，避免重复下单或付款。

新环境默认关闭真实支付，使用 mock 供应商。不要将生产密钥直接接入未经验证的测试环境。

## 七、队列与定时任务

队列工作进程：

~~~bash
php artisan queue:work --sleep=3 --tries=3 --timeout=120
~~~

调度采用以下方式之一：

- systemd 长驻运行 php artisan schedule:work。
- cron 每分钟执行 php artisan schedule:run。

不要同时重复运行同站点调度器。参考 deploy/finance-queue.service 与 deploy/finance-scheduler.service。

| 命令 | 作用 |
| --- | --- |
| payments:sync | 支付结果同步 |
| provision:dispatch | 交付分发 |
| mofang:fulfill | 魔方订单履约 |
| mofang:sync、mofang:sync-services | 商品与资源同步 |
| nodes:sync、services:metrics | 节点和指标同步 |
| services:guest-passwords | 客户机密码同步 |
| services:bill、services:lifecycle | 账单与生命周期 |
| services:notify、notifications:retry | 到期等通知与重试 |
| finance:reconcile | 财务核对 |
| backup:database、backup:verify | 数据库备份与验证 |

完整计划见 routes/console.php；用 php artisan schedule:list 查看，用 php artisan list 和具体命令的 --help 查询参数。这些命令可能操作真实财务或资源，不应批量试跑。

## 八、发布与回滚

1. 在独立环境验证变更，记录当前提交号并备份数据库、文件和密钥。
2. 拉取确认的提交，按 composer.lock 安装依赖。
3. 有迁移时先核对内容，再执行迁移；有构建入口变化时运行 npm run build。
4. 执行 php artisan view:cache 和 php artisan queue:restart。
5. 验证官网、登录、后台、支付回调及资源页面。
6. 异常时回退代码；数据迁移单独核对，不能只回退 Git。

执行 config:cache 前确认自定义配置读取方式。不以覆盖生产 .env、storage 或主控状态文件的方式部署。

## 九、备份与恢复

完整恢复至少需要：

- 本仓库的对应提交。
- 生产数据库：客户、实名、商品、资源、订单、余额、流水、支付及设置。
- 原始 .env 和**原始 APP_KEY**。
- storage/app 下的上传、工单附件、Logo 和私有文件。
- 独立保存的支付证书、SMTP 与供应商密钥。
- 主控的节点、IP、镜像、虚拟机绑定与状态文件。

恢复已有数据库时**不要重新生成 APP_KEY**，否则已加密内容可能无法解密。

恢复到隔离环境后先核对绑定，保持真实扣费和自动运维暂停；验证完成再切换入口。旧站与新站不能同时执行付款、交付和生命周期任务。

## 十、仓库边界与维护

已排除 .env、私钥、生产数据、SQL、SQLite、备份、日志、会话、缓存、用户上传、vendor、node_modules、历史 .bak、QA 脚本及未被运行页面引用的设计方案素材。

.env.example 是新写的无密钥模板；.gitignore 用于约束后续提交。每次提交仍应检查待提交文件，私有仓库不替代密钥管理和业务备份。

建议按功能分支开发、审查后合并；修改前同步分支，现网修复及时回传仓库，避免其他人用旧代码覆盖。提交说明记录原因、范围与验证结果。

本仓库没有重新授权第三方依赖、字体或素材；它们分别遵循原有许可。
