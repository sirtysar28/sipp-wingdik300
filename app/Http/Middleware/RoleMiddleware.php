<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;

class RoleMiddleware {
    public function handle(Request $request, Closure $next, ...$roles) {
        if (!auth()->check()) return redirect('/login');

        $userRole = auth()->user()->role;

        // super_admin has access to everything
        if ($userRole === 'super_admin') return $next($request);

        // old 'admin' role also has full access (backward compat)
        if ($userRole === 'admin') return $next($request);

        if (!in_array($userRole, $roles)) {
            abort(403, 'Akses tidak diizinkan untuk modul ini.');
        }
        return $next($request);
    }
}
