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
    ];
}
