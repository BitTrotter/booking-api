<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    protected $fillable = ['name', 'icon'];

    public function cabins()
    {
        return $this->belongsToMany(Cabin::class, 'cabin_feature', 'feature_id', 'cabin_id');
    }
}
