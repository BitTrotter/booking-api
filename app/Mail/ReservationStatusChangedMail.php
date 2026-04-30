<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationStatusChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Reservation $reservation,
        public string $previousStatus,
    ) {}

    public function envelope(): Envelope
    {
        $subjects = [
            'confirmed'  => 'Reservación confirmada #' . $this->reservation->id,
            'cancelled'  => 'Reservación cancelada #' . $this->reservation->id,
            'pending'    => 'Reservación en revisión #' . $this->reservation->id,
        ];

        return new Envelope(
            subject: $subjects[$this->reservation->status] ?? 'Actualización de reservación #' . $this->reservation->id,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.reservation.status_changed',
        );
    }
}
