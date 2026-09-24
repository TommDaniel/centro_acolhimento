<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuditIndexRequest;
use App\Models\AuditEvent;
use App\Models\User;
use App\Support\InstitutionContext;
use Inertia\Inertia;
use Inertia\Response;

class AuditController extends Controller
{
    public function __construct(private InstitutionContext $context) {}

    public function index(AuditIndexRequest $request): Response
    {
        $filters = $request->validated();
        $unitId = $this->context->unit()->id;

        $events = AuditEvent::query()
            ->select([
                'id', 'actor_id', 'action', 'subject_type', 'subject_id', 'result',
                'changed_fields', 'correlation_id', 'occurred_at',
            ])
            ->where('unidade_id', $unitId)
            ->with('actor:id,name')
            ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
            ->when($filters['result'] ?? null, fn ($query, $result) => $query->where('result', $result))
            ->when($filters['actor_id'] ?? null, fn ($query, $actorId) => $query->where('actor_id', $actorId))
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Auditoria/Index', [
            'events' => $events,
            'filters' => $filters,
            'actions' => AuditEvent::query()->where('unidade_id', $unitId)->distinct()->orderBy('action')->pluck('action'),
            'actors' => User::query()->where('unidade_id', $unitId)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
