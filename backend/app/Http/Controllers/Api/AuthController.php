<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\SecurityMonitoringService;
use App\Services\TotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Endpoint API Login (Fitur 1)
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'          => 'required|email',
            'password'       => 'required|string',
            'keep_logged_in' => 'nullable|boolean',
            'remember'       => 'nullable|boolean',
        ]);

        $user = User::with(['organization', 'roles'])->where('email', $request->email)->first();

        // Anti-timing attack: Run dummy hash check if user not found to normalize response time
        $passwordMatches = false;
        if ($user) {
            $passwordMatches = Hash::check($request->password, $user->password);
        } else {
            // Valid dummy bcrypt hash evaluation to prevent timing-based user enumeration
            Hash::check($request->password, '$2y$12$sUZqj47G1Q6zZ9mbCHURZu3Tr6Sckz6Mqzl4AQg0Kpc2y3U4l1NAC');
        }

        // Cek email dan password
        if (!$user || !$passwordMatches) {
            SecurityMonitoringService::recordFailedLogin($request->email, $request->ip());

            ActivityLogService::log(
                'login_failed',
                'authentication',
                'Login gagal: Kredensial tidak cocok',
                $user,
                ['ip' => $request->ip(), 'target_email' => $request->email],
                $user ? clone $user : null
            );

            throw ValidationException::withMessages([
                'email' => ['Kredensial yang diberikan tidak cocok.'],
            ]);
        }

        // Cek jika akun nonaktif (Fitur 9)
        if ($user->status !== 'active') {
            return response()->json([
                'message' => 'Akun Anda dinonaktifkan. Silakan hubungi admin.',
            ], 403);
        }

        // Cek jika organisasi nonaktif (Task 2)
        if ($user->organization_id && !$user->hasRole('Super Admin')) {
            $organization = $user->organization;
            if ($organization && $organization->status === 'inactive') {
                return response()->json([
                    'message' => 'Organisasi Anda sedang dinonaktifkan oleh Super Admin. Akses dashboard dan manajemen internal tidak tersedia.',
                ], 403);
            }
        }

        // Clear failed login counter upon valid primary credentials
        SecurityMonitoringService::recordSuccessfulLogin($request->email, $request->ip());

        // MFA / Two-Factor Authentication Check
        if ($user->hasTwoFactorEnabled()) {
            $mfaTicket = (string) Str::uuid();
            $remember = $request->boolean('keep_logged_in') || $request->boolean('remember');

            // Challenge valid for 5 minutes
            Cache::put('mfa_pending_' . $mfaTicket, [
                'user_id'  => $user->id,
                'remember' => $remember,
            ], now()->addMinutes(5));

            ActivityLogService::log('mfa_challenge', 'authentication', 'MFA challenge disajikan', $user, null, clone $user);

            return response()->json([
                'status'       => 'mfa_required',
                'mfa_required' => true,
                'mfa_token'    => $mfaTicket,
                'message'      => 'Autentikasi dua faktor (MFA/TOTP) diperlukan. Masukkan kode 6 digit dari aplikasi autentikator atau kode pemulihan Anda.',
            ], 200);
        }

        // Generate Sanctum Token with Expiration based on Session Risk
        $isExplicitShortSession = ($request->has('remember') && !$request->boolean('remember')) || $request->boolean('short_session');
        $expirationMinutes = $isExplicitShortSession ? 120 : (int) config('sanctum.expiration', 1440);

        $expiresAt = now()->addMinutes($expirationMinutes);
        $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;

        ActivityLogService::log('login', 'authentication', 'User berhasil login', $user, null, clone $user);

        return response()->json([
            'message'      => 'Login berhasil!',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => [
                'id'                 => $user->id,
                'name'               => $user->name,
                'email'              => $user->email,
                'avatar'             => $user->avatar,
                'role'               => $user->getRoleNames()->first(),
                'permissions'        => $user->getAllPermissions()->pluck('name')->values(),
                'organization'       => $user->organization,
                'two_factor_enabled' => false,
            ]
        ]);
    }

    /**
     * Endpoint Verifikasi MFA / TOTP Login (Step 2)
     */
    public function verifyMfa(Request $request)
    {
        $request->validate([
            'mfa_token' => 'required|string',
            'code'      => 'required|string',
        ], [
            'mfa_token.required' => 'Token sesi MFA tidak valid.',
            'code.required'      => 'Kode verifikasi wajib diisi.',
        ]);

        $challenge = Cache::get('mfa_pending_' . $request->mfa_token);
        if (!$challenge || !isset($challenge['user_id'])) {
            throw ValidationException::withMessages([
                'code' => ['Sesi verifikasi dua faktor telah kedaluwarsa atau tidak valid. Silakan login kembali.'],
            ]);
        }

        $user = User::with(['organization', 'roles'])->find($challenge['user_id']);
        if (!$user || $user->status !== 'active') {
            throw ValidationException::withMessages([
                'code' => ['Akun tidak aktif atau tidak ditemukan.'],
            ]);
        }

        $inputCode = trim($request->code);
        $isValid = false;
        $usedRecovery = false;

        // Anti-Replay Check (RFC 6238 Section 5.2): Cegah penggunaan ulang kode OTP yang sama
        $cleanOtp = preg_replace('/\D/', '', $inputCode);
        $replayKey = "mfa_otp_used_{$user->id}_{$cleanOtp}";
        if (strlen($cleanOtp) === 6 && Cache::has($replayKey)) {
            throw ValidationException::withMessages([
                'code' => ['Kode OTP ini sudah pernah digunakan. Mohon gunakan kode baru yang tampil di aplikasi autentikator Anda.'],
            ]);
        }

        // 1. Verify standard TOTP code (RFC 6238) with strict +/- 30s window
        if ($user->two_factor_secret && TotpService::verify($user->two_factor_secret, $inputCode, 1)) {
            $isValid = true;
            Cache::put($replayKey, true, now()->addMinutes(2));
        }

        // 2. Fallback: Verify recovery code if not standard TOTP
        if (!$isValid && is_array($user->two_factor_recovery_codes)) {
            $remaining = TotpService::verifyAndConsumeRecoveryCode($user->two_factor_recovery_codes, $inputCode);
            if ($remaining !== null) {
                $isValid = true;
                $usedRecovery = true;
                $user->two_factor_recovery_codes = $remaining;
                $user->save();

                ActivityLogService::log(
                    'mfa_recovery_used',
                    'authentication',
                    'User login menggunakan salah satu kode pemulihan MFA',
                    $user,
                    ['remaining_recovery_codes' => count($remaining)],
                    clone $user
                );
            }
        }

        if (!$isValid) {
            SecurityMonitoringService::recordFailedLogin($user->email, $request->ip());

            ActivityLogService::log(
                'mfa_failed',
                'authentication',
                'Verifikasi autentikasi dua faktor gagal: Kode tidak cocok',
                $user,
                ['ip' => $request->ip()],
                clone $user
            );

            throw ValidationException::withMessages([
                'code' => ['Kode verifikasi dua faktor atau kode pemulihan tidak valid.'],
            ]);
        }

        // Consumed valid challenge
        Cache::forget('mfa_pending_' . $request->mfa_token);
        SecurityMonitoringService::recordSuccessfulLogin($user->email, $request->ip());

        // Token Expiration
        $isExplicitShortSession = isset($challenge['remember']) && !$challenge['remember'];
        $expirationMinutes = $isExplicitShortSession ? 120 : (int) config('sanctum.expiration', 1440);

        $expiresAt = now()->addMinutes($expirationMinutes);
        $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;

        ActivityLogService::log('login', 'authentication', 'User berhasil login dengan verifikasi MFA' . ($usedRecovery ? ' (kode pemulihan)' : ''), $user, null, clone $user);

        return response()->json([
            'message'      => 'Login berhasil!',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => [
                'id'                 => $user->id,
                'name'               => $user->name,
                'email'              => $user->email,
                'avatar'             => $user->avatar,
                'role'               => $user->getRoleNames()->first(),
                'permissions'        => $user->getAllPermissions()->pluck('name')->values(),
                'organization'       => $user->organization,
                'two_factor_enabled' => true,
            ]
        ]);
    }

    /**
     * Endpoint Setup MFA (Authenticated - Inisialisasi secret & recovery codes)
     */
    public function setupMfa(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $secret = TotpService::generateSecret(16);
        $recoveryCodes = TotpService::generateRecoveryCodes(8);
        $hashedCodes = array_map([TotpService::class, 'hashRecoveryCode'], $recoveryCodes);

        // Store unconfirmed secret on user
        $user->two_factor_secret = $secret;
        $user->two_factor_recovery_codes = $hashedCodes;
        $user->two_factor_confirmed_at = null;
        $user->save();

        $company = config('app.name', 'CMS ORMAWA ITI');
        $otpAuthUrl = TotpService::getOtpAuthUrl($company, $user->email, $secret);

        ActivityLogService::log('mfa_setup', 'authentication', 'User menginisialisasi pendaftaran autentikasi dua faktor', $user);

        $response = [
            'status'         => 'success',
            'secret'         => $secret,
            'otpauth_url'    => $otpAuthUrl,
            'recovery_codes' => $recoveryCodes, // Plaintext shown once during setup
        ];

        // In local development / debug, provide current active OTP to assist quick testing
        if (config('app.debug') || app()->environment('local')) {
            $response['current_otp'] = TotpService::generateCode($secret);
        }

        return response()->json($response);
    }

    /**
     * Endpoint Konfirmasi Aktivasi MFA (Authenticated - Menguji kode OTP pertama)
     */
    public function confirmMfa(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
        ], [
            'code.required' => 'Kode OTP dari aplikasi autentikator wajib diisi.',
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        if (empty($user->two_factor_secret)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Silakan lakukan inisialisasi MFA terlebih dahulu.',
            ], 400);
        }

        $inputCode = trim($request->code);
        $isValid = false;

        // 1. Verify 6-digit TOTP code (RFC 6238) with extended tolerance window (+/- 5 minutes for initial setup)
        if (TotpService::verify($user->two_factor_secret, $inputCode, 10)) {
            $isValid = true;
        }

        // 2. Fallback: Also accept single-use recovery code if clock desynchronized
        if (!$isValid && is_array($user->two_factor_recovery_codes)) {
            $remaining = TotpService::verifyAndConsumeRecoveryCode($user->two_factor_recovery_codes, $inputCode);
            if ($remaining !== null) {
                $isValid = true;
                $user->two_factor_recovery_codes = $remaining;
            }
        }

        if (!$isValid) {
            throw ValidationException::withMessages([
                'code' => ['Kode verifikasi tidak sesuai. Pastikan jam pada perangkat Anda akurat atau gunakan salah satu kode pemulihan cadangan.'],
            ]);
        }

        $user->two_factor_confirmed_at = now();
        $user->save();

        ActivityLogService::log('mfa_enabled', 'authentication', 'User mengaktifkan autentikasi dua faktor (MFA/TOTP)', $user);

        return response()->json([
            'status'  => 'success',
            'message' => 'Autentikasi dua faktor (MFA) berhasil diaktifkan untuk akun Anda!',
        ]);
    }

    /**
     * Endpoint Nonaktifkan MFA (Authenticated - Wajib kata sandi saat ini)
     */
    public function disableMfa(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ], [
            'password.required' => 'Kata sandi saat ini wajib diisi untuk menonaktifkan MFA.',
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Kata sandi saat ini tidak sesuai.'],
            ]);
        }

        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        // Revoke other tokens except current session for security
        if ($user->currentAccessToken()) {
            $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();
        }

        ActivityLogService::log('mfa_disabled', 'authentication', 'User menonaktifkan autentikasi dua faktor', $user);

        return response()->json([
            'status'  => 'success',
            'message' => 'Autentikasi dua faktor berhasil dinonaktifkan.',
        ]);
    }

    /**
     * Endpoint Regenerasi Recovery Codes (Authenticated - Wajib kata sandi saat ini)
     */
    public function regenerateRecoveryCodes(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ], [
            'password.required' => 'Kata sandi saat ini wajib diisi.',
        ]);

        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user->hasTwoFactorEnabled()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Autentikasi dua faktor belum aktif pada akun Anda.',
            ], 400);
        }

        if (!Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['Kata sandi saat ini tidak sesuai.'],
            ]);
        }

        $newCodes = TotpService::generateRecoveryCodes(8);
        $user->two_factor_recovery_codes = array_map([TotpService::class, 'hashRecoveryCode'], $newCodes);
        $user->save();

        ActivityLogService::log('mfa_recovery_regenerated', 'authentication', 'User memperbarui kode pemulihan MFA', $user);

        return response()->json([
            'status'         => 'success',
            'message'        => 'Kode pemulihan baru berhasil dibuat.',
            'recovery_codes' => $newCodes,
        ]);
    }

    /**
     * Endpoint Cek User Login Saat Ini / Profil
     */
    public function me(Request $request)
    {
        $user = User::with(['organization', 'roles', 'permissions'])->find($request->user()->id);

        return response()->json([
            'user' => [
                'id'                 => $user->id,
                'name'               => $user->name,
                'email'              => $user->email,
                'avatar'             => $user->avatar,
                'role'               => $user->getRoleNames()->first(),
                'permissions'        => $user->getAllPermissions()->pluck('name')->values(),
                'organization'       => $user->organization,
                'status'             => $user->status,
                'two_factor_enabled' => $user->hasTwoFactorEnabled(),
                'created_at'         => $user->created_at?->toIso8601String(),
            ]
        ]);
    }

    /**
     * Endpoint Update Profil Pengguna Sendiri
     */
    public function updateProfile(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|min:2|max:100',
        ], [
            'name.required' => 'Nama lengkap wajib diisi.',
            'name.min'      => 'Nama minimal terdiri dari 2 karakter.',
            'name.max'      => 'Nama maksimal 100 karakter.',
        ]);

        $name = trim($request->name);
        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => ['Nama lengkap tidak boleh hanya berupa spasi.'],
            ]);
        }

        $user->name = $name;

        // Handle Avatar Upload if a file is provided
        if ($request->hasFile('avatar')) {
            $request->validate([
                'avatar' => 'file|mimes:jpeg,jpg,png,webp|max:5120',
            ], [
                'avatar.mimes' => 'Format foto profil harus berupa JPG, JPEG, PNG, atau WEBP.',
                'avatar.max'   => 'Ukuran foto profil maksimal 5 MB.',
            ]);

            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = '/storage/' . $path;
        } elseif ($request->has('avatar') && is_string($request->avatar)) {
            // If empty string is passed explicitly, clear avatar
            if (empty(trim($request->avatar))) {
                $user->avatar = null;
            }
        }

        $user->save();

        ActivityLogService::log('update', 'users', 'User memperbarui profil: ' . $user->name, $user);

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user'    => [
                'id'                 => $user->id,
                'name'               => $user->name,
                'email'              => $user->email,
                'avatar'             => $user->avatar,
                'role'               => $user->getRoleNames()->first(),
                'organization'       => $user->organization,
                'status'             => $user->status,
                'two_factor_enabled' => $user->hasTwoFactorEnabled(),
                'created_at'         => $user->created_at?->toIso8601String(),
            ]
        ]);
    }

    /**
     * Endpoint Update Password Pengguna Sendiri
     */
    public function updatePassword(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|confirmed',
        ], [
            'current_password.required' => 'Kata sandi saat ini wajib diisi.',
            'password.required'         => 'Kata sandi baru wajib diisi.',
            'password.min'              => 'Kata sandi baru minimal 8 karakter.',
            'password.confirmed'        => 'Konfirmasi kata sandi baru tidak cocok.',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Kata sandi saat ini tidak sesuai.'],
            ]);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        // Revoke all other active tokens for this user upon password change (Prevent persistent session hijacking)
        if ($user->currentAccessToken()) {
            $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();
        }

        ActivityLogService::log('password_changed', 'authentication', 'User mengubah kata sandi', $user);

        return response()->json([
            'message' => 'Kata sandi berhasil diperbarui.'
        ]);
    }

    /**
     * Endpoint Logout
     */
    public function logout(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $allDevices = $request->boolean('all_devices');

        if ($allDevices) {
            ActivityLogService::log('logout_all', 'authentication', 'User melakukan logout dari semua perangkat', $user);
            $user->tokens()->delete();
        } else {
            ActivityLogService::log('logout', 'authentication', 'User melakukan logout', $user);
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => $allDevices ? 'Berhasil keluar dari seluruh perangkat.' : 'Berhasil logout'
        ]);
    }

    /**
     * Endpoint API Forgot Password (Minta Tautan Reset Kata Sandi)
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => 'Email wajib diisi.',
            'email.email'    => 'Masukkan alamat email yang valid.',
        ]);

        // Kirim reset link menggunakan Laravel Password broker
        \Illuminate\Support\Facades\Password::sendResetLink(
            $request->only('email')
        );

        $user = User::where('email', $request->email)->first();
        if ($user) {
            ActivityLogService::log('password_reset_requested', 'authentication', 'Permintaan reset password diajukan', $user, ['ip' => $request->ip()], clone $user);
        }

        // Generic response to prevent user enumeration
        return response()->json([
            'status'  => 'success',
            'message' => 'Jika email Anda terdaftar dalam sistem, tautan untuk mengatur ulang kata sandi telah dikirimkan ke email Anda.'
        ], 200);
    }

    /**
     * Endpoint API Reset Password (Simpan Kata Sandi Baru dengan Token)
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required|string',
            'email'    => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'token.required'     => 'Token reset password tidak valid atau sudah kedaluwarsa.',
            'email.required'     => 'Email wajib diisi.',
            'email.email'        => 'Format email tidak valid.',
            'password.required'  => 'Kata sandi baru wajib diisi.',
            'password.min'       => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        $status = \Illuminate\Support\Facades\Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password'       => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Revoke all existing personal access tokens upon password reset
                $user->tokens()->delete();

                event(new \Illuminate\Auth\Events\PasswordReset($user));
            }
        );

        if ($status === \Illuminate\Support\Facades\Password::PASSWORD_RESET) {
            $user = User::where('email', $request->email)->first();
            if ($user) {
                ActivityLogService::log('password_reset_completed', 'authentication', 'Kata sandi akun berhasil diatur ulang', $user, null, clone $user);
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Kata sandi Anda berhasil diperbarui. Silakan login kembali dengan kata sandi baru Anda.'
            ], 200);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }
}