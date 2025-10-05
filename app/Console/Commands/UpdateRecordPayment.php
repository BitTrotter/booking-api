<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Surgerie\Surgerie;
use App\Models\Appointment\Appointment;
use App\Models\Vaccination\Vaccination;

class UpdateRecordPayment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-record-payment';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Para actualizar las fechas de pagos de cada uno de los servicios que hemos creado';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $appointments = Appointment::orderBy("id","desc")->get();
        $surgeries = Surgerie::orderBy("id","desc")->get();
        $vaccinations = Vaccination::orderBy("id","desc")->get();
        
        foreach ($appointments as $key => $appointment) {
            $payment = $appointment->payments->first();
            if($payment){
                $payment->update([
                    "created_at" => $appointment->created_at,
                ]);
            }
        }
        foreach ($surgeries as $key => $surgerie) {
            $payment = $surgerie->payments->first();
            if($payment){
                $payment->update([
                    "created_at" => $surgerie->created_at,
                ]);
            }
        }
        foreach ($vaccinations as $key => $vaccination) {
            $payment = $vaccination->payments->first();
            if($payment){
                $payment->update([
                    "created_at" => $vaccination->created_at,
                ]);
            }
        }
    }
}
