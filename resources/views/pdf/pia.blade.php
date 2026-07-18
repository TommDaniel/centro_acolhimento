<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>PIA — {{ $pia->crianca->nome_completo }}</title>
<style>
    @@page { margin: 110px 56px 90px 56px; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; line-height: 1.55; color: #111827; margin: 0; }
    header { position: fixed; top: -90px; left: 0; right: 0; }
    header .rule { border-bottom: 2px solid #1e3a8a; margin-top: 8px; }
    table.cabecalho { width: 100%; border-collapse: collapse; }
    table.cabecalho td { vertical-align: middle; padding: 0; }
    table.cabecalho td.logo { width: 70px; }
    table.cabecalho td.logo img { width: 60px; height: auto; display: block; }
    table.cabecalho td.textos { padding-left: 12px; }
    table.cabecalho .titulo { font-size: 13px; font-weight: bold; color: #1e3a8a; }
    table.cabecalho .versiculo { font-size: 10px; color: #374151; font-style: italic; margin-top: 4px; }
    footer { position: fixed; bottom: -70px; left: 0; right: 0; border-top: 1px solid #9ca3af; padding-top: 5px; font-size: 8.5px; color: #4b5563; text-align: center; line-height: 1.4; }
    h2.secao { font-size: 11px; font-weight: bold; background: #f3f4f6; border-left: 3px solid #0f766e; padding: 5px 8px; margin: 16px 0 8px; text-transform: uppercase; }
    table.ident { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.ident td { border: 1px solid #d1d5db; padding: 4px 7px; vertical-align: top; }
    table.ident td.rotulo { width: 32%; background: #f9fafb; font-weight: bold; }
    table.fam { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.fam th { border: 1px solid #d1d5db; background: #f9fafb; padding: 4px 7px; text-align: left; font-size: 10px; text-transform: uppercase; }
    table.fam td { border: 1px solid #d1d5db; padding: 4px 7px; vertical-align: top; }
    div.texto { margin: 0 0 8px; text-align: justify; }
    div.pessoa { margin: 0 0 6px; }
    span.vinculo { font-weight: bold; }
    table.oficio { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 11px; }
    table.oficio td { padding: 0; vertical-align: top; }
    table.oficio td:first-child { font-weight: bold; }
    table.oficio td:last-child { text-align: right; }
</style>
</head>
<body>
@php
    $n = 1;
    $rotulosTipo = ['genitora' => 'Genitora', 'genitor' => 'Genitor', 'responsavel' => 'Responsável'];
    $filiacao = $pia->crianca->familiares->whereIn('tipo', ['genitora', 'genitor', 'responsavel']);
    $familiares = $pia->crianca->familiares;
@endphp

<header>
    @include('pdf.cabecalho')
</header>

<footer>
    Registrado por {{ $pia->criador?->name ?? '—' }} em {{ $pia->created_at->format('d/m/Y \à\s H:i') }} — Setor: {{ $pia->setor?->nome ?? '—' }}<br>
    Documento gerado eletronicamente (POC — dados fictícios)
</footer>

<main>
    <table class="oficio">
        <tr>
            <td>Ofício nº {{ $pia->numero_oficio ?? '—' }}</td>
            <td>{{ $local_oficio }}, {{ $data_extenso }}</td>
        </tr>
    </table>

    @if($pia->crianca->foto && file_exists(storage_path('app/public/'.$pia->crianca->foto)))
        <img src="{{ storage_path('app/public/'.$pia->crianca->foto) }}" style="width:90px; float:right; margin:0 0 8px 12px;">
    @endif

    <h2 class="secao">{{ $n++ }}. Dados de identificação</h2>
    <table class="ident">
        <tbody>
            @foreach($pia->crianca->identificacao() as $rotulo => $valor)
                @if(filled($valor))
                    <tr>
                        <td class="rotulo">{{ $rotulo }}</td>
                        <td>{{ $valor }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>

    @if($filiacao->isNotEmpty())
        <h2 class="secao">{{ $n++ }}. Filiação e responsáveis</h2>
        @foreach($filiacao as $pessoa)
            <div class="pessoa">
                <span class="vinculo">{{ $pessoa->nome }}</span> ({{ $rotulosTipo[$pessoa->tipo] ?? ucfirst($pessoa->tipo) }})<br>
                {{ collect([
                    $pessoa->data_nascimento ? 'Nasc.: '.$pessoa->data_nascimento->format('d/m/Y') : null,
                    $pessoa->rg ? 'RG: '.$pessoa->rg : null,
                    $pessoa->cpf ? 'CPF: '.$pessoa->cpf : null,
                    $pessoa->telefone ? 'Telefone: '.$pessoa->telefone : null,
                    $pessoa->endereco ? 'Endereço: '.$pessoa->endereco : null,
                    $pessoa->ocupacao ? 'Ocupação: '.$pessoa->ocupacao : null,
                ])->filter()->implode(' · ') }}
            </div>
        @endforeach
    @endif

    @if($familiares->isNotEmpty() || filled($pia->composicao_familiar))
        <h2 class="secao">{{ $n++ }}. Composição familiar</h2>
        @if($familiares->isNotEmpty())
            <table class="fam">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Parentesco</th>
                        <th>Nascimento</th>
                        <th>Idade</th>
                        <th>Ocupação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($familiares as $familiar)
                        <tr>
                            <td>{{ $familiar->nome }}</td>
                            <td>{{ $familiar->parentesco ?? ($rotulosTipo[$familiar->tipo] ?? '—') }}</td>
                            <td>{{ $familiar->data_nascimento?->format('d/m/Y') ?? '—' }}</td>
                            <td>{{ $familiar->idade !== null ? $familiar->idade.' anos' : '—' }}</td>
                            <td>{{ $familiar->ocupacao ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
        @if(filled($pia->composicao_familiar))
            <div class="texto" style="margin-top:6px;"><strong>Observações:</strong> {!! nl2br(e($pia->composicao_familiar)) !!}</div>
        @endif
    @endif

    @if(filled($pia->dados_acolhimento) || filled($pia->encaminhado_por) || $pia->acolhimento_anterior)
        <h2 class="secao">{{ $n++ }}. Dados do acolhimento</h2>
        @if(filled($pia->dados_acolhimento))
            <div class="texto">{!! nl2br(e($pia->dados_acolhimento)) !!}</div>
        @endif
        @if(filled($pia->encaminhado_por))
            <div class="texto"><strong>Encaminhado por:</strong> {{ $pia->encaminhado_por }}</div>
        @endif
        <div class="texto">
            <strong>Acolhimento institucional anterior:</strong> {{ $pia->acolhimento_anterior ? 'Sim' : 'Não' }}
            @if($pia->acolhimento_anterior && filled($pia->acolhimento_anterior_detalhes))
                <br>{!! nl2br(e($pia->acolhimento_anterior_detalhes)) !!}
            @endif
        </div>
    @endif

    @foreach($pia->secoes() as $titulo => $texto)
        <h2 class="secao">{{ $n++ }}. {{ $titulo }}</h2>
        <div class="texto">{!! nl2br(e($texto)) !!}</div>
    @endforeach

    @if($pia->anexos && $pia->anexos->isNotEmpty())
        <h2 class="secao">{{ $n++ }}. Anexos</h2>
        <ul>
            @foreach($pia->anexos as $anexo)
                <li>
                    {{ $anexo->nome_original }}
                    @if(filled($anexo->descricao))
                        — {{ $anexo->descricao }}
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</main>
</body>
</html>
