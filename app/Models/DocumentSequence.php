<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocumentSequence extends Model
{
    protected $table = 'o_document_sequence';

    protected $fillable = ['doc_type', 'warehouse_id', 'period', 'last_no'];

    protected function casts(): array
    {
        return ['last_no' => 'integer'];
    }
}
