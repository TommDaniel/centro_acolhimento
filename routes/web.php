<?php

use App\Http\Controllers\CriancaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EquipeController;
use App\Http\Controllers\EventoController;
use App\Http\Controllers\PertenceController;
use App\Http\Controllers\PiaController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SetorController;
use App\Http\Controllers\VisitaTecnicaController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/busca', [SearchController::class, 'index'])->name('busca');

    // Agenda
    Route::get('agenda', [EventoController::class, 'index'])->name('agenda.index');
    Route::post('agenda', [EventoController::class, 'store'])->name('agenda.store');
    Route::put('agenda/{evento}', [EventoController::class, 'update'])->name('agenda.update');
    Route::patch('agenda/{evento}/concluido', [EventoController::class, 'toggleConcluido'])
        ->name('agenda.concluido');
    Route::delete('agenda/{evento}', [EventoController::class, 'destroy'])->name('agenda.destroy');

    // Cadastro de crianças/adolescentes + anexos
    Route::resource('criancas', CriancaController::class);
    Route::post('criancas/{crianca}/documentos', [CriancaController::class, 'storeDocumento'])
        ->name('criancas.documentos.store');
    Route::delete('documentos/{documento}', [CriancaController::class, 'destroyDocumento'])
        ->name('documentos.destroy');
    Route::post('criancas/{crianca}/familiares', [CriancaController::class, 'storeFamiliar'])
        ->name('criancas.familiares.store');
    Route::delete('familiares/{familiar}', [CriancaController::class, 'destroyFamiliar'])
        ->name('familiares.destroy');

    // Documentos
    Route::resource('pias', PiaController::class);
    Route::get('pias/{pia}/pdf', [PiaController::class, 'pdf'])->name('pias.pdf');
    Route::delete('pias/anexos/{anexo}', [PiaController::class, 'destroyAnexo'])->name('pias.anexos.destroy');

    Route::resource('visitas-tecnicas', VisitaTecnicaController::class)
        ->parameters(['visitas-tecnicas' => 'visitasTecnica']);
    Route::get('visitas-tecnicas/{visitasTecnica}/pdf', [VisitaTecnicaController::class, 'pdf'])
        ->name('visitas-tecnicas.pdf');

    Route::resource('reports', ReportController::class);
    Route::get('reports/{report}/pdf', [ReportController::class, 'pdf'])->name('reports.pdf');

    Route::resource('pertences', PertenceController::class);
    Route::get('pertences/{pertence}/pdf', [PertenceController::class, 'pdf'])->name('pertences.pdf');

    // Setores (visualização para todos; gestão restrita a admin)
    Route::resource('setores', SetorController::class)
        ->only(['index', 'show']);
    Route::resource('setores', SetorController::class)
        ->except(['index', 'show', 'create', 'edit'])
        ->middleware('admin');

    // Equipe (visualização para todos; gestão restrita a admin)
    Route::resource('equipe', EquipeController::class)
        ->only(['index'])
        ->parameters(['equipe' => 'equipe']);
    Route::resource('equipe', EquipeController::class)
        ->except(['index', 'show'])
        ->parameters(['equipe' => 'equipe'])
        ->middleware('admin');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
