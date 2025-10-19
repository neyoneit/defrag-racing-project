<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tag;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = [
            // Weapons
            ['name' => 'rocket', 'display_name' => 'Rocket', 'category' => 'weapons'],
            ['name' => 'rocketlauncher', 'display_name' => 'RocketLauncher', 'category' => 'weapons'],
            ['name' => 'plasmagun', 'display_name' => 'PlasmaGun', 'category' => 'weapons'],
            ['name' => 'plasma', 'display_name' => 'Plasma', 'category' => 'weapons'],
            ['name' => 'grenadelauncher', 'display_name' => 'GrenadeLauncher', 'category' => 'weapons'],
            ['name' => 'grenade', 'display_name' => 'Grenade', 'category' => 'weapons'],
            ['name' => 'bfg', 'display_name' => 'BFG', 'category' => 'weapons'],
            ['name' => 'railgun', 'display_name' => 'Railgun', 'category' => 'weapons'],
            ['name' => 'shotgun', 'display_name' => 'Shotgun', 'category' => 'weapons'],
            ['name' => 'machinegun', 'display_name' => 'MachineGun', 'category' => 'weapons'],
            ['name' => 'gauntlet', 'display_name' => 'Gauntlet', 'category' => 'weapons'],

            // Items
            ['name' => 'teleporter', 'display_name' => 'Teleporter', 'category' => 'items'],
            ['name' => 'jumppad', 'display_name' => 'JumpPad', 'category' => 'items'],
            ['name' => 'healtharmor', 'display_name' => 'Health/Armor', 'category' => 'items'],
            ['name' => 'megahealth', 'display_name' => 'MegaHealth', 'category' => 'items'],
            ['name' => 'quad', 'display_name' => 'Quad', 'category' => 'items'],
            ['name' => 'haste', 'display_name' => 'Haste', 'category' => 'items'],
            ['name' => 'battlesuit', 'display_name' => 'BattleSuit', 'category' => 'items'],

            // Functions/Techniques
            ['name' => 'strafe', 'display_name' => 'Strafe', 'category' => 'functions'],
            ['name' => 'circle', 'display_name' => 'Circle', 'category' => 'functions'],
            ['name' => 'circlejump', 'display_name' => 'CircleJump', 'category' => 'functions'],
            ['name' => 'doublejump', 'display_name' => 'DoubleJump', 'category' => 'functions'],
            ['name' => 'rampjump', 'display_name' => 'RampJump', 'category' => 'functions'],
            ['name' => 'trickjump', 'display_name' => 'TrickJump', 'category' => 'functions'],
            ['name' => 'wallclip', 'display_name' => 'WallClip', 'category' => 'functions'],
            ['name' => 'overbounce', 'display_name' => 'Overbounce', 'category' => 'functions'],
            ['name' => 'groundboost', 'display_name' => 'GroundBoost', 'category' => 'functions'],
            ['name' => 'funkyboost', 'display_name' => 'FunkyBoost', 'category' => 'functions'],

            // Difficulty Levels
            ['name' => 'newbie', 'display_name' => 'Newbie', 'category' => 'difficulty'],
            ['name' => 'beginner', 'display_name' => 'Beginner', 'category' => 'difficulty'],
            ['name' => 'intermediate', 'display_name' => 'Intermediate', 'category' => 'difficulty'],
            ['name' => 'advanced', 'display_name' => 'Advanced', 'category' => 'difficulty'],
            ['name' => 'pro', 'display_name' => 'Pro', 'category' => 'difficulty'],
            ['name' => 'master', 'display_name' => 'Master', 'category' => 'difficulty'],
            ['name' => 'expert', 'display_name' => 'Expert', 'category' => 'difficulty'],

            // Training/Purpose
            ['name' => 'training', 'display_name' => 'Training', 'category' => 'purpose'],
            ['name' => 'speedrun', 'display_name' => 'Speedrun', 'category' => 'purpose'],
            ['name' => 'freestyle', 'display_name' => 'Freestyle', 'category' => 'purpose'],
            ['name' => 'race', 'display_name' => 'Race', 'category' => 'purpose'],
            ['name' => 'run', 'display_name' => 'Run', 'category' => 'purpose'],

            // Movement Types
            ['name' => 'vq3', 'display_name' => 'VQ3', 'category' => 'movement'],
            ['name' => 'cpm', 'display_name' => 'CPM', 'category' => 'movement'],
            ['name' => 'promode', 'display_name' => 'ProMode', 'category' => 'movement'],

            // Map Types
            ['name' => 'linear', 'display_name' => 'Linear', 'category' => 'maptype'],
            ['name' => 'open', 'display_name' => 'Open', 'category' => 'maptype'],
            ['name' => 'technical', 'display_name' => 'Technical', 'category' => 'maptype'],
            ['name' => 'simple', 'display_name' => 'Simple', 'category' => 'maptype'],
            ['name' => 'complex', 'display_name' => 'Complex', 'category' => 'maptype'],
        ];

        foreach ($tags as $tag) {
            Tag::updateOrCreate(
                ['name' => $tag['name']],
                $tag
            );
        }
    }
}
