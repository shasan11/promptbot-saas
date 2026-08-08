<?php

namespace App\Http\Controllers\Tenant\Admin\Connections;

use App\Enums\Connections\AuthenticationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Connections\CredentialRotateRequest;
use App\Models\Connections\ConnectionCredential;
use App\Services\Connections\CredentialVault;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CredentialController extends Controller
{
    public function rotate(CredentialRotateRequest $request, ConnectionCredential $credential, CredentialVault $vault): RedirectResponse
    {
        $data = $request->validated();

        $vault->rotate(
            $credential,
            $data['credential'],
            $request->user('tenant'),
            isset($data['auth_type']) ? AuthenticationType::from($data['auth_type']) : null,
            $data['reason'] ?? null,
            [
                'expires_at' => $data['expires_at'] ?? null,
                'refresh_expires_at' => $data['refresh_expires_at'] ?? null,
                'source' => 'credentials_screen',
            ],
        );

        return back()->with('status', 'Credential rotated.');
    }

    public function revoke(Request $request, ConnectionCredential $credential, CredentialVault $vault): RedirectResponse
    {
        abort_unless($request->user('tenant')?->can('connections.credentials.manage'), 403);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $vault->revoke($credential, $request->user('tenant'), $data['reason'] ?? null);

        return back()->with('status', 'Credential revoked.');
    }
}
