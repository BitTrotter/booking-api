@php
    $messages = [
        'confirmed' => ['Your booking is confirmed.', 'Pack your bags — we look forward to welcoming you into nature.'],
        'active' => ['Your stay begins today!', 'Your cabin is ready for you. Welcome!'],
        'cancelled' => ['Your booking was cancelled.', 'If you need help or would like to make a new booking, reply to this email.'],
        'completed' => ['Thank you for staying with us.', 'We hope your stay was filled with memorable moments.'],
        'pending' => ['Your booking is under review.', 'We are reviewing your booking information and will contact you shortly.'],
    ];
    [$headline, $description] = $messages[$reservation->status] ?? ['Your booking was updated.', 'Your booking status has changed.'];
@endphp
<x-emails.layout title="Booking update">
    <p class="eyebrow">Booking #{{ $reservation->id }}</p>
    <h1>{{ $headline }}</h1>
    <p class="lead">{{ $description }}</p>
    <x-emails.reservation-details :reservation="$reservation" :previous-status="$previousStatus" />
</x-emails.layout>
