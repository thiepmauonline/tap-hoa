<?php

namespace Tests\Feature;

use App\Livewire\Pos\Cashier;
use App\Livewire\Settings\Index;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\Concerns\CreatesStoreData;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use CreatesStoreData, RefreshDatabase;

    public function test_owner_updates_only_their_store_and_receipt_uses_updated_details(): void
    {
        $user = $this->storeUser();
        $other = $this->storeUser();
        $this->actingAs($user)->get(route('settings.index'))->assertOk();
        Livewire::test(Index::class)
            ->set('storeName', 'Tạp hóa Bình An')
            ->set('storePhone', '0909999999')
            ->set('storeAddress', '12 Nguyễn Trãi')
            ->call('saveStore')->assertHasNoErrors()->assertSet('storeName', 'Tạp hóa Bình An')
            ->assertSee('Đã lưu thông tin cửa hàng');
        $this->assertSame('Tạp hóa Bình An', $user->store->fresh()->name);
        $this->assertSame('Cửa hàng thử nghiệm', $other->store->fresh()->name);

        $product = $this->stockProduct($user);
        Livewire::actingAs($user->fresh())->test(Cashier::class)
            ->call('addToCart', $product->id)->call('checkout')->assertHasNoErrors()
            ->assertSee('Tạp hóa Bình An')->assertSee('0909999999')->assertSee('12 Nguyễn Trãi');
    }

    public function test_staff_and_manager_cannot_change_store_details(): void
    {
        foreach (['staff', 'manager'] as $role) {
            $user = $this->storeUser($role);
            Livewire::actingAs($user)->test(Index::class)
                ->set('storeName', 'Tên không được phép')
                ->call('saveStore')->assertForbidden();
            $this->assertSame('Cửa hàng thử nghiệm', $user->store->fresh()->name);
        }
    }

    public function test_password_change_requires_current_password_and_matching_confirmation(): void
    {
        $user = $this->storeUser('staff');
        $user->update(['password' => Hash::make('current-password')]);
        $form = Livewire::actingAs($user)->test(Index::class)
            ->set('currentPassword', 'wrong-password')
            ->set('newPassword', 'new-password-123')
            ->set('newPasswordConfirmation', 'new-password-123')
            ->call('changePassword')->assertHasErrors('currentPassword');
        $this->assertTrue(Hash::check('current-password', $user->fresh()->password));

        $form->set('currentPassword', 'current-password')
            ->set('newPasswordConfirmation', 'different-password')
            ->call('changePassword')->assertHasErrors('newPasswordConfirmation');
        $form->set('newPasswordConfirmation', 'new-password-123')
            ->call('changePassword')->assertHasNoErrors()->assertSee('Đã đổi mật khẩu thành công.')
            ->assertSet('currentPassword', '')->assertSet('newPassword', '')->assertSet('newPasswordConfirmation', '');
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertFalse(Hash::check('current-password', $user->fresh()->password));
    }
}
