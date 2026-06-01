<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // Relationships
    public function ratingsReviews()
    {
        return $this->hasMany(RatingReview::class);
    }

    // Helper: cek role
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isPremium(): bool
    {
        return in_array($this->role, ['admin', 'user_premium']);
    }
}