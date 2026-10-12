<?php

namespace App\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class MailService
{
    public function send(?string $to, Mailable $mailable): void
    {
        if ($to === null || trim($to) === '') {
            return;
        }

        try {
            Mail::to($to)->send($mailable);
        } catch (\Throwable $e) {
            Log::error('Email sending failed', [
                'to'       => $to,
                'mailable' => get_class($mailable),
                'error'    => $e->getMessage(),
            ]);
        }
    }
}
