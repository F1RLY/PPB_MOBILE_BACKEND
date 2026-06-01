<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ComicSeeder extends Seeder
{
    public function run()
    {
        $genreIds = DB::table('genres')->pluck('id')->toArray();

        if (empty($genreIds)) {
            $this->command->error('Genre belum ada! Jalankan GenreSeeder dulu.');
            return;
        }

        // Gunakan URL gambar dari picsum (lebih reliable)
        $comics = [
            ['title' => 'Neon Vanguard', 'synopsis' => 'Cyberpunk action story.', 'cover_image' => 'https://picsum.photos/id/0/400/600', 'status' => 'ongoing', 'author' => 'Alex Chen'],
            ['title' => 'Shadow Realm', 'synopsis' => 'Dark fantasy adventure.', 'cover_image' => 'https://picsum.photos/id/1/400/600', 'status' => 'completed', 'author' => 'Sarah Williams'],
            ['title' => 'Void Runner', 'synopsis' => 'High speed space chase.', 'cover_image' => 'https://picsum.photos/id/2/400/600', 'status' => 'ongoing', 'author' => 'Mike Johnson'],
            ['title' => 'Stellar Knight', 'synopsis' => 'Epic space battles.', 'cover_image' => 'https://picsum.photos/id/3/400/600', 'status' => 'ongoing', 'author' => 'David Kim'],
            ['title' => 'Hidden Garden', 'synopsis' => 'Slice of life story.', 'cover_image' => 'https://picsum.photos/id/4/400/600', 'status' => 'completed', 'author' => 'Emily Brown'],
            ['title' => 'Vigilante X', 'synopsis' => 'Street justice.', 'cover_image' => 'https://picsum.photos/id/5/400/600', 'status' => 'ongoing', 'author' => 'James Wilson'],
            ['title' => 'Echoes of Time', 'synopsis' => 'Time travel mystery.', 'cover_image' => 'https://picsum.photos/id/6/400/600', 'status' => 'ongoing', 'author' => 'Lisa Martinez'],
            ['title' => 'Steel Soul', 'synopsis' => 'Mecha robot tournament.', 'cover_image' => 'https://picsum.photos/id/7/400/600', 'status' => 'completed', 'author' => 'Robert Taylor'],
            ['title' => 'Mystic Forest', 'synopsis' => 'Spirit exploration.', 'cover_image' => 'https://picsum.photos/id/8/400/600', 'status' => 'ongoing', 'author' => 'Maria Garcia'],
            ['title' => 'Cyber Ronin', 'synopsis' => 'Samurai in the future.', 'cover_image' => 'https://picsum.photos/id/9/400/600', 'status' => 'ongoing', 'author' => 'Kenji Tanaka'],
            ['title' => 'Ocean Depth', 'synopsis' => 'Exploring the unknown sea.', 'cover_image' => 'https://picsum.photos/id/10/400/600', 'status' => 'completed', 'author' => 'James Cameron'],
            ['title' => 'Royal Blood', 'synopsis' => 'Political palace drama.', 'cover_image' => 'https://picsum.photos/id/11/400/600', 'status' => 'ongoing', 'author' => 'Victoria Lee'],
            ['title' => 'Circuit Heart', 'synopsis' => 'AI searching for love.', 'cover_image' => 'https://picsum.photos/id/12/400/600', 'status' => 'ongoing', 'author' => 'Thomas Anderson'],
            ['title' => 'Dragon Whisperer', 'synopsis' => 'Bond with dragon.', 'cover_image' => 'https://picsum.photos/id/13/400/600', 'status' => 'ongoing', 'author' => 'Elena Rodriguez'],
            ['title' => 'Urban Legend', 'synopsis' => 'Horror investigation.', 'cover_image' => 'https://picsum.photos/id/14/400/600', 'status' => 'completed', 'author' => 'Stephen King'],
            ['title' => 'Gravity Shift', 'synopsis' => 'Physics-defying adventure.', 'cover_image' => 'https://picsum.photos/id/15/400/600', 'status' => 'ongoing', 'author' => 'Chris Nolan'],
            ['title' => 'Silent Blade', 'synopsis' => 'An assassin with a code.', 'cover_image' => 'https://picsum.photos/id/16/400/600', 'status' => 'ongoing', 'author' => 'Frank Miller'],
            ['title' => 'Star Nomad', 'synopsis' => 'Wandering the galaxy.', 'cover_image' => 'https://picsum.photos/id/17/400/600', 'status' => 'completed', 'author' => 'Arthur Clarke'],
            ['title' => 'After School', 'synopsis' => 'Simple life struggles.', 'cover_image' => 'https://picsum.photos/id/18/400/600', 'status' => 'ongoing', 'author' => 'Haruki Murakami'],
            ['title' => 'Titan Forge', 'synopsis' => 'Legendary weapons.', 'cover_image' => 'https://picsum.photos/id/19/400/600', 'status' => 'ongoing', 'author' => 'George Martin'],
            ['title' => 'Neon Rain', 'synopsis' => 'Noir detective story.', 'cover_image' => 'https://picsum.photos/id/20/400/600', 'status' => 'completed', 'author' => 'Raymond Chandler'],
            ['title' => 'Frostbite', 'synopsis' => 'Survival in frozen land.', 'cover_image' => 'https://picsum.photos/id/21/400/600', 'status' => 'ongoing', 'author' => 'John Snow'],
            ['title' => 'Phoenix Rising', 'synopsis' => 'Rebirth from ashes.', 'cover_image' => 'https://picsum.photos/id/22/400/600', 'status' => 'ongoing', 'author' => 'JK Rowling'],
        ];

        $types = ['Manga', 'Manhwa', 'Manhua'];
        
        foreach ($comics as $comicData) {
            $comicData['created_at'] = now();
            $comicData['updated_at'] = now();
            $comicData['type'] = $types[array_rand($types)];

            $comicId = DB::table('comics')->insertGetId($comicData);

            // Assign 1-3 random genres
            $numGenres = rand(1, 3);
            $randomKeys = (array) array_rand($genreIds, $numGenres);
            
            foreach ($randomKeys as $key) {
                DB::table('comic_genre')->insert([
                    'comic_id' => $comicId,
                    'genre_id' => $genreIds[$key],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
        
        $this->command->info('✅ Berhasil menambahkan ' . count($comics) . ' komik!');
    }
}