<?php

namespace App\Console\Commands;

use App\Services\StripePaymentDiagnostics;
use Illuminate\Console\Command;

class DiagnoseStripeConfiguration extends Command
{
    protected $signature = 'payments:diagnose-config';

    protected $description = 'Show only safe Stripe configuration checks, without contacting Stripe';

    public function handle(StripePaymentDiagnostics $diagnostics): int
    {
        $checks = $diagnostics->configuration();
        $this->line(json_encode($checks, JSON_THROW_ON_ERROR));

        return $checks['secret_configured'] && ! $checks['secret_has_whitespace']
            && ! $checks['debug_enabled'] && $checks['config_matches_runtime_secret'] !== false
                ? self::SUCCESS : self::FAILURE;
    }
}
