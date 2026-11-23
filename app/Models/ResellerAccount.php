<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class ResellerAccount extends Model
{
    use HasFactory;
    protected $fillable = ['user_id','status','site_name','site_url','purpose','ip_allowlist','secret_hash','secret_hint','review_note','reviewed_by','reviewed_at','last_used_at'];
    protected $hidden = ['secret_hash','token_hash'];
    protected function casts(): array { return ['ip_allowlist'=>'array','reviewed_at'=>'datetime','last_used_at'=>'datetime']; }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
