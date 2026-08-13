<?php

namespace App\Http\Controllers\Portal\Auth;

use App\Http\Controllers\Controller;
use App\Models\PortalUser;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmailVerificationController extends Controller
{
    public function notice(): Response|RedirectResponse
    {
        return request()->user('portal')->hasVerifiedEmail() ? redirect()->route('portal.dashboard') : Inertia::render('Auth/VerifyEmail', [
            'status' => session('status'), 'sendRoute' => 'portal.verification.send', 'logoutRoute' => 'portal.logout',
        ]);
    }

    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = PortalUser::findOrFail($id);
        abort_unless(hash_equals($hash, sha1($user->getEmailForVerification())), 403);
        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) event(new Verified($user));
        return redirect()->route('portal.dashboard')->with('status', 'Email verified.');
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user('portal')->hasVerifiedEmail()) return redirect()->route('portal.dashboard');
        $request->user('portal')->sendEmailVerificationNotification();
        return back()->with('status', 'verification-link-sent');
    }
}
