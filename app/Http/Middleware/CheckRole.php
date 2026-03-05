<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    /**
     * Cek apakah user memiliki role tertentu.
     * Contoh: ->middleware('role:admin') atau ->middleware('role:admin,supervisor')
     */
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        if (!$user->role || !in_array($user->role->slug, $roles)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Akses ditolak.'], 403);
            }
            abort(403, 'Anda tidak memiliki role yang diperlukan.');
        }

        return $next($request);
    }
}
