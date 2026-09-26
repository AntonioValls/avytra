<?php

namespace App\Http\Middleware;

use App\Models\Category;
use App\Models\Province;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * /empresas?sector=x and /empresas?provincia=y have a URL of their own (docs/15): a full
 * page request with one of them (and not the other) is sent there with a permanent
 * redirect, keeping any other filter. Livewire updates never pass through this route.
 */
class RedirectExploreFiltersToLandingPages
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET')) {
            return $next($request);
        }

        $sector = $request->query('sector');
        $province = $request->query('provincia');

        if (is_string($sector) && $sector !== '' && ! is_string($province)) {
            $category = Category::query()->active()->roots()->where('slug', $sector)->first();

            if ($category !== null) {
                return redirect()->to(route('categories.show', $category).$this->remainingQuery($request, 'sector'), 301);
            }
        }

        if (is_string($province) && $province !== '' && ! is_string($sector)) {
            $target = Province::query()->where('slug', $province)->first();

            if ($target !== null) {
                return redirect()->to(route('provinces.show', $target).$this->remainingQuery($request, 'provincia'), 301);
            }
        }

        return $next($request);
    }

    private function remainingQuery(Request $request, string $except): string
    {
        $query = array_filter($request->query(), fn (mixed $value, string $key): bool => $key !== $except && $value !== '' && $value !== null, ARRAY_FILTER_USE_BOTH);

        return $query === [] ? '' : '?'.http_build_query($query);
    }
}
