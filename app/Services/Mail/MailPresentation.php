<?php

namespace App\Services\Mail;

use App\Models\NotificationTemplate;
use App\Models\User;

class MailPresentation
{
    /** @return array<string, array<string, string>> */
    public static function definitions(): array
    {
        return [
            'email_verification' => ['label' => '邮箱安全验证', 'subject' => '德威云｜你的邮箱验证码', 'title' => '验证邮箱，安全继续', 'eyebrow' => 'ACCOUNT SECURITY', 'accent' => '#315df4', 'rule' => '注册或邮箱验证时发送；验证码有效期沿用验证码服务。', 'body' => "你好，{{customer}}：\n请使用下方验证码完成本次邮箱验证。验证码 {{expires_minutes}} 分钟内有效，请勿向任何人提供。\n如果这不是你发起的操作，请忽略本邮件。德威云工作人员不会索取你的验证码。"],
            'account_registered' => ['label' => '账户注册欢迎', 'subject' => '欢迎来到德威云，{{customer}}', 'title' => '欢迎开启你的云上工作空间', 'eyebrow' => 'WELCOME ABOARD', 'accent' => '#315df4', 'rule' => '账户注册成功后发送一次。', 'body' => "你好，{{customer}}：\n你的德威云账户已创建，登录邮箱为 {{email}}。\n你可以在控制台选购云资源、查看账单、管理服务和提交工单。首次使用建议完成邮箱验证与实名认证，并核对联系方式。\n请妥善保管登录凭据，勿与他人共享账户。"],
            'service_provisioned' => ['label' => '资源开通成功', 'subject' => '德威云｜{{service_name}} 已开通', 'title' => '资源已就绪，即刻开始', 'eyebrow' => 'RESOURCE READY', 'accent' => '#168069', 'rule' => '对应平台确认资源开通成功后发送；开通请求提交不视为成功。', 'body' => "你好，{{customer}}：\n{{service_name}}（{{service_no}}）已经开通。\n请进入资源详情查看连接方式、套餐配置和真实到期时间；CDN、游戏面板和托管产品以各自详情页提供的信息为准。\n首次使用建议检查访问权限、设置安全密码，并为重要数据制定备份计划。邮件中不会发送服务器密码。"],
            'service_due' => ['label' => '产品即将到期', 'subject' => '德威云｜{{service_name}} 将于 {{date}} 到期', 'title' => '提前续费，让业务安心运行', 'eyebrow' => 'RENEWAL REMINDER', 'accent' => '#b56a16', 'rule' => '按真实到期时间，前 7、3、1 天及当天上午 09:00 检查；同一到期时间每个节点仅提醒一次。', 'body' => "你好，{{customer}}：\n你的服务 {{service_name}}（{{service_no}}）将于 {{date}} 到期，{{due_description}}。\n请提前查看续费周期与金额，并在订单中完成付款。若已付款但日期尚未更新，请先查看订单处理状态，避免重复支付。\n托管资源续费请联系机房或提交工单。自动续费仍受余额和服务状态限制，请以控制台显示的续费结果为准。"],
            'service_expired' => ['label' => '产品到期提醒', 'subject' => '德威云｜{{service_name}} 已到期，请核对续费', 'title' => '服务已到期，请及时处理', 'eyebrow' => 'SERVICE EXPIRATION', 'accent' => '#c33a55', 'rule' => '真实到期时间已过且不超过 1 天时提醒一次；不重复追发历史过期记录。', 'body' => "你好，{{customer}}：\n{{service_name}}（{{service_no}}）的登记到期时间为 {{date}}，目前已超过该时间。\n请尽快在控制台核对服务状态与续费订单。不同产品的停用与保留规则可能不同，本邮件不代表数据仍在保留期内。\n若已完成续费但日期未更新，请通过工单提供订单编号，我们会协助核对。"],
            'payment_succeeded' => ['label' => '订单支付成功', 'subject' => '德威云｜订单 {{order_no}} 支付成功', 'title' => '付款已确认，进度随时可查', 'eyebrow' => 'PAYMENT CONFIRMED', 'accent' => '#168069', 'rule' => '订单付款确认并提交事务后发送；重复支付回调不重复通知。', 'body' => "你好，{{customer}}：\n订单 {{order_no}} 已完成支付，确认金额为 {{amount}}。\n支付成功后，新购服务仍需完成开通，续费仍需完成资源平台确认，流量包仍需等待套餐容量更新。请在订单详情查看交付进度。\n若订单显示待处理，请勿重复购买或重复支付；如需协助，请在工单中提供该订单编号。"],
            'recharge_succeeded' => ['label' => '账户充值到账', 'subject' => '德威云｜充值 {{payment_no}} 已到账', 'title' => '充值已到账，资金明细清晰可见', 'eyebrow' => 'BALANCE UPDATED', 'accent' => '#168069', 'rule' => '充值入账和余额流水写入成功后发送一次；失败、未支付充值不触发。', 'body' => "你好，{{customer}}：\n充值单 {{payment_no}} 已确认到账，充值金额为 {{amount}}。\n充值增加账户可用余额，不会自动支付未付款订单。购买或续费时请在收银台选择余额支付，并确认订单结果。\n你可以进入充值与资金记录核对本次流水。"],
            'service_suspended' => ['label' => '服务暂停处理', 'subject' => '德威云｜{{service_name}} 暂停处理通知', 'title' => '服务需要处理，请查看状态', 'eyebrow' => 'SERVICE ATTENTION', 'accent' => '#c33a55', 'rule' => '生命周期任务因到期发起暂停时通知；实际资源状态以详情页为准。', 'body' => "你好，{{customer}}：\n{{service_name}}（{{service_no}}）因到期未续费进入暂停处理。\n请在资源详情核对实际状态，并查看是否可续费恢复。暂停、数据保留与最终回收规则以具体产品约定为准。\n若你已付款或对状态有疑问，请提供服务编号与订单号联系支持。"],
            'ticket_reply' => ['label' => '工单回复提醒', 'subject' => '德威云｜工单 {{ticket_no}} 有新回复', 'title' => '支持团队已回复你的工单', 'eyebrow' => 'SUPPORT UPDATE', 'accent' => '#6650c8', 'rule' => '管理员发布客户可见回复时发送；内部备注不发送。', 'body' => "你好，{{customer}}：\n工单 {{ticket_no}}「{{ticket_subject}}」有新的客户可见回复。\n请进入工单查看完整内容与附件，并在原工单中补充信息，以便支持团队保持处理上下文。\n为保护账户与资源安全，邮件不包含工单附件和敏感连接凭据。"],
            'email_test' => ['label' => '邮件通道测试', 'subject' => '德威云｜邮件通道测试成功', 'title' => '通知通道已经连通', 'eyebrow' => 'DELIVERY CHECK', 'accent' => '#315df4', 'rule' => '管理员主动发送测试邮件时触发。', 'body' => "你好，{{customer}}：\n这是一封由德威云后台发起的邮件通道测试。收到本邮件表示 SMTP 已接受并送达这次测试消息。\n建议同时检查桌面与手机邮件客户端的正文、按钮和深色模式，并确认发件人显示符合预期。\n业务通知还会受到模板开关、通知偏好与免打扰设置影响。"],
            'password_reset' => ['label' => '密码重设链接', 'subject' => '德威云｜重设你的账户密码', 'title' => '确认身份后设置新密码', 'eyebrow' => 'PASSWORD RECOVERY', 'accent' => '#6650c8', 'rule' => '访客通过邮箱、点选验证与频率限制后申请发送；链接有效期为 60 分钟，只能使用一次。', 'body' => "你好，{{customer}}：\n我们收到一项为你的德威云账户重设密码的请求。请使用下方的安全链接继续设置新密码。链接 {{expires_minutes}} 分钟内有效，完成后只能使用一次。\n如果这不是你本人发起的请求，请忽略本邮件。你的当前密码不会因此改变。德威云工作人员不会索取你的密码或重设链接。"],
            'password_changed' => ['label' => '密码变更确认', 'subject' => '德威云｜账户密码已更新', 'title' => '账户密码已安全更新', 'eyebrow' => 'SECURITY CONFIRMATION', 'accent' => '#168069', 'rule' => '密码重设成功后发送；旧登录状态同时失效。', 'body' => "你好，{{customer}}：\n你的账户密码已于 {{changed_at}} 完成重设。为保护账户安全，原有登录状态已失效，请使用新密码重新登录。\n如果这不是你发起的操作，请立即联系 admin@deviy.cn，并说明账户邮箱与操作时间。请勿在邮件中发送密码、验证码或证件照片。"],
        ];
    }

