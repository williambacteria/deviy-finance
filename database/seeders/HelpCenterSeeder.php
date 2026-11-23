<?php

namespace Database\Seeders;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Database\Seeder;

class HelpCenterSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'getting-started' => HelpCategory::updateOrCreate(
                ['slug' => 'getting-started'],
                ['name' => '新手指南', 'description' => '第一次使用德威云，从这里开始', 'icon' => 'rocket', 'sort_order' => 10, 'is_visible' => true]
            ),
            'purchase' => HelpCategory::updateOrCreate(
                ['slug' => 'purchase'],
                ['name' => '购买与开通', 'description' => '选购、下单、支付与实例开通', 'icon' => 'card', 'sort_order' => 20, 'is_visible' => true]
            ),
            'network' => HelpCategory::updateOrCreate(
                ['slug' => 'network'],
                ['name' => '网络与端口', 'description' => 'NAT、端口映射与网络排障', 'icon' => 'network', 'sort_order' => 30, 'is_visible' => true]
            ),
            'manage' => HelpCategory::updateOrCreate(
                ['slug' => 'manage'],
                ['name' => '连接与管理', 'description' => '远程连接、系统与日常运维', 'icon' => 'terminal', 'sort_order' => 40, 'is_visible' => true]
            ),
            'billing' => HelpCategory::updateOrCreate(
                ['slug' => 'billing'],
                ['name' => '账单与支持', 'description' => '续费、账单与工单支持', 'icon' => 'receipt', 'sort_order' => 50, 'is_visible' => true]
            ),
        ];

        $articles = [
            [
                'category' => 'purchase',
                'sort' => 10,
                'title' => '如何选购并开通一台云服务器',
                'slug' => 'how-to-buy-cloud-server',
                'summary' => '从注册登录、挑选配置到支付成功和查看开通结果，完整走一遍云服务器购买流程。',
                'cover' => 'assets/images/help/purchase-flow.svg',
                'body' => <<<'HTML'
<p>第一次购买云服务器时，最容易卡住的不是付款，而是不知道该选哪种产品、配置怎么填、付款后去哪里看机器。下面按实际控制台流程拆成五步，照着做即可完成开通。</p>

<figure>
    <img src="/assets/images/help/purchase-flow.svg" alt="云服务器购买与开通流程：选择产品、配置规格、下单支付、自动开通">
    <figcaption>云服务器购买与开通的四个核心步骤</figcaption>
</figure>

<h2>一、注册并登录账户</h2>
<ol class="hc-steps">
    <li><b>注册账户</b>打开官网右上角「免费注册」，使用常用邮箱完成注册，并按页面提示完成邮箱验证。</li>
    <li><b>登录控制台</b>注册完成后登录，进入「控制台」。购买、查看服务和提交工单都在这里完成。</li>
    <li><b>补充账户信息</b>如购买页面要求实名认证或补充资料，请先按提示完成，否则订单可能无法进入开通流程。</li>
</ol>

<h2>二、进入产品选购页</h2>
<p>从首页点击「产品选购」，或直接访问 <a href="/products">产品选购页</a>。左侧可以按产品分组筛选，也可以使用搜索框查找具体型号。</p>
<p>商品卡片会展示地区、配置、线路和月付价格。点击「立即购买」进入配置页面；如果不确定选独立 IP 还是 NAT 云主机，可以先阅读 <a href="/help/what-is-nat-cloud-server">NAT 云主机新手说明</a>。</p>

<h2>三、选择配置</h2>
<p>在配置页面确认以下信息。带「可选」的项目可以按业务需要调整，其余项目以商品默认配置为准。</p>

<table class="hc-table">
    <thead><tr><th>配置项</th><th>怎么选</th></tr></thead>
    <tbody>
        <tr><td>CPU / 内存</td><td>建站、面板类业务建议至少 2 核 4G；游戏服务、数据库等按并发和实际占用适当上调。</td></tr>
        <tr><td>系统盘 / 数据盘</td><td>系统盘用于操作系统和基础软件；业务数据较多时再增加数据盘。</td></tr>
        <tr><td>操作系统</td><td>不熟悉 Linux 可选择 Windows Server；建站常用 Debian / Ubuntu；请确认所选系统与业务软件兼容。</td></tr>
        <tr><td>带宽 / 流量</td><td>按访问量和下载需求选择。流量型产品需关注每月流量额度，避免超额。</td></tr>
        <tr><td>计费周期</td><td>月付适合短期测试，季付 / 年付通常更划算；请确认续费价格和到期时间。</td></tr>
        <tr><td>端口 / IP 类型</td><td>NAT 产品通过端口映射访问，独立 IP 产品直接使用公网 IPv4；按业务形态选择。</td></tr>
    </tbody>
</table>

<div class="hc-callout warn"><b>注意：</b>NAT 云主机不能像独立 IP 一样直接监听任意公网端口。购买前请确认所需外部端口在主控允许范围内，开通后再到控制台添加端口映射。</div>

<h2>四、确认订单并支付</h2>
<ol class="hc-steps">
    <li><b>核对订单</b>确认商品名称、地区、配置、计费周期和应付金额，优惠信息以提交订单时页面展示为准。</li>
    <li><b>选择支付方式</b>按页面支持的方式完成支付。使用账户余额时，请先确认余额充足。</li>
    <li><b>等待支付结果</b>支付成功后页面会跳转到订单结果。不要重复提交同一笔订单；如支付成功但订单未更新，可稍等片刻后刷新，或提交工单并附上订单号。</li>
</ol>

<h2>五、查看开通结果</h2>
<ol class="hc-steps">
    <li><b>进入服务列表</b>打开控制台「云服务器」，找到刚购买的服务，状态应显示为「运行中」。</li>
    <li><b>查看实例信息</b>在服务详情页查看公网地址、内网地址、操作系统、到期时间和远程连接信息。</li>
    <li><b>连接实例</b>Windows 实例可按 <a href="/help/connect-windows-server-rdp">远程桌面教程</a> 登录；NAT 实例需先添加端口映射。</li>
</ol>

<div class="hc-callout"><b>开通要多久？</b>支付成功后系统会自动下发开通任务，通常 1–5 分钟内完成。若遇到库存、镜像或人工审核，处理进度会在工单中同步。</div>

<h2>常见问题</h2>
<h3>支付成功但服务列表没有机器？</h3>
<p>先刷新服务列表；如果仍没有，请确认订单是否已支付成功，并在工单中提供订单号和支付时间，支持团队会协助核对。</p>
<h3>买错系统或配置还能改吗？</h3>
<p>部分配置支持在控制台升级或重装，具体以服务详情页可用操作为准。重装系统会清空系统盘数据，请先完成备份。</p>
<h3>到期后会立即删除吗？</h3>
<p>到期后通常先进入宽限期，宽限期结束会停机并进入保留 / 回收流程。请提前续费或备份数据，避免业务中断。</p>
HTML,
            ],
            [
                'category' => 'network',
                'sort' => 20,
                'title' => '如何添加端口映射并验证是否生效',
                'slug' => 'how-to-add-port-mapping',
                'summary' => 'NAT 云主机如何填写外部端口、内部端口与协议，并通过命令行验证映射是否真正连通。',
                'cover' => 'assets/images/help/port-mapping.svg',
                'body' => <<<'HTML'
<p>NAT 云主机没有独立公网 IPv4，需要通过端口映射把公网端口转发到实例的内网端口。本文以远程桌面 3389 为例，其他服务（网站 80/443、SSH 22、游戏端口等）操作方式相同。</p>

<figure>
    <img src="/assets/images/help/port-mapping.svg" alt="公网地址通过 NAT 网关转发到内网实例的端口映射示意">
    <figcaption>外部端口 → NAT 网关 → 内网 IP + 内部端口</figcaption>
</figure>

<h2>先理解两个端口</h2>
<table class="hc-table">
    <thead><tr><th>字段</th><th>含义</th><th>示例</th></tr></thead>
    <tbody>
        <tr><td>外部端口</td><td>客户从公网访问时使用的端口，必须符合主控允许的端口范围。</td><td>41252</td></tr>
        <tr><td>内部端口</td><td>实例内部服务实际监听的端口。远程桌面是 3389，SSH 是 22，网站通常是 80 / 443。</td><td>3389</td></tr>
        <tr><td>协议</td><td>根据服务选择 TCP、UDP 或 TCP + UDP。远程桌面使用 TCP，部分游戏需要 UDP。</td><td>TCP + UDP</td></tr>
    </tbody>
</table>

<h2>添加映射的步骤</h2>
<ol class="hc-steps">
    <li><b>确认实例状态</b>登录控制台，进入「云服务器」，确认实例状态为「运行中」，并确认它是 NAT 云主机。</li>
    <li><b>打开服务详情</b>点击对应实例进入详情页，找到「端口映射」区域。页面会显示当前公网 IP 和端口段。</li>
    <li><b>填写映射信息</b>输入外部端口、内部端口，选择协议。内部 IP 由主控自动分配，通常不需要手动填写。</li>
    <li><b>提交并等待下发</b>点击「添加映射」，状态会先显示为处理中，主控下发完成后变为「运行中」。</li>
    <li><b>验证连接</b>使用公网地址 + 外部端口连接实例。如果连接失败，先检查实例内部服务是否已启动、系统防火墙是否放行。</li>
</ol>

<div class="hc-callout warn"><b>不要占用主控保留端口。</b>外部端口必须在服务详情页显示的端口段内，并避开主控保留端口。提交时如提示端口不合法或已被占用，请更换一个端口。</div>

<h2>验证映射是否生效</h2>
<p>在本地电脑打开终端（Windows 可使用 PowerShell），测试端口是否可连通：</p>
<pre><code># Windows PowerShell
Test-NetConnection p7.deviy.cn -Port 41252

# macOS / Linux
nc -vz p7.deviy.cn 41252</code></pre>
<p>显示连接成功、端口开放或 <code>succeeded</code> 表示网络层已连通。若端口通但业务仍不可用，通常是实例内部服务未启动或系统防火墙未放行。</p>

<h2>常见问题排查</h2>
<table class="hc-table">
    <thead><tr><th>现象</th><th>可能原因</th><th>处理</th></tr></thead>
    <tbody>
        <tr><td>添加后一直处理中</td><td>主控下发有延迟或目标端口冲突</td><td>等待 1–2 分钟后刷新；仍无变化时提交工单，附实例编号和端口。</td></tr>
        <tr><td>端口测试不通</td><td>实例关机、内部端口错误、系统防火墙未放行</td><td>确认实例运行中，核对内部端口，并在系统内检查防火墙规则。</td></tr>
        <tr><td>连接被拒绝</td><td>内部服务没有监听该端口</td><td>启动对应服务，确认监听地址为 0.0.0.0 或实例内网地址。</td></tr>
        <tr><td>游戏能登录但语音 / 联机异常</td><td>只映射了 TCP，缺少 UDP</td><td>新增或修改映射，协议选择 TCP + UDP。</td></tr>
    </tbody>
</table>

<div class="hc-callout"><b>安全建议：</b>只开放业务真正需要的端口，不要长期暴露数据库、管理后台等敏感服务；使用强密码并定期更新系统补丁。</div>
HTML,
            ],
            [
                'category' => 'manage',
                'sort' => 30,
                'title' => '如何用远程桌面连接 Windows 云服务器',
                'slug' => 'connect-windows-server-rdp',
                'summary' => '从服务详情页获取地址、账号和密码，在 Windows 或 macOS 上完成首次远程桌面登录。',
                'cover' => 'assets/images/help/rdp-connect.svg',
                'body' => <<<'HTML'
<p>Windows 云服务器开通后，可以通过远程桌面（RDP）连接。连接前需要准备三样信息：公网地址、管理员账号和密码，这些都可以在服务详情页找到。</p>

<figure>
    <img src="/assets/images/help/rdp-connect.svg" alt="Windows 远程桌面连接窗口，填写公网地址、管理员账号和密码">
    <figcaption>远程桌面连接需要地址、账号和密码</figcaption>
</figure>

<h2>连接前的检查</h2>
<ul>
    <li>实例状态为「运行中」。</li>
    <li>如果是 NAT 云主机，已经添加了 3389 的端口映射，并且状态为「运行中」。</li>
    <li>服务详情页中的连接地址、端口、用户名和密码完整可用。</li>
</ul>

<h2>Windows 电脑连接步骤</h2>
<ol class="hc-steps">
    <li><b>打开远程桌面连接</b>在开始菜单搜索「远程桌面连接」并打开；也可以按 <code>Win + R</code> 输入 <code>mstsc</code> 回车。</li>
    <li><b>填写连接地址</b>在「计算机」一栏输入服务详情页的地址。NAT 实例需要带端口，例如 <code>p7.deviy.cn:41252</code>；独立 IP 通常直接填 IP 地址。</li>
    <li><b>输入账号密码</b>点击连接后，用户名填写 <code>Administrator</code>，密码粘贴服务详情页中的密码。</li>
    <li><b>确认证书提示</b>首次连接可能出现证书不受信任的提示，确认地址无误后选择继续即可。</li>
    <li><b>进入桌面</b>连接成功后即可看到 Windows 桌面，可以开始安装软件和配置业务。</li>
</ol>

<h2>macOS 连接方式</h2>
<p>在 App Store 安装「Microsoft Remote Desktop」，添加 PC 时填写服务详情页的地址和端口，再输入管理员账号和密码。若无法连接，请确认地址中的冒号和端口没有遗漏。</p>

<h2>连接失败怎么办</h2>
<table class="hc-table">
    <thead><tr><th>提示 / 现象</th><th>排查方向</th></tr></thead>
    <tbody>
        <tr><td>无法连接到远程计算机</td><td>确认实例运行中、端口映射状态正常，并用 <code>Test-NetConnection</code> 测试端口。</td></tr>
        <tr><td>登录失败，用户名或密码错误</td><td>重新从服务详情页复制密码，注意大小写和首尾空格；不要手动猜测密码。</td></tr>
        <tr><td>能连上但马上断开</td><td>检查系统远程桌面是否开启、是否达到最大连接数，或实例资源是否耗尽。</td></tr>
        <tr><td>连接超时</td><td>检查本地网络、公司防火墙是否拦截了该外部端口；尝试切换网络测试。</td></tr>
    </tbody>
</table>

<div class="hc-callout warn"><b>安全提示：</b>不要把远程桌面账号密码发送到公开群聊或工单正文。若怀疑密码泄露，请及时在控制台修改密码，并检查实例登录日志。</div>
HTML,
            ],
            [
                'category' => 'getting-started',
                'sort' => 40,
                'title' => 'NAT 云主机新手说明：独立 IP 与端口映射',
                'slug' => 'what-is-nat-cloud-server',
                'summary' => '搞清楚独立 IP、NAT 云主机、公网端口与内网端口的关系，再决定买哪种产品。',
                'cover' => 'assets/images/help/nat-explained.svg',
                'body' => <<<'HTML'
<p>选购云服务器时，经常会看到「独立 IP」和「NAT 云主机」两种类型。它们都能运行完整的操作系统，主要差别在公网地址的使用方式。</p>

<figure>
    <img src="/assets/images/help/nat-explained.svg" alt="独立 IP 与 NAT 云主机访问方式对比">
    <figcaption>独立 IP 独享公网地址；NAT 云主机共享公网 IP，通过端口区分实例</figcaption>
</figure>

<h2>一张表看懂区别</h2>
<table class="hc-table">
    <thead><tr><th>对比项</th><th>独立 IP 云服务器</th><th>NAT 云主机</th></tr></thead>
    <tbody>
        <tr><td>公网地址</td><td>独享一个公网 IPv4</td><td>多个实例共享出口公网 IP</td></tr>
        <tr><td>访问方式</td><td>直接使用公网 IP + 服务端口</td><td>公网 IP + 外部端口，经 NAT 转发到内网</td></tr>
        <tr><td>端口限制</td><td>可监听业务需要的端口</td><td>外部端口需在主控允许范围内，并按配额添加映射</td></tr>
        <tr><td>域名解析</td><td>可直接把域名 A 记录指向公网 IP</td><td>可解析到共享 IP，通过端口访问；更适合端口型服务</td></tr>
        <tr><td>适用场景</td><td>建站、需要独立地址的业务</td><td>游戏联机、远程桌面、测试环境等成本敏感场景</td></tr>
    </tbody>
</table>

<h2>NAT 端口映射是怎么工作的</h2>
<p>假设实例的内网地址是 <code>192.168.122.11</code>，远程桌面监听 <code>3389</code>，你添加了一条外部端口 <code>41252</code> 的映射。客户访问 <code>p7.deviy.cn:41252</code> 时，NAT 网关会把流量转发到 <code>192.168.122.11:3389</code>。</p>
<p>因此，外部端口可以和服务端口不同，但内部端口必须与实例内实际监听的端口一致。映射的添加方法见 <a href="/help/how-to-add-port-mapping">端口映射教程</a>。</p>

<h2>购买前的三个确认</h2>
<ol class="hc-steps">
    <li><b>确认业务需要的端口</b>例如远程桌面 3389、SSH 22、网站 80/443、游戏端口等，并确认这些端口在允许范围内。</li>
    <li><b>确认端口数量</b>不同 NAT 商品包含的端口配额不同，需要同时开放多个服务时请提前核对。</li>
    <li><b>确认访问方式</b>如果业务必须使用独立公网 IP（例如某些回调、邮件或特殊网络场景），应选择独立 IP 产品。</li>
</ol>

<div class="hc-callout"><b>建议：</b>第一次使用可以先购买短周期测试，确认 NAT 端口、系统和业务软件都能正常配合后，再决定是否长期续费。</div>
HTML,
            ],
        ];

        foreach ($articles as $item) {
            HelpArticle::updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'help_category_id' => $categories[$item['category']]->id,
                    'title' => $item['title'],
                    'summary' => $item['summary'],
                    'body' => $item['body'],
                    'cover_image' => $item['cover'],
                    'sort_order' => $item['sort'],
                    'is_published' => true,
                    'published_at' => now(),
                ]
            );
        }
    }
}
