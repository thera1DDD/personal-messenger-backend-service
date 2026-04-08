<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ChatUsersSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = config('chat.accounts');

        foreach ($accounts as $username => $account) {
            User::updateOrCreate(
                ['username' => $username],
                [
                    'name' => $account['name'],
                    'email' => $account['email'],
                    'password' => Str::password(32),
                    'avatar_color' => $username === 'tagir' ? '#0F766E' : '#BE123C',
                ]
            );
        }
    }
}
