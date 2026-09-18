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

    public function __construct(public Reservation $reservation, public string $previousStatus) {}

    public function envelope(): Envelope
    {
        $subjects = [
            'confirmed' => 'Booking confirmed #' . $this->reservation->id,
            'active' => 'Your stay begins today! Booking #' . $this->reservation->id,
            'cancelled' => 'Booking cancelled #' . $this->reservation->id,
            'pending' => 'Booking under review #' . $this->reservation->id,
            'completed' => 'Your stay has ended — Booking #' . $this->reservation->id,
        ];

        return new Envelope(subject: $subjects[$this->reservation->status] ?? 'Booking update #' . $this->reservation->id);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.reservation.status_changed');
    }
}