    /** @param array<string, mixed> $data @return array{subject: string, body: string} */
    public static function render(NotificationTemplate $template, User $user, array $data = []): array
    {
        $definition = self::definitions()[$template->key] ?? ['title' => '你的云服务有一条新消息', 'eyebrow' => 'SERVICE NOTIFICATION', 'accent' => '#315df4'];
        $variables = array_merge([
            'customer' => (string) $user->name, 'email' => (string) $user->email, 'sign' => '德威云计算',
            'name' => '', 'service_name' => '', 'service_no' => '', 'date' => '请以控制台为准', 'days' => '',
            'due_description' => '请及时核对续费安排', 'amount' => '请查看账务记录', 'order_no' => '',
            'payment_no' => '', 'ticket_no' => '', 'ticket_subject' => '', 'code' => '', 'expires_minutes' => '',
        ], $data);
        $plain = [];
        $escaped = [];
        foreach ($variables as $key => $value) {
            if (! is_scalar($value) && $value !== null) {
                continue;
            }
            $plain['{{'.$key.'}}'] = (string) $value;
            $escaped['{{'.$key.'}}'] = e((string) $value);
        }
        $content = (string) $template->body;
        $content = strip_tags($content) === $content ? nl2br(e(strtr($content, $plain))) : strtr($content, $escaped);
        $base = rtrim((string) config('app.url'), '/');
        $action = match ($template->key) {
            'account_registered' => ['进入账户', $data['login_url'] ?? route('login')],
            'password_reset' => ['安全重设密码', $data['reset_url'] ?? url('/forgot-password')],
            'password_changed' => ['重新登录', $data['login_url'] ?? route('login')],
            'payment_succeeded' => ['查看订单进度', $data['order_url'] ?? route('console.orders.index')],
            'recharge_succeeded' => ['查看充值记录', route('console.recharge.index')],
            'ticket_reply' => ['查看工单回复', $data['ticket_url'] ?? route('console.tickets.index')],
            default => ['进入资源控制台', $data['console_url'] ?? route('console.dashboard')],
        };
        if (! str_starts_with((string) $action[1], $base.'/')) {
            $action[1] = route('console.dashboard');
        }
        $facts = [];
        $factKeys = match ($template->key) {
            'service_provisioned', 'service_due', 'service_expired', 'service_suspended' => ['service_name', 'service_no', 'date'],
            'payment_succeeded' => ['order_no', 'amount'],
            'recharge_succeeded' => ['payment_no', 'amount'],
            'ticket_reply' => ['ticket_no'],
            'password_changed' => ['changed_at'],
            default => [],
        };
        foreach (['service_name' => '服务名称', 'service_no' => '服务编号', 'date' => '到期时间', 'order_no' => '订单编号', 'payment_no' => '充值编号', 'amount' => '确认金额', 'ticket_no' => '工单编号'] as $key => $label) {
            if (in_array($key, $factKeys, true) && ! empty($data[$key])) {
                $facts[$label] = (string) $data[$key];
            }
        }

        return [
            'subject' => preg_replace('/[\r\n]+/', ' ', strtr((string) ($template->subject ?: $definition['title']), $plain)),
            'body' => view('emails.notification', ['definition' => $definition, 'content' => $content, 'facts' => $facts, 'action' => $action, 'code' => $template->key === 'email_verification' ? (string) $variables['code'] : '', 'expiresMinutes' => (string) $variables['expires_minutes'], 'unsubscribeUrl' => (string) ($data['unsubscribe_url'] ?? ''), 'siteUrl' => $base])->render(),
        ];
    }
}
