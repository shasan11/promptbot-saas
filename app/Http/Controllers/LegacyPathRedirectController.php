<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class LegacyPathRedirectController extends Controller
{
    public function superadmin(string $path): RedirectResponse
    {
        return redirect('/superadmin/'.$this->safePath($path));
    }

    public function tenant(string $path): RedirectResponse
    {
        return redirect('/'.$this->safePath($path));
    }

    private function safePath(string $path): string
    {
        $path = ltrim($path, '/');
        abort_if(str_contains($path, '://') || str_starts_with($path, '//'), 404);

        return $path;
    }
}
