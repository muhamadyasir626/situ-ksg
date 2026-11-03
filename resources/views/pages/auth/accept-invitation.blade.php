<x-layouts::auth title="Buat kata sandi">
    <div class="flex flex-col gap-6">
        <x-auth-header title="Buat kata sandi" description="Selamat datang di SITU-KSG. Buat kata sandi untuk mengaktifkan akses Anda." />
        <p class="text-sm text-base-content/70">Akun: <strong>{{ $user->email }}</strong></p>
        <form method="POST" action="{{ route('invitation.store', ['user' => $user->id, 'token' => $token]) }}" class="flex flex-col gap-6">
            @csrf
            <x-mary-password name="password" label="Kata sandi baru" autocomplete="new-password" required right />
            <x-mary-password name="password_confirmation" label="Konfirmasi kata sandi" autocomplete="new-password" required right />
            @error('password')
                <p class="text-sm text-error" role="alert">{{ $message }}</p>
            @enderror
            <p class="text-sm text-base-content/60">Gunakan kata sandi yang unik. Setelah disimpan, tautan undangan ini tidak dapat digunakan kembali.</p>
            <x-mary-button type="submit" label="Simpan kata sandi" class="btn-primary w-full" />
        </form>
    </div>
</x-layouts::auth>
