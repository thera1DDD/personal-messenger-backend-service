<?php

namespace App\Support;

use App\Models\User;

class ChatChannel
{
    public static function dialogChannelName(): string
    {
        $participants = array_keys(config('chat.accounts', []));

        return 'chat.' . implode('-', $participants);
    }

    public static function canAccess(User $user): bool
    {
        return in_array($user->username, array_keys(config('chat.accounts', [])), true);
    }
}
