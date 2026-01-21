<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stat extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    const UPDATED_AT = 'updated_at';
    const CREATED_AT = null;

    protected $fillable = [
        'id',
        'credits_used',
        'credits_remaining',
    ];

    protected function casts(): array
    {
        return [
            'credits_used' => 'decimal:2',
            'credits_remaining' => 'decimal:2',
        ];
    }
}
