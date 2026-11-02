<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Laravel\Telescope\Telescope;
use Tests\TestCase;

/**
 * Protects Telescope access outside `local`.
 *
 * Regression: the viewTelescope gate admitted any human user, exposing every
 * workspace's request payloads, queries and logs to anyone who could sign up.
 * Access now requires an explicit TELESCOPE_ALLOWED_EMAILS allowlist; a
 * workspace admin role is not enough.
 */
class TelescopeGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['env'] = 'production';
    }

    public function test_normal_workspace_member_is_denied_in_production(): void
    {
        config(['telescope.allowed_emails' => 'ops@example.com']);

        // actingAs() makes this user an admin of the test workspace, which
        // must still not grant Telescope access.
        $member = User::factory()->create(['email' => 'member@example.com']);
        $this->actingAs($member);

        $this->assertFalse($this->telescopeAllows($member));
    }

    public function test_empty_allowlist_denies_everyone_in_production(): void
    {
        config(['telescope.allowed_emails' => '']);

        $this->assertFalse($this->telescopeAllows(User::factory()->create()));
    }

    public function test_allowlisted_human_is_admitted_case_insensitively(): void
    {
        config(['telescope.allowed_emails' => ' Ops@Example.com , other@example.com']);

        $this->assertTrue($this->telescopeAllows(User::factory()->create(['email' => 'ops@example.com'])));
    }

    public function test_agent_is_denied_even_if_email_is_allowlisted(): void
    {
        config(['telescope.allowed_emails' => 'agent@example.com']);

        $agent = User::factory()->agent()->create(['email' => 'agent@example.com']);

        $this->assertFalse($this->telescopeAllows($agent));
    }

    /**
     * Run Telescope's real authorization callback (gate + local bypass) for
     * a request authenticated as $user. Gate::check resolves the actor from
     * the auth guard, so authenticate there as well as on the request.
     */
    private function telescopeAllows(User $user): bool
    {
        $this->be($user);

        $request = Request::create('/telescope');
        $request->setUserResolver(fn () => $user);

        return Telescope::check($request);
    }
}
