<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegalLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'comic_id',
        'platform_name',
        'url',
    ];

    // Relationship
    public function comic()
    {
        return $this->belongsTo(Comic::class);
    }
}