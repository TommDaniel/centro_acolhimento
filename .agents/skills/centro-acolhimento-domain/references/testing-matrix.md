# Matriz de testes

Selecione as linhas afetadas pela tarefa e transforme-as em testes automatizados. Todo fluxo P0/P1 exige caminho feliz, negação e falha parcial relevantes.

| Área | Casos mínimos |
|---|---|
| Autenticação | visitante redirecionado/negado; registro público indisponível; usuário inativo; sessão revogada |
| Autorização | usuário permitido; autenticado sem vínculo; setor/unidade alheios; ID trocado; dado sensível; download/exportação |
| Acolhimento | episódio aberto único; todas as transições válidas; transições inválidas; concorrência; linha do tempo preservada; filtro/contagem coerentes |
| Ingresso | obrigatórios; datas/identificadores; “Outro” com complemento; duplicidade sinalizada; órgão e pessoa separados |
| Saúde | UBS/UPA/Hospital/CAPS/Outro; acompanhante; encaminhamento; snapshot completo; corrente versus vigência clínica; encerramento/retificação; atualização concorrente; limite do alerta de desatualização; dose única separada; ausência de vazamento reprodutivo |
| Exames | solicitado/agendado/realizado/resultado/cancelado; ordem inválida; datas limite; lembrete não contabilizado como realização |
| Educação | matrícula vigente; transferência preserva histórico; reunião/ida/ligação; tarefa não contabilizada antes do desfecho |
| Agenda | escopos aprovados; criador versus responsável; reatribuição; intervalo visível; vínculo sensível não amplia acesso |
| Indicadores | fixture com total manual conhecido; ocorrências versus pessoas únicas; limites do período/fuso; filtros; zero; sem dupla contagem; drill-down igual ao agregado |
| Documentos | destinatário ausente/presente; snapshot; 1–4 signatários; rascunho/finalização/retificação; hash; numeração concorrente; caracteres portugueses |
| Foto/PDF | sem foto; retrato; paisagem; arquivo ausente/corrompido; acesso negado; múltiplas páginas; assinatura sem sobreposição |
| Upload | tipo/tamanho válidos; extensão disfarçada; malware/quarentena simulada; nome com PII; URL expirada; objeto sem autorização |
| Auditoria | sucesso e negação; ator/alvo/horário; alteração preservada; sem payload sensível; auditor não altera trilha |
| Concorrência | duplo clique/retry; optimistic lock; sequência única; falha parcial banco/storage; job idempotente |
| Acessibilidade | teclado, foco, label, mensagem de erro, contraste, leitor de tela básico, viewport móvel |
| Desempenho | paginação; N+1; consulta/índice PostgreSQL; payload mínimo; calendário por intervalo; massa representativa |

## Camadas

- Unitário: máquinas de estado, taxonomia/contagem, permissões puras, numeração e versionamento.
- PHPUnit Feature: autenticação/Policy, validação, persistência, auditoria, upload/download, filas e respostas Inertia.
- Integração PostgreSQL: constraints, locks, concorrência, índices, datas/JSON e queries de relatório.
- Playwright E2E: login → fluxo assistencial → documento/relatório, desktop e mobile, com seletores acessíveis.
- Visual/PDF: renderização de casos combinatórios e comparação/inspeção de páginas.
- Segurança: abuse cases do threat model, IDOR, mass assignment, upload, XSS, CSRF, rate limits e vazamento em logs/artefatos.

## Critério de evidência

Registre comando, ambiente, resultado e artefato. Um teste não executado é `BLOCKED`; um teste ausente não é “não aplicável” sem justificativa. Evite mocks onde a integração (Policy, banco, storage, fila ou PDF) é justamente o risco.
