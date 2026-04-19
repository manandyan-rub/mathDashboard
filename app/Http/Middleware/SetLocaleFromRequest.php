<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromRequest
{
    private const SUPPORTED = ['en', 'hy', 'fr', 'ru'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolveLocale($request);
        app()->setLocale($locale);

        if ($request->hasSession()) {
            $request->session()->put('lang', $locale);
        }

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $rawLocale = $request->header('X-Locale')
            ?? $request->query('lang')
            ?? $request->cookie('lang')
            ?? ($request->hasSession() ? $request->session()->get('lang') : null)
            ?? config('app.locale', 'en');

        $locale = strtolower((string) $rawLocale);
        if (str_contains($locale, '-')) {
            $locale = explode('-', $locale)[0];
        }

        if (!in_array($locale, self::SUPPORTED, true)) {
            return 'en';
        }

        return $locale;
    }
}
