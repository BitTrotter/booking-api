<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CabinImage extends Model
{
    protected $fillable = [
        'cabin_id',
        'image_url',
        'is_cover'
    ];

    public function cabin()
    {
        return $this->belongsTo(Cabin::class);
    }
}
