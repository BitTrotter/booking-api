@props(['reservation', 'previousStatus' => null])

@php
    $statusLabels = ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'active' => 'Active', 'cancelled' => 'Cancelled', 'completed' => 'Completed'];
    $status = $statusLabels[$reservation->status] ?? ucfirst($reservation->status);
@endphp

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border:1px solid #ded0bb; border-collapse:collapse; margin:28px 0;">
    <tr><td colspan="2" style="background:#f3eadc; color:#654b34; font-family:Georgia, 'Times New Roman', serif; font-size:17px; font-weight:bold; padding:16px 20px;">Your stay details</td></tr>
    <tr><td style="border-top:1px solid #eee5d8; color:#7a6a59; font-size:13px; padding:13px 20px; width:42%;">Cabin</td><td style="border-top:1px solid #eee5d8; color:#30271f; font-size:14px; font-weight:bold; padding:13px 20px;">{{ $reservation->cabin->name }}</td></tr>
    <tr><td style="border-top:1px solid #eee5d8; color:#7a6a59; font-size:13px; padding:13px 20px;">Check-in</td><td style="border-top:1px solid #eee5d8; color:#30271f; font-size:14px; padding:13px 20px;">{{ $reservation->start_date->format('d/M/Y') }}</td></tr>
    <tr><td style="border-top:1px solid #eee5d8; color:#7a6a59; font-size:13px; padding:13px 20px;">Check-out</td><td style="border-top:1px solid #eee5d8; color:#30271f; font-size:14px; padding:13px 20px;">{{ $reservation->end_date->format('d/M/Y') }}</td></tr>
    <tr><td style="border-top:1px solid #eee5d8; color:#7a6a59; font-size:13px; padding:13px 20px;">Nights</td><td style="border-top:1px solid #eee5d8; color:#30271f; font-size:14px; padding:13px 20px;">{{ $reservation->total_days }}</td></tr>
    <tr><td style="border-top:1px solid #eee5d8; color:#7a6a59; font-size:13px; padding:13px 20px;">Guests</td><td style="border-top:1px solid #eee5d8; color:#30271f; font-size:14px; padding:13px 20px;">{{ $reservation->guest_count }}</td></tr>
    <tr><td style="border-top:1px solid #eee5d8; color:#7a6a59; font-size:13px; padding:13px 20px;">Total</td><td style="border-top:1px solid #eee5d8; color:#654b34; font-size:16px; font-weight:bold; padding:13px 20px;">${{ number_format($reservation->total_price, 2) }} MXN</td></tr>
    <tr><td style="border-top:1px solid #eee5d8; color:#7a6a59; font-size:13px; padding:13px 20px;">Status</td><td style="border-top:1px solid #eee5d8; color:#654b34; font-size:14px; font-weight:bold; padding:13px 20px;">{{ $status }}</td></tr>
    {{-- @if ($previousStatus)
        <tr><td style="border-top:1px solid #eee5d8; color:#7a6a59; font-size:13px; padding:13px 20px;">Previous status</td><td style="border-top:1px solid #eee5d8; color:#30271f; font-size:14px; padding:13px 20px;">{{ $statusLabels[$previousStatus] ?? ucfirst($previousStatus) }}</td></tr>
    @endif --}}
</table>

