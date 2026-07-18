<?php

namespace App\Http\Controllers;

use App\Models\Setor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class EquipeController extends Controller
{
    public function index()
    {
        $grupos = User::with('setor')->orderBy('name')->get()
            ->groupBy(fn ($user) => $user->setor?->nome ?? 'Sem setor');

        return Inertia::render('Equipe/Index', ['grupos' => $grupos]);
    }

    public function create()
    {
        $setores = Setor::orderBy('nome')->get(['id', 'nome']);

        return Inertia::render('Equipe/Form', ['usuario' => null, 'setores' => $setores]);
    }

    public function store(Request $request)
    {
        $dados = $this->validar($request);

        User::create($dados);

        return redirect()->route('equipe.index')
            ->with('sucesso', 'Usuário criado com sucesso.');
    }

    public function edit(User $equipe)
    {
        $setores = Setor::orderBy('nome')->get(['id', 'nome']);

        return Inertia::render('Equipe/Form', ['usuario' => $equipe, 'setores' => $setores]);
    }

    public function update(Request $request, User $equipe)
    {
        $dados = $this->validar($request, $equipe);

        if (empty($dados['password'])) {
            unset($dados['password']);
        }

        $equipe->update($dados);

        return redirect()->route('equipe.index')
            ->with('sucesso', 'Usuário atualizado com sucesso.');
    }

    public function destroy(Request $request, User $equipe)
    {
        abort_if($equipe->id === $request->user()->id, 422, 'Você não pode remover o próprio usuário.');

        $equipe->delete();

        return redirect()->route('equipe.index')
            ->with('sucesso', 'Usuário removido.');
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
            'role' => ['required', 'in:admin,servidor'],
            'cargo' => ['nullable', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:50'],
        ]);
    }
}
