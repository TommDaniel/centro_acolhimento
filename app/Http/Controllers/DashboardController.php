<?php

namespace App\Http\Controllers;

use App\Models\Crianca;
use App\Models\Evento;
use App\Models\Pertence;
use App\Models\Pia;
use App\Models\Report;
use App\Models\VisitaTecnica;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $totais = [
            'criancas' => Crianca::where('status', 'acolhida')->count(),
            'pias' => Pia::count(),
            'reports' => Report::count(),
            'visitas' => VisitaTecnica::count(),
            'pertences' => Pertence::count(),
        ];

        $recentes = collect()
            ->merge(Pia::with('crianca', 'criador')->latest()->take(5)->get()->map(fn ($d) => [
                'tipo' => 'PIA', 'cor' => 'indigo',
                'doc' => $d, 'rota' => route('pias.show', $d),
            ]))
            ->merge(Report::with('crianca', 'criador')->latest()->take(5)->get()->map(fn ($d) => [
                'tipo' => 'Ocorrência', 'cor' => 'rose',
                'doc' => $d, 'rota' => route('reports.show', $d),
            ]))
            ->merge(VisitaTecnica::with('crianca', 'criador')->latest()->take(5)->get()->map(fn ($d) => [
                'tipo' => 'Visita técnica', 'cor' => 'emerald',
                'doc' => $d, 'rota' => route('visitas-tecnicas.show', $d),
            ]))
            ->merge(Pertence::with('crianca', 'criador')->latest()->take(5)->get()->map(fn ($d) => [
                'tipo' => 'Pertences', 'cor' => 'amber',
                'doc' => $d, 'rota' => route('pertences.show', $d),
            ]))
            ->sortByDesc(fn ($item) => $item['doc']->created_at)
            ->take(12)
            ->values();

        $proximosEventos = Evento::with('crianca:id,nome_completo')
            ->where('concluido', false)
            ->where('inicio', '>=', now()->startOfDay())
            ->orderBy('inicio')
            ->take(8)
            ->get();

        return Inertia::render('Dashboard', compact('totais', 'recentes', 'proximosEventos'));
    }
}
