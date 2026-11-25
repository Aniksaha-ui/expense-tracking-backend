<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TraceId
{
    public const HEADER = 'X-Trace-Id';

    public const ATTRIBUTE = 'trace_id';

    /**
     * Resolve or initialize the trace ID for the active request and bind it to logging context.
     */
    public static function resolve(?Request $request = null): string
    {
        $request ??= app()->bound('request') ? request() : null;

        if ($request instanceof Request) {
            $existing = $request->attributes->get(self::ATTRIBUTE);
            if (is_string($existing) && $existing !== '') {
                return $existing;
            }
        }

        $contextTraceId = Context::get(self::ATTRIBUTE);
        if (is_string($contextTraceId) && $contextTraceId !== '') {
            if ($request instanceof Request) {
                $request->attributes->set(self::ATTRIBUTE, $contextTraceId);
            }

            return $contextTraceId;
        }

        $incoming = $request instanceof Request
            ? ($request->header(self::HEADER) ?: $request->header('X-Request-Id'))
            : null;

        $traceId = self::sanitizeIncoming($incoming) ?? (string) Str::uuid();

        if ($request instanceof Request) {
            $request->attributes->set(self::ATTRIBUTE, $traceId);
        }

        Context::add(self::ATTRIBUTE, $traceId);
        Log::shareContext([self::ATTRIBUTE => $traceId]);

        return $traceId;
    }

    /**
     * Get the current request's trace ID, generating one if none exists yet.
     */
    public static function current(): string
    {
        return self::resolve();
    }

    private static function sanitizeIncoming(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return preg_match('/^[A-Za-z0-9._-]{8,64}$/', $trimmed) === 1
            ? $trimmed
            : null;
    }
}
