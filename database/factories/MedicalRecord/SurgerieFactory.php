<?php

namespace Database\Factories\MedicalRecord;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Pets\Pet;
use App\Models\Surgerie\Surgerie;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class SurgerieFactory extends Factory
{
    protected $model = Surgerie::class;
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
            $surgerie_date = $this->faker->dateTimeBetween("2024-01-01 00:00:00", "2024-12-25 23:59:59");
            // $surgerie_date = $this->faker->dateTimeBetween("2023-01-01 00:00:00", "2023-12-25 23:59:59");
        } while (in_array($surgerie_date->format('N'), [6, 7]));
        $status = $this->faker->randomElement([1, 2 , 3]);
        return [
            "veterinarie_id" => $veterinarie->id,
            "pet_id" => $pet->id,
            "day" => Carbon::parse($surgerie_date)->dayName,
            "surgerie_date" => $surgerie_date,
            "surgerie_type" => $this->faker->randomElement([
                "ESTERILIZACIÓN",
                "CASTRACIÓN",
                "TRAUMATOLÓGICAS",
                "OCULARES",
                "ONCOLÓGICAS",
                "OTRO"
            ]),
            "medical_notes" => $this->faker->text($maxNbChars = 350),
            "reprogramar" => $this->faker->randomElement([1,0]),
            "outside" => $this->faker->randomElement([0,1]),
            "state" => $status,
            "user_id" => User::all()->random()->id,
            "amount" => $this->faker->randomElement([600,750,300,250,880,120,495,575,1060,530,610]),
            "state_pay" => $this->faker->randomElement([1, 2, 3]),
            "created_at" => Carbon::parse($surgerie_date)->subDay($this->faker->randomElement([2,4,5])),
        ];
    }
}
