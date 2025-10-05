<table>
    <thead>
        <tr>
            <th>#</th>
            <th width="40">Mascota</th>
            <th width="25">Especie</th>
            <th width="40">Veterinario</th>
            <th width="25">Fecha de la cita</th>
            <th width="25">Estado de la cita</th>
            <th width="25">Estado de pago</th>
            <th width="40">Horario</th>
        </tr>
    </thead>
    <tbody>

        @foreach ($appointments as $key=>$appointment)
            <tr>
                <td>{{$key+1}}</td>
                <td> {{ $appointment->pet->name }} </td>
                <td> {{ $appointment->pet->specie }} </td>
                <td> {{ $appointment->veterinarie->name.' '.$appointment->veterinarie->surname }} </td>
                <td> {{ Carbon\Carbon::parse($appointment->date_appointment)->format("Y/m/d") }} </td>
                    @php
                        $state_appointment = "";
                        switch ($appointment->state) {
                            case 1:
                                $state_appointment = "Pendiente";
                                break;
                            case 2:
                                $state_appointment = "Cancelado";
                                break;
                            case 3:
                                $state_appointment = "Atendido";
                                break;
                            default:
                                # code...
                                break;
                        }
                    @endphp
                @if ($appointment->state == 1)
                <td style="background: #fdf5b1">
                    {{$state_appointment}}
                </td>
                @endif
                @if ($appointment->state == 2)
                <td style="background: #ffb6b6">
                    {{$state_appointment}}
                </td>
                @endif
                @if ($appointment->state == 3)
                <td style="background: #d9ffb6">
                    {{$state_appointment}}
                </td>
                @endif
                    @php
                        $state_payment = "";
                        switch ($appointment->state_pay) {
                            case 1:
                                $state_payment = "Pendiente";
                                break;
                            case 2:
                                $state_payment = "Parcial";
                                break;
                            case 3:
                                $state_payment = "Completo";
                                break;
                            default:
                                # code...
                                break;
                        }
                    @endphp
                @if ($appointment->state_pay == 1)
                <td style="background: #f7a6a6">
                    {{$state_payment}}
                </td>
                @endif
                @if ($appointment->state_pay == 2)
                <td style="background: #b6c8ff">
                    {{$state_payment}}
                </td>
                @endif
                @if ($appointment->state_pay == 3)
                <td style="background: #e9b6ff">
                    {{$state_payment}}
                </td>
                @endif

                <td>
                    <ul>
                        @foreach ($appointment->schedules as $schedule)
                            <li>
                                {{Carbon\Carbon::parse(date("Y-m-d").' '.$schedule->schedule_hour->hour_start)->format("h:i A") .' '.Carbon\Carbon::parse(date("Y-m-d").' '.$schedule->schedule_hour->hour_end)->format("h:i A")}}
                            </li>
                        @endforeach
                    </ul>
                </td>
            </tr>
        @endforeach

    </tbody>
</table>