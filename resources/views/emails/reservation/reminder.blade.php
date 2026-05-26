<x-mail::message>
# Recordatorio de tu reservación

¡Tu estadía comienza **mañana**! Te recordamos los detalles para que llegues preparado.

---

**Reservación #{{ $reservation->id }}**

| | |
|---|---|
| Cabaña | {{ $reservation->cabin->name }} |
| Entrada | {{ $reservation->start_date->format('d/m/Y') }} |
| Salida | {{ $reservation->end_date->format('d/m/Y') }} |
| Noches | {{ $reservation->total_days }} |
| Total | ${{ number_format($reservation->total_price, 2) }} MXN |

---

**Huéspedes:**

@foreach ($reservation->guests as $guest)
- {{ $guest->full_name }} — {{ $guest->guest_type === 'adult' ? 'Adulto' : 'Niño' }}
@endforeach

¡Te esperamos mañana! Si tienes alguna duda, no dudes en contactarnos.

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
