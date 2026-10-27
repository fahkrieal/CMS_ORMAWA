<?php

namespace Tests\Feature\Auth;

use App\Models\Activity;
use App\Models\ActivityLog;
use App\Models\Notification;
use App\Models\Organization;
use App\Models\Post;
use App\Models\User;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MfaAndExtendedSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $orgA;
    protected Organization $orgB;
    protected User $superAdmin;
    protected User $adminOrgA;
    protected User $adminOrgB;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin Organisasi', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Editor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Kontributor', 'guard_name' => 'web']);

        $this->superAdmin = User::factory()->create([
            'name' => 'Super Admin Sec',
            'email' => 'superadmin@sec-test.iti.ac.id',
            'password' => bcrypt('Password123!'),
            'organization_id' => null,
            'status' => 'active',
        ]);
        $this->superAdmin->assignRole('Super Admin');

        $this->orgA = Organization::create([
            'nama' => 'HIMATIF Sec A',
            'jenis' => 'HMPS',
            'subdomain' => 'himatif-sec-test',
            'status' => 'active',
        ]);

        $this->orgB = Organization::create([
            'nama' => 'BEM Sec B',
            'jenis' => 'BEM',
            'subdomain' => 'bem-sec-test',
            'status' => 'active',
        ]);

        $this->adminOrgA = User::factory()->create([
            'name' => 'Admin Org A',
            'email' => 'adminA@sec-test.iti.ac.id',
            'password' => bcrypt('Password123!'),
            'organization_id' => $this->orgA->id,
            'status' => 'active',
        ]);
        $this->adminOrgA->assignRole('Admin Organisasi');

        $this->adminOrgB = User::factory()->create([
            'name' => 'Admin Org B',
            'email' => 'adminB@sec-test.iti.ac.id',
            'password' => bcrypt('Password123!'),
            'organization_id' => $this->orgB->id,
            'status' => 'active',
        ]);
        $this->adminOrgB->assignRole('Admin Organisasi');
    }

    // ─── 1. Strong Authentication & Anti-Enumeration ─────────────────────────

    public function test_login_failure_is_generic_and_prevents_user_enumeration(): void
    {
        // Non-existent user
        $response1 = $this->postJson('/api/login', [
            'email' => 'ghost@sec-test.iti.ac.id',
            'password' => 'WrongPassword!',
        ]);
        $response1->assertStatus(422);
        $response1->assertJsonValidationErrors(['email']);
        $this->assertEquals(
            'Kredensial yang diberikan tidak cocok.',
            $response1->json('errors.email.0')
        );

        // Existing user with wrong password
        $response2 = $this->postJson('/api/login', [
            'email' => $this->adminOrgA->email,
            'password' => 'WrongPassword!',
        ]);
        $response2->assertStatus(422);
        $response2->assertJsonValidationErrors(['email']);
        $this->assertEquals(
            'Kredensial yang diberikan tidak cocok.',
            $response2->json('errors.email.0')
        );
    }

    // ─── 2. MFA Challenge & Enforcement ──────────────────────────────────────

    public function test_user_with_mfa_requires_second_factor_verification(): void
    {
        $secret = TotpService::generateSecret();
        $recoveryCodes = TotpService::generateRecoveryCodes(8);
        $hashedCodes = TotpService::hashRecoveryCodes($recoveryCodes);

        $mfaUser = User::factory()->create([
            'name' => 'MFA Protected User',
            'email' => 'mfa-user@sec-test.iti.ac.id',
            'password' => bcrypt('Password123!'),
            'organization_id' => $this->orgA->id,
            'status' => 'active',
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $hashedCodes,
            'two_factor_confirmed_at' => now(),
        ]);
        $mfaUser->assignRole('Admin Organisasi');

        // Step 1: Initial Login -> Returns mfa_required without access_token
        $loginRes = $this->postJson('/api/login', [
            'email' => $mfaUser->email,
            'password' => 'Password123!',
        ]);

        $loginRes->assertStatus(200);
        $loginRes->assertJson([
            'mfa_required' => true,
        ]);
        $this->assertNull($loginRes->json('access_token'));
        $mfaToken = $loginRes->json('mfa_token');
        $this->assertNotEmpty($mfaToken);

        // Attempting to access protected endpoint as guest must fail
        $this->getJson('/api/me')->assertStatus(401);

        // Step 2a: Invalid TOTP verification fails
        $invalidVerify = $this->postJson('/api/mfa/verify', [
            'mfa_token' => $mfaToken,
            'code' => '000000',
        ]);
        $invalidVerify->assertStatus(422);
        $invalidVerify->assertJsonValidationErrors(['code']);

        // Step 2b: Valid TOTP verification succeeds and grants token
        $validCode = TotpService::generateCode($secret);
        $validVerify = $this->postJson('/api/mfa/verify', [
            'mfa_token' => $mfaToken,
            'code' => $validCode,
        ]);

        $validVerify->assertStatus(200);
        $token = $validVerify->json('access_token');
        $this->assertNotEmpty($token);
        $this->assertEquals('Bearer', $validVerify->json('token_type'));

        // Access protected endpoint with granted token
        $meRes = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/me');
        $meRes->assertStatus(200);
        $meRes->assertJson([
            'user' => [
                'id' => $mfaUser->id,
                'email' => $mfaUser->email,
                'two_factor_enabled' => true,
            ]
        ]);
    }

    public function test_mfa_verification_with_recovery_code_is_single_use(): void
    {
        $secret = TotpService::generateSecret();
        $recoveryCodes = TotpService::generateRecoveryCodes(8);
        $hashedCodes = TotpService::hashRecoveryCodes($recoveryCodes);

        $mfaUser = User::factory()->create([
            'name' => 'MFA Recovery User',
            'email' => 'mfa-recovery@sec-test.iti.ac.id',
            'password' => bcrypt('Password123!'),
            'organization_id' => $this->orgA->id,
            'status' => 'active',
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $hashedCodes,
            'two_factor_confirmed_at' => now(),
        ]);
        $mfaUser->assignRole('Admin Organisasi');

        // Step 1: Login
        $loginRes = $this->postJson('/api/login', [
            'email' => $mfaUser->email,
            'password' => 'Password123!',
        ]);
        $mfaToken = $loginRes->json('mfa_token');

        // Use the first plain recovery code
        $chosenCode = $recoveryCodes[0];

        $verifyRes = $this->postJson('/api/mfa/verify', [
            'mfa_token' => $mfaToken,
            'code' => $chosenCode,
        ]);
        $verifyRes->assertStatus(200);
        $this->assertNotEmpty($verifyRes->json('access_token'));

        // Fresh login challenge to test re-use of consumed recovery code
        $loginRes2 = $this->postJson('/api/login', [
            'email' => $mfaUser->email,
            'password' => 'Password123!',
        ]);
        $mfaToken2 = $loginRes2->json('mfa_token');

        // Re-using the same code must now be rejected
        $reuseRes = $this->postJson('/api/mfa/verify', [
            'mfa_token' => $mfaToken2,
            'code' => $chosenCode,
        ]);
        $reuseRes->assertStatus(422);
        $reuseRes->assertJsonValidationErrors(['code']);
    }

    public function test_mfa_totp_code_cannot_be_replayed_once_used(): void
    {
        $secret = TotpService::generateSecret();
        $mfaUser = User::factory()->create([
            'name' => 'MFA Replay Test User',
            'email' => 'mfa-replay@sec-test.iti.ac.id',
            'password' => bcrypt('Password123!'),
            'organization_id' => $this->orgA->id,
            'status' => 'active',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);
        $mfaUser->assignRole('Admin Organisasi');

        // First login attempt
        $loginRes1 = $this->postJson('/api/login', [
            'email' => $mfaUser->email,
            'password' => 'Password123!',
        ]);
        $mfaToken1 = $loginRes1->json('mfa_token');

        $currentOtp = TotpService::generateCode($secret);

        // Verification 1 succeeds
        $verifyRes1 = $this->postJson('/api/mfa/verify', [
            'mfa_token' => $mfaToken1,
            'code' => $currentOtp,
        ]);
        $verifyRes1->assertStatus(200);

        // Second login attempt using the EXACT SAME OTP within the same time window
        $loginRes2 = $this->postJson('/api/login', [
            'email' => $mfaUser->email,
            'password' => 'Password123!',
        ]);
        $mfaToken2 = $loginRes2->json('mfa_token');

        $verifyRes2 = $this->postJson('/api/mfa/verify', [
            'mfa_token' => $mfaToken2,
            'code' => $currentOtp,
        ]);
        $verifyRes2->assertStatus(422);
        $verifyRes2->assertJsonValidationErrors(['code']);
        $this->assertStringContainsString('sudah pernah digunakan', $verifyRes2->json('errors.code.0'));
    }

    public function test_mfa_brute_force_is_throttled_by_rate_limiter(): void
    {
        $mfaToken = 'test-mfa-dummy-token';

        // 5 attempts allowed by throttle:5,1
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/mfa/verify', [
                'mfa_token' => $mfaToken,
                'code' => '999999',
            ]);
        }

        // 6th attempt must be throttled with HTTP 429 Too Many Requests
        $throttledRes = $this->postJson('/api/mfa/verify', [
            'mfa_token' => $mfaToken,
            'code' => '999999',
        ]);

        $throttledRes->assertStatus(429);
    }

    public function test_mfa_setup_and_confirmation_with_totp_and_recovery_code(): void
    {
        // 1. Setup MFA
        $setupRes = $this->actingAs($this->superAdmin, 'sanctum')->postJson('/api/mfa/setup');
        $setupRes->assertStatus(200);
        $secret = $setupRes->json('secret');
        $recoveryCodes = $setupRes->json('recovery_codes');

        $this->assertNotEmpty($secret);
        $this->assertCount(8, $recoveryCodes);

        // 2. Refresh user & verify confirm with valid TOTP code
        $validOtp = TotpService::generateCode($secret);
        $confirmRes = $this->actingAs($this->superAdmin->fresh(), 'sanctum')->postJson('/api/mfa/confirm', [
            'code' => $validOtp,
        ]);
        $confirmRes->assertStatus(200);
        $this->assertNotNull($this->superAdmin->fresh()->two_factor_confirmed_at);

        // 3. Setup again and confirm with recovery code
        $setupRes2 = $this->actingAs($this->superAdmin, 'sanctum')->postJson('/api/mfa/setup');
        $recoveryCodes2 = $setupRes2->json('recovery_codes');

        $confirmRecoveryRes = $this->actingAs($this->superAdmin->fresh(), 'sanctum')->postJson('/api/mfa/confirm', [
            'code' => $recoveryCodes2[0],
        ]);
        $confirmRecoveryRes->assertStatus(200);
        $this->assertNotNull($this->superAdmin->fresh()->two_factor_confirmed_at);
        $this->assertCount(7, $this->superAdmin->fresh()->two_factor_recovery_codes);
    }

    // ─── 3. Session Lifetime & Expiration ────────────────────────────────────

    public function test_login_supports_short_lived_and_remember_session_lifetimes(): void
    {
        // 1. Explicit short session (120 minutes)
        $shortLogin = $this->postJson('/api/login', [
            'email' => $this->adminOrgA->email,
            'password' => 'Password123!',
            'remember' => false,
        ]);
        $shortLogin->assertStatus(200);

        $shortTokenId = $this->adminOrgA->tokens()->latest('id')->first();
        $this->assertNotNull($shortTokenId->expires_at);
        $shortExpiresAt = \Carbon\Carbon::parse($shortTokenId->expires_at);
        $this->assertTrue(abs($shortExpiresAt->diffInMinutes(now()->addMinutes(120))) <= 1);

        // 2. Standard / Remembered session (1440 minutes / 24 hours)
        $standardLogin = $this->postJson('/api/login', [
            'email' => $this->adminOrgA->email,
            'password' => 'Password123!',
            'remember' => true,
        ]);
        $standardLogin->assertStatus(200);

        $standardTokenId = $this->adminOrgA->tokens()->latest('id')->first();
        $this->assertNotNull($standardTokenId->expires_at);
        $standardExpiresAt = \Carbon\Carbon::parse($standardTokenId->expires_at);
        $this->assertTrue(abs($standardExpiresAt->diffInMinutes(now()->addMinutes(1440))) <= 1);
    }

    public function test_expired_token_is_rejected(): void
    {
        $expiredToken = $this->adminOrgA->createToken(
            'expired_token_unit',
            ['*'],
            now()->subMinutes(10)
        )->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$expiredToken}")
            ->getJson('/api/me');

        $response->assertStatus(401);
    }

    // ─── 4. Logout & Lifecycle Invalidation ──────────────────────────────────

    public function test_logout_current_device_and_all_devices(): void
    {
        // Create 2 active tokens
        $token1 = $this->adminOrgA->createToken('device_1')->plainTextToken;
        $token2 = $this->adminOrgA->createToken('device_2')->plainTextToken;

        $this->assertEquals(2, $this->adminOrgA->tokens()->count());

        // Logout only device 1
        $logoutRes1 = $this->withHeader('Authorization', "Bearer {$token1}")
            ->postJson('/api/logout', ['all_devices' => false]);
        $logoutRes1->assertStatus(200);

        app('auth')->forgetGuards();

        // Device 1 token is revoked
        $this->withHeader('Authorization', "Bearer {$token1}")
            ->getJson('/api/me')
            ->assertStatus(401);

        // Device 2 token is still active
        app('auth')->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$token2}")
            ->getJson('/api/me')
            ->assertStatus(200);

        // Logout all devices from device 2
        app('auth')->forgetGuards();
        $logoutAllRes = $this->withHeader('Authorization', "Bearer {$token2}")
            ->postJson('/api/logout', ['all_devices' => true]);
        $logoutAllRes->assertStatus(200);

        // All tokens purged
        $this->assertEquals(0, $this->adminOrgA->tokens()->count());

        app('auth')->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$token2}")
            ->getJson('/api/me')
            ->assertStatus(401);
    }

    public function test_password_update_revokes_other_tokens(): void
    {
        $currentToken = $this->adminOrgA->createToken('current_session')->plainTextToken;
        $otherToken = $this->adminOrgA->createToken('other_session')->plainTextToken;

        $this->assertEquals(2, $this->adminOrgA->tokens()->count());

        $updateRes = $this->withHeader('Authorization', "Bearer {$currentToken}")
            ->putJson('/api/password', [
                'current_password' => 'Password123!',
                'password' => 'NewPassword456!',
                'password_confirmation' => 'NewPassword456!',
            ]);

        $updateRes->assertStatus(200);

        // Other session token must be revoked
        app('auth')->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$otherToken}")
            ->getJson('/api/me')
            ->assertStatus(401);

        // Current session token remains valid
        app('auth')->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$currentToken}")
            ->getJson('/api/me')
            ->assertStatus(200);
    }

    public function test_password_reset_revokes_all_existing_tokens(): void
    {
        $activeToken = $this->adminOrgA->createToken('pre_reset_session')->plainTextToken;
        $this->assertEquals(1, $this->adminOrgA->tokens()->count());

        // Generate reset token via PasswordBroker
        $brokerToken = Password::broker()->createToken($this->adminOrgA);

        $resetRes = $this->postJson('/api/reset-password', [
            'token' => $brokerToken,
            'email' => $this->adminOrgA->email,
            'password' => 'BrandNewPassword789!',
            'password_confirmation' => 'BrandNewPassword789!',
        ]);

        $resetRes->assertStatus(200);

        // Pre-reset token is purged
        $this->assertEquals(0, $this->adminOrgA->tokens()->count());

        app('auth')->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$activeToken}")
            ->getJson('/api/me')
            ->assertStatus(401);

        // Reusing the reset token must fail (single-use)
        $reuseReset = $this->postJson('/api/reset-password', [
            'token' => $brokerToken,
            'email' => $this->adminOrgA->email,
            'password' => 'BrandNewPassword999!',
            'password_confirmation' => 'BrandNewPassword999!',
        ]);
        $reuseReset->assertStatus(422);
    }

    // ─── 5. Inactive User Middleware Rejection & Token Purge ─────────────────

    public function test_inactive_user_token_is_rejected_and_revoked_by_middleware(): void
    {
        $token = $this->adminOrgA->createToken('active_before_deactivation')->plainTextToken;

        // User is deactivated by administrator
        $this->adminOrgA->status = 'inactive';
        $this->adminOrgA->save();

        // Middleware EnsureActiveOrganization must reject with 403 and delete token
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/organization/dashboard');

        $response->assertStatus(403);
        $response->assertJson([
            'message' => 'Akun Anda dinonaktifkan. Silakan hubungi admin.',
        ]);

        // Token must now be purged from database
        app('auth')->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertStatus(401);
    }

    // ─── 6. Multi-Tenant IDOR / BOLA Prevention ──────────────────────────────

    public function test_cross_organization_idor_is_prevented(): void
    {
        // Create an activity owned by Org B
        $activityOrgB = Activity::create([
            'user_id' => $this->adminOrgB->id,
            'nama_kegiatan' => 'Kegiatan Rahasia B',
            'deskripsi' => 'Deskripsi B',
            'tanggal_mulai' => now(),
            'status' => 'terlaksana',
            'organization_id' => $this->orgB->id,
        ]);

        // Admin Org A authenticates
        $tokenA = $this->adminOrgA->createToken('admin_a_token')->plainTextToken;

        // Attempt to view Org B activity via show endpoint
        $showRes = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->getJson("/api/activities/{$activityOrgB->id}");

        // Must be rejected with 403 Forbidden or 404 Not Found (tenant scoped)
        $this->assertTrue(in_array($showRes->status(), [403, 404]));

        // Attempt to update Org B activity
        $updateRes = $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->putJson("/api/activities/{$activityOrgB->id}", [
                'nama_kegiatan' => 'Hacked by Org A',
            ]);

        $this->assertTrue(in_array($updateRes->status(), [403, 404]));
    }

    // ─── 7. Monitoring & Anomaly Detection ───────────────────────────────────

    public function test_repeated_login_failures_trigger_security_alert_and_super_admin_notification(): void
    {
        $targetEmail = 'targeted@sec-test.iti.ac.id';
        $targetIp = '192.168.1.100';

        // 5 consecutive login failures from the same IP/target
        for ($i = 1; $i <= 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => $targetIp])
                ->postJson('/api/login', [
                    'email' => $targetEmail,
                    'password' => 'WrongPassword!',
                ]);
        }

        // Verify security alert activity log was created
        $alertLog = ActivityLog::where('action', 'security_alert')->first();
        $this->assertNotNull($alertLog);
        $this->assertEquals('authentication', $alertLog->module);
        $this->assertEquals(5, $alertLog->metadata['failed_count'] ?? 0);

        // Verify Super Admin received in-app notification
        $superAdminNotification = Notification::where('user_id', $this->superAdmin->id)
            ->where('title', 'Peringatan Keamanan: Percobaan Login Gagal Berulang')
            ->first();

        $this->assertNotNull($superAdminNotification);
        $this->assertStringContainsString($targetEmail, $superAdminNotification->message);
    }
}
