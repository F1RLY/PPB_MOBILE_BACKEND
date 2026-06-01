<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comic extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'alternative_title',
        'author',
        'artist',
        'type',
        'synopsis',
        'cover_image',
        'status',
        'view_count',
    ];

    protected function casts(): array
    {
        return [
            'view_count' => 'integer',
        ];
    }

    // Relationships
    public function genres()
    {
        return $this->belongsToMany(Genre::class, 'comic_genre');
    }

    public function legalLinks()
    {
        return $this->hasMany(LegalLink::class);
    }

    public function ratingsReviews()
    {
        return $this->hasMany(RatingReview::class);
    }

    // Accessor: hitung rata-rata rating secara otomatis
    public function getAverageRatingAttribute(): float|null
    {
        return $this->ratingsReviews()->avg('rating');
    }

    // Accessor: URL cover image - PERBAIKI INI!
    public function getCoverImageUrlAttribute(): string|null
    {
        if (!$this->cover_image) {
            return null;
        }
        
        // Cek apakah ini URL eksternal (http:// atau https://)
        if (str_starts_with($this->cover_image, 'http://') || 
            str_starts_with($this->cover_image, 'https://')) {
            return $this->cover_image; // Langsung return URL asli
        }
        
        // Jika hanya nama file, tambahkan storage path
        return asset('storage/' . $this->cover_image);
    }

    // Scope: filter berdasarkan type
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    // Scope: filter trending (urut by view_count)
    public function scopeTrending($query)
    {
        return $query->orderByDesc('view_count');
    }

    // Scope: filter popular (urut by avg rating)
    public function scopePopular($query)
    {
        return $query->withAvg('ratingsReviews', 'rating')
                     ->orderByDesc('ratings_reviews_avg_rating');
    }
}