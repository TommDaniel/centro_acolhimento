# ADR 0001 — Patches de segurança do motor de PDF e cliente HTTP

- Status: aceito para este incremento
- Data: 31/07/2026
- Tarefas: `OPS-03`, `SEG-05`, infraestrutura de qualidade

## Contexto

Após instalar Laravel Boost, `composer audit --locked` reportou nove advisories em dependências já existentes: seis em `dompdf/dompdf` abaixo de 3.1.6 e três em `guzzlehttp/guzzle` abaixo de 7.15.1. Os cenários do Dompdf incluem leitura/oráculo de arquivos via SVG e esgotamento de recursos por imagens; este produto gera PDFs com fotos e documentos sensíveis.

## Decisão

Atualizar somente:

- `dompdf/dompdf` de 3.1.5 para 3.1.6;
- `guzzlehttp/guzzle` de 7.15.0 para 7.15.2.

As constraints públicas não mudaram. O lockfile continua sendo a fonte reproduzível. Não foi adicionada nova API ou biblioteca de runtime.

## Evidência e consequências

- `composer validate --strict`: aprovado;
- `composer audit --locked`: zero advisories após a atualização;
- build Vite: aprovado;
- PHPUnit: 23 testes aprovados e 2 falhas preexistentes de cadastro/redirect, sem falha atribuída aos patches;
- regressão visual/funcional completa de PDFs ainda é obrigatória antes de produção, conforme `DOC-01`, `DOC-03`, `DOC-06` e `QA-01`.

Rollback consiste em restaurar o lockfile anterior, mas isso reabre vulnerabilidades conhecidas e exige exceção formal, mitigação e prazo. A decisão preferida é corrigir eventual incompatibilidade à frente, preservando as versões seguras.
