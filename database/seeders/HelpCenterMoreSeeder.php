<?php

namespace Database\Seeders;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Database\Seeder;

class HelpCenterMoreSeeder extends Seeder
{
    public function run(): void
    {
        $billing = HelpCategory::query()->where('slug', 'billing')->firstOrFail();
        $manage = HelpCategory::query()->where('slug', 'manage')->firstOrFail();

        $articles = [
            [
                'category_id' => $billing->id,
                'sort' => 50,
                'title' => '如何续费、查看账单和申请发票',
                'slug' => 'renew-bills-invoices',
                'summary' => '到期前如何续费、开启自动续费，以及在哪里查看订单、余额流水和申请电子发票。',
                'cover' => 'assets/images/help/billing-renew.svg',
                'body' => <<<'HTML'
<p>服务到期前及时续费，可以避免停机与数据回收。本文把续费、账单和发票三个环节放在一起说明，方便你按顺序处理。</p>

<figure>
    <img src="/assets/images/help/billing-renew.svg" alt="到期提醒、续费支付、账单与发票三个环节">
    <figcaption>续费、账单与发票的完整路径</figcaption>
</figure>

<h2>一、先确认到期时间</h2>
<ol class="hc-steps">
    <li><b>进入服务列表</b>登录控制台，打开「云服务器」，列表会展示每台实例的到期时间。</li>
    <li><b>查看服务详情</b>进入具体实例，在「到期时间」区域确认日期；如果已开启自动续费，也会在这里显示状态。</li>
    <li><b>提前处理</b>建议在到期前 3–7 天完成续费，避免支付延迟或余额不足导致停机。</li>
</ol>

<h2>二、续费服务</h2>
<ol class="hc-steps">
    <li><b>选择续费周期</b>在服务详情页点击「续费」，选择月付、季付、半年付或年付。续费时长会从当前到期日顺延。</li>
    <li><b>确认金额</b>核对续费周期、价格和优惠信息，确认无误后创建续费订单。</li>
    <li><b>完成支付</b>使用账户余额或页面支持的在线方式支付。支付成功后回到服务详情页刷新，确认到期时间已更新。</li>
</ol>

<div class="hc-callout"><b>自动续费：</b>开启后系统会在到期前尝试生成续费订单并扣费，但仍需保证账户余额充足。自动续费失败不代表服务不会到期，请同时关注通知和到期时间。</div>

<h2>三、查看账单与余额流水</h2>
<p>控制台「订单」中可以查看订单号、商品、金额和支付状态；「账单与发票」中可查看余额变动和消费记录。</p>
<ul>
    <li><a href="/console/orders">查看订单</a>：核对订单是否支付成功、是否重复下单。</li>
    <li><a href="/console/invoices">账单与发票</a>：查看消费明细和发票申请进度。</li>
    <li><a href="/console/recharge">账户充值</a>：余额不足时先充值，再完成续费或新购。</li>
</ul>

<h2>四、申请发票</h2>
<ol class="hc-steps">
    <li><b>准备开票信息</b>个人抬头填写姓名；企业抬头填写公司全称、税号，并确认接收邮箱。</li>
    <li><b>提交申请</b>在「账单与发票」中选择可开票的订单或金额，填写信息后提交。</li>
    <li><b>等待开具</b>财务审核后生成电子发票，可在原页面下载 PDF。信息有误时请及时提交工单说明。</li>
</ol>

<div class="hc-callout warn"><b>注意：</b>退款、余额和发票的处理规则以订单页面和实际支付情况为准。不要重复支付同一笔续费订单；如支付成功但到期时间未更新，请提交工单并附订单号。</div>

<h2>常见问题</h2>
<h3>续费后到期时间没有变化？</h3>
<p>先刷新页面；若订单已支付但服务未顺延，请提供订单号和实例编号，让支持团队核对。</p>
<h3>可以只续费一个月吗？</h3>
<p>可选周期以续费页面展示为准；页面没有的周期暂时无法直接选择。</p>
HTML,
            ],
            [
                'category_id' => $manage->id,
                'sort' => 31,
                'title' => '如何重装系统、重置密码和查看实例状态',
                'slug' => 'reinstall-system-reset-password',
                'summary' => '重装系统前要备份什么、如何重置远程密码，以及如何通过状态和监控判断实例是否正常。',
                'cover' => 'assets/images/help/reinstall-password.svg',
                'body' => <<<'HTML'
<p>重装系统、修改密码和查看状态都属于高频操作。它们看起来简单，但重装和改密会直接影响现有业务，执行前请确认操作对象和影响范围。</p>

<figure>
    <img src="/assets/images/help/reinstall-password.svg" alt="重装系统、重置密码和查看实例状态三个常用操作">
    <figcaption>常用系统操作集中在服务详情页</figcaption>
</figure>

<h2>一、重装系统前先备份</h2>
<p>重装会清空系统盘并重新安装操作系统，网站文件、数据库和配置都会被覆盖。请先完成以下准备：</p>
<ul>
    <li>导出网站文件和数据库，保存到本地或其他存储。</li>
    <li>记录当前系统版本、软件版本、端口和防火墙规则。</li>
    <li>确认业务可以接受短暂停机，并尽量安排在低峰期。</li>
</ul>
<p>进入服务详情页，找到「重装系统」，选择目标镜像并阅读页面提示。提交后实例会重启并进入重装流程，期间不要重复提交。</p>

<div class="hc-callout warn"><b>不可逆：</b>重装系统通常无法恢复原系统盘数据。数据盘是否保留以具体商品和页面提示为准，不要假设数据盘一定不受影响。</div>

<h2>二、重置远程密码</h2>
<ol class="hc-steps">
    <li><b>打开远程连接区域</b>在服务详情页找到「远程连接」和密码管理入口。</li>
    <li><b>设置新密码</b>输入符合要求的新密码并确认。建议使用大小写字母、数字和符号组合，不要与其他网站共用。</li>
    <li><b>重新连接</b>修改成功后旧密码立即失效，请使用新密码重新登录；如果连接失败，先确认账号和端口是否正确。</li>
</ol>

<h2>三、查看实例状态与监控</h2>
<p>服务详情页会展示运行状态、CPU、内存、磁盘和带宽趋势。出现访问慢、连接断开时，可以先看监控判断是资源打满、带宽跑高，还是实例本身异常。</p>
<ul>
    <li>状态为「运行中」才表示实例处于开机状态。</li>
    <li>CPU / 内存长期接近上限时，考虑优化程序或调整配置。</li>
    <li>带宽持续跑满时，检查是否有大流量下载、攻击或异常进程。</li>
</ul>
<p>需要更完整的数据时，点击「资源监控」进入独立页面，切换时间范围查看趋势。</p>

<h2>四、操作后的检查清单</h2>
<ol class="hc-steps">
    <li>确认实例状态恢复为「运行中」。</li>
    <li>用新的密码或系统重新连接，检查业务端口是否正常。</li>
    <li>检查网站、数据库、计划任务和防火墙规则是否恢复。</li>
    <li>确认监控无异常后，再恢复对外服务。</li>
</ol>

<div class="hc-callout"><b>提示：</b>如果重装后无法连接，先确认端口映射是否仍然存在、系统防火墙是否放行，再提交工单并附实例编号和报错截图。</div>
HTML,
            ],
            [
                'category_id' => $manage->id,
                'sort' => 32,
                'title' => '如何做好数据备份与恢复',
                'slug' => 'backup-and-restore-data',
                'summary' => '重装、迁移或故障前，如何备份网站文件和数据库，并验证备份真的可以恢复。',
                'cover' => 'assets/images/help/backup-snapshot.svg',
                'body' => <<<'HTML'
<p>磁盘冗余、快照和备份解决的是不同问题。对重要业务来说，最可靠的做法是保留一份可以独立恢复的备份，并定期验证它真的能恢复。</p>

<figure>
    <img src="/assets/images/help/backup-snapshot.svg" alt="实例数据创建快照并用于恢复或迁移">
    <figcaption>备份的核心不是“有文件”，而是“能恢复”</figcaption>
</figure>

<h2>一、需要备份什么</h2>
<table class="hc-table">
    <thead><tr><th>对象</th><th>内容</th><th>建议方式</th></tr></thead>
    <tbody>
        <tr><td>网站文件</td><td>程序、主题、插件、上传附件、配置文件</td><td>打包压缩后下载到本地或其他存储</td></tr>
        <tr><td>数据库</td><td>业务数据、用户、订单、配置表</td><td>使用数据库导出工具生成 SQL 文件</td></tr>
        <tr><td>系统配置</td><td>Nginx / Apache、计划任务、防火墙、环境变量</td><td>导出配置文件或记录变更清单</td></tr>
        <tr><td>证书与密钥</td><td>SSL 证书、应用密钥</td><td>加密保存，避免公开分享</td></tr>
    </tbody>
</table>

<h2>二、备份网站文件</h2>
<pre><code># Linux 示例：打包网站目录
tar -czf website-$(date +%Y%m%d).tar.gz /www/wwwroot/your-site

# 下载到本地后，至少保留两份不同日期的备份</code></pre>
<p>Windows 实例可以直接把网站目录复制到其他磁盘，再通过远程桌面下载；重要文件建议同时上传到对象存储或另一台服务器。</p>

<h2>三、备份数据库</h2>
<pre><code># MySQL 示例
mysqldump -u用户名 -p 数据库名 > backup-$(date +%Y%m%d).sql

# 恢复前先导入测试库验证，不要直接覆盖生产库</code></pre>
<p>导出后检查文件大小是否正常，并随机打开确认包含表结构和数据。只有“导出成功”的提示还不够，必须实际做过一次恢复演练。</p>

<h2>四、快照与异地备份</h2>
<p>部分云产品支持快照或备份功能，入口以服务详情页实际展示为准。快照适合在重装、升级等变更前创建还原点，但不应当作为唯一的备份方式：</p>
<ul>
    <li>快照通常与实例在同一平台，平台级故障时可能同时不可用。</li>
    <li>数据库建议额外保留逻辑导出文件，便于迁移和单表恢复。</li>
    <li>重要业务至少保留一份异地或离线备份。</li>
</ul>

<h2>五、恢复演练清单</h2>
<ol class="hc-steps">
    <li><b>准备测试环境</b>不要在唯一的生产实例上直接试恢复。</li>
    <li><b>恢复文件与数据库</b>按先文件、后数据库的顺序恢复，注意目录权限和数据库版本。</li>
    <li><b>检查业务</b>登录后台、打开关键页面、测试下单或登录流程。</li>
    <li><b>记录恢复时间</b>评估备份频率是否足够，并更新备份计划。</li>
</ol>

<div class="hc-callout warn"><b>不要只备份到同一块磁盘。</b>实例磁盘损坏、误删或重装时，同盘备份会一起丢失。至少保留一份独立存储，并定期检查备份任务是否仍在运行。</div>
HTML,
            ],
            [
                'category_id' => $billing->id,
                'sort' => 51,
                'title' => '如何提交工单并快速获得技术支持',
                'slug' => 'how-to-submit-support-ticket',
                'summary' => '工单应该写哪些信息、如何选择优先级，以及提交后如何跟进，减少反复确认。',
                'cover' => 'assets/images/help/support-ticket.svg',
                'body' => <<<'HTML'
<p>支持团队处理问题时，最需要的是可定位的信息。把实例、现象、时间和已排查内容一次说清楚，通常能明显缩短处理时间。</p>

<figure>
    <img src="/assets/images/help/support-ticket.svg" alt="工单内容示例与处理流程">
    <figcaption>一条信息完整的工单应包含实例、现象、时间和排查记录</figcaption>
</figure>

<h2>一、什么时候提交工单</h2>
<ul>
    <li>实例无法开机、连接不上，或控制台操作失败。</li>
    <li>端口映射提交后长时间未生效。</li>
    <li>订单已支付但服务未开通、续费未到账。</li>
    <li>账单、发票或实名信息需要人工核对。</li>
    <li>教程无法覆盖的配置和故障问题。</li>
</ul>

<h2>二、工单内容怎么写</h2>
<table class="hc-table">
    <thead><tr><th>信息</th><th>示例</th></tr></thead>
    <tbody>
        <tr><td>实例编号</td><td>SVC-202611-XXXXXX</td></tr>
        <tr><td>问题现象</td><td>外部端口 41252 连接超时，内部 3389 正常监听</td></tr>
        <tr><td>发生时间</td><td>2026-11-23 16:30 左右开始</td></tr>
        <tr><td>已排查内容</td><td>实例运行中、端口映射运行中、系统防火墙已放行</td></tr>
        <tr><td>附件</td><td>报错截图、订单号、命令输出（隐去密码和密钥）</td></tr>
    </tbody>
</table>

<h2>三、提交与跟进</h2>
<ol class="hc-steps">
    <li><b>进入工单页面</b>登录控制台，打开「提交工单」，选择最接近的业务类型。</li>
    <li><b>选择优先级</b>普通咨询选择「中」或「低」；业务已中断、无法访问选择「高」，并在标题中写明影响。</li>
    <li><b>提交并等待回复</b>支持团队会在工单中回复，补充信息时直接在同一工单跟进，不要重复开新工单。</li>
    <li><b>确认结果</b>问题恢复后回复确认，便于支持团队关闭工单和记录处理结果。</li>
</ol>

<div class="hc-callout warn"><b>安全提醒：</b>不要在工单中发送完整登录密码、服务器密码、私钥或身份证照片。需要提供账号信息时，只提供必要的用户名或实例编号。</div>

<h2>四、紧急故障怎么更快</h2>
<p>如果业务已经中断，请在标题中体现「业务中断」和影响范围，并附上最近一次变更（重装、改配置、加端口等）。支持人员可以优先判断是否为变更引起的问题。</p>
<p>同时可以先做这些基础检查：实例是否运行中、端口映射是否生效、系统防火墙是否放行、账户余额和订单状态是否正常。</p>
HTML,
            ],
        ];

        foreach ($articles as $item) {
            HelpArticle::firstOrCreate(
                ['slug' => $item['slug']],
                [
                    'help_category_id' => $item['category_id'],
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
