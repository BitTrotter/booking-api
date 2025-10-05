<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationScheduleMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public $medical_record;
    public function __construct($data)
    {
        $this->medical_record = $data;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $type_service = "";
        switch ($this->medical_record["event_type"]) {
            case 1:
                $type_service = "Cita Medica";
                break;
            case 2:
                $type_service = "Vacuna";
                break;
            case 3:
                $type_service = "Cirujía";
                break;
            default:
                # code...
                break;
        }
        $hour_start = $this->medical_record["hour_start"];
        return new Envelope(
            subject: 'Recordatorio de '.$type_service.' '.$hour_start,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'email.medical_record_email',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
