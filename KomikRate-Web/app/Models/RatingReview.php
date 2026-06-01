<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RatingReview extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ratings_reviews';

    protected $fillable = [
        'user_id',
        'comic_id',
        'rating',
        'review_text',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comic()
    {
        return $this->belongsTo(Comic::class);
    }
}