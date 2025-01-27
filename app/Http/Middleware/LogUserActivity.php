<?php

namespace App\Http\Middleware;

use App\Models\RecentActivity;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LogUserActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Log the activity only if the user is authenticated
        if (Auth::check()) {
            $user = Auth::user();
            $activity = sprintf(
                '%s %s accessed %s [%s]',
                $user->role,
                $user->name,
                $request->path(),
                $request->method()
            );

            // Exclude specific routes from being logged
            $excludedRoutes = [
                'auth.login',
                'auth.register',
                'auth.logout',
                'admin.recent-activities',
            ];

            if (!in_array($request->route()->getName(), $excludedRoutes)) {
                RecentActivity::create([
                    'user_id' => $user->id,
                    'activity' => $activity,
                ]);
            }
        }

        return $response;
    }
}