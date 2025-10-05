<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\FromCollection;

class DownloadAppointment implements FromView
{
    protected $appointments;
    public function __construct($records_appointments)
    {
        $this->appointments = $records_appointments;
    }
    /**
    * @return \Illuminate\Support\Collection
    */
    public function view(): View
    {
        return view("appointments.download_excel",[
            "appointments" => $this->appointments,
        ]);
    }
}
