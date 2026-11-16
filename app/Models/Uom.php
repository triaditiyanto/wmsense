<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Uom extends Model
{
    protected $table = 'master_uom';

    protected $fillable = ['code', 'name', 'allow_decimal'];

    protected function casts(): array
    {
        return [
            'allow_decimal' => 'boolean',
        ];
    }
}
