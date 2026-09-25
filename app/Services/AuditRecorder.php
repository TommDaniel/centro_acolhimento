<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Crianca;
use App\Models\User;
use App\Support\InstitutionContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;

class AuditRecorder
{
    public function __construct(private InstitutionContext $context) {}

    /** @var list<string> */
    private const FORBIDDEN_FIELD_NAMES = [
        'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes',
        'secret', 'code', 'qr_code', 'access_generation', 'last_accepted_time_step',
    ];

    /**
     * @param  list<string>  $changedFields
     */
    public function record(
        string $action,
        string $result,
        ?User $actor = null,
        ?Model $subject = null,
        array $changedFields = [],
    ): AuditEvent {
        $unitId = $this->context->unit()->id;

        if ($actor !== null && (int) $actor->unidade_id !== $unitId) {
            throw new \LogicException('O ator da auditoria não pertence ao contexto institucional ativo.');
        }

        if ($subject instanceof Crianca
            && (int) $subject->organizacao_id !== $this->context->organization()->id) {
            throw new \LogicException('O alvo da auditoria não pertence ao contexto institucional ativo.');
        }

        if ($subject !== null
            && $subject->getAttribute('unidade_id') !== null
            && (int) $subject->getAttribute('unidade_id') !== $unitId) {
            throw new \LogicException('O alvo da auditoria não pertence ao contexto institucional ativo.');
        }

        $correlationId = Context::get('correlation_id');

        if (! is_string($correlationId) || ! Str::isUuid($correlationId)) {
            $correlationId = (string) Str::uuid();
        }

        $fields = collect($changedFields)
            ->filter(fn (mixed $field): bool => is_string($field))
            ->reject(fn (string $field): bool => in_array($field, self::FORBIDDEN_FIELD_NAMES, true))
            ->unique()
            ->sort()
            ->values()
            ->all();

        return AuditEvent::query()->create([
            'actor_id' => $actor?->getKey(),
            'unidade_id' => $unitId,
            'action' => Str::limit($action, 100, ''),
            'subject_type' => $subject === null ? null : class_basename($subject),
            'subject_id' => $subject?->getKey() === null ? null : (string) $subject->getKey(),
            'result' => $result,
            'changed_fields' => $fields,
            'correlation_id' => $correlationId,
            'occurred_at' => now('UTC'),
        ]);
    }
}
