<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortfolioToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = (string) config('services.portfolio.blog_api_token');
        $providedToken = (string) $request->header('X-Portfolio-Token', '');

        if ($expectedToken === '') {
            return ApiResponse::error('Portfolio blog API token is not configured.', [], 503);
        }

        if ($providedToken === '' || ! hash_equals($expectedToken, $providedToken)) {
            return ApiResponse::error('Invalid portfolio API token.', [], 401);
        }

        return $next($request);
    }
}
