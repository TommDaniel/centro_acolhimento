# Runbook técnico e protocolo dos agentes

Este documento define como o coordenador, o implementador, o revisor sênior e o QA trabalham. Ele complementa `TODO.md`; não substitui critérios de aceite nem decisões de produto.

## 1. Contrato de entrada da tarefa

Antes de editar código, registre no handoff:

- `task_id`: identificador de `TODO.md` ou `ENG-<descrição>` para infraestrutura;
- objetivo e valor para o usuário;
- critérios de aceite verificáveis;
- escopo incluído e explicitamente excluído;
- risco: baixo, médio, alto ou crítico;
- dados afetados, autorização necessária e eventos de auditoria;
- migração/backfill/rollback ou justificativa de não aplicabilidade;
- plano de testes, incluindo pelo menos um caso negativo.

Se uma decisão pendente (`DEC-*`) mudar modelo, permissão, retenção, contagem ou valor jurídico, pare e peça decisão humana. Não transforme recomendação em regra definitiva.

## 2. Responsabilidades

### Coordenador

- fatia o backlog em incrementos pequenos e sequenciais;
- fornece o contrato de entrada e escolhe skills relevantes;
- garante que somente um agente escreva código de produção por vez;
- encaminha achados entre agentes e mantém a rastreabilidade;
- decide com o usuário trade-offs de produto; não mascara incertezas.

### Implementer (Junior)

- investiga o código existente antes de propor estrutura nova;
- implementa código, migrações e testes do escopo;
- prefere clareza, constraints e operações transacionais a abstrações prematuras;
- entrega evidências e riscos; nunca declara autoaprovação.

### Senior Reviewer

- trabalha em modo somente leitura;
- revisa correção, domínio, autorização, concorrência, dados, desempenho, operação e testes;
- sugere biblioteca ou estrutura diferente somente com benefício mensurável e custo de adoção explícito;
- classifica achados e retorna `PASS` ou `CHANGES_REQUIRED`.

### QA & Security

- deriva testes dos critérios de aceite e regras da skill de domínio;
- pode criar/ajustar apenas testes, configuração de teste e artefatos de diagnóstico;
- testa UI → API → banco → resposta/PDF, além de casos unitários e de integração;
- procura IDOR, elevação de privilégio, mass assignment, upload perigoso, vazamento em logs e violações de transição;
- relata defeitos; não corrige código de produção.
- se alterar qualquer arquivo, devolve `NEEDS_REVIEW`; o Senior precisa revisar esse diff antes de um `PASS` final.

## 3. Formato dos handoffs

### Implementer → Senior

```text
TASK: <id>
OBJETIVO/ACEITE: <resumo>
ARQUIVOS: <lista>
DECISÕES: <decisão e motivo>
DADOS/MIGRAÇÃO: <impacto>
SEGURANÇA/LGPD: <controles>
TESTES: <comando e resultado>
RISCOS/ABERTOS: <lista ou nenhum>
```

### Senior → Implementer

Cada achado deve conter severidade, arquivo/linha, cenário reproduzível, impacto e direção segura de correção. A decisão final é `PASS` ou `CHANGES_REQUIRED`.

### QA → Coordenador

```text
STATUS: PASS | FAIL | BLOCKED
AMBIENTE: <versões/configuração, sem segredos>
COBERTURA: <aceites e riscos exercitados>
COMANDOS: <comando + resultado>
DEFEITOS: <severidade, reprodução, esperado, obtido, evidência>
ARTEFATOS: <caminhos, sem dados reais>
LIMITAÇÕES: <o que não pôde ser provado>
```

## 4. Severidade e gates

- `BLOCKER`: perda/corrupção de dados, acesso indevido, segredo exposto, operação destrutiva, regra central incorreta ou build inviável.
- `HIGH`: autorização ausente, histórico adulterável, contagem/documento materialmente incorreto, falha recorrente de fluxo P0/P1 ou vulnerabilidade explorável.
- `MEDIUM`: borda relevante, degradação, manutenção arriscada, acessibilidade ou observabilidade insuficiente.
- `LOW`: melhoria localizada sem impacto funcional ou de segurança imediato.

Todos os `BLOCKER` e `HIGH` são corrigidos antes da entrega. `MEDIUM` precisa ser corrigido ou aceito explicitamente com tarefa no backlog. `LOW` não deve gerar refatoração fora do escopo.

## 5. Avaliação de dependências

Antes de adicionar pacote, o Senior verifica:

1. problema concreto que a stack atual não resolve bem;
2. documentação oficial, compatibilidade com versões e maturidade;
3. licença e custo operacional/comercial;
4. histórico de segurança, manutenção e cadência de releases;
5. impacto em bundle, runtime, banco, lockfile e supply chain;
6. comportamento em falha e plano de remoção/migração;
7. teste que prova o benefício alegado.

Sem justificativa registrada, prefira Laravel, React/Inertia, PostgreSQL e APIs nativas já adotadas.

## 6. Gates mínimos

Execute no ambiente disponível. Playwright sempre usa a versão do lockfile por `npm run test:e2e`, nunca download dinâmico por wrapper:

```bash
composer validate --strict
composer audit --locked
vendor/bin/pint --test
php -d memory_limit=512M artisan test
npm audit --audit-level=high
npm run build
npm run test:e2e
```

Para migrations, constraints, concorrência, busca ou relatórios, acrescente integração com PostgreSQL. Para PDFs, acrescente conferência visual com e sem foto, caracteres portugueses, múltiplas páginas e 1–4 assinaturas. Para autorização, cubra visitante, usuário inativo, usuário autorizado e usuário autenticado sem vínculo.

Se um comando não puder rodar, marque como `BLOCKED`, explique o motivo e não converta ausência de evidência em sucesso.

## 7. Definition of Done

- critérios de aceite rastreados e verificados;
- testes positivos, negativos e de autorização aprovados;
- Senior em `PASS` e QA em `PASS`;
- migrations e operações em fila são idempotentes ou têm proteção contra repetição;
- logs/métricas não expõem dados sensíveis;
- documentação e `TODO.md` atualizados quando o contrato mudar;
- diff contém somente o escopo e preserva mudanças pré-existentes do usuário;
- riscos residuais, rollback e limitações constam no handoff final.
