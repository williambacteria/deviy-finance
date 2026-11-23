<?php
namespace Database\Factories;
use App\Models\ResellerAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
class ResellerAccountFactory extends Factory
{
    protected $model=ResellerAccount::class;
    public function definition(): array
    {
        return ['user_id'=>User::factory(),'status'=>'pending','site_name'=>'API 测试平台','site_url'=>'https://example.invalid','purpose'=>'用于自动化接口兼容性验证'];
    }
    public function approved(): static
    {
        return $this->state(fn()=>['status'=>'approved']);
    }
}
