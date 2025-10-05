<?php

namespace Database\Seeders;

use App\Models\MedicalRecord;
use Illuminate\Database\Seeder;
use App\Models\Surgerie\Surgerie;
use App\Models\Surgerie\SurgeriePayment;
use App\Models\Surgerie\SurgerieSchedule;
use App\Models\Veterinarie\VeterinarieScheduleHour;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class SurgerieSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Surgerie::factory()->count(1000)->create()->each(function($p) {
            $faker = \Faker\Factory::create();

            $veterinarie_schedule_hour =  VeterinarieScheduleHour::inRandomOrder()->first();
            SurgerieSchedule::create([
                "surgerie_id" => $p->id,
                "hour" =>  $veterinarie_schedule_hour->hour,
                "veterinarie_schedule_hour_id" => $veterinarie_schedule_hour->id,
            ]);

            MedicalRecord::create([ 
                "veterinarie_id" => $p->veterinarie_id,
                "pet_id" => $p->pet_id,
                "event_type" => 3,
                "event_date"=> $p->surgerie_date,
                "surgerie_id" => $p->id,
                // "vaccination_id",
                "notes" => $p->state == 3 ? $faker->text($maxNbChars = 350) : NULL,
                "created_at" => $p->created_at,
            ]);
            if($p->state_pay == 2){
                SurgeriePayment::create([
                    "surgerie_id" => $p->id,
                    "amount" => $faker->randomElement([150,250,190]),
                    "method_payment" => $faker->randomElement(["EFECTIVO","TRANSFERENCIA","YAPE","PLIN"]),
                ]);
            }
            if($p->state_pay == 3){
                SurgeriePayment::create([
                    "surgerie_id" => $p->id,
                    "amount" => $p->amount,
                    "method_payment" => $faker->randomElement(["EFECTIVO","TRANSFERENCIA","YAPE","PLIN"]),
                ]);
            }
        });
        // php artisan db:seed --class=SurgerieSeeder
    }
}
