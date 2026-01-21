<?php

namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class AuthenticationTest extends DuskTestCase
{
    use DatabaseMigrations;

    /**
     * Test that the login page displays correctly.
     */
    public function test_login_page_displays(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->pause(1000)
                ->assertSee('Email')
                ->assertSee('Password')
                ->assertSee('Log in')
                ->assertSee('Remember me');
        });
    }

    /**
     * Test that users can login with valid credentials.
     */
    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/login')
                ->pause(1000)
                ->type('#email', 'test@example.com')
                ->type('#password', 'password')
                ->press('Log in')
                ->waitForLocation('/', 10)
                ->assertPathIs('/');
        });
    }

    /**
     * Test that users cannot login with invalid credentials.
     */
    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/login')
                ->pause(1000)
                ->type('#email', 'test@example.com')
                ->type('#password', 'wrong-password')
                ->press('Log in')
                ->pause(1000)
                ->assertPathIs('/login')
                ->assertSee('credentials');
        });
    }

    /**
     * Test that users can logout via the Dusk logout route.
     */
    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/')
                ->pause(2000)
                ->assertAuthenticated()
                ->visit('/_dusk/logout')
                ->assertGuest();
        });
    }

    /**
     * Test that the register page displays correctly.
     */
    public function test_register_page_displays(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->pause(1000)
                ->assertSee('Name')
                ->assertSee('Email')
                ->assertSee('Password')
                ->assertSee('Confirm Password')
                ->assertSee('Register');
        });
    }

    /**
     * Test that users can register.
     */
    public function test_user_can_register(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->pause(1000)
                ->type('#name', 'Test User')
                ->type('#email', 'newuser@example.com')
                ->type('#password', 'password123')
                ->type('#password_confirmation', 'password123')
                ->press('Register')
                ->pause(3000)  // Wait for registration to complete
                ->assertPathIsNot('/register');  // Should redirect away from register
        });
    }

    /**
     * Test that password reset page displays correctly.
     */
    public function test_password_reset_page_displays(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/forgot-password')
                ->pause(1000)
                ->assertSee('Forgot your password')
                ->assertSee('Email')
                ->assertSee('Email Password Reset Link');
        });
    }

    /**
     * Test remember me checkbox is present.
     */
    public function test_remember_me_checkbox_present(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/login')
                ->pause(1000)
                ->assertPresent('input[name="remember"]')
                ->assertSee('Remember me');
        });
    }

    /**
     * Test that unverified users see verification notice.
     */
    public function test_email_verification_notice_displayed(): void
    {
        $user = User::factory()->unverified()->create([
            'email' => 'unverified@example.com',
        ]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/verify-email')
                ->pause(1000)
                ->assertPathIs('/verify-email')
                ->assertSee('verify');
        });
    }
}
