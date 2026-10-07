<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/** Guest (recruiter) accounts cannot change their login details or delete themselves. */
class BlockGuestAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_guest) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'The guest account cannot change this. Everything else is open to try.']);

            return back();
        }

        return $next($request);
    }
}
