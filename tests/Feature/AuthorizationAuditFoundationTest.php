<?php

namespace Tests\Feature;

use App\Actions\CreateUserAccount;
use App\Actions\UpdateUserAccount;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Middleware\AssignCorrelationId;
use App\Http\Middleware\EnsureUserHasApprovedAccess;
use App\Http\Middleware\RejectClientInstitutionContext;
use App\Http\Middleware\RejectRememberedSession;
use App\Models\AuditEvent;
use App\Models\Crianca;
use App\Models\Setor;
use App\Models\User;
use App\Services\AuditRecorder;
use App\Support\InstitutionContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class AuthorizationAuditFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_technical_and_administrator_accounts_share_assistential_access_across_sectors(): void
    {
        $firstSector = Setor::query()->create(['nome' => 'Setor Técnico Fictício']);
        $secondSector = Setor::query()->create(['nome' => 'Setor Administrativo Fictício']);
        $technical = User::factory()->create(['setor_id' => $firstSector->id]);
        $administrator = User::factory()->administrator()->create(['setor_id' => $secondSector->id]);
        $child = Crianca::query()->create(['nome_completo' => 'Acolhido Fictício Inicial']);

        $this->actingAs($technical)
            ->put(route('criancas.update', $child), ['nome_completo' => 'Acolhido Fictício Técnico'])
            ->assertRedirect(route('criancas.show', $child));

        $this->actingAs($administrator)
            ->put(route('criancas.update', $child), ['nome_completo' => 'Acolhido Fictício Administração'])
            ->assertRedirect(route('criancas.show', $child));

        $this->assertSame('Acolhido Fictício Administração', $child->fresh()->nome_completo);
    }

    public function test_visitor_inactive_account_and_unapproved_database_role_are_denied(): void
    {
        $this->get(route('criancas.index'))->assertRedirect(route('login'));

        $inactive = User::factory()->inactive()->create();
        $this->actingAs($inactive)->get(route('dashboard'))->assertForbidden();
        $this->assertGuest();

        $this->expectException(QueryException::class);
        User::query()->whereKey(User::factory()->create()->id)->update(['role' => 'papel_nao_aprovado']);
    }

    public function test_revoked_account_is_denied_before_context_rejection_and_route_binding_for_existing_and_missing_targets(): void
    {
        $child = Crianca::query()->create(['nome_completo' => 'Alvo Protegido Fictício']);
        $queries = [];

        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        foreach ([
            User::factory()->inactive()->create(),
            User::factory()->create(['status' => UserStatus::PendenteMfa]),
        ] as $revokedUser) {
            $existingResponse = $this->actingAs($revokedUser)
                ->get(route('criancas.show', $child).'?organization_id=999999');
            $existingResponse->assertForbidden();
            $this->assertTrue((bool) preg_match(
                '/^[0-9a-f-]{36}$/',
                (string) $existingResponse->headers->get('X-Correlation-ID'),
            ));

            $missingResponse = $this->actingAs($revokedUser)
                ->get(route('criancas.show', 999999999).'?organization_id=999999');
            $missingResponse->assertForbidden();
            $this->assertTrue((bool) preg_match(
                '/^[0-9a-f-]{36}$/',
                (string) $missingResponse->headers->get('X-Correlation-ID'),
            ));
        }

        $childQueries = array_filter(
            $queries,
            fn (string $query): bool => str_contains(strtolower($query), 'from "criancas"'),
        );

        $this->assertSame([], array_values($childQueries));
        $this->assertSame(
            ['access.denied.child.view'],
            AuditEvent::query()->distinct()->pluck('action')->all(),
        );
    }

    public function test_security_middleware_priority_is_authentication_correlation_approval_context_then_binding(): void
    {
        $priority = app(HttpKernel::class)->getMiddlewarePriority();
        $orderedMiddleware = [
            AuthenticatesRequests::class,
            AssignCorrelationId::class,
            RejectRememberedSession::class,
            AuthenticateSession::class,
            EnsureUserHasApprovedAccess::class,
            RejectClientInstitutionContext::class,
            SubstituteBindings::class,
        ];
        $positions = array_map(
            fn (string $middleware): int|false => array_search($middleware, $priority, true),
            $orderedMiddleware,
        );

        foreach ($positions as $position) {
            $this->assertIsInt($position);
        }

        $this->assertSame($positions, collect($positions)->sort()->values()->all());
    }

    public function test_only_administrator_can_manage_accounts_or_open_operational_audit(): void
    {
        $technical = User::factory()->create();
        $administrator = User::factory()->administrator()->create();
        $this->withoutVite();

        $this->actingAs($technical)->get(route('equipe.index'))->assertForbidden();
        $this->actingAs($technical)->get(route('equipe.create'))->assertForbidden();
        $this->actingAs($technical)->get(route('auditoria.index'))->assertForbidden();

        $this->actingAs($administrator)->get(route('equipe.create'))->assertOk();
        $this->actingAs($administrator)->get(route('auditoria.index'))->assertOk();
    }

    public function test_administrator_cannot_change_own_privileged_role_or_access_status(): void
    {
        $administrator = User::factory()->administrator()->create();

        foreach ([
            ['role' => UserRole::EquipeTecnica->value, 'status' => UserStatus::Ativa->value],
            ['role' => UserRole::Administradora->value, 'status' => UserStatus::Inativa->value],
        ] as $accessChange) {
            $this->actingAs($administrator)
                ->put(route('equipe.update', $administrator), $this->accountPayload($administrator, $accessChange))
                ->assertSessionHasErrors(['role', 'status']);

            $administrator->refresh();
            $this->assertSame(UserRole::Administradora, $administrator->role);
            $this->assertSame(UserStatus::Ativa, $administrator->status);
        }

        $this->assertDatabaseMissing('audit_events', ['action' => 'user.updated']);
    }

    public function test_transactional_action_preserves_the_only_active_administrator(): void
    {
        $administrator = User::factory()->administrator()->create();

        try {
            app(UpdateUserAccount::class)->handle(
                $administrator,
                $this->accountPayload($administrator, [
                    'role' => UserRole::EquipeTecnica->value,
                    'status' => UserStatus::Ativa->value,
                ]),
                $administrator,
            );
            $this->fail('A última administradora ativa não poderia ser rebaixada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('role', $exception->errors());
        }

        $administrator->refresh();
        $this->assertSame(UserRole::Administradora, $administrator->role);
        $this->assertSame(UserStatus::Ativa, $administrator->status);
        $this->assertDatabaseMissing('audit_events', ['action' => 'user.updated']);
    }

    public function test_administrator_can_demote_another_administrator_when_one_active_administrator_remains(): void
    {
        $actor = User::factory()->administrator()->create();
        $subject = User::factory()->administrator()->create();

        $this->actingAs($actor)
            ->put(route('equipe.update', $subject), $this->accountPayload($subject, [
                'role' => UserRole::EquipeTecnica->value,
                'status' => UserStatus::Ativa->value,
            ]))
            ->assertRedirect(route('equipe.index'));

        $this->assertSame(UserRole::EquipeTecnica, $subject->fresh()->role);
        $this->assertSame(1, User::query()
            ->where('role', UserRole::Administradora->value)
            ->where('status', UserStatus::Ativa->value)
            ->count());
        $this->assertDatabaseHas('audit_events', [
            'actor_id' => $actor->id,
            'action' => 'user.updated',
            'subject_id' => (string) $subject->id,
        ]);
    }

    public function test_transactional_action_revalidates_actor_after_policy_check(): void
    {
        $actor = User::factory()->administrator()->create();
        $subject = User::factory()->create();
        User::query()->whereKey($actor)->update(['role' => UserRole::EquipeTecnica->value]);

        try {
            app(UpdateUserAccount::class)->handle(
                $subject,
                $this->accountPayload($subject, [
                    'role' => UserRole::EquipeTecnica->value,
                    'status' => UserStatus::Inativa->value,
                ]),
                $actor,
            );
            $this->fail('Um ator rebaixado após a Policy não poderia concluir a mutação.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $this->assertSame(UserStatus::Ativa, $subject->fresh()->status);
        $this->assertDatabaseMissing('audit_events', ['action' => 'user.updated']);
    }

    public function test_account_creation_revalidates_actor_inside_the_transaction(): void
    {
        $actor = User::factory()->administrator()->create();
        User::query()->whereKey($actor)->update(['status' => UserStatus::Inativa->value]);

        try {
            app(CreateUserAccount::class)->handle([
                'name' => 'Nova Técnica Fictícia',
                'email' => 'nova-tecnica-ficticia@poc.local',
                'password' => 'senha-ficticia-segura',
                'role' => UserRole::EquipeTecnica->value,
                'status' => UserStatus::Ativa->value,
            ], $actor);
            $this->fail('Uma administradora revogada não poderia criar uma conta.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseMissing('users', ['email' => 'nova-tecnica-ficticia@poc.local']);
        $this->assertDatabaseMissing('audit_events', ['action' => 'user.created']);
    }

    public function test_spoofed_institution_context_is_audited_without_client_values(): void
    {
        $technical = User::factory()->create();

        $response = $this->actingAs($technical)
            ->withHeader('X-Correlation-ID', 'correlation-forjada-pelo-cliente')
            ->get(route('criancas.index').'?organization_id=987654321');

        $response->assertUnprocessable()->assertHeader('X-Correlation-ID');

        $event = AuditEvent::query()
            ->where('action', 'access.denied.institution_context_override')
            ->sole();

        $this->assertSame($technical->id, $event->actor_id);
        $this->assertSame([], $event->changed_fields);
        $this->assertNotNull($event->unidade_id);
        $this->assertNotSame('correlation-forjada-pelo-cliente', $event->correlation_id);
        $this->assertStringNotContainsString('987654321', $event->toJson());
        $this->assertStringNotContainsString('/criancas', $event->toJson());
    }

    public function test_administrator_password_reset_has_a_stable_minimized_audit_event(): void
    {
        $administrator = User::factory()->administrator()->create();
        $technical = User::factory()->create();
        $oldPasswordHash = $technical->getAuthPassword();
        $oldRememberToken = $technical->getRememberToken();

        $payload = $this->accountPayload($technical, [
            'role' => UserRole::EquipeTecnica->value,
            'status' => UserStatus::Ativa->value,
        ]);
        $payload['password'] = 'nova-senha-ficticia-segura';
        $payload['password_confirmation'] = 'nova-senha-ficticia-segura';

        $this->actingAs($administrator)
            ->put(route('equipe.update', $technical), $payload)
            ->assertRedirect(route('equipe.index'));

        $event = AuditEvent::query()->where('action', 'user.password_admin_reset')->sole();
        $this->assertSame($administrator->id, $event->actor_id);
        $this->assertSame((string) $technical->id, $event->subject_id);
        $this->assertSame([], $event->changed_fields);
        $this->assertStringNotContainsString('nova-senha-ficticia-segura', $event->toJson());
        $this->assertDatabaseMissing('audit_events', ['action' => 'user.updated']);
        $this->assertNotSame($oldRememberToken, $technical->refresh()->getRememberToken());

        $this->app['auth']->forgetGuards();
        $guard = Auth::guard('web');
        $this->withSession([
            $guard->getName() => $technical->id,
            'password_hash_web' => $oldPasswordHash,
        ])->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_administrator_cannot_reset_own_password_through_account_management(): void
    {
        $administrator = User::factory()->administrator()->create();
        $oldPasswordHash = $administrator->getAuthPassword();
        $payload = $this->accountPayload($administrator, [
            'role' => UserRole::Administradora->value,
            'status' => UserStatus::Ativa->value,
        ]);
        $payload['password'] = 'senha-auto-reset-ficticia';
        $payload['password_confirmation'] = 'senha-auto-reset-ficticia';

        $this->actingAs($administrator)
            ->put(route('equipe.update', $administrator), $payload)
            ->assertSessionHasErrors('password');

        $this->assertSame($oldPasswordHash, $administrator->refresh()->getAuthPassword());
        $this->assertDatabaseMissing('audit_events', ['action' => 'user.password_admin_reset']);
    }

    public function test_assistential_and_account_records_cannot_be_physically_deleted(): void
    {
        $administrator = User::factory()->administrator()->create();
        $technical = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Acolhido Protegido Fictício']);

        $this->actingAs($administrator)->delete(route('criancas.destroy', $child))->assertForbidden();
        $this->actingAs($administrator)->delete(route('equipe.destroy', $technical))->assertForbidden();

        $this->assertModelExists($child);
        $this->assertModelExists($technical);
    }

    public function test_child_mutation_and_minimized_audit_event_commit_together(): void
    {
        $technical = User::factory()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Nome Anterior Fictício']);

        $response = $this->actingAs($technical)
            ->withHeader('X-Correlation-ID', 'correlation-forjado-pelo-cliente')
            ->put(route('criancas.update', $child), [
                'nome_completo' => 'Nome Atual Fictício',
            ]);

        $response->assertRedirect(route('criancas.show', $child));
        $event = AuditEvent::query()->where('action', 'crianca.updated')->sole();

        $this->assertSame($technical->id, $event->actor_id);
        $this->assertSame(['nome_completo', 'updated_by'], $event->changed_fields);
        $this->assertStringNotContainsString('Nome Anterior Fictício', $event->toJson());
        $this->assertStringNotContainsString('Nome Atual Fictício', $event->toJson());
        $this->assertTrue((bool) preg_match('/^[0-9a-f-]{36}$/', $event->correlation_id));
        $this->assertNotNull($response->headers->get('X-Correlation-ID'));
        $this->assertNotSame('correlation-forjado-pelo-cliente', $event->correlation_id);
    }

    public function test_child_write_rolls_back_when_audit_write_fails(): void
    {
        $technical = User::factory()->create();
        $audit = Mockery::mock(AuditRecorder::class);
        $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('Falha sintética da auditoria.'));
        $this->app->instance(AuditRecorder::class, $audit);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($technical)->post(route('criancas.store'), [
                'nome_completo' => 'Cadastro que Deve Reverter Fictício',
            ]);
            $this->fail('A falha sintética deveria interromper a requisição.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Falha sintética da auditoria.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('criancas', [
            'nome_completo' => 'Cadastro que Deve Reverter Fictício',
        ]);
    }

    public function test_postgresql_rejects_update_of_append_only_audit_event(): void
    {
        $user = User::factory()->create();
        $event = app(AuditRecorder::class)->record('test.synthetic', 'success', $user, $user);

        $this->expectException(QueryException::class);
        AuditEvent::query()->whereKey($event)->update(['result' => 'denied']);
    }

    public function test_postgresql_rejects_delete_of_append_only_audit_event(): void
    {
        $user = User::factory()->create();
        $event = app(AuditRecorder::class)->record('test.synthetic', 'success', $user, $user);

        $this->expectException(QueryException::class);
        AuditEvent::query()->whereKey($event)->delete();
    }

    public function test_postgresql_rejects_truncate_and_preserves_append_only_audit_event(): void
    {
        $user = User::factory()->create();
        $event = app(AuditRecorder::class)->record('test.synthetic', 'success', $user, $user);
        DB::statement('SAVEPOINT audit_truncate_attempt');

        try {
            DB::statement('TRUNCATE TABLE audit_events');
            $this->fail('O PostgreSQL deveria impedir TRUNCATE da auditoria.');
        } catch (QueryException) {
            DB::statement('ROLLBACK TO SAVEPOINT audit_truncate_attempt');
        }

        $this->assertModelExists($event);
    }

    public function test_login_success_and_failure_are_audited_without_attempted_email(): void
    {
        $user = User::factory()->create(['email' => 'usuario-ficticio@poc.local']);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $failed = AuditEvent::query()->where('action', 'auth.login_failed')->sole();
        $succeeded = AuditEvent::query()->where('action', 'auth.login_succeeded')->sole();

        $this->assertNull($failed->actor_id);
        $this->assertSame(app(InstitutionContext::class)->unit()->id, $failed->unidade_id);
        $this->assertStringNotContainsString($user->email, $failed->toJson());
        $this->assertSame($user->id, $succeeded->actor_id);
    }

    public function test_technical_user_cannot_mutate_audit_records_through_policy(): void
    {
        $technical = User::factory()->create();
        $event = app(AuditRecorder::class)->record('test.synthetic', 'success', $technical, $technical);

        $this->assertFalse($technical->can('view', $event));
        $this->assertFalse($technical->can('update', $event));
        $this->assertFalse($technical->can('delete', $event));
        $this->assertTrue(User::factory()->administrator()->create()->can('view', $event));
    }

    /**
     * @param  array{role: string, status: string}  $accessChange
     * @return array<string, mixed>
     */
    private function accountPayload(User $user, array $accessChange): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'setor_id' => $user->setor_id,
            'role' => $accessChange['role'],
            'status' => $accessChange['status'],
            'cargo' => $user->cargo,
            'telefone' => $user->telefone,
        ];
    }
}
