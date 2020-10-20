<?php

namespace Diviky\Bright\Http\Middleware;

use Closure;

class Ajax
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ('iframe' == $request->input('_request')) {
            $request->headers->add(['Accept' => 'application/json']);
        }

        return $next($request);
    }
}
