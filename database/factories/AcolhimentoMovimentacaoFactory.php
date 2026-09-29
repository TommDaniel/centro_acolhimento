<?php

namespace Database\Factories;

use App\Enums\AcolhimentoMovimentacaoTipo;
use App\Models\AcolhimentoMovimentacao;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AcolhimentoMovimentacao>
 */
class AcolhimentoMovimentacaoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo' => AcolhimentoMovimentacaoTipo::Ingresso,
            'situacao_resultante' => AcolhimentoMovimentacaoTipo::Ingresso->situacaoResultante(),
            'efetiva_em' => now('UTC')->subDay(),
            'motivo' => 'Movimentação inteiramente fictícia.',
            'fundamento' => null,
            'local_destino' => null,
            'observacao' => null,
            'created_by' => User::factory(),
            'recorded_at' => now('UTC'),
            'idempotency_key' => (string) Str::uuid(),
        ];
    }
}
