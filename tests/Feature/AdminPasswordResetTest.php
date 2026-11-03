<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdminPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
        $this->target = User::factory()->create(['remember_token' => 'old-remember-token']);
        $this->actingAs($this->admin);
    }

    public function test_admin_can_open_modal_and_reset_password_with_a_safe_audit(): void
    {
        Event::fake([PasswordReset::class]);
        $oldHash = $this->target->password;
        $resetToken = Password::broker()->createToken($this->target);
        $password = 'PasswordBaru!2026';

        Livewire::test('admin.manage-users')
            ->assertSee('Reset Password')
            ->call('openPasswordReset', $this->target->id)
            ->assertSet('showPasswordResetModal', true)
            ->assertSet('passwordResetUserId', $this->target->id)
            ->assertSee($this->target->email)
            ->set('password', $password)
            ->set('password_confirmation', $password)
            ->call('resetUserPassword')
            ->assertHasNoErrors()
            ->assertSet('showPasswordResetModal', false)
            ->assertSet('passwordResetUserId', null)
            ->assertSet('password', '')
            ->assertSet('password_confirmation', '');

        $this->target->refresh();
        $this->assertTrue(Hash::check($password, $this->target->password));
        $this->assertNotSame($oldHash, $this->target->password);
        $this->assertNotSame('old-remember-token', $this->target->remember_token);
        $this->assertFalse(Password::broker()->tokenExists($this->target, $resetToken));

        $audit = Activity::where('event', 'password_reset')->sole();
        $this->assertTrue($audit->subject->is($this->target));
        $this->assertTrue($audit->causer->is($this->admin));
        $this->assertSame('user', $audit->log_name);
        $this->assertStringNotContainsString($password, $audit->toJson());
        $this->assertStringNotContainsString($this->target->password, $audit->toJson());
        $this->assertStringNotContainsString($this->target->remember_token, $audit->toJson());
        Event::assertDispatched(PasswordReset::class, fn ($event) => $event->user->is($this->target));
    }

    #[DataProvider('invalidPasswords')]
    public function test_invalid_password_does_not_change_credentials_or_create_audit(string $password, string $confirmation): void
    {
        $oldHash = $this->target->password;

        Livewire::test('admin.manage-users')
            ->call('openPasswordReset', $this->target->id)
            ->set('password', $password)
            ->set('password_confirmation', $confirmation)
            ->call('resetUserPassword')
            ->assertHasErrors(['password'])
            ->assertSet('showPasswordResetModal', true);

        $this->assertSame($oldHash, $this->target->fresh()->password);
        $this->assertSame('old-remember-token', $this->target->fresh()->remember_token);
        $this->assertSame(0, Activity::where('event', 'password_reset')->count());
    }

    public static function invalidPasswords(): array
    {
        return [
            'required' => ['', ''],
            'too short' => ['short', 'short'],
            'confirmation mismatch' => ['PasswordBaru!2026', 'PasswordLain!2026'],
        ];
    }

    public function test_cancelling_clears_password_and_target_without_saving(): void
    {
        $oldHash = $this->target->password;

        Livewire::test('admin.manage-users')
            ->call('openPasswordReset', $this->target->id)
            ->set('password', 'PasswordBaru!2026')
            ->set('password_confirmation', 'PasswordBaru!2026')
            ->call('cancelPasswordReset')
            ->assertSet('showPasswordResetModal', false)
            ->assertSet('passwordResetUserId', null)
            ->assertSet('password', '')
            ->assertSet('password_confirmation', '');

        $this->assertSame($oldHash, $this->target->fresh()->password);
    }

    public function test_missing_target_cannot_be_opened(): void
    {
        $this->expectException(ModelNotFoundException::class);

        Livewire::test('admin.manage-users')
            ->call('openPasswordReset', 999999);
    }

    public function test_dismissing_modal_clears_sensitive_state(): void
    {
        Livewire::test('admin.manage-users')
            ->call('openPasswordReset', $this->target->id)
            ->set('password', 'PasswordBaru!2026')
            ->set('password_confirmation', 'PasswordBaru!2026')
            ->set('showPasswordResetModal', false)
            ->assertSet('passwordResetUserId', null)
            ->assertSet('password', '')
            ->assertSet('password_confirmation', '');
    }

    public function test_credentials_are_rolled_back_if_audit_cannot_be_saved(): void
    {
        $oldHash = $this->target->password;
        $resetToken = Password::broker()->createToken($this->target);

        Activity::creating(function (Activity $activity): void {
            if ($activity->event === 'password_reset') {
                throw new RuntimeException('Audit unavailable');
            }
        });

        $component = Livewire::test('admin.manage-users')
            ->call('openPasswordReset', $this->target->id)
            ->set('password', 'PasswordBaru!2026')
            ->set('password_confirmation', 'PasswordBaru!2026');

        try {
            $component->call('resetUserPassword');
            $this->fail('Reset should fail when its audit cannot be saved.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }

        $this->assertSame($oldHash, $this->target->fresh()->password);
        $this->assertSame('old-remember-token', $this->target->fresh()->remember_token);
        $this->assertTrue(Password::broker()->tokenExists($this->target, $resetToken));
    }

    public function test_target_deleted_after_opening_cannot_be_saved(): void
    {
        $component = Livewire::test('admin.manage-users')
            ->call('openPasswordReset', $this->target->id);

        $this->target->delete();

        $this->expectException(ModelNotFoundException::class);

        $component->set('password', 'PasswordBaru!2026')
            ->set('password_confirmation', 'PasswordBaru!2026')
            ->call('resetUserPassword');
    }

    public function test_reset_requires_an_opened_target(): void
    {
        $this->expectException(ModelNotFoundException::class);

        Livewire::test('admin.manage-users')
            ->call('resetUserPassword');
    }

    public function test_client_cannot_change_the_locked_target(): void
    {
        $component = Livewire::test('admin.manage-users')
            ->call('openPasswordReset', $this->target->id);

        $this->expectException(CannotUpdateLockedPropertyException::class);

        $component->set('passwordResetUserId', $this->admin->id);
    }

    public function test_authorization_is_checked_again_on_open_and_save(): void
    {
        $openComponent = Livewire::test('admin.manage-users');
        $saveComponent = Livewire::test('admin.manage-users')
            ->call('openPasswordReset', $this->target->id)
            ->set('password', 'PasswordBaru!2026')
            ->set('password_confirmation', 'PasswordBaru!2026');
        $oldHash = $this->target->password;

        $this->admin->syncRoles(['Auditor']);

        $openComponent->call('openPasswordReset', $this->target->id)->assertForbidden();
        $saveComponent->call('resetUserPassword')->assertForbidden();

        $this->assertSame($oldHash, $this->target->fresh()->password);
        $this->assertSame(0, Activity::where('event', 'password_reset')->count());
    }

    public function test_create_and_reset_modals_do_not_share_password_values(): void
    {
        Livewire::test('admin.manage-users')
            ->call('createUser')
            ->set('password', 'PasswordTambah!2026')
            ->call('openPasswordReset', $this->target->id)
            ->assertSet('showModal', false)
            ->assertSet('password', '')
            ->set('password', 'PasswordReset!2026')
            ->call('createUser')
            ->assertSet('showPasswordResetModal', false)
            ->assertSet('passwordResetUserId', null)
            ->assertSet('password', '');
    }
}
