<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SsoController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('sipetra')->redirect();
    }

    public function callback(Request $request)
    {
        if ($request->has('error')) {
            return redirect()->route('login')->with('error', 'Login SSO Dibatalkan');
        }

        try {
            $ssoUser = Socialite::driver('sipetra')->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Gagal mengambil data user');
        }

        $rawData = $ssoUser->getRaw();

        // Cari user berdasarkan sipetra_id atau email
        $user = User::where('sipetra_id', $ssoUser->getId())->first()
             ?? User::where('email', $ssoUser->getEmail())->first();

        $data = [
            'sipetra_id'    => $ssoUser->getId(),
            'name'          => $ssoUser->getName(),
            'email'         => $ssoUser->getEmail(),
            'sipetra_token' => $ssoUser->token,
            'sipetra_refresh_token' => $ssoUser->refreshToken,
        ];

        if ($user) {
            $user->update($data);
        } else {
            $data['password'] = bcrypt(\Illuminate\Support\Str::random(24));
            $user = User::create($data);
            
            // Assign role default (jika pakai Spatie Permission)
            if (method_exists($user, 'assignRole')) {
                // Sesuai guide: assignRole('pegawai')
                // Pastikan role ini ada, atau bisa disesuaikan nanti.
                try {
                    $user->assignRole('pegawai');
                } catch (\Exception $e) {
                    // Ignore if role doesn't exist
                }
            }
        }

        // Sync with UserProfile
        $profile = $user->profile()->firstOrCreate([
            'user_id' => $user->id
        ], [
            'employment_status' => 'aktif',
            'full_name' => $user->name,
        ]);
        
        $updateData = [
            'full_name' => $user->name,
            'jabatan' => $rawData['jabatan'] ?? $profile->jabatan,
        ];
        
        // Hanya timpa NIP profil jika NIP di profil masih kosong,
        // untuk menghindari NIP Baru yang sudah diinput tertimpa oleh data NIP Lama dari SSO.
        $ssoNip = $rawData['nip'] ?? null;
        if (empty($profile->nip) && !empty($ssoNip)) {
            $updateData['nip'] = $ssoNip;
        }
        
        $profile->update($updateData);

        Auth::login($user);
        return redirect()->intended('/admin');
    }
}
