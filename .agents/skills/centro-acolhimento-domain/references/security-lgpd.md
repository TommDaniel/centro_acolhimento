# Segurança e LGPD

Estas regras são controles de engenharia; definições de base legal, retenção e comunicação devem ser aprovadas pelo controlador/encarregado/jurídico.

## Classificação e minimização

- `restrito`: identificação, família, processo judicial, localização/agenda e documentos.
- `sensível`: saúde, medicação, deficiência, saúde sexual/reprodutiva, biometria e qualquer inferência correlata.
- Colete e retorne somente campos necessários para a finalidade/tela. Não envie objeto completo via Inertia se a página usa um resumo.
- Ambientes não produtivos recebem dados sintéticos. Proíba dumps de produção, inclusive “temporários”.

## Autorização

- Defina matriz por ação: listar, ver, criar, alterar, encerrar/finalizar, cancelar, baixar, exportar, auditar e administrar acesso.
- Políticas avaliam usuário ativo, organização/unidade, função, ação e sensibilidade. Na fase atual, setor, equipe, responsável e vínculo com o caso são filtros/atribuições e não isolam informação assistencial entre `equipe_tecnica` e `administradora`, que possuem o mesmo acesso funcional assistencial aprovado.
- Mantenha Policies separadas por ação para gestão de contas, consulta de auditoria, download/exportação, cancelamento, segredos e infraestrutura. Somente a administradora gere contas/acessos e consulta a auditoria funcional minimizada em modo somente leitura; nenhuma conta altera/apaga auditoria ou controle de segurança.
- Aplique escopo no backend e teste IDOR em rotas, endpoints, downloads e IDs aninhados. Retorne resposta que não confirme existência quando apropriado.
- Sessões privilegiadas exigem controles reforçados; remoção/inativação revoga sessões e acessos derivados.

## Auditoria

- Audite autenticação, busca sensível, visualização, criação, alteração antes/depois minimizada, transição, finalização, download, PDF, exportação, acesso negado e administração de permissão.
- Para propostas, agenda e notificações, cubra também corrigir, aprovar, rejeitar, cancelar, reagendar e notificar. Registre ator, ação, alvo/ID, unidade, resultado, horário UTC, campos alterados, revision ID, contexto/correlation ID e justificativa quando exigida.
- Não copie corpo de e-mail, narrativa clínica, PII, valor anterior/novo sensível, arquivo ou documento completo para log técnico/auditoria. Antes/depois restrito pertence ao histórico funcional versionado e autorizado.
- Auditoria é append-only, protegida contra alteração, com acesso e retenção próprios. Administradora pode consultar a visão funcional minimizada em modo somente leitura; técnicas veem autoria/data no recurso autorizado. Nenhuma delas edita/apaga a trilha. Log técnico não substitui trilha de auditoria.

## Arquivos e documentos

- Use object storage privado, criptografia, nomes aleatórios e chave contendo organização/unidade sem PII no nome.
- Valide tamanho, extensão permitida, assinatura/MIME real e dimensões; remova EXIF de imagens; coloque upload em quarentena e faça varredura antimalware antes de liberar.
- Download passa por Policy ou URL assinada curta e é auditado. `public` e URL permanente são proibidos para conteúdo assistencial.
- Previna conteúdo ativo em SVG/HTML/PDF e Content-Type perigoso. Gere visualização em sandbox/processo isolado quando aplicável.

## Aplicação e operação

- Produção: HTTPS, `APP_DEBUG=false`, cookies `Secure`/`HttpOnly`/`SameSite`, segredos por ambiente e rotação.
- Adote CSRF, rate limiting, validação server-side, queries parametrizadas/ORM, proteção de mass assignment e headers/CSP compatíveis.
- Logs, traces, erros e analytics devem redigir PII/sensível. Nunca registre senha, token, cookie, payload de formulário ou URL assinada.
- Backups de banco e objetos são criptografados, isolados, mantidos fora do domínio de falha da VPS e testados por restauração. Snapshot/auto backup do provedor ou cópia local é somente camada complementar; valide escopo, frequência, retenção, região, consistência, criptografia e restore. Exclusão considera réplicas, backups, legal hold e obrigações arquivísticas.
- Dependência externa exige contrato, região/localização de dados, suboperadores, DPA quando aplicável, resposta a incidente e plano de saída.

## Revisão de privacidade por mudança

Registre: finalidade; categorias e titulares; hipótese legal aprovada ou pendente; quem acessa; compartilhamentos; retenção; auditoria; risco/mitigação; comportamento em exportação, backup e exclusão. Se algo estiver pendente, não invente resposta e impeça go-live daquele tratamento.
