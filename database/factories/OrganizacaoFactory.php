<?php

namespace Database\Factories;

use App\Models\Organizacao;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organizacao>
 */
class OrganizacaoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => 'org-ficticia-'.fake()->unique()->numerify('#####'),
            'nome' => 'Organização Fictícia '.fake()->unique()->numerify('#####'),
        ];
    }
}
