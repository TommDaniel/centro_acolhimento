<?php

namespace Database\Factories;

use App\Models\Crianca;
use App\Models\CriancaInformacaoEscolar;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CriancaInformacaoEscolar>
 */
class CriancaInformacaoEscolarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crianca_id' => fn (): int => Crianca::query()->create([
                'nome_completo' => 'Pessoa Escolar Fictícia '.fake()->unique()->numerify('####'),
            ])->id,
            'situacao_codigo' => 'matriculada',
            'escola_nome' => 'Escola Fictícia '.fake()->unique()->numerify('####'),
            'rede_codigo' => 'municipal',
            'matricula' => 'MATR-FICT-'.fake()->unique()->numerify('#####'),
            'ano_serie' => 'Ano fictício',
            'turma' => 'Turma fictícia',
            'turno_codigo' => 'matutino',
            'vigente_em' => now('America/Sao_Paulo')->toDateString(),
            'fonte_codigo' => 'documento',
            'created_by' => User::factory(),
            'recorded_at' => now('UTC'),
            'idempotency_key' => (string) Str::uuid(),
        ];
    }
}
