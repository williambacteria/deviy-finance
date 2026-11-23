<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'region', 'domain', 'target_ip', 'status', 'icp_status', 'provider_status', 'response_code', 'message', 'attempts', 'submitted_at'])]
class DomainAllowlistRequest extends Model
{
    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'attempts' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, array<string, string>> */
    public static function regions(): array
    {
        return [
            'xy' => ['name' => '襄阳', 'description' => '襄阳机房 · 域名接入白名单', 'icon' => 'shield'],
            'nb' => ['name' => '宁波', 'description' => '宁波机房 · 域名接入白名单', 'icon' => 'network'],
            'sy' => ['name' => '十堰', 'description' => '十堰机房 · 人工审核通道', 'icon' => 'globe'],
        ];
    }

    /** @return array<string, string> */
    public static function statuses(): array
    {
        return ['processing' => '提交处理中', 'success' => '过白成功', 'already_whitelisted' => '已在白名单', 'submitted' => '已提交 · 待审核', 'rejected' => '校验未通过', 'failed' => '过白失败', 'skipped' => '未执行过白', 'unknown' => '结果待核对'];
    }

    public function canResubmit(): bool
    {
        return in_array($this->status, ['rejected', 'failed', 'skipped'], true)
            && ($this->submitted_at === null || $this->submitted_at->lte(now()->subMinutes(2)));
    }
}
