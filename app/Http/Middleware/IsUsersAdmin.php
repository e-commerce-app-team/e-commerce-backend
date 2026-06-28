<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsUsersAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $admin = auth()->user();
        // السماح فقط للـ super_admin أو الـ users_admin
        if ($admin && ($admin->role === 'users_admin' || $admin->role === 'super_admin')) {
            return $next($request);
        }

        return response()->json(['message' => 'Access Denied. Users Admin only.'], 403);
    }
}
