<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = auth()->user();
        if ($admin && $admin->role === 'super_admin') {
            return $next($request);
        }

        return response()->json(['message' => 'Access Denied. Super Admin only.'], 403);
    }
    }

