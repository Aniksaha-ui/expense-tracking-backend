<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureMenuManager
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user('api');
        $managerEmail = trim((string) config('menu.manager_email'));
        if (! $user || (int) $user->id !== 1 || $managerEmail === '' || strcasecmp(trim($user->email ?? ''), $managerEmail) !== 0) {
            return response()->json(['isExecute' => 'failed', 'data' => [], 'msg' => 'Forbidden'], 403);
        }
        return $next($request);
    }
}
