<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
class ResellerCartItem extends Model
{
    use HasFactory;
    protected $fillable = ['reseller_account_id','request_key','payload_hash','payload','order_id','expires_at'];
    protected $hidden = ['secret_hash','token_hash'];
    protected function casts(): array { return ['payload'=>'array','expires_at'=>'datetime']; }
    public function account(): BelongsTo { return $this->belongsTo(ResellerAccount::class, 'reseller_account_id'); }
}
