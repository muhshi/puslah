<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    public function redirectToGoogle()
    {
        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $socialUser = Socialite::driver('google')->user();
        } catch (\Exception $e) {
            return redirect('/admin/login')->with('error', 'Google login failed');
        }

        $user = User::where('google_id', $socialUser->getId())->first();

        if (!$user) {
            $user = User::where('email', $socialUser->getEmail())->first();
            if ($user) {
                $user->update([
                    'google_id' => $socialUser->getId(),
                ]);
            }
        }

        if ($user) {
            Auth::login($user);
            return redirect('/admin');
        }

        return redirect('/admin/login')->with('error', 'User not found');
    }
}
