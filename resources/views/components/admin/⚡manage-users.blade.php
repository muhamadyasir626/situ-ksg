<?php

use App\Actions\Fortify\ResetUserPassword;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Spatie\Permission\Models\Role;

new #[Layout('layouts.app')] #[Title('Manajemen Pengguna')] class extends Component
{
    use Toast, WithPagination;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public ?int $roleId = null;

    public ?int $unitId = null;

    public bool $showModal = false;

    public bool $showPasswordResetModal = false;

    #[Locked]
    public ?int $passwordResetUserId = null;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    #[Computed]
    public function users()
    {
        return User::with(['unit', 'roles'])->latest()->paginate(15);
    }

    #[Computed]
    public function roles()
    {
        return Role::orderBy('name')->get();
    }

    #[Computed]
    public function units()
    {
        return Unit::where('is_active', true)->orderBy('name')->get();
    }

    public function createUser(): void
    {
        $this->authorize('create', User::class);

        $this->cancelPasswordReset();
        $this->resetForm();
        $this->showModal = true;
    }

    #[Computed]
    public function passwordResetUser(): ?User
    {
        return $this->passwordResetUserId === null ? null : User::find($this->passwordResetUserId);
    }

    public function openPasswordReset(int $userId): void
    {
        $targetUser = User::findOrFail($userId);
        $this->authorize('update', $targetUser);

        $this->resetForm();
        $this->showModal = false;
        $this->passwordResetUserId = $targetUser->id;
        $this->showPasswordResetModal = true;
        unset($this->passwordResetUser);
    }

    public function resetUserPassword(): void
    {
        $targetUser = User::findOrFail($this->passwordResetUserId);
        $this->authorize('update', $targetUser);

        DB::transaction(function () use ($targetUser): void {
            app(ResetUserPassword::class)->reset($targetUser, [
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
            ]);

            $targetUser->forceFill(['remember_token' => Str::random(60)])->save();
            Password::broker()->deleteToken($targetUser);

            activity('user')
                ->performedOn($targetUser)
                ->causedBy(auth()->user())
                ->event('password_reset')
                ->log('Password pengguna direset oleh Admin.');
        });

        event(new PasswordReset($targetUser));

        $this->cancelPasswordReset();
        $this->success('Password pengguna berhasil diperbarui. Sampaikan password baru melalui kanal aman.');
    }

    public function cancelPasswordReset(): void
    {
        $this->showPasswordResetModal = false;
        $this->passwordResetUserId = null;
        $this->password = '';
        $this->password_confirmation = '';
        $this->resetValidation();
        unset($this->passwordResetUser);
    }

    public function updatedShowPasswordResetModal(bool $value): void
    {
        if (! $value) {
            $this->cancelPasswordReset();
        }
    }

    public function save(): void
    {
        $this->authorize('create', User::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'roleId' => ['required', 'exists:roles,id'],
            'unitId' => [
                Rule::requiredIf(fn () => Role::find($this->roleId)?->name === 'Pelapor Unit'),
                'nullable',
                'exists:units,id',
            ],
        ], [
            'unitId.required' => 'Pelapor Unit wajib memiliki unit kerja.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'unit_id' => $validated['unitId'],
        ]);
        // email_verified_at sengaja tidak fillable; akun dibuat Admin dianggap terverifikasi.
        $user->forceFill(['email_verified_at' => now()])->save();

        $role = Role::find($validated['roleId']);
        $user->assignRole($role);

        $this->success('Pengguna baru ditambahkan.');

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('$refresh');
    }

    public function assignRole(int $userId, int $roleId): void
    {
        $targetUser = User::findOrFail($userId);
        $this->authorize('assignRole', $targetUser);

        $role = Role::findOrFail($roleId);

        if ($targetUser->is(auth()->user()) && $role->name !== 'Admin') {
            $this->error('Anda tidak dapat mengubah peran akun Anda sendiri.');
            unset($this->users);

            return;
        }

        if ($role->name === 'Pelapor Unit' && ! $targetUser->unit_id) {
            $this->error('Tetapkan unit kerja terlebih dahulu sebelum menjadikan Pelapor Unit.');
            unset($this->users);

            return;
        }

        $targetUser->syncRoles([$role->name]);

        $this->success("Peran diubah menjadi {$role->name}.");
    }

    public function assignUnit(int $userId, int|string|null $unitId): void
    {
        $targetUser = User::findOrFail($userId);
        $this->authorize('update', $targetUser);

        if ($unitId === null || $unitId === '') {
            if ($targetUser->hasRole('Pelapor Unit')) {
                $this->error('Pelapor Unit wajib memiliki unit kerja.');
                unset($this->users);

                return;
            }

            $targetUser->update(['unit_id' => null]);
            $this->success('Unit kerja dikosongkan.');

            return;
        }

        $unit = Unit::findOrFail((int) $unitId);
        $targetUser->update(['unit_id' => $unit->id]);

        $this->success("Unit diubah menjadi {$unit->name}.");
    }

    private function resetForm(): void
    {
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->password_confirmation = '';
        $this->roleId = null;
        $this->unitId = null;
        $this->resetValidation();
    }
};
?>

