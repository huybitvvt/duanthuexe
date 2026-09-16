<?php

namespace App\Http\Middleware;

use App\Support\OperationalSchema;
use Closure;
use Illuminate\Support\Facades\Log;

class EnsureOperationalSchema
{
    private $schema;

    public function __construct(OperationalSchema $schema)
    {
        $this->schema = $schema;
    }

    public function handle($request, Closure $next, string $profile)
    {
        try {
            $missing = $this->schema->missing($profile);
        } catch (\Throwable $exception) {
            Log::error('Operational schema check failed.', [
                'profile' => $profile,
                'exception' => get_class($exception),
            ]);

            return response()->json([
                'status' => 'error',
                'code' => 'SCHEMA_CHECK_FAILED',
                'message' => 'Không thể xác minh cấu trúc cơ sở dữ liệu. Chức năng đã được khóa an toàn.',
            ], 503);
        }

        if ($missing !== []) {
            Log::warning('Blocked workflow because operational schema is incomplete.', [
                'profile' => $profile,
                'missing' => $missing[$profile] ?? [],
            ]);

            return response()->json([
                'status' => 'error',
                'code' => 'SCHEMA_NOT_READY',
                'message' => 'Cơ sở dữ liệu chưa được nâng cấp đầy đủ. Chức năng tạm khóa để bảo vệ giao dịch.',
                'missing' => $missing[$profile] ?? [],
                'required_migrations' => $this->schema->migrationsFor($profile),
            ], 503);
        }

        return $next($request);
    }
}
