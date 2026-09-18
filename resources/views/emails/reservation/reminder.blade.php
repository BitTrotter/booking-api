<x-emails.layout title="Your stay is tomorrow">
    <p class="eyebrow">Booking #{{ $reservation->id }}</p>
    <h1>The mountains are waiting.</h1>
    <p class="lead">Your stay begins tomorrow. Here are your booking details so you can arrive fully prepared.</p>
    <x-emails.reservation-details :reservation="$reservation" />
    <p class="closing">If you have any questions before you arrive, just reply to this email. See you soon!</p>
</x-emails.layout>
