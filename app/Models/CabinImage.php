<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CabinImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'cabin_id',
        'url',
        'is_main'
    ];

    protected $casts = [
        'is_main' => 'boolean'
    ];

    /**
     * Relación con la cabaña
     */
    public function cabin()
    {
        return $this->belongsTo(Cabin::class);
    }

    /**
     * Scope para obtener la imagen principal
     */
    public function scopeMain($query)
    {
        return $query->where('is_main', true);
    }

    /**
     * Scope para obtener imágenes secundarias
     */
    public function scopeSecondary($query)
    {
        return $query->where('is_main', false);
    }
}