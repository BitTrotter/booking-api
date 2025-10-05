<?php

namespace Database\Seeders;

use App\Models\MedicalRecord;
use Illuminate\Database\Seeder;
use App\Models\Vaccination\Vaccination;
use App\Models\Vaccination\VaccinationPayment;
use App\Models\Vaccination\VaccinationSchedule;
use App\Models\Veterinarie\VeterinarieScheduleHour;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class VaccinationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Vaccination::factory()->count(1000)->create()->each(function($p) {
            $faker = \Faker\Factory::create();

            $veterinarie_schedule_hour =  VeterinarieScheduleHour::inRandomOrder()->first();
            VaccinationSchedule::create([
                "vaccination_id" => $p->id,
                "hour" =>  $veterinarie_schedule_hour->hour,
                "veterinarie_schedule_hour_id" => $veterinarie_schedule_hour->id,
            ]);

            MedicalRecord::create([ 
                "veterinarie_id" => $p->veterinarie_id,
                "pet_id" => $p->pet_id,
                "event_type" => 2,
                "event_date"=> $p->vaccination_date,
                "vaccination_id" => $p->id,
                "notes" => $p->state == 3 ? $faker->text($maxNbChars = 350) : NULL,
                "created_at" => $p->created_at,
            ]);
            if($p->state_pay == 2){
                VaccinationPayment::create([
                    "vaccination_id" => $p->id,
                    "amount" => $faker->randomElement([150,250,190]),
                    "method_payment" => $faker->randomElement(["EFECTIVO","TRANSFERENCIA","YAPE","PLIN"]),
                ]);
            }
            if($p->state_pay == 3){
                VaccinationPayment::create([
                    "vaccination_id" => $p->id,
                    "amount" => $p->amount,
                    "method_payment" => $faker->randomElement(["EFECTIVO","TRANSFERENCIA","YAPE","PLIN"]),
                ]);
            }
        });
        // php artisan db:seed --class=VaccinationSeeder
    }
}
