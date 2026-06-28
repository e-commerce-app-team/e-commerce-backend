<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsProductsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $admin = auth()->user();
        if ($admin && ($admin->role === 'products_admin' || $admin->role === 'super_admin')) {
            return $next($request);
        }

        return response()->json(['message' => 'Access Denied. Products Admin only.'], 403);
    }
}
