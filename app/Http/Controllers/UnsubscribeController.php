<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UnsubscribeController extends Controller
{
    public function __invoke(Request $request, User $user, string $type, string $channel): View
    {
        abort_unless(in_array($channel, ['email', 'sms'], true), 404);

        NotificationPreference::updateOrCreate(
            ['user_id' => $user->id, 'channel' => $channel, 'type' => $type],
            ['enabled' => false],
        );

        return view('notifications.unsubscribe', compact('user', 'type', 'channel'));
    }
}
