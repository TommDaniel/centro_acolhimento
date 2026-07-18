<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Termo de pertences — {{ $pertence->crianca->nome_completo }}</title>
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
    table.ident, table.itens { width: 100%; border-collapse: collapse; margin-top: 4px; }
    table.ident td, table.itens td, table.itens th { border: 1px solid #d1d5db; padding: 4px 7px; vertical-align: top; text-align: left; }
    table.ident td.rotulo { width: 32%; background: #f9fafb; font-weight: bold; }
    table.itens th { background: #f9fafb; font-weight: bold; }
    div.texto { margin: 0 0 8px; text-align: justify; }
    table.oficio { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 11px; }
    table.oficio td { padding: 0; vertical-align: top; }
    table.oficio td:first-child { font-weight: bold; }
    table.oficio td:last-child { text-align: right; }
    .assinatura { margin-top: 34px; width: 300px; text-align: center; }
    .assinatura .nome { font-style: italic; min-height: 14px; }
    .assinatura .linha { border-top: 1px solid #111827; padding-top: 3px; font-size: 9.5px; }
</style>
</head>
<body>
<header>
    @include('pdf.cabecalho')
</header>

<footer>
    Registrado por {{ $pertence->criador?->name ?? '—' }} em {{ $pertence->created_at->format('d/m/Y \à\s H:i') }} — Setor: {{ $pertence->setor?->nome ?? '—' }}<br>
    Documento gerado eletronicamente (POC — dados fictícios)
</footer>

<main>
    <table class="oficio">
        <tr>
            <td>Ofício nº {{ $pertence->numero_oficio ?? '—' }}</td>
            <td>{{ $local_oficio }}, {{ $data_extenso }}</td>
        </tr>
    </table>

    <h2 class="secao">Identificação</h2>
    <table class="ident">
        <tbody>
            <tr>
                <td class="rotulo">Nome completo</td>
                <td>{{ $pertence->crianca->nome_completo }}</td>
            </tr>
            @if($pertence->crianca->data_nascimento)
                <tr>
                    <td class="rotulo">Data de nascimento</td>
                    <td>{{ $pertence->crianca->data_nascimento->format('d/m/Y') }}</td>
                </tr>
            @endif
            @if($pertence->crianca->processo_numero)
                <tr>
                    <td class="rotulo">Nº do processo</td>
                    <td>{{ $pertence->crianca->processo_numero }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <h2 class="secao">Itens entregues</h2>
    @if(count($pertence->itens ?? []))
        <table class="itens">
            <thead>
                <tr>
                    <th style="width: 8%;">#</th>
                    <th>Descrição</th>
                    <th style="width: 18%;">Quantidade</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pertence->itens as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $item['descricao'] ?? '' }}</td>
                        <td>{{ $item['quantidade'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="texto">Nenhum item registrado.</div>
    @endif

    <h2 class="secao">Entrega</h2>
    <div class="texto">
        <strong>Data da entrega:</strong> {{ $pertence->data_entrega->format('d/m/Y') }}
    </div>
    <div class="assinatura">
        <div class="nome">{{ $pertence->assinatura_entrega }}</div>
        <div class="linha">Assinatura do menor</div>
    </div>

    <h2 class="secao">Devolução</h2>
    @if($pertence->devolvido)
        <div class="texto">
            <strong>Data da devolução:</strong> {{ $pertence->data_devolucao?->format('d/m/Y') ?? '—' }}
        </div>
        <div class="assinatura">
            <div class="nome">{{ $pertence->assinatura_devolucao }}</div>
            <div class="linha">Assinatura do menor</div>
        </div>
    @else
        <div class="texto">Pertences ainda não devolvidos.</div>
    @endif
    @if(filled($pertence->observacao_devolucao))
        <div class="texto">{!! nl2br(e($pertence->observacao_devolucao)) !!}</div>
    @endif
</main>
</body>
</html>
