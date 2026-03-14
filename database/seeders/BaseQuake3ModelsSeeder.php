<?php

namespace Database\Seeders;

use App\Models\PlayerModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BaseQuake3ModelsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // First, clear all existing models
        PlayerModel::query()->delete();

        // Base Quake 3 Arena player models
        $baseModels = [
            [
                'name' => 'Anarki',
                'description' => 'The cyberpunk roller-skating anarchist. Fast and unpredictable.',
                'author' => 'id Software',
                'has_sounds' => false,
                'has_ctf_skins' => true,
            ],
            [
                'name' => 'Biker',
                'description' => 'The tough biker with attitude. Rides into battle with style.',
                'author' => 'id Software',
                'has_sounds' => false,
                'has_ctf_skins' => true,
            ],
            [
                'name' => 'Bitterman',
                'description' => 'The classic Quake 2 marine. A veteran of many battles.',
                'author' => 'id Software',
                'has_sounds' => false,
                'has_ctf_skins' => true,
            ],
            [
                'name' => 'Bones',
                'description' => 'The skeletal warrior. Death incarnate on the battlefield.',
                'author' => 'id Software',
                'has_sounds' => false,
                'has_ctf_skins' => true,
            ],
            [
                'name' => 'Crash',
                'description' => 'The female marine commando. Tough as nails and twice as deadly.',
                'author' => 'id Software',
                'has_sounds' => false,
                'has_ctf_skins' => true,
            ],
            [
                'name' => 'Doom',
                'description' => 'The legendary Doom Marine. The original demon slayer.',
                'author' => 'id Software',
                'has_sounds' => false,
                'has_ctf_skins' => true,
            ],
            [
                'name' => 'Grunt',
                'description' => 'The armored space marine. Ready for any combat situation.',
                'author' => 'id Software',
                'has_sounds' => false,
                'has_ctf_skins' => true,
            ],
            [
                'name' => 'Hunter',
                'description' => 'The stealthy predator. Strikes from the shadows.',
                'author' => 'id Software',
                'has_sounds' => false,
                'has_ctf_skins' => true,
            ],
            [
                'name' => 'Keel',
                'description' => 'The cyborg warrior. Half-man, half-machine, all destruction.',
                'author' => 'id Software',
                'has_sounds' => false,
                'has_ctf_skins' => true,
            ],
            [
                'name' => 'Klesk',
                'description' => 'The alien gladiator. Fierce and deadly in combat.',
                'author' => 'id Software',
                'has_sounds' => false,
                'has_ctf_skins' => true,
            ],
            [
                'name' => 'Lucy',
                'description' => 'The agile fighter. Quick reflexes and deadly accuracy.',
                'author' => 'id Software',
                'has_sounds' => false,
                'has_ctf_skins' => true,
            ],
            [
                'name' => 'Major',
                'description' => 'The military commander. Tactical genius and combat expert.',
                'author' => 'id Software',
                'has_sounds' => false,
                'has_ctf_skins' => true,
            ],
            [
                'name' => 'Mynx',
                'description' => 'The feline warrior. Graceful and lethal.',
                'author' => 'id Software',
                'has_sounds' => false,
                'has_ctf_skins' => true,
            ],
            [
                'name' => 'Orbb',
                'description' => 'The floating eyeball creature. Sees all, destroys all.',
                'author' => 'id Software',
                'has_sounds' => false,
                'has_ctf_skins' => true,
            ],
            [
                'name' => 'Ranger',
                'description' => 'The original Quake hero. Battle-hardened and legendary.',
                'author' => 'id Software',
                'has_sounds' => false,
                'has_ctf_skins' => true,
            ],
            [
                'name' => 'Razor',
                'description' => 'The punk rocker warrior. Fights with style and fury.',
                'author' => 'id Software',
                'has_sounds' => false,
                'has_ctf_skins' => true,
            ],
            [
                'name' => 'Sarge',
                'description' => 'The drill sergeant from hell. Tough, mean, and ready to kick ass.',
                'author' => 'id Software',
                'has_sounds' => false,
                'has_ctf_skins' => true,
            ],
            [
                'name' => 'Slash',
                'description' => 'The female skater. Fast, agile, and deadly.',
                'author' => 'id Software',
                'has_sounds' => false,
                'has_ctf_skins' => true,
            ],
            [
                'name' => 'Sorlag',
                'description' => 'The lizard-like alien. Scales, claws, and pure aggression.',
                'author' => 'id Software',
                'has_sounds' => false,
                'has_ctf_skins' => true,
            ],
            [
                'name' => 'Tank Jr',
                'description' => 'The miniature tank. Small but heavily armored.',
                'author' => 'id Software',
                'has_sounds' => false,
                'has_ctf_skins' => true,
            ],
            [
                'name' => 'Uriel',
                'description' => 'The angelic warrior. Fights with divine fury.',
                'author' => 'id Software',
                'has_sounds' => false,
                'has_ctf_skins' => true,
            ],
            [
                'name' => 'Visor',
                'description' => 'The cybernetic assassin. Enhanced vision and deadly precision.',
                'author' => 'id Software',
                'has_sounds' => false,
                'has_ctf_skins' => true,
            ],
            [
                'name' => 'Xaero',
                'description' => 'The final boss. The ultimate Arena champion.',
                'author' => 'id Software',
                'has_sounds' => false,
                'has_ctf_skins' => true,
            ],
        ];

        foreach ($baseModels as $modelData) {
            $modelSlug = strtolower($modelData['name']);
            $modelSlug = str_replace(' ', '', $modelSlug); // Remove spaces (Tank Jr -> tankjr)

            // Path to the model files (they're in public, not storage)
            $filePath = 'models/basequake3/players/' . $modelSlug;

            PlayerModel::create([
                'user_id' => 8,
                'name' => $modelData['name'],
                'description' => $modelData['description'],
                'category' => 'player',
                'author' => $modelData['author'],
                'author_email' => null,
                'file_path' => $filePath,
                'zip_path' => null, // These are not zipped
                'poly_count' => null,
                'vert_count' => null,
                'has_sounds' => $modelData['has_sounds'],
                'has_ctf_skins' => $modelData['has_ctf_skins'],
                'approved' => true, // Base models are pre-approved
                'downloads' => 0,
            ]);
        }

        $this->command->info('Successfully seeded ' . count($baseModels) . ' base Quake 3 Arena player models!');
    }
}
