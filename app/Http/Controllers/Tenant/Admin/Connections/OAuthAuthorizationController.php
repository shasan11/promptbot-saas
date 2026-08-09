<?php

namespace App\Http\Controllers\Tenant\Admin\Connections;

use App\Http\Controllers\Controller;
use App\Models\Connections\Connection;
use App\Models\Connections\ConnectionIntegration;
use App\Services\Connections\ConnectionAuditService;
use App\Services\Connections\OAuthStateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class OAuthAuthorizationController extends Controller
{
    public function start(Request $request, OAuthStateService $states, ConnectionAuditService $audit): RedirectResponse
    {
        abort_unless(
            $request->user('tenant')?->can('connections.create') || $request->user('tenant')?->can('connections.update'),
            403
        );

        $data = $request->validate([
            'connection_integration_id' => ['required', 'integer', 'exists:connection_integrations,id'],
            'connection_id' => ['nullable', 'integer', 'exists:connections,id'],
            'scopes' => ['nullable', 'array'],
            'scopes.*' => ['string', 'max:255'],
            'redirect_path' => ['nullable', 'string', 'max:500'],
        ]);

        $integration = ConnectionIntegration::query()->findOrFail($data['connection_integration_id']);
        $connection = isset($data['connection_id']) ? Connection::query()->findOrFail($data['connection_id']) : null;

        if ($connection && (int) $connection->connection_integration_id !== (int) $integration->id) {
            return back()->withErrors(['connection_id' => 'Connection does not belong to this integration.']);
        }

        try {
            $authorization = $states->create(
                $integration,
                $connection,
                $request->user('tenant'),
                $request,
                $data['scopes'] ?? [],
                $data['redirect_path'] ?? '/connections',
            );
        } catch (InvalidArgumentException $exception) {
            $field = str_contains(strtolower($exception->getMessage()), 'redirect') ? 'redirect_path' : 'scopes';

            return back()->withErrors([$field => $exception->getMessage()])->withInput();
        }

        $audit->record('oauth.authorization_started', $connection, $request->user('tenant'), message: 'OAuth authorization started.', context: [
            'integration_key' => $integration->key,
            'provider' => $integration->provider,
            'scopes' => $authorization['scopes'],
            'redirect_path' => $authorization['redirect_path'],
            'pkce' => $authorization['code_challenge_method'],
        ]);

        return back()->with('oauth_authorization', [
            'integration' => $integration->only(['id', 'key', 'name', 'provider']),
            'state' => $authorization['state'],
            'code_challenge' => $authorization['code_challenge'],
            'code_challenge_method' => $authorization['code_challenge_method'],
            'scopes' => $authorization['scopes'],
            'scope_descriptions' => $authorization['scope_descriptions'],
            'redirect_path' => $authorization['redirect_path'],
            'expires_in_minutes' => 10,
            'requires_reauthorization_on_scope_change' => true,
        ])->with('status', 'OAuth authorization prepared. Review the requested scopes before continuing with the provider.');
    }

    public function callback(Request $request, OAuthStateService $states, ConnectionAuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'state' => ['required', 'string', 'max:255'],
            'error' => ['nullable', 'string', 'max:255'],
            'error_description' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $state = $states->consume($data['state']);
        } catch (InvalidArgumentException $exception) {
            return redirect('/connections')->with('error', $exception->getMessage());
        }

        $connection = $state->connection;

        if (! empty($data['error'])) {
            $audit->record('oauth.authorization_failed', $connection, $request->user('tenant'), status: 'failed', message: $data['error_description'] ?? $data['error'], context: [
                'provider_error' => $data['error'],
                'integration_key' => $state->integration?->key,
            ], level: 'warning');

            return redirect($state->redirect_path ?: '/connections')->with('error', 'OAuth provider returned an authorization error.');
        }

        $audit->record('oauth.authorization_completed', $connection, $request->user('tenant'), message: 'OAuth callback state verified.', context: [
            'integration_key' => $state->integration?->key,
            'scopes' => $state->scopes ?? [],
        ]);

        return redirect($state->redirect_path ?: '/connections')->with('status', 'OAuth authorization state verified.');
    }
}
