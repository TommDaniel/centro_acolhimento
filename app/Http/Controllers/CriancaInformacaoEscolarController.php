<?php

namespace App\Http\Controllers;

use App\Actions\RecordCriancaInformacaoEscolar;
use App\Http\Requests\StoreCriancaInformacaoEscolarRequest;
use App\Models\Crianca;
use App\Services\AuditRecorder;
use App\Services\CriancaInformacaoEscolarHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CriancaInformacaoEscolarController extends Controller
{
    public function __construct(
        private RecordCriancaInformacaoEscolar $recordSchoolInformation,
        private CriancaInformacaoEscolarHistory $history,
        private AuditRecorder $audit,
    ) {}

    public function index(Request $request, Crianca $crianca): JsonResponse
    {
        $this->authorize('view', $crianca);

        $validated = $request->validate([
            'before_id' => ['required', 'integer', 'min:1'],
        ]);
        $page = $this->history->olderPage($crianca, (int) $validated['before_id']);

        $this->audit->record(
            'crianca.informacao_escolar.history.viewed',
            'success',
            $request->user(),
            $crianca,
        );

        return response()->json($page);
    }

    public function store(
        StoreCriancaInformacaoEscolarRequest $request,
        Crianca $crianca,
    ): RedirectResponse {
        $this->recordSchoolInformation->handle($crianca, $request->validated(), $request->user());

        return redirect()->route('criancas.show', $crianca)
            ->with('sucesso', 'Informação escolar registrada sem alterar o histórico anterior.');
    }
}
