<?php

namespace Tests\Feature\Tenant\Widget;

use App\Models\Channel\Channel;
use App\Models\Channel\WebChatWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/**
 * Guest rate limits must be per-route.
 *
 * Laravel's `throttle:N,M` builds its key from the *route's* limiter name when
 * one is given, but for an unauthenticated request with no name it falls back
 * to `sha1(domain|ip)` — which contains no route at all. Every guest endpoint
 * on a tenant domain therefore shared one bucket: a visitor polling for new
 * messages (120/min) burned the same allowance as the session endpoint
 * (20/min), so a normal chat session locked itself out of endpoints it had
 * barely touched. The third `throttle:` argument is what separates them.
 */
class GuestThrottleIsolationTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    protected function tearDown(): void
    {
        $this->cleanUpTenants();
        parent::tearDown();
    }

    /**
     * The static half: every public tenant endpoint declares its own limiter
     * name, and no two share one. Cheap, and it catches the realistic
     * regression — a copy-pasted route reusing a neighbour's prefix.
     */
    public function test_every_public_tenant_route_declares_its_own_throttle_prefix(): void
    {
        $prefixes = [];
        $seenWidgetRoutes = [];

        foreach (Route::getRoutes() as $route) {
            if (! $this->isPublicTenantRoute($route)) {
                continue;
            }

            $throttles = $this->throttleMiddleware($route);

            if (str_starts_with((string) $route->getName(), 'tenant.widget.') && $route->uri() !== 'widget/promptbot.js') {
                $this->assertNotEmpty($throttles, "Widget endpoint [{$route->uri()}] has no rate limit at all.");
                $seenWidgetRoutes[] = $route->getName();
            }

            foreach ($throttles as $throttle) {
                $arguments = explode(',', Str::after($throttle, 'throttle:'));

                $this->assertCount(
                    3,
                    $arguments,
                    "Public route [{$route->uri()}] uses [{$throttle}] with no limiter name, so it shares the anonymous sha1(domain|ip) bucket with every other guest route.",
                );

                $prefix = trim($arguments[2]);

                $this->assertArrayNotHasKey(
                    $prefix,
                    $prefixes,
                    "Routes [{$route->uri()}] and [".($prefixes[$prefix] ?? '')."] share the throttle prefix [{$prefix}].",
                );

                $prefixes[$prefix] = $route->uri();
            }
        }

        // Guards against the filter silently matching nothing and the test
        // passing vacuously.
        $this->assertGreaterThan(5, count($prefixes));
        $this->assertGreaterThanOrEqual(5, count($seenWidgetRoutes));
    }

    /** @return array<int, string> */
    private function throttleMiddleware(RoutingRoute $route): array
    {
        return array_values(array_filter(
            $route->gatherMiddleware(),
            fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'throttle:'),
        ));
    }

    /**
     * The runtime half: exhausting one widget endpoint's allowance must leave
     * the others usable. This is the behaviour a real chat session depends on.
     */
    public function test_exhausting_one_widget_endpoint_does_not_lock_out_the_others(): void
    {
        [$tenant, $domain] = $this->createTenantWithDomain();

        tenancy()->initialize($tenant);
        $channel = Channel::create(['type' => 'web_chat', 'name' => 'Site Chat', 'status' => 'active']);
        $widget = WebChatWidget::create(['channel_id' => $channel->id, 'public_key' => Str::random(48), 'widget_name' => 'Support']);
        $key = $widget->public_key;
        tenancy()->end();

        // The rate endpoint allows 10/min. Requests with no visitor token are
        // rejected by the controller (401) but still consume the allowance,
        // because the throttle runs first — exactly the traffic shape that
        // used to poison every other guest route.
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $response = $this->postJson("http://{$domain}/widget/api/{$key}/rate", ['score' => 5]);
            $this->assertSame(401, $response->getStatusCode(), "Attempt {$attempt} returned {$response->getStatusCode()}.");
        }

        $exhausted = $this->postJson("http://{$domain}/widget/api/{$key}/rate", ['score' => 5]);
        $this->assertSame(429, $exhausted->getStatusCode(), 'The rate endpoint never ran out of allowance, so the isolation below proves nothing.');

        // Everything else on the same domain, from the same IP, must be
        // unaffected. The exact status is the controller's business; what
        // matters is that the request reached it rather than being turned away
        // by a bucket it never spent anything from.
        $this->assertSame(200, $this->getJson("http://{$domain}/widget/api/{$key}/config")->getStatusCode());
        $this->assertSame(401, $this->getJson("http://{$domain}/widget/api/{$key}/messages")->getStatusCode());
        $this->assertNotSame(429, $this->postJson("http://{$domain}/widget/api/{$key}/session", ['name' => 'Visitor', 'email' => 'v@example.test'])->getStatusCode());
    }

    /**
     * A public tenant endpoint: registered by routes/tenant.php, reachable
     * with no session, and rate limited. Scoped by route name rather than URI
     * because the shared-bucket key is `sha1(domain|ip)` — routes on the
     * central domain are a separate bucket and a separate question.
     */
    private function isPublicTenantRoute(RoutingRoute $route): bool
    {
        $name = (string) $route->getName();

        if (! str_starts_with($name, 'tenant.') || str_starts_with($name, 'tenant.admin.')) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (is_string($middleware) && str_starts_with($middleware, 'auth')) {
                return false;
            }
        }

        return $this->throttleMiddleware($route) !== [] || str_starts_with($name, 'tenant.widget.');
    }
}
