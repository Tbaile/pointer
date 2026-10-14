<?php

namespace App\Http\Middleware;

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    /**
     * Send users still on a temporary password to the profile page.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->must_change_password && ! $request->routeIs('filament.*.auth.*')) {
            return redirect()->to(EditProfile::getUrl());
        }

        return $next($request);
    }
}
