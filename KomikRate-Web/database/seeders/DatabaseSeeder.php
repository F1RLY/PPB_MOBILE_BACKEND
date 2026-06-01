<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat User Test
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // 2. Panggil Seeder lain secara berurutan
        $this->call([
            GenreSeeder::class, // Isi kategori dulu (Action, Fantasy, dll)
            ComicSeeder::class, // Baru isi data komik
        ]);
    }
}