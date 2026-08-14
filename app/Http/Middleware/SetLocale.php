<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('Accept-Language');
        
        if ($request->has('lang')) {
            $locale = $request->input('lang');
        }

        // Clean locale if it has quality values like "ar-EG,ar;q=0.9,en-US;q=0.8"
        if ($locale) {
            $locale = substr(trim($locale), 0, 2);
        }

        if ($locale && in_array($locale, ['ar', 'en'])) {
            app()->setLocale($locale);
        } else {
            app()->setLocale('ar'); // Default to Arabic
        }

        return $next($request);
    }
}
