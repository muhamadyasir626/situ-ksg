<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AccountInvitationController extends Controller
{
    public function show(User $user, string $token): Response
    {
        $this->validateToken($user, $token);

        return response()->view('pages.auth.accept-invitation', compact('user', 'token'))
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'no-store');
    }

    public function store(Request $request, User $user, string $token): RedirectResponse
    {
        DB::transaction(function () use ($request, $user, $token): void {
            $user = User::lockForUpdate()->findOrFail($user->id);
            $this->validateToken($user, $token);
            app(ResetUserPassword::class)->reset($user, $request->only('password', 'password_confirmation'));
            $user->forceFill(['email_verified_at' => now(), 'remember_token' => Str::random(60)])->save();
            Password::broker()->deleteToken($user);
            event(new PasswordReset($user));
        });

        return redirect()->route('login')->with('status', 'Kata sandi berhasil dibuat. Silakan masuk menggunakan email dan kata sandi Anda.');
    }

    private function validateToken(User $user, string $token): void
    {
        abort_unless($user->invitation_token_hash && hash_equals($user->invitation_token_hash, hash('sha256', $token)), 404);
    }
}
