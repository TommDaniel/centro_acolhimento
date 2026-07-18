<?php

namespace Database\Seeders;

use App\Models\Crianca;
use App\Models\Evento;
use App\Models\Pertence;
use App\Models\Pia;
use App\Models\Report;
use App\Models\Setor;
use App\Models\User;
use App\Models\VisitaTecnica;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Dados 100% FICTÍCIOS, apenas para demonstração da POC.
     */
    public function run(): void
    {
        $setores = collect([
            'Coordenação' => 'Gestão da unidade e articulação com a rede de proteção.',
            'Serviço Social' => 'Acompanhamento sociofamiliar, PIA e demandas ao Judiciário.',
            'Psicologia' => 'Atendimento psicológico e avaliações das crianças acolhidas.',
            'Pedagogia' => 'Acompanhamento escolar e atividades educativas.',
            'Enfermagem' => 'Cuidados de saúde e controle de medicação.',
        ])->map(fn ($descricao, $nome) => Setor::create(compact('nome', 'descricao')));

        $senha = Hash::make('password');

        $admin = User::create([
            'name' => 'Ana Coordenadora (Fictício)',
            'email' => 'admin@poc.local',
            'password' => $senha,
            'role' => 'admin',
            'cargo' => 'Coordenadora da unidade',
            'telefone' => '(47) 99999-0001',
            'setor_id' => $setores['Coordenação']->id,
        ]);

        $assistente = User::create([
            'name' => 'Bruno Assistente (Fictício)',
            'email' => 'bruno@poc.local',
            'password' => $senha,
            'role' => 'servidor',
            'cargo' => 'Assistente social',
            'telefone' => '(47) 99999-0002',
            'setor_id' => $setores['Serviço Social']->id,
        ]);

        $psicologa = User::create([
            'name' => 'Carla Psicóloga (Fictício)',
            'email' => 'carla@poc.local',
            'password' => $senha,
            'role' => 'servidor',
            'cargo' => 'Psicóloga',
            'telefone' => '(47) 99999-0003',
            'setor_id' => $setores['Psicologia']->id,
        ]);

        $pedagoga = User::create([
            'name' => 'Diego Pedagogo (Fictício)',
            'email' => 'diego@poc.local',
            'password' => $senha,
            'role' => 'servidor',
            'cargo' => 'Pedagogo',
            'telefone' => '(47) 99999-0004',
            'setor_id' => $setores['Pedagogia']->id,
        ]);

        $joao = Crianca::create([
            'nome_completo' => 'João Pedro da Silva Fictício',
            'data_nascimento' => '2014-03-12',
            'sexo' => 'Masculino',
            'identidade_genero' => 'Menino',
            'cor_raca' => 'Parda',
            'naturalidade' => 'Joinville/SC',
            'nacionalidade' => 'Brasileira',
            'rg' => '12.345.678-9',
            'rn' => '2024-123456',
            'cartao_sus' => '700 1234 5678 9012',
            'nis' => '123.45678.90-1',
            'nome_mae' => 'Maria Fictícia da Silva',
            'nome_pai' => 'José Fictício da Silva',
            'responsavel_legal' => 'Maria Fictícia da Silva',
            'contato_responsavel' => '(47) 98888-1111',
            'endereco_familia' => 'Rua das Palmeiras, 123 — Bairro Fictício, Joinville/SC',
            'processo_numero' => '1234567-89.2026.8.24.0000',
            'vara' => 'Vara da Infância e Juventude',
            'comarca' => 'Joinville',
            'data_acolhimento' => '2026-05-10',
            'motivo_acolhimento' => 'Medida protetiva por situação de negligência familiar, determinada judicialmente.',
            'status' => 'acolhida',
            'created_by' => $assistente->id,
        ]);

        $joao->familiares()->createMany([
            [
                'tipo' => 'genitora',
                'nome' => 'Maria Fictícia da Silva',
                'parentesco' => 'Mãe',
                'data_nascimento' => '1988-06-20',
                'rg' => '9.876.543-2',
                'cpf' => '111.222.333-44',
                'telefone' => '(47) 98888-1111',
                'endereco' => 'Rua das Palmeiras, 123 — Bairro Fictício, Joinville/SC',
                'ocupacao' => 'Diarista',
                'created_by' => $assistente->id,
            ],
            [
                'tipo' => 'genitor',
                'nome' => 'José Fictício da Silva',
                'parentesco' => 'Pai',
                'data_nascimento' => '1985-02-10',
                'telefone' => '(47) 98888-1112',
                'ocupacao' => 'Desempregado',
                'created_by' => $assistente->id,
            ],
            [
                'tipo' => 'familiar',
                'nome' => 'Pedro Fictício da Silva',
                'parentesco' => 'Irmão',
                'data_nascimento' => '2019-09-05',
                'observacoes' => 'Sob guarda da avó materna.',
                'created_by' => $assistente->id,
            ],
        ]);

        $luana = Crianca::create([
            'nome_completo' => 'Luana Santos Fictícia',
            'data_nascimento' => '2011-08-25',
            'sexo' => 'Feminino',
            'cor_raca' => 'Branca',
            'naturalidade' => 'São Francisco do Sul/SC',
            'nacionalidade' => 'Brasileira',
            'nome_mae' => 'Patrícia Fictícia Santos',
            'contato_responsavel' => '(47) 98888-2222',
            'processo_numero' => '9876543-21.2025.8.24.0000',
            'vara' => 'Vara da Infância e Juventude',
            'comarca' => 'Joinville',
            'data_acolhimento' => '2025-11-02',
            'motivo_acolhimento' => 'Abandono afetivo e desamparo familiar.',
            'status' => 'acolhida',
            'created_by' => $assistente->id,
        ]);

        $luana->familiares()->create([
            'tipo' => 'genitora',
            'nome' => 'Patrícia Fictícia Santos',
            'parentesco' => 'Mãe',
            'data_nascimento' => '1990-04-15',
            'telefone' => '(47) 98888-2222',
            'ocupacao' => 'Auxiliar de cozinha',
            'created_by' => $assistente->id,
        ]);

        Crianca::create([
            'nome_completo' => 'Miguel Oliveira Fictício',
            'data_nascimento' => '2017-01-30',
            'sexo' => 'Masculino',
            'processo_numero' => '5555555-55.2024.8.24.0000',
            'data_acolhimento' => '2024-08-15',
            'status' => 'desligada',
            'observacoes' => 'Desligamento por reintegração familiar em 10/01/2026.',
            'created_by' => $assistente->id,
        ]);

        Pia::create([
            'crianca_id' => $joao->id,
            'numero_oficio' => '210/2026',
            'composicao_familiar' => "Irmãos (7 e 2 anos) estão sob guarda da avó materna.\nAvó materna é rede de apoio ativa.",
            'dados_acolhimento' => "Data do acolhimento: 10/05/2026.\nMotivo: medida protetiva por situação de negligência familiar, determinada judicialmente.\nProcesso nº 1234567-89.2026.8.24.0000 — Vara da Infância e Juventude / Joinville.",
            'acolhimento_anterior' => true,
            'acolhimento_anterior_detalhes' => 'Acolhimento anterior em 2023, na Casa Lar Fictício, por aproximadamente 4 meses, com reintegração em 06/2023.',
            'encaminhado_por' => 'Conselho Tutelar de Joinville (fictício)',
            'especificidades' => 'Criança comunicativa, apresenta dificuldade de adaptação inicial à rotina da unidade. Sem deficiência diagnosticada.',
            'saude' => 'Acompanhamento pediátrico em dia. Asma leve, uso eventual de bombinha. Vacinação em atualização.',
            'educacao_menor' => 'Matriculado no 4º ano do Ensino Fundamental na Escola Municipal Fictícia. Bom rendimento, dificuldade pontual em matemática.',
            'assistencia_social' => 'Família cadastrada no CadÚnico. Acompanhamento pelo CRAS Fictício.',
            'esporte_cultura_lazer' => 'Participa das atividades de futebol da unidade às terças e quintas.',
            'plano_acao' => "1. Manter acompanhamento psicológico semanal.\n2. Fortalecer vínculo familiar com visitas quinzenais.\n3. Reforço escolar em matemática 2x por semana.\n4. Avaliar reintegração familiar no próximo trimestre.",
            'providencias_judiciario' => 'Solicitar ao Juízo a manutenção das visitas supervisionadas até nova avaliação.',
            'setor_id' => $setores['Serviço Social']->id,
            'created_by' => $assistente->id,
        ]);

        Report::create([
            'crianca_id' => $joao->id,
            'numero_oficio' => '211/2026',
            'titulo' => 'Ocorrência de fuga da unidade',
            'introducao' => 'A presente ocorrência refere-se ao acolhido João Pedro da Silva Fictício, processo nº 1234567-89.2026.8.24.0000, acolhido nesta unidade desde 10/05/2026.',
            'desenvolvimento' => "No dia 14/07/2026, por volta das 21h30, o acolhido evadiu-se da unidade pela janela do dormitório, retornando por volta das 23h45 por iniciativa própria.\nDurante o período, a equipe acionou a Coordenação e o Conselho Tutelar, conforme protocolo interno. A criança retornou sem sinais de lesão, relatando que foi à casa de um colega no bairro vizinho.",
            'consideracoes' => 'Diante do exposto, solicita-se ao Juízo ciência da ocorrência e autorização para reforço das medidas de supervisão noturna, bem como a intensificação do acompanhamento psicológico.',
            'setor_id' => $setores['Serviço Social']->id,
            'created_by' => $assistente->id,
        ]);

        VisitaTecnica::create([
            'crianca_id' => $joao->id,
            'numero_oficio' => '212/2026',
            'data_visita' => '2026-07-05',
            'hora_visita' => '14:00',
            'tipo' => 'Visita ao núcleo familiar',
            'visitante' => 'Bruno Assistente (Fictício)',
            'local' => 'Residência da família — Bairro Fictício',
            'motivo' => 'Avaliação das condições do núcleo familiar para possível reintegração.',
            'relato' => "A residência apresenta condições adequadas de higiene e moradia. A genitora demonstrou engajamento com o plano de reintegração e está participando das orientações do CRAS.\nObservou-se a presença de rede de apoio (avó materna) no imóvel.",
            'encaminhamentos' => 'Manter visitas quinzenais e reavaliar em 60 dias. Encaminhar genitora ao curso de qualificação do SENAI Fictício.',
            'setor_id' => $setores['Serviço Social']->id,
            'created_by' => $assistente->id,
        ]);

        VisitaTecnica::create([
            'crianca_id' => $luana->id,
            'numero_oficio' => '213/2026',
            'data_visita' => '2026-07-10',
            'tipo' => 'Conselho Tutelar',
            'visitante' => 'Conselheira Fictícia Ramos',
            'local' => 'Unidade de acolhimento',
            'relato' => 'Visita de rotina para acompanhamento da medida protetiva. A acolhida relatou estar bem adaptada e satisfeita com a escola.',
            'setor_id' => $setores['Serviço Social']->id,
            'created_by' => $psicologa->id,
        ]);

        Pertence::create([
            'crianca_id' => $joao->id,
            'numero_oficio' => '214/2026',
            'itens' => [
                ['descricao' => 'Celular marca X, cor preta', 'quantidade' => '1'],
                ['descricao' => 'Relógio de pulso', 'quantidade' => '1'],
                ['descricao' => 'Corrente prateada', 'quantidade' => '1'],
            ],
            'data_entrega' => '2026-05-10',
            'assinatura_entrega' => 'João Pedro da Silva Fictício',
            'devolvido' => false,
            'observacao_devolucao' => null,
            'setor_id' => $setores['Coordenação']->id,
            'created_by' => $admin->id,
        ]);

        Pertence::create([
            'crianca_id' => $luana->id,
            'numero_oficio' => '215/2026',
            'itens' => [
                ['descricao' => 'Fone de ouvido sem fio', 'quantidade' => '1'],
                ['descricao' => 'Carteira com documentos', 'quantidade' => '1'],
            ],
            'data_entrega' => '2025-11-02',
            'assinatura_entrega' => 'Luana Santos Fictícia',
            'devolvido' => true,
            'data_devolucao' => '2026-03-15',
            'assinatura_devolucao' => 'Luana Santos Fictícia',
            'setor_id' => $setores['Coordenação']->id,
            'created_by' => $admin->id,
        ]);

        Report::create([
            'crianca_id' => $luana->id,
            'numero_oficio' => '216/2026',
            'titulo' => 'Indisciplina em horário de aula interna',
            'introducao' => 'Ocorrência referente à acolhida Luana Santos Fictícia, processo nº 9876543-21.2025.8.24.0000.',
            'desenvolvimento' => 'Em 08/07/2026, durante a atividade pedagógica, a acolhida recusou-se a participar e desrespeitou a profissional, necessitando intervenção da Coordenação.',
            'consideracoes' => 'Solicita-se ciência do Juízo. A equipe segue com acompanhamento psicológico reforçado.',
            'setor_id' => $setores['Pedagogia']->id,
            'created_by' => $pedagoga->id,
        ]);

        Evento::create([
            'titulo' => 'Visita ao núcleo familiar do João',
            'tipo' => 'visita',
            'descricao' => 'Visita quinzenal de acompanhamento da reintegração familiar.',
            'inicio' => now()->addDays(3)->setTime(14, 0),
            'fim' => now()->addDays(3)->setTime(16, 0),
            'crianca_id' => $joao->id,
            'setor_id' => $setores['Serviço Social']->id,
            'created_by' => $assistente->id,
        ]);

        Evento::create([
            'titulo' => 'Audiência de revisão da medida protetiva',
            'tipo' => 'audiencia',
            'descricao' => 'Levar relatório de ocorrência atualizado e PIA revisado.',
            'inicio' => now()->addDays(7)->setTime(9, 30),
            'crianca_id' => $joao->id,
            'setor_id' => $setores['Serviço Social']->id,
            'created_by' => $assistente->id,
        ]);

        Evento::create([
            'titulo' => 'Atendimento psicológico da Luana',
            'tipo' => 'atendimento',
            'inicio' => now()->addDays(1)->setTime(10, 0),
            'fim' => now()->addDays(1)->setTime(11, 0),
            'crianca_id' => $luana->id,
            'setor_id' => $setores['Psicologia']->id,
            'created_by' => $psicologa->id,
        ]);

        Evento::create([
            'titulo' => 'Renovar documentação da Luana (RG)',
            'tipo' => 'tarefa',
            'descricao' => 'Agendar emissão da 1ª via do RG no Poupatempo Fictício.',
            'inicio' => now()->addDays(5)->setTime(8, 0),
            'dia_inteiro' => true,
            'crianca_id' => $luana->id,
            'setor_id' => $setores['Serviço Social']->id,
            'created_by' => $assistente->id,
        ]);

        Evento::create([
            'titulo' => 'Reunião de equipe — planejamento do mês',
            'tipo' => 'outro',
            'inicio' => now()->addDays(2)->setTime(15, 0),
            'dia_inteiro' => false,
            'concluido' => true,
            'setor_id' => $setores['Coordenação']->id,
            'created_by' => $admin->id,
        ]);
    }
}
