<?php

namespace Tests\Feature\Auth;

use App\User;
use Tests\TestCase;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected $verificationVerifyRouteName = 'verification.verify';

    protected function successfulVerificationRoute()
    {
        return route('home');
    }

    protected function verificationNoticeRoute()
    {
        return route('verification.notice');
    }

    protected function validVerificationVerifyRoute($id, $hash)
    {
        return URL::signedRoute($this->verificationVerifyRouteName, ['id' => $id, 'hash' => $hash]);
    }

    protected function invalidVerificationVerifyRoute($id)
    {
        return route($this->verificationVerifyRouteName, ['id' => $id, 'hash' => 'invalid-hash']);
    }

    protected function verificationResendRoute()
    {
        return route('verification.resend');
    }

    protected function loginRoute()
    {
        return route('login');
    }

    public function testGuestCannotSeeTheVerificationNotice()
    {
        $response = $this->get($this->verificationNoticeRoute());

        $response->assertRedirect($this->loginRoute());
    }

    public function testUserSeesTheVerificationNoticeWhenNotVerified()
    {
        $user = factory(User::class)->create([
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user)->get($this->verificationNoticeRoute());

        $response->assertStatus(200);
        $response->assertViewIs('auth.verify');
    }

    public function testVerifiedUserIsRedirectedHomeWhenVisitingVerificationNoticeRoute()
    {
        $user = factory(User::class)->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get($this->verificationNoticeRoute());

        $response->assertRedirect($this->successfulVerificationRoute());
    }

    public function testGuestCannotSeeTheVerificationVerifyRoute()
    {
        $user = factory(User::class)->create([
            'id' => 1,
            'email_verified_at' => null,
        ]);

        $response = $this->get($this->validVerificationVerifyRoute($user->id, sha1($user->getEmailForVerification())));

        $response->assertRedirect($this->loginRoute());
    }

    public function testUserCannotVerifyOthers()
    {
        $user = factory(User::class)->create([
            'id' => 1,
            'email_verified_at' => null,
        ]);

        $user2 = factory(User::class)->create(['id' => 2, 'email_verified_at' => null]);

        $response = $this->actingAs($user)->get($this->validVerificationVerifyRoute($user2->id, sha1($user2->getEmailForVerification())));

        $response->assertForbidden();
        $this->assertFalse($user2->fresh()->hasVerifiedEmail());
    }

    public function testUserIsRedirectedToCorrectRouteWhenAlreadyVerified()
    {
        $user = factory(User::class)->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get($this->validVerificationVerifyRoute($user->id, sha1($user->getEmailForVerification())));

        $response->assertRedirect($this->successfulVerificationRoute());
    }

    public function testForbiddenIsReturnedWhenSignatureIsInvalidInVerificationVerifyRoute()
    {
        $user = factory(User::class)->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get($this->invalidVerificationVerifyRoute($user->id));

        $response->assertStatus(403);
    }

    public function testUserCanVerifyThemselves()
    {
        $user = factory(User::class)->create([
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user)->get($this->validVerificationVerifyRoute($user->id, sha1($user->getEmailForVerification())));

        $response->assertRedirect($this->successfulVerificationRoute());
        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function testGuestCannotResendAVerificationEmail()
    {
        $response = $this->get($this->verificationResendRoute());

        $response->assertRedirect($this->loginRoute());
    }

    public function testUserIsRedirectedToCorrectRouteIfAlreadyVerified()
    {
        $user = factory(User::class)->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get($this->verificationResendRoute());

        $response->assertRedirect($this->successfulVerificationRoute());
    }

    public function testUserCanResendAVerificationEmail()
    {
        Notification::fake();
        $user = factory(User::class)->create([
            'email_verified_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->from($this->verificationNoticeRoute())
            ->get($this->verificationResendRoute());

        Notification::assertSentTo($user, VerifyEmail::class);
        $response->assertRedirect($this->verificationNoticeRoute());
    }
}
