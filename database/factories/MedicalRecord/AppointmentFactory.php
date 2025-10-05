<?php

namespace Database\Factories\MedicalRecord;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Pets\Pet;
use App\Models\Appointment\Appointment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $veterinarie = User::whereHas("roles",function($q) {
            $q->where("name","ilike","%veterinario%");
        })->inRandomOrder()->first();
        $pet = Pet::inRandomOrder()->first();
        date_default_timezone_set('America/Lima');
        Carbon::setLocale('es');
        do {
            $date_appointment = $this->faker->dateTimeBetween("2024-01-01 00:00:00", "2024-12-25 23:59:59");
            // $date_appointment = $this->faker->dateTimeBetween("2023-01-01 00:00:00", "2023-12-25 23:59:59");
        } while (in_array($date_appointment->format('N'), [6, 7]));
        $status = $this->faker->randomElement([1, 2 , 3]);
        return [
            "veterinarie_id" => $veterinarie->id,
            "pet_id" => $pet->id,
            "day" => Carbon::parse($date_appointment)->dayName,
            "date_appointment" => $date_appointment,
            "reason" => $this->faker->text($maxNbChars = 350),
            "reprogramar" => $this->faker->randomElement([1,0]),
            "state" => $status,
            "user_id" => User::all()->random()->id,
            "amount" => $this->faker->randomElement([100,150,200,250,80,120,95,75,160,230,110]),
            "state_pay" => $this->faker->randomElement([1, 2, 3]),
            "created_at" => Carbon::parse($date_appointment)->subDay($this->faker->randomElement([2,4,5])),
        ];
    }
}
