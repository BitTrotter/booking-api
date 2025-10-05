<?php

namespace App\Console\Commands;

use App\Models\MedicalRecord;
use Illuminate\Console\Command;
use App\Models\Appointment\Appointment;

class AppointmentScheduleRecord extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:appointment-schedule-record';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Para todas las citas medicas que tengamos registradas, vamos a asignarle su historial medico';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $appointments = Appointment::orderBy("id","desc")->get();

        foreach ($appointments as $key => $appointment) {
            MedicalRecord::create([
                "veterinarie_id" => $appointment->veterinarie_id,
                "pet_id"=> $appointment->pet_id,
                "event_type" => 1,
                "event_date" => $appointment->date_appointment,
                "appointment_id" => $appointment->id,
            ]);
        }
    }
}
