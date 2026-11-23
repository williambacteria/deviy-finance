<?php

namespace App\Support;

final class AdminSettings
{
    /** @return array<string, array<string, array<string, mixed>>> */
    public static function fields(): array
    {
        return [
            'site' => [
                'site_name' => ['label' => '站点名称', 'default' => '德威云计算', 'rules' => ['required', 'string', 'max:80'], 'help' => '用于账单抬头和站点标识。'],
                'site_slogan' => ['label' => '站点宣传语', 'default' => '稳定、透明、可长期依赖的云服务', 'rules' => ['required', 'string', 'max:160'], 'help' => '显示在网页标题和账单页。'],
                'site_url' => ['label' => '官网地址', 'default' => 'https://www.deviy.cn', 'rules' => ['required', 'url:https', 'max:255'], 'help' => '用于账单联系信息；域名与支付回调另由部署配置管理。'],
                'support_email' => ['label' => '客服邮箱', 'default' => 'admin@deviy.cn', 'rules' => ['required', 'email', 'max:190'], 'help' => '官网联系入口、账单和财务告警使用此邮箱。'],
                'support_qq_url' => ['label' => '在线咨询链接', 'default' => 'https://qm.qq.com/cgi-bin/qm/qr?k=854124946', 'rules' => ['required', 'url:https', 'max:500'], 'help' => '显示为添加好友入口，前台不展示 QQ 号码。'],
                'icp_number' => ['label' => 'ICP备案号', 'default' => '蜀ICP备2024089086号', 'rules' => ['nullable', 'string', 'max:80'], 'help' => '在官网底部显示并链接工信部查询。'],
            ],
            'billing' => [
                'order_prefix' => ['label' => '订单编号前缀', 'default' => 'ORD', 'rules' => ['required', 'regex:/^[A-Z][A-Z0-9]{1,7}$/D'], 'help' => '仅影响保存后新创建的订单，不修改历史编号。'],
                'invoice_prefix' => ['label' => '账单编号前缀', 'default' => 'INV', 'rules' => ['required', 'regex:/^[A-Z][A-Z0-9]{1,7}$/D'], 'help' => '仅影响新账单；人民币为本站结算币种。'],
                'grace_days' => ['label' => '到期停机宽限期（天）', 'default' => 3, 'type' => 'number', 'rules' => ['required', 'integer', 'min:0', 'max:30'], 'help' => '本地及轻舟实例到期后，超过宽限期进入停机处理。魔方产品遵循供应商规则。'],
                'retention_days' => ['label' => '暂停后的保留期（天）', 'default' => 7, 'type' => 'number', 'rules' => ['required', 'integer', 'min:1', 'max:90'], 'help' => '超过宽限截止时间与保留期后才进入回收流程；已存在的宽限截止日期保留。'],
            ],
            'notification' => [
                'email_enabled' => ['label' => '业务邮件通知', 'default' => 'true', 'type' => 'boolean', 'rules' => ['required', 'in:true,false'], 'help' => '控制到期、支付、工单等业务通知。登录验证邮件继续由邮件通道单独保障。'],
                'sms_enabled' => ['label' => '短信通知', 'default' => 'false', 'type' => 'boolean', 'rules' => ['required', 'in:true,false'], 'help' => '只有已配置真实短信网关才能开启。'],
                'rate_limit_per_hour' => ['label' => '单客户每渠道每小时上限', 'default' => 20, 'type' => 'number', 'rules' => ['required', 'integer', 'min:1', 'max:200'], 'help' => '用于业务通知限流；不影响邮件验证码的独立频率限制。'],
                'sms_sign' => ['label' => '短信签名', 'default' => 'Deviy', 'rules' => ['required', 'string', 'max:40'], 'help' => '应与短信供应商审核通过的签名一致。'],
            ],
            'attachment' => [
                'quota_standard_mb' => ['label' => '标准客户附件额度（MB）', 'default' => 50, 'type' => 'number', 'rules' => ['required', 'integer', 'min:1', 'max:10240'], 'help' => '用于客户工单附件累计限额，普通客户含 default 分组。'],
                'quota_vip_mb' => ['label' => 'VIP客户附件额度（MB）', 'default' => 200, 'type' => 'number', 'rules' => ['required', 'integer', 'min:1', 'max:102400'], 'help' => '额度大幅变化沿用现有变更审批。'],
                'upload_max_mb' => ['label' => '单文件大小上限（MB）', 'default' => 20, 'type' => 'number', 'rules' => ['required', 'integer', 'min:1', 'max:20'], 'help' => '同时受到网页表单、服务器和文件类型限制，最大20MB。'],
            ],
        ];
    }
}
