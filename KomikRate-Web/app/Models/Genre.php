<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Genre extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    // Auto-generate slug dari name saat create/update
    protected static function boot()
    {
        parent::boot();

        static::saving(function (Genre $genre) {
            if (empty($genre->slug)) {
                $genre->slug = Str::slug($genre->name);
            }
        });
    }

    // Relationships
    public function comics()
    {
        return $this->belongsToMany(Comic::class, 'comic_genre');
    }
}