<?php

namespace Database\Factories;

use App\Enums\AcolhimentoMovimentacaoTipo;
use App\Models\Acolhimento;
use App\Models\AcolhimentoMovimentacao;
use App\Models\Crianca;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Acolhimento>
 */
class AcolhimentoFactory extends Factory
{
    public function configure(): static
    {
        return $this->afterCreating(function (Acolhimento $acolhimento): void {
            if (! $acolhimento->movimentacoes()->exists()) {
                AcolhimentoMovimentacao::factory()->create([
                    'acolhimento_id' => $acolhimento->id,
                    'tipo' => AcolhimentoMovimentacaoTipo::Ingresso,
                    'situacao_resultante' => AcolhimentoMovimentacaoTipo::Ingresso->situacaoResultante(),
                    'efetiva_em' => $acolhimento->ingresso_em,
                    'motivo' => $acolhimento->motivo,
                    'fundamento' => $acolhimento->fundamento,
                    'created_by' => $acolhimento->created_by,
                    'idempotency_key' => $acolhimento->idempotency_key,
                ]);
            }
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'crianca_id' => fn (): int => Crianca::query()->create([
                'nome_completo' => 'Pessoa acolhida fictícia '.fake()->unique()->numerify('####'),
            ])->id,
            'ingresso_em' => now('UTC')->subDay(),
            'motivo' => 'Motivo de ingresso inteiramente fictício.',
            'fundamento' => 'Fundamento de teste inteiramente fictício.',
            'origem_codigo' => 'conselho_tutelar',
            'origem_complemento' => null,
            'orgao_condutor_codigo' => 'conselho_tutelar',
            'orgao_condutor_complemento' => null,
            'pessoa_condutora' => 'Pessoa Condutora Fictícia',
            'created_by' => User::factory(),
            'recorded_at' => now('UTC'),
            'idempotency_key' => (string) Str::uuid(),
        ];
    }
}
