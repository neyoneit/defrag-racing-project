<?php
require __DIR__ . "/../vendor/autoload.php";
use App\Models\User;
User::create(['name' => 'tmpuser', 'email' => 'tmpuser@example.test', 'password' => bcrypt('secretpass')]);
echo "created\n";
