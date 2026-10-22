<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Throwable;

class StripePaymentDiagnostics
{
    public function configuration(): array
    {
        $secret = config('services.stripe.secret');
        $runtimeSecret = getenv('STRIPE_SECRET_KEY');

        return [
            'secret_configured' => is_string($secret) && trim($secret) !== '',
            'secret_has_whitespace' => is_string($secret) && preg_match('/\s/', $secret) === 1,
            'configuration_cached' => app()->configurationIsCached(),
            'runtime_secret_present' => is_string($runtimeSecret) && $runtimeSecret !== '',
            'config_matches_runtime_secret' => is_string($secret) && is_string($runtimeSecret)
                ? hash_equals($secret, $runtimeSecret) : null,
            'debug_enabled' => (bool) config('app.debug'),
        ];
    }

    public function report(Throwable $exception, string $stage, string $flow): void
    {
        // Never log the exception object, trace, original message, SQL, or request.
        // Even an invalid-key error can contain fragments of a credential. Replace
        // free text entirely instead of attempting to redact known key patterns.
        $context = [
            'flow' => $flow,
            'stage' => $stage,
            'exception_type' => get_class($exception),
            'message' => 'Payment intent operation failed; original exception message omitted.',
            ...$this->configuration(),
        ];

        if ($exception instanceof ApiErrorException) {
            $context['stripe_http_status'] = $exception->getHttpStatus();
            $context['stripe_error_code'] = $this->safeIdentifier($exception->getStripeCode(), '/\A[a-z][a-z0-9_]{0,99}\z/');
            $context['stripe_request_id'] = $this->safeIdentifier($exception->getRequestId(), '/\Areq_[a-zA-Z0-9]{1,100}\z/');
        }

        Log::channel('payments')->error('Payment intent creation failed', $context);
    }

    private function safeIdentifier(?string $value, string $pattern): ?string
    {
        return $value !== null && preg_match($pattern, $value) === 1 ? $value : null;
    }
}
