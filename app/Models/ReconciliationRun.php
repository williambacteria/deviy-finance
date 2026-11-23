<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['run_date', 'checked', 'issues_count', 'status', 'issues', 'notified'])]
class ReconciliationRun extends Model
{
    protected function casts(): array
    {
        return [
            'checked' => 'integer',
            'issues_count' => 'integer',
            'issues' => 'array',
            'notified' => 'boolean',
        ];
    }
}
