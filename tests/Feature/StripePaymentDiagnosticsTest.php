<?php

namespace Tests\Feature;

use App\Models\Cabin;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\StripePaymentDiagnostics;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Log;
use Mockery;
use Stripe\ApiRequestor;
use Stripe\Exception\AuthenticationException;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class StripePaymentDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    private ?string $originalConfigCachePath = null;

    public function createApplication()
    {
        // These tests must never read local .env files or cached credentials.
        $this->originalConfigCachePath = Env::getRepository()->get('APP_CONFIG_CACHE');
        Env::getRepository()->set('APP_CONFIG_CACHE', __DIR__.'/nonexistent-config.php');
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->instance(LoadEnvironmentVariables::class, new class
        {
            public function bootstrap($app): void {}
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('x', 32)),
            'app.debug' => true,
            'jwt.secret' => str_repeat('local-test-only-', 4),
            'services.stripe.secret' => 'sk_test_fake_for_local_tests',
            'services.stripe.currency' => 'mxn',
        ]);
        // Unexpected SDK requests fail locally, never fall back to the network.
        ApiRequestor::setHttpClient(Mockery::mock(ClientInterface::class));
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            if ($this->originalConfigCachePath === null) {
                Env::getRepository()->clear('APP_CONFIG_CACHE');
            } else {
                Env::getRepository()->set('APP_CONFIG_CACHE', $this->originalConfigCachePath);
            }
            ApiRequestor::setHttpClient(new \Stripe\HttpClient\CurlClient);
            \Stripe\Stripe::setApiKey(null);
        }
    }

    public function test_authenticated_endpoint_also_keeps_failure_response_generic(): void
    {
        $reservation = $this->reservation();
        $user = \App\Models\User::factory()->create();
        $reservation->update(['user_id' => $user->id]);
        config(['services.stripe.secret' => null]);
        $this->expectDiagnostic('configuration', \LogicException::class);
        $this->actingAs($user, 'api')->postJson('/api/payments/intent', [
            'reservation_id' => $reservation->id,
        ])->assertStatus(500)->assertExactJson(['message' => 'Error creating payment intent']);
    }

    public function test_missing_key_is_logged_without_contacting_stripe(): void
    {
        config(['services.stripe.secret' => null]);
        $this->expectDiagnostic('configuration', \LogicException::class);
        $this->intentRequest()->assertStatus(500)->assertExactJson([
            'message' => 'Error creating payment intent',
        ]);
    }

    public function test_stripe_authentication_error_logs_only_safe_metadata_even_in_debug(): void
    {
        $error = AuthenticationException::factory(
            'Invalid API Key sk_live_PARTIAL; guest@example.com; pi_fake_secret_PRIVATE',
            401,
            'sensitive response body',
            null,
            ['Request-Id' => 'req_fake123', 'Authorization' => 'secret'],
            'api_key_expired'
        );
        $http = Mockery::mock(ClientInterface::class);
        $http->shouldReceive('request')->once()->andThrow($error);
        ApiRequestor::setHttpClient($http);
        $this->expectDiagnostic('stripe_request', AuthenticationException::class, [
            'stripe_http_status' => 401,
            'stripe_error_code' => 'api_key_expired',
            'stripe_request_id' => 'req_fake123',
        ]);
        $this->intentRequest()->assertStatus(500)->assertExactJson([
            'message' => 'Error creating payment intent',
        ]);
    }

    public function test_transport_failure_has_no_invented_request_id(): void
    {
        $http = Mockery::mock(ClientInterface::class);
        $http->shouldReceive('request')->once()->andThrow(
            \Stripe\Exception\ApiConnectionException::factory('connection failed: secret')
        );
        ApiRequestor::setHttpClient($http);
        $this->expectDiagnostic('stripe_request', \Stripe\Exception\ApiConnectionException::class, [
            'stripe_http_status' => null,
            'stripe_error_code' => null,
            'stripe_request_id' => null,
        ]);
        $this->intentRequest()->assertStatus(500);
    }

    public function test_success_creates_an_unconfirmed_intent_and_reuses_existing_payment(): void
    {
        $http = Mockery::mock(ClientInterface::class);
        $http->shouldReceive('request')->once()->withArgs(function ($method, $url, $headers, $params) {
            return $method === 'post' && str_ends_with($url, '/v1/payment_intents')
                && $params['amount'] === 240000 && $params['currency'] === 'mxn'
                && ! isset($params['confirm']) && ! isset($params['payment_method']);
        })->andReturn([$this->fakeIntent(), 200, ['Request-Id' => 'req_fake123']]);
        ApiRequestor::setHttpClient($http);
        $reservation = $this->reservation();
        $payload = ['reservation_id' => $reservation->public_code, 'confirmation_token' => 'local-token'];
        $this->postJson('/api/public/payments/intent', $payload)->assertCreated()
            ->assertJsonPath('payment_intent_id', 'pi_fake');
        $this->postJson('/api/public/payments/intent', $payload)->assertOk();
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('pending', $reservation->fresh()->status);
    }

    public function test_database_failure_is_distinguished_from_stripe_failure(): void
    {
        $http = Mockery::mock(ClientInterface::class);
        $http->shouldReceive('request')->once()->andReturn([$this->fakeIntent(), 200, []]);
        ApiRequestor::setHttpClient($http);
        Payment::creating(function () {
            throw new \RuntimeException('SQL with pi_fake_secret_PRIVATE and guest@example.com');
        });
        $this->expectDiagnostic('persist_payment', \RuntimeException::class);
        $this->intentRequest()->assertStatus(500)->assertExactJson([
            'message' => 'Error creating payment intent',
        ]);
    }

    public function test_diagnostic_drops_unstructured_error_fields(): void
    {
        $this->expectDiagnostic('stripe_request', AuthenticationException::class, [
            'stripe_http_status' => 401,
            'stripe_error_code' => null,
            'stripe_request_id' => null,
        ]);
        app(StripePaymentDiagnostics::class)->report(AuthenticationException::factory(
            'secret', 401, null, null, ['Request-Id' => "req_fake\nsecret"], 'guest@example.com'
        ), 'stripe_request', 'public');
    }

    public function test_configuration_command_outputs_only_booleans_or_null(): void
    {
        config(['app.debug' => false]);
        $checks = app(StripePaymentDiagnostics::class)->configuration();
        foreach ($checks as $value) {
            $this->assertTrue(is_bool($value) || $value === null);
        }
        $this->artisan('payments:diagnose-config')
            ->expectsOutput(json_encode($checks, JSON_THROW_ON_ERROR))
            ->assertExitCode($checks['config_matches_runtime_secret'] === false ? 1 : 0);
    }

    private function expectDiagnostic(string $stage, string $type, array $extra = []): void
    {
        $logger = Mockery::mock(\Psr\Log\LoggerInterface::class);
        Log::shouldReceive('channel')->once()->with('payments')->andReturn($logger);
        $logger->shouldReceive('error')->once()->with('Payment intent creation failed', Mockery::on(
            function (array $context) use ($stage, $type, $extra): bool {
                $this->assertSame($stage, $context['stage']);
                $this->assertSame($type, $context['exception_type']);
                foreach ($extra as $key => $value) {
                    $this->assertArrayHasKey($key, $context);
                    $this->assertSame($value, $context[$key]);
                }
                $json = json_encode($context);
                foreach (['sk_live_', 'sk_test_', 'PRIVATE', 'guest@example.com', 'local-token', 'Authorization', 'sensitive response body', 'SQL with'] as $forbidden) {
                    $this->assertStringNotContainsString($forbidden, $json);
                }
                $this->assertArrayNotHasKey('exception', $context);

                return true;
            }
        ));
    }

    private function intentRequest()
    {
        $reservation = $this->reservation();

        return $this->postJson('/api/public/payments/intent', [
            'reservation_id' => $reservation->public_code,
            'confirmation_token' => 'local-token',
        ]);
    }

    private function reservation(): Reservation
    {
        $cabin = Cabin::create([
            'name' => 'Test cabin', 'price_per_night' => 1200, 'capacity' => 2,
            'beds' => 1, 'bathrooms' => 1, 'status' => 'available',
        ]);

        return Reservation::create([
            'cabin_id' => $cabin->id, 'start_date' => now()->addDay(),
            'end_date' => now()->addDays(3), 'guest_count' => 1,
            'total_days' => 2, 'total_price' => 2400, 'status' => 'pending',
            'confirmation_token' => bcrypt('local-token'),
        ]);
    }

    private function fakeIntent(): string
    {
        return json_encode([
            'id' => 'pi_fake', 'object' => 'payment_intent',
            'client_secret' => 'pi_fake_secret_PRIVATE', 'status' => 'requires_payment_method',
        ], JSON_THROW_ON_ERROR);
    }
}
