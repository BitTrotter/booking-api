<table>
    <thead>
        <tr>
            <th>#</th>
            <th width="40">Mascota</th>
            <th width="25">Especie</th>
            <th width="40">Veterinario</th>
            <th width="25">Fecha de la cirugia</th>
            <th width="25">Estado de la cirugia</th>
            <th width="25">Estado de pago</th>
            <th width="40">Horario</th>
        </tr>
    </thead>
    <tbody>

        @foreach ($surgeries as $key=>$surgerie)
            <tr>
                <td>{{$key+1}}</td>
                <td> {{ $surgerie->pet->name }} </td>
                <td> {{ $surgerie->pet->specie }} </td>
                <td> {{ $surgerie->veterinarie->name.' '.$surgerie->veterinarie->surname }} </td>
                <td> {{ Carbon\Carbon::parse($surgerie->surgerie_date)->format("Y/m/d") }} </td>
                    @php
                        $state_surgerie = "";
                        switch ($surgerie->state) {
                            case 1:
                                $state_surgerie = "Pendiente";
                                break;
                            case 2:
                                $state_surgerie = "Cancelado";
                                break;
                            case 3:
                                $state_surgerie = "Atendido";
                                break;
                            default:
                                # code...
                                break;
                        }
                    @endphp
                @if ($surgerie->state == 1)
                <td style="background: #fdf5b1">
                    {{$state_surgerie}}
                </td>
                @endif
                @if ($surgerie->state == 2)
                <td style="background: #ffb6b6">
                    {{$state_surgerie}}
                </td>
                @endif
                @if ($surgerie->state == 3)
                <td style="background: #d9ffb6">
                    {{$state_surgerie}}
                </td>
                @endif
                    @php
                        $state_payment = "";
                        switch ($surgerie->state_pay) {
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
                @if ($surgerie->state_pay == 1)
                <td style="background: #f7a6a6">
                    {{$state_payment}}
                </td>
                @endif
                @if ($surgerie->state_pay == 2)
                <td style="background: #b6c8ff">
                    {{$state_payment}}
                </td>
                @endif
                @if ($surgerie->state_pay == 3)
                <td style="background: #e9b6ff">
                    {{$state_payment}}
                </td>
                @endif

                <td>
                    <ul>
                        @foreach ($surgerie->schedules as $schedule)
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