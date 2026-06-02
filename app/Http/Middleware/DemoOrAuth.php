<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoOrAuth
{
    /**
     * Allow access without real auth when demo_mode is enabled.
     * Injects a synthetic demo user so Inertia and controllers
     * that rely on $request->user() continue to work.
     */
    public function handle(Request $request, Closure $next, string ...$guards): mixed
    {
        if (config('app.demo_mode') && !Auth::check()) {
            $user = new User();
            $user->forceFill([
                'id' => 0,
                'name' => 'Demo User',
                'email' => 'demo@aetherdb.ai',
                'current_team_id' => null,
                'email_verified_at' => now(),
            ]);
            $user->exists = false;

            Auth::guard()->setUser($user);
            $request->setUserResolver(fn () => $user);
        }

        if (!Auth::check()) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
