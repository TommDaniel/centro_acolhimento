<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Visita técnica — {{ $visita->crianca->nome_completo }}</title>
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
    p.data { font-size: 11px; margin: 0 0 4px; }
    div.texto { margin: 0 0 8px; text-align: justify; }
    table.oficio { width: 100%; border-collapse: collapse; margin-bottom: 16px; font-size: 11px; }
    table.oficio td { padding: 0; vertical-align: top; }
    table.oficio td:first-child { font-weight: bold; }
    table.oficio td:last-child { text-align: right; }
</style>
</head>
<body>
<header>
    @include('pdf.cabecalho')
</header>

<footer>
    Registrado por {{ $visita->criador?->name ?? '—' }} em {{ $visita->created_at->format('d/m/Y \à\s H:i') }} — Setor: {{ $visita->setor?->nome ?? '—' }}<br>
    Documento gerado eletronicamente (POC — dados fictícios)
</footer>

<main>
    <table class="oficio">
        <tr>
            <td>Ofício nº {{ $visita->numero_oficio ?? '—' }}</td>
            <td>{{ $local_oficio }}, {{ $data_extenso }}</td>
        </tr>
    </table>

    <p class="data">
        <strong>Data da visita:</strong> {{ $visita->data_visita->format('d/m/Y') }}
        @if($visita->hora_visita)
            &nbsp;&nbsp;<strong>Hora:</strong> {{ substr($visita->hora_visita, 0, 5) }}
        @endif
    </p>

    <h2 class="secao">Identificação</h2>
    <table class="ident">
        <tbody>
            <tr>
                <td class="rotulo">Nome completo</td>
                <td>{{ $visita->crianca->nome_completo }}</td>
            </tr>
            @if($visita->crianca->data_nascimento)
                <tr>
                    <td class="rotulo">Data de nascimento</td>
                    <td>{{ $visita->crianca->data_nascimento->format('d/m/Y') }}</td>
                </tr>
            @endif
            @if($visita->crianca->processo_numero)
                <tr>
                    <td class="rotulo">Nº do processo</td>
                    <td>{{ $visita->crianca->processo_numero }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    @foreach($visita->secoes() as $titulo => $texto)
        <h2 class="secao">{{ $titulo }}</h2>
        <div class="texto">{!! nl2br(e($texto)) !!}</div>
    @endforeach
</main>
</body>
</html>
