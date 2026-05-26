<?php

namespace App\Console\Commands;

use App\Mail\ReservationReminderMail;
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
        $this->activate();
        $this->completeReservations();
        $this->sendReminders();
    }

    private function activate(): void
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

    private function completeReservations(): void
    {
        $today = Carbon::today()->toDateString();

        $reservations = Reservation::with(['cabin', 'guests'])
            ->where('status', 'active')
            ->where('end_date', '<=', $today)
            ->get();

        foreach ($reservations as $reservation) {
            $reservation->update(['status' => 'completed']);
            $this->mail->send($reservation->email, new ReservationStatusChangedMail($reservation, 'active'));
        }

        $this->info("Completed {$reservations->count()} reservation(s).");
    }

    private function sendReminders(): void
    {
        $tomorrow = Carbon::tomorrow()->toDateString();

        $reservations = Reservation::with(['cabin', 'guests'])
            ->where('status', 'confirmed')
            ->where('start_date', $tomorrow)
            ->get();

        foreach ($reservations as $reservation) {
            $this->mail->send($reservation->email, new ReservationReminderMail($reservation));
        }

        $this->info("Sent reminders for {$reservations->count()} reservation(s).");
    }
}
