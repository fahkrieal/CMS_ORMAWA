<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class SecurityMonitoringService
{
    public const FAILURE_THRESHOLD = 5;
    public const DECAY_MINUTES = 15;

    /**
     * Record a failed login attempt for an email and IP, and trigger security alerts if threshold is reached.
     */
    public static function recordFailedLogin(string $email, string $ip): void
    {
        $normalizedEmail = strtolower(trim($email));
        $emailKey = 'sec_fail_email_' . md5($normalizedEmail);
        $ipKey = 'sec_fail_ip_' . md5($ip);

        $emailCount = (int) Cache::get($emailKey, 0) + 1;
        $ipCount = (int) Cache::get($ipKey, 0) + 1;

        Cache::put($emailKey, $emailCount, now()->addMinutes(self::DECAY_MINUTES));
        Cache::put($ipKey, $ipCount, now()->addMinutes(self::DECAY_MINUTES));

        $maxAttempts = max($emailCount, $ipCount);

        // Trigger anomaly alert when the threshold is reached exactly, and every 5 attempts thereafter
        if ($maxAttempts >= self::FAILURE_THRESHOLD && ($maxAttempts % self::FAILURE_THRESHOLD === 0)) {
            $reason = $emailCount >= self::FAILURE_THRESHOLD
                ? "Percobaan berulang ({$emailCount}x) pada akun {$normalizedEmail}"
                : "Percobaan berulang ({$ipCount}x) dari IP {$ip}";

            // 1. Log security anomaly to Activity Logs
            ActivityLogService::log(
                'security_alert',
                'authentication',
                "Terdeteksi anomali: Lonjakan percobaan login gagal ({$maxAttempts}x) dari IP {$ip} pada akun {$normalizedEmail}.",
                null,
                [
                    'anomaly_type' => 'excessive_failed_logins',
                    'target_email' => $normalizedEmail,
                    'ip_address'   => $ip,
                    'failed_count' => $maxAttempts,
                ]
            );

            // 2. Alert Super Admins via In-App Notification System
            NotificationService::sendToSuperAdmins(
                'security_alert',
                'Peringatan Keamanan: Percobaan Login Gagal Berulang',
                "Terdeteksi {$maxAttempts}x percobaan login gagal dari IP {$ip} pada akun {$normalizedEmail}. {$reason}",
                '/dashboard',
                [
                    'target_email' => $normalizedEmail,
                    'ip_address'   => $ip,
                    'failed_count' => $maxAttempts,
                ]
            );
        }
    }

    /**
     * Clear failed login counters upon successful authentication.
     */
    public static function recordSuccessfulLogin(string $email, string $ip): void
    {
        $normalizedEmail = strtolower(trim($email));
        Cache::forget('sec_fail_email_' . md5($normalizedEmail));
        Cache::forget('sec_fail_ip_' . md5($ip));
    }
}
