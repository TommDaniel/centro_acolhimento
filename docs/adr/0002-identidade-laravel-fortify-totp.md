# ADR 0002 — Identidade local com Laravel Fortify e MFA TOTP

- Status: aceito; implementação pendente
- Data: 12/08/2026
- Tarefas: `DEC-07`, `IAM-01`, `IAM-02`, `SEG-01`
- Referência: [Laravel Fortify 13.x](https://laravel.com/docs/13.x/fortify)

## Contexto

O sistema trata dados restritos e sensíveis de crianças e adolescentes. Autenticação precisa ser individual, forte, revogável e auditável, mas não substitui Policies e escopo por recurso no backend.

O cenário aprovado para os próximos dois anos é uma única aplicação, sem SSO ou segunda aplicação prevista, com quatro usuários iniciais e orçamento total aproximado de R$ 60 por mês. Cadastro público não é necessário. Operar um provedor adicional aumentaria superfície de falha, atualização, backup, monitoramento e resposta a incidente sem benefício atual comprovado.

`IAM-01` concluiu esta comparação documental. Nenhuma PoC foi executada e nenhuma implementação é declarada por este ADR.

## Opções comparadas

| Opção | Benefícios | Custos e riscos no cenário atual | Decisão |
|---|---|---|---|
| Keycloak autogerido | OIDC, SSO, federação e políticas centralizadas | Novo serviço crítico, banco, patches, backup/restore, monitoramento e contingência; nó único continuaria sem HA | Não adotar agora |
| IdP gerenciado | Reduz parte da operação e facilita federação | Custo recorrente, dependência externa, DPA/localização/suboperadores, saída e possível inadequação ao orçamento | Não adotar agora |
| Laravel Fortify | Integração nativa ao monólito, menor número de componentes e fluxo TOTP documentado | A aplicação assume lifecycle, auditoria e controles de recuperação adicionais; os endpoints padrão de QR/recovery não satisfazem sozinhos esta política | Adotar e restringir/estender |

## Decisão

Usar Laravel Fortify como backend de autenticação, mantendo a interface Inertia/React e toda autorização no Laravel. Cada pessoa terá uma conta individual; e-mail é atributo mutável e canal operacional, nunca conta coletiva nem fonte exclusiva de permissão.

O cadastro público será desativado. Contas serão criadas ou convidadas somente por administrador autorizado. Toda conta humana atual ou futura segue `pendente_mfa -> ativa`: enquanto pendente, só pode enrolar/confirmar MFA ou encerrar a sessão; nenhuma rota, prop ou ação assistencial fica acessível. A confirmação TOTP promove a conta a ativa. Inativação ou remoção do fator revoga imediatamente sessões, tokens/recallers residuais e acessos derivados; a desativação local falha fechado.

Esse estado durável da conta é diferente do nível transitório de uma sessão. Uma conta `ativa` que validou somente a senha permanece em sessão intermediária `password_only`; ela ainda não está autorizada como MFA. Só pode acessar TOTP/recovery challenge e logout. Middleware/Policies no backend negam recurso assistencial, QR/segredo de enrolamento, listagem/rotação de recovery codes, remoção/re-enrollment de MFA e administração, inclusive por requisição direta.

A sessão intermediária tem timeout de 5 minutos e rate limit próprios. Na conclusão válida, o servidor rotaciona o session ID e marca o novo nível `mfa_verified`. Remember-me/recaller fica integralmente desativado na fase inicial, antes e depois de MFA: campo, endpoint ou payload `remember` é ignorado/rejeitado, nenhum remember token/cookie persistente é emitido e todo login novo exige senha + TOTP. Recovery code de uso único só substitui TOTP no fluxo explícito de contingência.

O cookie autenticado é apenas de sessão do navegador, sem `Expires`/`Max-Age` persistente. `mfa_verified` expira após 15 minutos de inatividade e, independentemente de atividade, após duração absoluta de 8 horas. Timeout, falha ou limite excedido invalida o estado intermediário sem criar credencial persistente nem reaproveitar seu ID.

Respostas Inertia mantêm o estado do histórico criptografado. Todo encerramento ou revogação recria uma sessão anônima e elimina as chaves desse histórico; páginas autenticadas candidatas ao bfcache desmontam o conteúdo antes do snapshot e recarregam ao serem restauradas, revalidando sessão e autorização antes de voltar a apresentar qualquer dado. Isso inclui logout, timeout, inativação e divergência do hash de senha após troca remota em outro dispositivo.

### MFA e recuperação

- MFA é obrigatório para toda conta humana atual ou futura, incluindo os quatro usuários iniciais, por TOTP compatível com RFC 6238 e aplicativo autenticador compatível, como FreeOTP.
- Passkeys ficam desativadas por decisão do produto. Habilitá-las exige nova decisão e testes específicos; não é fallback implícito.
- Segredo TOTP e QR são exibidos somente durante o enrolamento pendente e até a confirmação válida, protegidos em repouso e nunca incluídos em logs, analytics ou trilha de auditoria. O endpoint padrão documentado de QR será desabilitado ou restringido após confirmação; acesso posterior falha fechado. Re-enrollment exige autenticação reforçada, autorização, auditoria, rotação do segredo e revogação das sessões anteriores.
- Enrolamento é serializado e versionado por usuário. Cada geração pendente recebe versão/identificador não reutilizável; lock/constraint garante uma única geração corrente. Apenas essa geração fornece QR e aceita confirmação, e criar uma nova invalida imediatamente o QR/segredo pendente anterior.
- Em primeiro enrolamento, confirmação da geração corrente promove atomicamente `pendente_mfa -> ativa`. Em re-enrollment, o fator atual continua corrente enquanto a nova geração está pendente; somente a confirmação válida executa cutover atômico para o novo fator, invalida geração/QR anterior e revoga todas as sessões anteriores. Falha antes do commit preserva o fator atual; falha após o commit não pode restaurá-lo ou deixar dois fatores correntes.
- Geração e confirmação simultâneas são serializadas: exatamente uma geração permanece corrente e no máximo uma confirmação/cutover é aceita. Confirmação com versão ou QR antigo falha fechado.
- Para conta `ativa`, visualizar QR de re-enrollment, gerar/regenerar recovery codes, remover MFA ou iniciar/concluir re-enrollment exige step-up realizado nos últimos 5 minutos com senha e fator TOTP corrente. O marcador de step-up é separado de `mfa_verified`; uma sessão MFA antiga não basta e o step-up não é transferível entre sessões.
- No primeiro enrolamento de `pendente_mfa`, ainda não há fator corrente: exige senha recém-validada na sessão restrita e confirmação pelo novo TOTP. Se o fator foi perdido, somente recuperação administrada com sua verificação reforçada, atribuição e duplo controle substitui o requisito do fator corrente.
- O Fortify documenta endpoints para obter e regenerar recovery codes; isso não estabelece armazenamento individualmente hasheado, exibição única e consumo concorrente exigidos pelo produto. `IAM-02` deve substituir ou estender o fluxo padrão, não presumir que ativar Fortify cumpre a política.
- Cada conjunto e código será um registro próprio; cada código será hasheado individualmente e nunca poderá ser listado em claro depois da entrega inicial. Uma constraint parcial/estratégia equivalente garante no máximo um conjunto ativo por usuário.
- Consumo ocorre em transação: localizar/verificar o código do conjunto ativo, bloquear o registro aplicável e atualizar condicionalmente apenas se não usado e não revogado. Concorrência com o mesmo código produz exatamente um sucesso. Rotação cria novo conjunto e invalida atomicamente todo o anterior; replay, conjunto revogado e falha parcial falham fechado.
- E-mail OTP não é MFA primário porque compartilha o canal com reset de senha. Se usado em recuperação, será apenas parte de fluxo controlado com verificação reforçada, TTL curto, token de uso único, rate limit, resposta sem enumeração de contas, auditoria e revogação de sessões e credenciais de recuperação anteriores.
- Links de reset gerados pela aplicação não carregam bearer no path/query: token e e-mail ficam no fragmento local, removido do histórico antes do bootstrap Inertia mesmo quando um redirect autenticado herda o fragmento, e seguem por POST same-origin com CSRF para sessão criptografada e temporária. Fragmentos parciais sensíveis são descartados e âncoras comuns permanecem intactas. A rota GET legada com token no caminho permanece ausente; a borda ainda precisa sanitizar URLs arbitrárias conforme `OPS-01`.
- Reset de senha, sozinho, não desativa nem substitui MFA.
- O endpoint padrão de auto-desativação do segundo fator será bloqueado. Remoção ocorre somente por recuperação/re-enrollment autorizado e auditado, revoga todas as sessões/códigos anteriores e retorna a conta a `pendente_mfa` até nova confirmação.
- Recuperação assistida exige uma administradora individual autorizada e uma segunda pessoa previamente designada, com as duas aprovações atribuíveis registradas e motivo. A segunda pessoa apenas atesta essa recuperação e não recebe acesso administrativo amplo. Sem designação prévia ou sem ambas as aprovações, o fluxo falha fechado. A conclusão revoga sessões/códigos anteriores e exige novo vínculo TOTP; nenhuma das aprovadoras conhece ou define o segundo fator do usuário.

### Lifecycle, contingência e controles

Convite, ativação, alteração de função/vínculo, inativação, recuperação e contingência serão autorizados no backend e auditados de forma append-only. A auditoria registra ator, ação, conta-alvo, resultado, horário UTC e correlation ID, sem senha, segredo, QR, token ou código.

Login, desafio TOTP, enrolamento, confirmação, recuperação, reset e operações administrativas terão rate limit e respostas que evitem enumeração. Sessões usarão cookies `Secure`, `HttpOnly` e `SameSite` apropriado, sem recaller, com rotação após MFA e revogação nos eventos definidos. Middleware/Policies permitem enrolamento/confirmar/logout apenas à conta `pendente_mfa`; para conta `ativa` em `password_only`, permitem somente challenge/logout. Eventos suspeitos geram alerta sem copiar payload sensível.

Para impedir ataque sustentado entre janelas curtas, challenge e confirmação mantêm strikes consecutivos duráveis por conta no PostgreSQL, sob o mesmo lock/transação da validação TOTP. Falhas 5–9 aplicam cooldown de 5 minutos, 10–14 aplicam 15 minutos e 15 ou mais aplicam o teto de 60 minutos por tentativa, sem bloqueio permanente. Cache, logout, novo login, troca de IP, reset de senha e expiração do cooldown não zeram strikes; somente um segundo fator válido reinicia o contador. Rate limits curtos por conta, IP e sessão permanecem como camadas complementares. Cada início de cooldown é auditado e emite `warning` técnico estruturado sem PII, segredo ou código; entrega proativa e teste do canal de alerta dependem de `OPS-01` e não são presumidos por este ADR.

Logout global, inativação, reset de senha, recuperação, cutover de re-enrollment e perda declarada de dispositivo incrementam/revogam a geração de acesso e encerram sessões e tokens derivados em todos os dispositivos. Logout local encerra a sessão corrente; a ação explícita de logout global cobre as demais. Restauração e retries não podem reativar geração anterior.

`Break-glass` não é conta compartilhada. A ativação exige identidade atribuível, menor escopo, TTL curto, duplo controle, alerta imediato, auditoria append-only, rotação/revogação após uso e exercício periódico revisado. Se o controle mínimo não estiver disponível, a contingência falha fechado e segue procedimento operacional fora do sistema.

## Evidência exigida na implementação

- conta humana não enrolada permanece `pendente_mfa` e recebe negação em toda rota fora de enrolamento/confirmar/logout;
- conta `ativa` com sessão `password_only` recebe negação direta em recurso assistencial, QR, recovery codes, remoção/re-enrollment e admin; timeout/rate limit encerram a sessão intermediária e o ID muda ao obter `mfa_verified`;
- campo/endpoint/payload `remember` é ignorado/negado; cookie recaller fabricado, antigo, roubado ou revogado não autentica nem cria `password_only`/`mfa_verified`; novo login sempre exige senha + segundo fator;
- cookie de browser-session, idle de 15 minutos e absoluto de 8 horas expiram nos limites aprovados;
- QR/segredo funciona até confirmação, falha depois e re-enrollment só ocorre pelo fluxo autorizado;
- operações MFA de conta ativa negam `mfa_verified` antigo e exigem step-up recente de senha + fator corrente; primeiro enrolamento e recuperação administrada exercitam suas exceções controladas;
- duas gerações/confirmações simultâneas deixam exatamente uma geração corrente e no máximo um cutover; QR/versão antiga falha, fator anterior permanece até confirmação e falhas parciais nunca deixam zero ou dois fatores correntes;
- endpoint `DELETE`/auto-desativação de MFA falha, e remoção controlada revoga a sessão anterior;
- duas requisições concorrentes com o mesmo recovery code resultam em um sucesso e uma negação; replay e conjunto rotacionado também são negados;
- logout global, inativação, reset, recuperação, re-enrollment e perda de dispositivo invalidam sessões/tokens de todos os dispositivos, inclusive cópias antigas/restauradas;
- logs e auditoria não contêm senha, segredo, QR, TOTP ou recovery code.

## Consequências

- Menor custo e superfície operacional para o cenário atual, sem introduzir IdP ou protocolo distribuído.
- Desativar recaller aumenta a frequência de login, mas reduz a vida útil de credencial roubada e mantém a exigência senha + TOTP em cada nova sessão.
- A disponibilidade da autenticação acompanha a aplicação; restauração de usuários, fatores e recovery codes hasheados passa a integrar backup/restore do PostgreSQL. Versões de `APP_KEY` e chaves necessárias a segredos TOTP vigentes ficam em cofre/escrow segregado conforme o ADR 0003, nunca no repositório.
- A equipe precisa implementar e testar lifecycle, revogação, recovery e auditoria; Fortify não torna esses processos corretos automaticamente.
- Não há SSO, federação corporativa ou identidade centralizada entre aplicações nesta fase.
- Este ADR não autoriza dados reais nem declara `IAM-02` ou `SEG-01` concluídos.

## Gatilhos de saída e migração

Reabrir `DEC-07` somente quando ao menos um fator for comprovado:

1. segunda aplicação ou requisito real de SSO;
2. múltiplas organizações ou diretório corporativo/federação;
3. custo operacional total de OIDC/Keycloak ou IdP gerenciado comprovadamente menor que manter Fortify com os mesmos controles e SLOs.

Antes da migração, inventariar identificadores locais, papéis, vínculos, sessões e fatores; escolher `iss` + `sub` como vínculo externo estável; executar piloto somente com contas sintéticas; manter coexistência temporal controlada e trilha de reconciliação; e aprovar DPA, localização, disponibilidade, backup, incidentes e plano de saída do IdP. Senhas, segredos TOTP e recovery codes não serão exportados em formato reversível; usuários reenrolam MFA no novo provedor.

## Rollback

Durante a implantação de `IAM-02`, rollback significa retornar à última versão compatível da aplicação e do schema por roll-forward seguro, preservando contas, fatores e auditoria. Não reabrir cadastro público, remover MFA, restaurar recovery codes em claro ou reativar sessões como atalho. Se Fortify se mostrar inviável antes do go-live, manter o sistema restrito a dados sintéticos, registrar nova ADR e selecionar outra opção pelos gatilhos e plano de migração acima.
