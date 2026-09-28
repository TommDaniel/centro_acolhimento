<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Crianca;
use App\Models\CriancaDocumento;
use App\Models\Evento;
use App\Models\Familiar;
use App\Models\Pertence;
use App\Models\Pia;
use App\Models\PiaAnexo;
use App\Models\Report;
use App\Models\Setor;
use App\Models\User;
use App\Models\VisitaTecnica;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthorizationPolicyMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * @param  class-string<Model>  $resource
     * @param  list<string>  $classAbilities
     * @param  list<string>  $allowedInstanceAbilities
     * @param  list<string>  $deniedInstanceAbilities
     */
    #[DataProvider('assistentialResourceProvider')]
    public function test_assistential_policy_matrix(
        string $resource,
        array $classAbilities,
        array $allowedInstanceAbilities,
        array $deniedInstanceAbilities,
    ): void {
        $technical = User::factory()->create();
        $administrator = User::factory()->administrator()->create();
        $inactive = User::factory()->inactive()->create();
        $subject = new $resource;

        foreach ([$technical, $administrator] as $activeUser) {
            foreach ($classAbilities as $ability) {
                $this->assertTrue(Gate::forUser($activeUser)->allows($ability, $resource), "$resource::$ability");
            }

            foreach ($allowedInstanceAbilities as $ability) {
                $this->assertTrue(Gate::forUser($activeUser)->allows($ability, $subject), "$resource::$ability");
            }

            foreach ($deniedInstanceAbilities as $ability) {
                $this->assertFalse(Gate::forUser($activeUser)->allows($ability, $subject), "$resource::$ability");
            }
        }

        foreach ($classAbilities as $ability) {
            $this->assertFalse(Gate::forUser($inactive)->allows($ability, $resource), "inactive $resource::$ability");
            $this->assertFalse(Gate::forUser(null)->allows($ability, $resource), "visitor $resource::$ability");
        }

        foreach (array_merge($allowedInstanceAbilities, $deniedInstanceAbilities) as $ability) {
            $this->assertFalse(Gate::forUser($inactive)->allows($ability, $subject), "inactive $resource::$ability");
            $this->assertFalse(Gate::forUser(null)->allows($ability, $subject), "visitor $resource::$ability");
        }
    }

    /**
     * @return array<string, array{class-string<Model>, list<string>, list<string>, list<string>}>
     */
    public static function assistentialResourceProvider(): array
    {
        return [
            'crianca' => [Crianca::class, ['viewAny', 'create'], ['view', 'update'], ['delete']],
            'pia' => [Pia::class, ['viewAny', 'create'], ['view', 'update', 'download'], ['delete']],
            'visita' => [VisitaTecnica::class, ['viewAny', 'create'], ['view', 'update', 'download'], ['delete']],
            'parecer' => [Report::class, ['viewAny', 'create'], ['view', 'update', 'download'], ['delete']],
            'pertence' => [Pertence::class, ['viewAny', 'create'], ['view', 'update', 'download'], ['delete']],
            'agenda' => [Evento::class, ['viewAny', 'create'], ['update'], ['delete']],
            'familiar' => [Familiar::class, ['create'], [], ['delete']],
            'documento da crianca' => [CriancaDocumento::class, ['create'], [], ['delete']],
            'anexo do pia' => [PiaAnexo::class, [], [], ['delete']],
        ];
    }

    public function test_portrait_policy_requires_an_active_user_in_the_child_organization(): void
    {
        $technical = User::factory()->create();
        $administrator = User::factory()->administrator()->create();
        $inactive = User::factory()->inactive()->create();
        $child = Crianca::query()->create(['nome_completo' => 'Acolhido Matriz de Retrato Fictício']);
        $foreignChild = $child->replicate();
        $foreignChild->organizacao_id = $child->organizacao_id + 999;

        $this->assertTrue(Gate::forUser($technical)->allows('viewPortrait', $child));
        $this->assertTrue(Gate::forUser($administrator)->allows('viewPortrait', $child));
        $this->assertFalse(Gate::forUser($inactive)->allows('viewPortrait', $child));
        $this->assertFalse(Gate::forUser(null)->allows('viewPortrait', $child));
        $this->assertFalse(Gate::forUser($technical)->allows('viewPortrait', $foreignChild));
        $this->assertFalse(Gate::forUser($administrator)->allows('viewPortrait', $foreignChild));
    }

    public function test_sector_account_and_audit_policy_matrix(): void
    {
        $technical = User::factory()->create();
        $administrator = User::factory()->administrator()->create();
        $inactive = User::factory()->inactive()->create();
        $otherAccount = User::factory()->create();
        $sector = new Setor;
        $auditEvent = new AuditEvent;

        $this->assertTrue($technical->can('viewAny', Setor::class));
        $this->assertTrue($technical->can('view', $sector));
        $this->assertFalse($technical->can('create', Setor::class));
        $this->assertFalse($technical->can('update', $sector));
        $this->assertFalse($technical->can('delete', $sector));

        $this->assertTrue($administrator->can('viewAny', Setor::class));
        $this->assertTrue($administrator->can('view', $sector));
        $this->assertTrue($administrator->can('create', Setor::class));
        $this->assertTrue($administrator->can('update', $sector));
        $this->assertFalse($administrator->can('delete', $sector));

        foreach (['viewAny', 'create'] as $ability) {
            $this->assertFalse(Gate::forUser($inactive)->allows($ability, Setor::class));
            $this->assertFalse(Gate::forUser(null)->allows($ability, Setor::class));
        }

        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertFalse(Gate::forUser($inactive)->allows($ability, $sector));
            $this->assertFalse(Gate::forUser(null)->allows($ability, $sector));
        }

        $this->assertFalse($technical->can('viewAny', User::class));
        $this->assertFalse($technical->can('view', $otherAccount));
        $this->assertFalse($technical->can('create', User::class));
        $this->assertFalse($technical->can('update', $otherAccount));
        $this->assertFalse($technical->can('delete', $otherAccount));
        $this->assertTrue($technical->can('view', $technical));

        $this->assertTrue($administrator->can('viewAny', User::class));
        $this->assertTrue($administrator->can('view', $otherAccount));
        $this->assertTrue($administrator->can('create', User::class));
        $this->assertTrue($administrator->can('update', $otherAccount));
        $this->assertFalse($administrator->can('delete', $otherAccount));

        foreach (['viewAny', 'create'] as $ability) {
            $this->assertFalse(Gate::forUser($inactive)->allows($ability, User::class));
            $this->assertFalse(Gate::forUser(null)->allows($ability, User::class));
        }

        foreach (['view', 'update', 'delete'] as $ability) {
            $this->assertFalse(Gate::forUser($inactive)->allows($ability, $otherAccount));
            $this->assertFalse(Gate::forUser(null)->allows($ability, $otherAccount));
        }

        $this->assertFalse($technical->can('viewAny', AuditEvent::class));
        $this->assertFalse($technical->can('view', $auditEvent));
        $this->assertTrue($administrator->can('viewAny', AuditEvent::class));
        $this->assertTrue($administrator->can('view', $auditEvent));

        foreach ([$technical, $administrator, $inactive] as $user) {
            $this->assertFalse($user->can('create', AuditEvent::class));
            $this->assertFalse($user->can('update', $auditEvent));
            $this->assertFalse($user->can('delete', $auditEvent));
        }
    }

    public function test_technical_user_cannot_swap_account_identifier_to_update_another_account(): void
    {
        $technical = User::factory()->create();
        $target = User::factory()->administrator()->create();

        $this->actingAsWithVerifiedMfa($technical)
            ->put(route('equipe.update', $target), [
                'name' => 'Nome Alterado Indevidamente Fictício',
                'email' => $target->email,
                'role' => 'equipe_tecnica',
                'status' => 'ativa',
            ])
            ->assertForbidden();

        $this->assertNotSame('Nome Alterado Indevidamente Fictício', $target->fresh()->name);
    }
}
