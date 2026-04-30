<x-mail::message>
# Reservación recibida

Hola, hemos recibido tu reservación. Aquí están los detalles:

---

**Reservación #{{ $reservation->id }}**

| | |
|---|---|
| Cabaña | {{ $reservation->cabin->name }} |
| Entrada | {{ $reservation->start_date->format('d/m/Y') }} |
| Salida | {{ $reservation->end_date->format('d/m/Y') }} |
| Noches | {{ $reservation->total_days }} |
| Total | ${{ number_format($reservation->total_price, 2) }} MXN |
| Estado | {{ ucfirst($reservation->status) }} |

---

**Huéspedes ({{ $reservation->guest_count }}):**

@foreach ($reservation->guests as $guest)
- {{ $guest->full_name }} — {{ $guest->guest_type === 'adult' ? 'Adulto' : 'Niño' }}
@endforeach

---

**Contacto registrado:**
- Correo: {{ $reservation->email }}
- Teléfono: {{ $reservation->phone }}

Nos pondremos en contacto contigo para confirmar tu reservación.

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
