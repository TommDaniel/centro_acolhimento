<?php

namespace App\Http\Controllers;

use App\Actions\CreateUserAccount;
use App\Actions\UpdateUserAccount;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class EquipeController extends Controller
{
    public function __construct(
        private CreateUserAccount $createUserAccount,
        private UpdateUserAccount $updateUserAccount,
    ) {}

    public function index()
    {
        $this->authorize('viewAny', User::class);

        $grupos = User::with('setor')->orderBy('name')->get()
            ->groupBy(fn ($user) => $user->setor?->nome ?? 'Sem setor');

        return Inertia::render('Equipe/Index', ['grupos' => $grupos]);
    }

    public function create()
    {
        $this->authorize('create', User::class);

        $setores = Setor::orderBy('nome')->get(['id', 'nome']);

        return Inertia::render('Equipe/Form', ['usuario' => null, 'setores' => $setores]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $dados = $this->validar($request);

        $this->createUserAccount->handle($dados, $request->user());

        return redirect()->route('equipe.index')
            ->with('sucesso', 'Usuário criado com sucesso.');
    }

    public function edit(User $equipe)
    {
        $this->authorize('update', $equipe);

        $setores = Setor::orderBy('nome')->get(['id', 'nome']);

        return Inertia::render('Equipe/Form', ['usuario' => $equipe, 'setores' => $setores]);
    }

    public function update(Request $request, User $equipe)
    {
        $this->authorize('update', $equipe);

        $dados = $this->validar($request, $equipe);

        if (empty($dados['password'])) {
            unset($dados['password']);
        }

        $this->updateUserAccount->handle($equipe, $dados, $request->user());

        return redirect()->route('equipe.index')
            ->with('sucesso', 'Usuário atualizado com sucesso.');
    }

    public function destroy(Request $request, User $equipe)
    {
        $this->authorize('delete', $equipe);

        abort(405, 'Contas devem ser inativadas e não podem ser excluídas fisicamente.');
    }

    private function validar(Request $request, ?User $usuario = null): array
    {
        $senha = $usuario
            ? ['nullable', 'confirmed', Password::defaults()]
            : ['required', 'confirmed', Password::defaults()];

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($usuario)],
            'password' => $senha,
            'setor_id' => ['nullable', 'exists:setores,id'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'cargo' => ['nullable', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:50'],
        ]);
    }
}
