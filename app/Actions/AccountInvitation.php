<?php

namespace App\Actions;

use App\Models\EmailDelivery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountInvitation
{
    public function send(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $token = Str::random(64);
            $hash = hash('sha256', $token);
            $user->forceFill(['invitation_token_hash' => $hash])->save();

            EmailDelivery::enqueue('invitation:'.$user->id.':'.$hash, $user, [
                'kind' => 'invitation',
                'token_hash' => $hash,
                'subject' => 'Undangan akun SITU-KSG — buat kata sandi Anda',
                'title' => 'Akun Anda siap digunakan',
                'intro' => 'Anda diundang untuk menggunakan aplikasi pelaporan internal Direktorat KSG. Buat kata sandi Anda sendiri untuk mulai mengakses aplikasi.',
                'action' => 'Buat kata sandi',
                'url' => route('invitation.show', ['user' => $user->id, 'token' => $token]),
            ]);
        });
    }
}
