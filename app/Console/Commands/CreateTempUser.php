<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class CreateTempUser extends Command
{
    protected $signature = 'dev:create-temp-user {email=tmpuser@example.test} {password=secretpass}';
    protected $description = 'Create a temporary test user (dev only)';

    public function handle()
    {
        $email = $this->argument('email');
        $password = $this->argument('password');

        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'tmpuser', 'password' => bcrypt($password)]
        );

        $this->info('User created: ' . $user->email);
        return 0;
    }
}
