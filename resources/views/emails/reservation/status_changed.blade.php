<x-mail::message>
# Actualización de reservación

@if ($reservation->status === 'confirmed')
Tu reservación ha sido **confirmada**. ¡Te esperamos!
@elseif ($reservation->status === 'cancelled')
Tu reservación ha sido **cancelada**. Si tienes dudas, contáctanos.
@else
El estado de tu reservación ha cambiado a **{{ $reservation->status }}**.
@endif

---

**Reservación #{{ $reservation->id }}**

| | |
|---|---|
| Cabaña | {{ $reservation->cabin->name }} |
| Entrada | {{ $reservation->start_date->format('d/m/Y') }} |
| Salida | {{ $reservation->end_date->format('d/m/Y') }} |
| Noches | {{ $reservation->total_days }} |
| Total | ${{ number_format($reservation->total_price, 2) }} MXN |
| Estado anterior | {{ ucfirst($previousStatus) }} |
| Estado actual | {{ ucfirst($reservation->status) }} |

---

**Huéspedes:**

@foreach ($reservation->guests as $guest)
- {{ $guest->full_name }} — {{ $guest->guest_type === 'adult' ? 'Adulto' : 'Niño' }}
@endforeach

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
