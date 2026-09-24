<?php

namespace Database\Factories;

use App\Models\Organizacao;
use App\Models\Unidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unidade>
 */
class UnidadeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organizacao_id' => Organizacao::factory(),
            'codigo' => 'unidade-ficticia-'.fake()->unique()->numerify('#####'),
            'nome' => 'Unidade Fictícia '.fake()->unique()->numerify('#####'),
        ];
    }
}
