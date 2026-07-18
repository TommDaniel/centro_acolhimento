<?php

namespace App\Http\Controllers;

use App\Models\Crianca;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q'));

        $criancas = null;

        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';

            $criancas = Crianca::query()
                ->where(function ($where) use ($like) {
                    $where->where('nome_completo', 'like', $like)
                        ->orWhere('nome_social', 'like', $like)
                        ->orWhere('processo_numero', 'like', $like)
                        ->orWhere('rg', 'like', $like)
                        ->orWhere('cpf', 'like', $like)
                        ->orWhere('nome_mae', 'like', $like)
                        ->orWhere('nome_pai', 'like', $like)
                        ->orWhere('responsavel_legal', 'like', $like);
                })
                ->withCount(['pias', 'reports', 'visitasTecnicas', 'pertences'])
                ->orderBy('nome_completo')
                ->paginate(15)
                ->withQueryString();
        }

        return Inertia::render('Busca', compact('q', 'criancas'));
    }
}
