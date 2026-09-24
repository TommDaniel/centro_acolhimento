# Contexto institucional único — ARQ-01B / ARQ-06A

- Tarefas: `ARQ-01B`, `ARQ-06A`; implementa o primeiro corte físico de `DEC-05`
- Escopo: uma organização e uma unidade por implantação
- Estado: concluído em 10/09/2026; `NOT NULL`, produção Contabo e dados reais continuam bloqueados

## Finalidade e invariantes

O banco registra explicitamente a organização e a unidade, mas a interface não oferece seleção, filtro ou troca desse contexto. O backend deriva as chaves persistidas e rejeita com `422` qualquer tentativa de enviar IDs de contexto por body, query string ou parâmetros de rota, inclusive aliases em camelCase, notação por ponto/colchete e estruturas aninhadas. Campos conceituais legítimos, como `unit` usado como unidade de medida, não são bloqueados por nome isolado.

O PostgreSQL protege os seguintes invariantes:

- apenas um `context_slot = 1` pode existir em `organizacoes` e `unidades`;
- a unidade pertence à organização;
- `criancas.organizacao_id` aponta para a organização única;
- `users`, `setores`, `pias`, `visitas_tecnicas`, `reports`, `pertences` e `eventos` apontam para a unidade única;
- agregados que possuem `setor_id` validam também o par `(setor_id, unidade_id)`;
- familiares e anexos derivam o contexto do pai, sem chaves redundantes.

As colunas dos agregados continuam **nullable apenas durante o rollout**. Torná-las `NOT NULL` pertence a `ARQ-01C`, depois que este procedimento for executado e reconciliado no ambiente-alvo.

## Configuração

Desenvolvimento, PHPUnit e a demonstração Vercel usam somente valores sintéticos. Produção futura deve definir valores próprios por segredo/configuração de ambiente:

```dotenv
INSTITUTION_ORGANIZATION_CODE=codigo-tecnico-da-organizacao
INSTITUTION_ORGANIZATION_NAME="Nome institucional"
INSTITUTION_UNIT_CODE=codigo-tecnico-da-unidade
INSTITUTION_UNIT_NAME="Nome da unidade"
INSTITUTION_CONTEXT_PRODUCTION_CONFIRMED=true
```

Os códigos aceitam de 3 a 64 caracteres minúsculos (`a-z`, `0-9`, `_` e `-`). Configuração ausente, valor sintético em produção, contexto divergente ou confirmação ausente falham antes do backfill.

## Operação segura

1. Aplicar as migrations aditivas normalmente, sem `migrate:fresh`.
2. Configurar os quatro valores institucionais no ambiente.
3. Simular e conferir somente contagens e IDs técnicos:

   ```bash
   php artisan institution:provision-context --dry-run
   ```

4. Provisionar e executar o backfill transacional em lotes:

   ```bash
   php artisan institution:provision-context --chunk=200
   ```

5. Reconciliar sem alterar registros:

   ```bash
   php artisan institution:provision-context --reconcile-only
   ```

O comando é idempotente. No PostgreSQL, um advisory lock transacional serializa o provisionamento antes da primeira leitura/inserção; a compatibilidade SQLite sintética usa a transação do banco, espera limitada de escrita e as mesmas constraints singleton. Ele bloqueia segundo contexto, divergência de código/relação, chave conflitante e setor órfão ou incompatível. Erros inesperados são convertidos em mensagem operacional estável, sem SQL, bindings, configuração ou credenciais. A saída não contém nomes, códigos institucionais, cadastros, documentos ou dados assistenciais.

O `DatabaseSeeder` executa o mesmo provisionamento antes de gerar a massa integralmente fictícia. Isso mantém o E2E funcional. O SQLite da demonstração Vercel continua apenas como compatibilidade sintética legada e não é evidência do comportamento canônico, que deve ser provado em PostgreSQL.

## Rollout e diagnóstico

- Execute primeiro `--dry-run`; contagens inesperadas bloqueiam o avanço.
- Uma falha não produz backfill parcial porque provisionamento, validações e atualizações participam da mesma transação.
- Após sucesso, `--reconcile-only` deve retornar código zero e nenhuma pendência.
- `contexto persistido diverge`: confira códigos de ambiente e não renomeie códigos já provisionados para contornar o erro.
- `setor órfão ou conflitante`: corrija o vínculo por procedimento autorizado; não desative constraints.
- `configuração obrigatória ausente`: defina o valor no ambiente, nunca em migration ou código.

## Rollback roll-forward

Em ambiente persistente, não execute o `down` para apagar organização, unidade ou colunas de contexto. Enquanto as colunas permanecerem nullable, a imagem anterior da aplicação continua compatível com o schema aditivo. Qualquer defeito de dados ou constraint deve ser corrigido por nova migration/comando roll-forward, preservando IDs e histórico.

Este corte não configura Contabo, TLS, roles mínimas, backup, PITR, restore, casas, episódios, RBAC, auditoria, storage, Redis, numeração documental ou suporte a segunda unidade/organização. Nada aqui autoriza uso de dados reais.
