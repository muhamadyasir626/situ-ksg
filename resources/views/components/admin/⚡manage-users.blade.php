<?php

use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;
use Spatie\Permission\Models\Role;

new #[Layout('layouts.app')] #[Title('Manajemen Pengguna')] class extends Component {
    use WithPagination, Toast;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public ?int $roleId = null;
    public ?int $unitId = null;

    public bool $showModal = false;

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

        $this->resetForm();
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('create', User::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'roleId' => ['required', 'exists:roles,id'],
            'unitId' => ['nullable', 'exists:units,id'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'unit_id' => $validated['unitId'],
            'email_verified_at' => now(),
        ]);

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
        $targetUser->syncRoles([$role->name]);

        $this->success("Peran diubah menjadi {$role->name}.");
    }

    public function assignUnit(int $userId, int $unitId): void
    {
        $targetUser = User::findOrFail($userId);
        $this->authorize('update', $targetUser);

        $unit = Unit::findOrFail($unitId);
        $targetUser->update(['unit_id' => $unit->id]);

        $this->success("Unit diubah menjadi {$unit->name}.");
    }

    private function resetForm(): void
    {
        $this->name = '';
        $this->email = '';
        $this->password = '';
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
</div>
