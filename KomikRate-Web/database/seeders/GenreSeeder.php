<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GenreSeeder extends Seeder
{
public function run(): void
{
    DB::table('genres')->insert([
        ['name' => 'Action', 'slug' => 'action'],
        ['name' => 'Fantasy', 'slug' => 'fantasy'],
        ['name' => 'Cyberpunk', 'slug' => 'cyberpunk'],
        ['name' => 'Slice of Life', 'slug' => 'slice-of-life'],
        ['name' => 'Sci-Fi', 'slug' => 'sci-fi'],
        ['name' => 'Horror', 'slug' => 'horror'],
        ['name' => 'Romance', 'slug' => 'romance'],
        ['name' => 'Comedy', 'slug' => 'comedy'],
        ['name' => 'Mystery', 'slug' => 'mystery'],
        ['name' => 'Adventure', 'slug' => 'adventure'],
    ]);
}
}