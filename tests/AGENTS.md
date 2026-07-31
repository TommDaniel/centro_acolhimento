# Regras de QA e testes

- Leia `../AGENTS.md`, `../RTK.md` e `$centro-acolhimento-domain`, especialmente `references/testing-matrix.md`.
- Use somente fixtures sintéticas marcadas como fictícias. Nunca copie banco, screenshot ou anexo de produção.
- Testes são determinísticos, independentes de ordem e relógio real; congele tempo quando a regra depender de data.
- Para cada ação protegida, cubra permitido e negado, inclusive troca manual de IDs (IDOR), unidade/setor alheio e dado sensível.
- Prove transições válidas e inválidas, histórico preservado, idempotência, concorrência, limites e falha parcial.
- E2E seleciona por papel, label ou `data-testid`; não use `waitForTimeout` nem seletores CSS frágeis.
- Capture trace/screenshot/vídeo apenas em falha ou retry e assegure que artefatos não contenham dados reais/segredos.
- Ao criar um teste de regressão, confirme que ele falha sem a correção quando isso puder ser feito com segurança.
- QA pode mudar testes e configuração de teste, mas não código de produção. Defeitos devem ser relatados ao implementador.
