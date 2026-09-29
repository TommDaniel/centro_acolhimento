<?php

namespace App\Http\Controllers;

use App\Actions\OpenAcolhimento;
use App\Actions\RecordAcolhimentoMovimentacao;
use App\Http\Requests\StoreAcolhimentoMovimentacaoRequest;
use App\Http\Requests\StoreAcolhimentoRequest;
use App\Models\Acolhimento;
use App\Models\Crianca;
use Illuminate\Http\RedirectResponse;

class AcolhimentoController extends Controller
{
    public function __construct(
        private OpenAcolhimento $openAcolhimento,
        private RecordAcolhimentoMovimentacao $recordMovement,
    ) {}

    public function store(StoreAcolhimentoRequest $request, Crianca $crianca): RedirectResponse
    {
        $this->authorize('create', Acolhimento::class);
        $this->openAcolhimento->handle($crianca, $request->validatedForPersistence(), $request->user());

        return redirect()->route('criancas.show', $crianca)
            ->with('sucesso', 'Ingresso registrado com sucesso.');
    }

    public function storeMovement(
        StoreAcolhimentoMovimentacaoRequest $request,
        Crianca $crianca,
        Acolhimento $acolhimento,
    ): RedirectResponse {
        $this->authorize('recordMovement', $acolhimento);
        $this->recordMovement->handle(
            $crianca,
            $acolhimento,
            $request->validatedForPersistence(),
            $request->user(),
        );

        return redirect()->route('criancas.show', $crianca)
            ->with('sucesso', 'Movimentação registrada com sucesso.');
    }
}
