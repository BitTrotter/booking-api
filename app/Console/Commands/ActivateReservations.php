<?php

namespace App\Console\Commands;

use App\Mail\ReservationStatusChangedMail;
use App\Models\Reservation;
use App\Services\MailService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ActivateReservations extends Command
{
    protected $signature = 'reservations:activate';
    protected $description = 'Mark confirmed reservations as active when their start date has arrived';

    public function __construct(private MailService $mail)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $today = Carbon::today()->toDateString();

        $reservations = Reservation::with(['cabin', 'guests'])
            ->where('status', 'confirmed')
            ->where('start_date', '<=', $today)
            ->where('end_date', '>', $today)
            ->get();

        foreach ($reservations as $reservation) {
            $reservation->update(['status' => 'active']);
            $this->mail->send($reservation->email, new ReservationStatusChangedMail($reservation, 'confirmed'));
        }

        $this->info("Activated {$reservations->count()} reservation(s).");
    }
}
