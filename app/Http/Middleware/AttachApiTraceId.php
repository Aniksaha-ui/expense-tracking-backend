<?php

namespace App\Http\Middleware;

use App\Support\TraceId;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AttachApiTraceId
{
    /**
     * Sensitive payload keys that must always be redacted before writing API logs.
     *
     * @var array<int, string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'token',
        'access_token',
        'refresh_token',
        'secret',
        'api_key',
        'authorization',
        'pin',
        'otp',
        'cookie',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldTrace($request)) {
            return $next($request);
        }

        $startedAt = microtime(true);
        $traceId = TraceId::resolve($request);

        $response = $next($request);

        $this->attachToResponse($response, $traceId);
        $this->writeApiLog($request, $response, $traceId, $startedAt);

        return $response;
    }

    private function shouldTrace(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    private function attachToResponse(Response $response, string $traceId): void
    {
        $response->headers->set(TraceId::HEADER, $traceId);
        $this->exposeHeader($response, TraceId::HEADER);

        if ($response instanceof JsonResponse) {
            $payload = $response->getData(true);
            if (is_array($payload) && ! array_is_list($payload)) {
                $payload['trace_id'] = $traceId;
                $response->setData($payload);
            }

            return;
        }

        $contentType = (string) $response->headers->get('Content-Type', '');
        if (! str_contains(strtolower($contentType), 'application/json')) {
            return;
        }

        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            return;
        }

        $decoded = json_decode($content, true);
        if (is_array($decoded) && ! array_is_list($decoded)) {
            $decoded['trace_id'] = $traceId;
            $encoded = json_encode($decoded);
            if ($encoded !== false) {
                $response->setContent($encoded);
            }
        }
    }

    private function exposeHeader(Response $response, string $header): void
    {
        $existing = (string) $response->headers->get('Access-Control-Expose-Headers', '');
        if ($existing === '') {
            $response->headers->set('Access-Control-Expose-Headers', $header);

            return;
        }

        $headers = array_map('trim', explode(',', $existing));
        if (! in_array($header, $headers, true)) {
            $headers[] = $header;
            $response->headers->set('Access-Control-Expose-Headers', implode(', ', $headers));
        }
    }

    private function writeApiLog(
        Request $request,
        Response $response,
        string $traceId,
        float $startedAt
    ): void {
        try {
            $status = $response->getStatusCode();
            $durationMs = round((microtime(true) - $startedAt) * 1000, 2);
            $user = $request->user('api') ?? $request->user();
            $summary = $this->extractResponseSummary($response);

            $context = [
                'trace_id' => $traceId,
                'method' => $request->method(),
                'path' => '/'.ltrim($request->path(), '/'),
                'query' => $this->redactSensitive($request->query()),
                'status' => $status,
                'duration_ms' => $durationMs,
                'ip' => $request->ip(),
                'user_id' => $user?->id,
                'user_agent' => $request->userAgent(),
                'request_payload' => $this->redactSensitive($request->except(['_token'])),
                'response' => $summary,
            ];

            $level = $status >= 500 ? 'error' : ($status >= 400 ? 'warning' : 'info');
            $message = sprintf(
                'API %s /%s -> %d (%sms)',
                $request->method(),
                ltrim($request->path(), '/'),
                $status,
                $durationMs
            );

            Log::channel('api_trace')->log($level, $message, $context);

            if ($status >= 400) {
                Log::log($level, $message, $context);
            }
        } catch (Throwable) {
            // Never allow logging issues to break an API response.
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function extractResponseSummary(Response $response): array
    {
        $payload = null;

        if ($response instanceof JsonResponse) {
            $payload = $response->getData(true);
        } elseif (str_contains(strtolower((string) $response->headers->get('Content-Type', '')), 'application/json')) {
            $content = $response->getContent();
            if (is_string($content) && $content !== '') {
                $payload = json_decode($content, true);
            }
        }

        if (! is_array($payload)) {
            return [];
        }

        $summary = array_filter([
            'isExecute' => $payload['isExecute'] ?? null,
            'msg' => $payload['msg'] ?? ($payload['message'] ?? null),
        ], fn (mixed $value) => $value !== null);

        if (isset($payload['errors']) && is_array($payload['errors'])) {
            $summary['errors'] = $payload['errors'];
        }

        return $summary;
    }

    /**
     * Recursively redact sensitive keys from request payloads before logging.
     *
     * @param  array<string|int, mixed>  $data
     * @return array<string|int, mixed>
     */
    private function redactSensitive(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $normalizedKey = is_string($key) ? strtolower($key) : '';
            if ($normalizedKey !== '' && $this->isSensitiveKey($normalizedKey)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->redactSensitive($value);
            } elseif (is_object($value)) {
                $sanitized[$key] = '[OBJECT:'.$value::class.']';
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $needle) {
            if ($key === $needle || str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
