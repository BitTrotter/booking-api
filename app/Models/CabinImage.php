<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CabinImage extends Model
{
    protected $fillable = [
        'cabin_id',
        'url',
        'is_main'
    ];

    public function cabin()
    {
        return $this->belongsTo(Cabin::class);
    }
}
