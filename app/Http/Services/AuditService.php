<?php

namespace App\Http\Services;

use App\Models\AuditEvent;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AuditService
{
    /**
     * Log an immutable audit event.
     *
     * @param string $action
     * @param mixed $subject Model or string identifier
     * @param array|null $before
     * @param array|null $after
     * @param string|null $reason
     * @param int|null $storeId
     * @param int|null $actorId
     * @return AuditEvent|null
     */
    public static function log(
        string $action,
        $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null,
        ?int $storeId = null,
        ?int $actorId = null
    ): ?AuditEvent {
        if (!Schema::hasTable('audit_events')) {
            return null;
        }

        $subjectType = null;
        $subjectId = null;

        if ($subject instanceof Model) {
            $subjectType = get_class($subject);
            $subjectId = $subject->getKey();
            if (!$storeId && isset($subject->store_id)) {
                $storeId = (int) $subject->store_id;
            }
        } elseif (is_string($subject)) {
            $subjectType = $subject;
        }

        $currentActor = $actorId ?: Auth::id();
        if (!$storeId && Auth::check()) {
            $storeId = Auth::user()->store_id ? (int) Auth::user()->store_id : null;
        }

        $requestId = Request::header('X-Request-Id') ?: (string) Str::uuid();
        $ip = Request::ip() ?: '127.0.0.1';
        $ipHash = hash('sha256', $ip);

        return AuditEvent::create([
            'actor_user_id' => $currentActor,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'store_id' => $storeId,
            'before_json' => self::sanitizeData($before),
            'after_json' => self::sanitizeData($after),
            'reason' => $reason,
            'request_id' => $requestId,
            'ip_hash' => $ipHash,
            'created_at' => Carbon::now(),
        ]);
    }

    /**
     * Sanitize and mask sensitive fields before persisting audit logs.
     *
     * @param array|null $data
     * @return array|null
     */
    public static function sanitizeData(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        $sensitiveKeys = [
            'password', 'secret', 'token', 'access_token', 'refresh_token',
            'wallet_private_key', 'mnemonic', 'api_key', 'authorization'
        ];

        $sanitized = [];
        foreach ($data as $key => $value) {
            $lowerKey = strtolower((string) $key);

            if (in_array($lowerKey, $sensitiveKeys, true)) {
                $sanitized[$key] = '***REDACTED***';
                continue;
            }

            if (in_array($lowerKey, ['id_card', 'cccd', 'cmnd'], true) && is_string($value)) {
                $sanitized[$key] = self::maskIdCard($value);
                continue;
            }

            if (in_array($lowerKey, ['phone', 'staff_phone', 'recipient_phone'], true) && is_string($value)) {
                $sanitized[$key] = self::maskPhone($value);
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = self::sanitizeData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Mask ID card / CCCD to protect PII.
     */
    public static function maskIdCard(string $idCard): string
    {
        $idCard = trim($idCard);
        $len = strlen($idCard);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }
        return substr($idCard, 0, 4) . str_repeat('*', max(4, $len - 4));
    }

    public static function maskPhone(string $phone): string
    {
        $phone = trim($phone);
        $len = strlen($phone);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return str_repeat('*', $len - 4) . substr($phone, -4);
    }
}
