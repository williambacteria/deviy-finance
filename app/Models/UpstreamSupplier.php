<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UpstreamSupplier extends Model
{
    protected $fillable = [
        'name',
        'contact',
        'note',
        'driver',
        'base_url',
        'username',
        'api_key',
        'password',
        'verify_ssl',
        'status',
        'last_test_status',
        'last_error',
        'last_tested_at',
    ];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'password' => 'encrypted',
            'verify_ssl' => 'boolean',
            'last_tested_at' => 'datetime',
        ];
    }
}
