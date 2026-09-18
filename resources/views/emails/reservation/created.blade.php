<x-emails.layout title="Booking received">
    <p class="eyebrow">Booking #{{ $reservation->id }}</p>
    <h1>Your getaway starts here.</h1>
    <p class="lead">Hello, we received your booking request. We will be in touch shortly to confirm it.</p>
    <x-emails.reservation-details :reservation="$reservation" />
    <p class="closing">Thank you for choosing {{ config('app.name') }}. We look forward to welcoming you into nature.</p>
</x-emails.layout>
