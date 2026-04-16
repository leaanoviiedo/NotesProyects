<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GoogleCalendarController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')
            ->scopes([
                'https://www.googleapis.com/auth/calendar',
                'https://www.googleapis.com/auth/calendar.events',
            ])
            ->with(['access_type' => 'offline', 'prompt' => 'consent'])
            ->redirect();
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect()->route('calendar')->with('error', 'Google Calendar authorization failed.');
        }

        /** @var User $user */
        $user = auth()->user();
        $user->update([
            'google_id'            => $googleUser->getId(),
            'google_token'         => $googleUser->token,
            'google_refresh_token' => $googleUser->refreshToken ?? $user->google_refresh_token,
        ]);

        return redirect()->route('calendar')->with('success', 'Google Calendar connected successfully!');
    }

    public function disconnect()
    {
        /** @var User $user */
        $user = auth()->user();
        $user->update([
            'google_id'            => null,
            'google_token'         => null,
            'google_refresh_token' => null,
            'google_calendar_id'   => null,
        ]);

        return redirect()->route('calendar')->with('success', 'Google Calendar disconnected.');
    }
}
