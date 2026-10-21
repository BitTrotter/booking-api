<x-emails.layout title="New reservation">
    <p class="eyebrow">Booking #{{ $reservation->public_code }}</p>
    <h1>A new reservation was created.</h1>
    <p class="lead">Source: {{ $reservation->created_by === null ? 'Public website' : 'Admin panel' }}</p>
    <p class="lead">Guest: {{ $reservation->full_name }}</p>
    <p class="lead">Email: {{ $reservation->email ?: 'Not provided' }}</p>
    <p class="lead">Phone: {{ $reservation->phone }}</p>
    @if ($reservation->reservation_group_id)
        <p class="lead">Reservation group: {{ $reservation->reservation_group_id }}</p>
    @endif
    <x-emails.reservation-details :reservation="$reservation" />
    @if ($reservation->status === 'pending')
        <p class="closing">This reservation is pending; payment has not yet been confirmed.</p>
    @endif
</x-emails.layout>
