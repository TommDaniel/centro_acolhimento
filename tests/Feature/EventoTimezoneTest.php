<?php

namespace Tests\Feature;

use App\Models\Evento;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventoTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_interprets_timed_input_in_sao_paulo_and_reads_it_as_utc(): void
    {
        $user = $this->seededAdmin();

        $this->actingAsWithVerifiedMfa($user)->post(route('agenda.store'), [
            'titulo' => 'Consulta sintética com fuso',
            'tipo' => 'atendimento',
            'inicio' => '2026-09-01T12:30:00',
            'fim' => '2026-09-01T13:15:00',
            'dia_inteiro' => false,
        ])->assertRedirect(route('agenda.index'));

        $event = Evento::query()->where('titulo', 'Consulta sintética com fuso')->firstOrFail();

        $this->assertSame('2026-09-01 15:30:00', $event->inicio->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-01 16:15:00', $event->fim->utc()->format('Y-m-d H:i:s'));

        $this->actingAsWithVerifiedMfa($user)->get(route('agenda.index'))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('Agenda/Index')
                ->where('eventos', fn ($events): bool => collect($events)->contains(
                    fn (array $item): bool => $item['titulo'] === 'Consulta sintética com fuso'
                        && $item['inicio'] === '2026-09-01T15:30:00.000000Z'
                        && $item['fim'] === '2026-09-01T16:15:00.000000Z',
                )));
    }

    public function test_it_updates_a_timed_event_using_sao_paulo_wall_time(): void
    {
        $user = $this->seededAdmin();
        $event = Evento::query()->create([
            'titulo' => 'Evento sintético para edição',
            'tipo' => 'tarefa',
            'inicio' => CarbonImmutable::parse('2026-09-01 12:00:00 UTC'),
            'dia_inteiro' => false,
            'setor_id' => $user->setor_id,
            'created_by' => $user->id,
        ]);

        $this->actingAsWithVerifiedMfa($user)->put(route('agenda.update', $event), [
            'titulo' => 'Evento sintético editado',
            'tipo' => 'tarefa',
            'inicio' => '2026-09-02T08:45:00',
            'fim' => null,
            'dia_inteiro' => false,
        ])->assertRedirect(route('agenda.index'));

        $event->refresh();

        $this->assertSame('Evento sintético editado', $event->titulo);
        $this->assertSame('2026-09-02 11:45:00', $event->inicio->utc()->format('Y-m-d H:i:s'));
        $this->assertNull($event->fim);
    }

    public function test_all_day_event_preserves_the_sao_paulo_civil_date(): void
    {
        $user = $this->seededAdmin();

        $this->actingAsWithVerifiedMfa($user)->post(route('agenda.store'), [
            'titulo' => 'Audiência sintética de dia inteiro',
            'tipo' => 'audiencia',
            'inicio' => '2026-09-03',
            'fim' => null,
            'dia_inteiro' => true,
        ])->assertRedirect(route('agenda.index'));

        $event = Evento::query()->where('titulo', 'Audiência sintética de dia inteiro')->firstOrFail();

        $this->assertTrue($event->dia_inteiro);
        $this->assertSame('2026-09-03 03:00:00', $event->inicio->utc()->format('Y-m-d H:i:s'));
        $this->assertSame(
            '2026-09-03',
            $event->inicio->setTimezone('America/Sao_Paulo')->format('Y-m-d'),
        );
        $this->assertNull($event->fim);
    }

    public function test_dashboard_boundary_uses_the_sao_paulo_civil_day_converted_to_utc(): void
    {
        Date::setTestNow(CarbonImmutable::parse('2026-09-01 01:00:00 UTC'));

        try {
            $user = $this->seededAdmin();
            Evento::query()->create([
                'titulo' => 'Evento sintético antes do dia local',
                'tipo' => 'tarefa',
                'inicio' => CarbonImmutable::parse('2026-08-31 02:59:59 UTC'),
                'setor_id' => $user->setor_id,
                'created_by' => $user->id,
            ]);
            Evento::query()->create([
                'titulo' => 'Evento sintético no início do dia local',
                'tipo' => 'tarefa',
                'inicio' => CarbonImmutable::parse('2026-08-31 03:00:00 UTC'),
                'setor_id' => $user->setor_id,
                'created_by' => $user->id,
            ]);

            $this->actingAsWithVerifiedMfa($user)->get(route('dashboard'))
                ->assertInertia(fn (Assert $page): Assert => $page
                    ->component('Dashboard')
                    ->where('proximosEventos', function ($events): bool {
                        $titles = collect($events)->pluck('titulo');

                        return ! $titles->contains('Evento sintético antes do dia local')
                            && $titles->contains('Evento sintético no início do dia local');
                    }));
        } finally {
            Date::setTestNow();
        }
    }

    private function seededAdmin(): User
    {
        $this->seed();

        return User::query()->where('email', 'admin@poc.local')->firstOrFail();
    }
}
