<table>
    <thead>
        <tr>
            <th>#</th>
            <th width="40">Mascota</th>
            <th width="25">Especie</th>
            <th width="40">Veterinario</th>
            <th width="25">Fecha de la vacunación</th>
            <th width="25">Estado de la vacuna</th>
            <th width="25">Estado de pago</th>
            <th width="40">Horario</th>
        </tr>
    </thead>
    <tbody>

        @foreach ($vaccinations as $key=>$vaccination)
            <tr>
                <td>{{$key+1}}</td>
                <td> {{ $vaccination->pet->name }} </td>
                <td> {{ $vaccination->pet->specie }} </td>
                <td> {{ $vaccination->veterinarie->name.' '.$vaccination->veterinarie->surname }} </td>
                <td> {{ Carbon\Carbon::parse($vaccination->vaccination_date)->format("Y/m/d") }} </td>
                    @php
                        $state_vaccination = "";
                        switch ($vaccination->state) {
                            case 1:
                                $state_vaccination = "Pendiente";
                                break;
                            case 2:
                                $state_vaccination = "Cancelado";
                                break;
                            case 3:
                                $state_vaccination = "Atendido";
                                break;
                            default:
                                # code...
                                break;
                        }
                    @endphp
                @if ($vaccination->state == 1)
                <td style="background: #fdf5b1">
                    {{$state_vaccination}}
                </td>
                @endif
                @if ($vaccination->state == 2)
                <td style="background: #ffb6b6">
                    {{$state_vaccination}}
                </td>
                @endif
                @if ($vaccination->state == 3)
                <td style="background: #d9ffb6">
                    {{$state_vaccination}}
                </td>
                @endif
                    @php
                        $state_payment = "";
                        switch ($vaccination->state_pay) {
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
                @if ($vaccination->state_pay == 1)
                <td style="background: #f7a6a6">
                    {{$state_payment}}
                </td>
                @endif
                @if ($vaccination->state_pay == 2)
                <td style="background: #b6c8ff">
                    {{$state_payment}}
                </td>
                @endif
                @if ($vaccination->state_pay == 3)
                <td style="background: #e9b6ff">
                    {{$state_payment}}
                </td>
                @endif

                <td>
                    <ul>
                        @foreach ($vaccination->schedules as $schedule)
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