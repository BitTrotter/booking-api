<?php

namespace Database\Seeders;

use App\Models\MedicalRecord;
use Illuminate\Database\Seeder;
use App\Models\Appointment\Appointment;
use App\Models\Appointment\AppointmentPayment;
use App\Models\Appointment\AppointmentSchedule;
use App\Models\Veterinarie\VeterinarieScheduleHour;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class AppointmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Appointment::factory()->count(1000)->create()->each(function($p) {//$p -> Cita medica registrada
            $faker = \Faker\Factory::create();

            $veterinarie_schedule_hour =  VeterinarieScheduleHour::inRandomOrder()->first();
            AppointmentSchedule::create([
                "appointment_id" => $p->id,
                "veterinarie_schedule_hour_id" => $veterinarie_schedule_hour->id,
            ]);
            
            MedicalRecord::create([ 
                "veterinarie_id" => $p->veterinarie_id,
                "pet_id" => $p->pet_id,
                "event_type" => 1,
                "event_date"=> $p->date_appointment,
                "appointment_id" => $p->id,
                // "vaccination_id",
                // "surgerie_id",
                "notes" => $p->state == 3 ? $faker->text($maxNbChars = 350) : NULL,
                "created_at" => $p->created_at,
            ]);
            if($p->state_pay == 2){
                AppointmentPayment::create([
                    "appointment_id" => $p->id,
                    "amount" => 50,
                    "method_payment" => $faker->randomElement(["EFECTIVO","TRANSFERENCIA","YAPE","PLIN"]),
                ]);
            }
            if($p->state_pay == 3){
                AppointmentPayment::create([
                    "appointment_id" => $p->id,
                    "amount" => $p->amount,
                    "method_payment" => $faker->randomElement(["EFECTIVO","TRANSFERENCIA","YAPE","PLIN"]),
                ]);
            }
        });
         // php artisan db:seed --class=AppointmentSeeder
    }
}
