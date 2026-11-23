<?php

namespace App\Models;

use Database\Factories\PluginFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'code', 'type', 'version', 'description', 'is_enabled', 'config'])]
#[Hidden(['config'])]
class Plugin extends Model
{
    /** @use HasFactory<PluginFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'config' => 'encrypted:array',
        ];
    }
}