<div>
    <x-mary-header title="Manajemen Pengguna" subtitle="Kelola akun pengguna, peran, dan unit kerja." separator>
        <x-slot:actions>
            <x-mary-button icon="o-plus" label="Tambah Pengguna" class="btn-primary btn-sm" wire:click="createUser" />
        </x-slot:actions>
    </x-mary-header>

    <x-mary-card>
        <x-mary-table :headers="[['key' => 'name', 'label' => 'Nama'], ['key' => 'email', 'label' => 'Email'], ['key' => 'role', 'label' => 'Peran', 'sortable' => false], ['key' => 'unit', 'label' => 'Unit Kerja', 'sortable' => false]]" :rows="$this->users" with-pagination class="table-sm">
            @scope('cell_name', $user)
                <span class="font-medium">{{ $user->name }}</span>
            @endscope
            @scope('cell_role', $user)
                <select wire:change="assignRole({{ $user->id }}, $event.target.value)" class="select select-sm min-w-[140px]">
                    @foreach ($this->roles as $role)
                        <option value="{{ $role->id }}" @if ($user->roles->contains('id', $role->id)) selected @endif>{{ $role->name }}</option>
                    @endforeach
                </select>
            @endscope
            @scope('cell_unit', $user)
                <select wire:change="assignUnit({{ $user->id }}, $event.target.value)" class="select select-sm min-w-[180px]">
                    <option value="">— Tidak ada —</option>
                    @foreach ($this->units as $unit)
                        <option value="{{ $unit->id }}" @if ($user->unit_id === $unit->id) selected @endif>{{ $unit->name }}</option>
                    @endforeach
                </select>
            @endscope
            @scope('actions', $user)
                <x-mary-button icon="o-key" label="Reset Password" class="btn-ghost btn-sm" wire:click="openPasswordReset({{ $user->id }})" spinner="openPasswordReset({{ $user->id }})" />
            @endscope
            @slot('empty')
                <p class="text-sm text-base-content/60">Belum ada pengguna.</p>
            @endslot
        </x-mary-table>
    </x-mary-card>

    <x-mary-modal wire:model="showModal" box-class="md:w-[520px]">
        <div class="mb-4">
            <h3 class="text-sm font-semibold text-base-content">Tambah Pengguna Baru</h3>
            <p class="mt-1 text-sm text-base-content/60">Buat akun pengguna baru, lalu tetapkan peran dan unit kerja.</p>
        </div>

        <form wire:submit="save" class="space-y-4">
            <x-mary-input wire:model="name" label="Nama" required />
            <x-mary-input wire:model="email" label="Email" type="email" required />
            <x-mary-input wire:model="password" label="Password" type="password" required />

            <div class="grid grid-cols-2 gap-4">
                <x-mary-select wire:model="roleId" :options="$this->roles" option-value="id" option-label="name" placeholder="Pilih peran…" label="Peran" required />
                <x-mary-select wire:model="unitId" :options="$this->units" option-value="id" option-label="name" placeholder="Tidak ada" label="Unit Kerja" />
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <x-mary-button type="button" label="Batal" class="btn-ghost btn-sm" wire:click="$set('showModal', false)" />
                <x-mary-button type="submit" label="Simpan" class="btn-primary btn-sm" />
            </div>
        </form>
    </x-mary-modal>

    <x-mary-modal wire:model="showPasswordResetModal" box-class="md:w-[520px]">
        <div class="mb-4">
            <h3 class="text-sm font-semibold text-base-content">Reset Password Pengguna</h3>
            @if ($this->passwordResetUser)
                <p class="mt-1 text-sm text-base-content/60">Tetapkan password baru untuk <strong>{{ $this->passwordResetUser->name }}</strong> ({{ $this->passwordResetUser->email }}).</p>
            @endif
        </div>

        <form wire:submit="resetUserPassword" class="space-y-4">
            <x-mary-input wire:model="password" label="Password Baru" type="password" autocomplete="new-password" required />
            <x-mary-input wire:model="password_confirmation" label="Konfirmasi Password Baru" type="password" autocomplete="new-password" required />
            <p class="text-sm text-base-content/60">Password lama tidak dapat dilihat. Sampaikan password baru kepada pengguna melalui kanal aman.</p>

            <div class="flex justify-end gap-2 pt-2">
                <x-mary-button type="button" label="Batal" class="btn-ghost btn-sm" wire:click="cancelPasswordReset" wire:loading.attr="disabled" wire:target="resetUserPassword" />
                <x-mary-button type="submit" label="Simpan Password" class="btn-primary btn-sm" spinner="resetUserPassword" />
            </div>
        </form>
    </x-mary-modal>
</div>
