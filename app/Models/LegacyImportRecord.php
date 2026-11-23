<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['source', 'source_table', 'source_id', 'target_table', 'target_id', 'state', 'payload'])]
#[Hidden(['payload'])]
class LegacyImportRecord extends Model
{
    protected function casts(): array
    {
        return ['payload' => 'encrypted:array'];
    }
}
