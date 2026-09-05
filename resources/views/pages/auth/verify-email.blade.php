<x-layouts::auth :title="__('Verifikasi email')">
    <div class="mt-4 flex flex-col gap-6">
        <p class="text-center text-base-content/70">
            {{ __('Silakan verifikasi alamat email Anda dengan mengklik tautan yang baru saja kami kirimkan.') }}
        </p>

        @if (session('status') == 'verification-link-sent')
            <p class="text-center font-medium text-success">
                {{ __('Tautan verifikasi baru telah dikirim ke alamat email yang Anda daftarkan.') }}
            </p>
        @endif

        <div class="flex flex-col items-center justify-between space-y-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <x-mary-button type="submit" class="btn-primary w-full">
                    {{ __('Kirim ulang email verifikasi') }}
                </x-mary-button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-mary-button type="submit" class="btn-ghost text-sm cursor-pointer" data-test="logout-button">
                    {{ __('Keluar') }}
                </x-mary-button>
            </form>
        </div>
    </div>
</x-layouts::auth>
