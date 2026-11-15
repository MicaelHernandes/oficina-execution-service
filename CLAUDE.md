# Convenções — oficina-execution-service

Execution Service da Fase 4 (fila de execução, diagnóstico e reparo),
Laravel 13 com DDD em `src/Execution`, PostgreSQL próprio (database
`execution` no RDS compartilhado), participante da saga orquestrada pelo OS
Service (oficina-api).

## Regras

- **Deploy só no GitHub Actions** (`deploy.yml`, branch `master`): terraform da
  `infra/` → ECR → Secret → Job de bootstrap do banco → `kubectl apply -k
  k8s/overlays/prod` → Job de migrations → rollout.
- Merge só via PR (`homolog` → `master`).
- Segredos nunca no repo: DB em `/oficina/rds/execution`, token das rotas em
  `/oficina/execution/api-token`. As credenciais master do RDS só entram no
  Secret temporário do bootstrap.
- Qualidade: `pint`, `phpstan` (nível 5), `pest` com cobertura mínima de 80%
  (testes de Feature precisam de PostgreSQL).
- Contrato das mensagens espelha `src/Saga/Domain/Messages` do oficina-api:
  mudou um tipo/payload lá, muda aqui (`MessageType`, `Envelope`).
- Actions do GitHub fixadas por SHA (SonarCloud githubactions:S7637).

## Estrutura

- `Application/CommandHandler` — comandos da saga (enqueue/cancel) com inbox.
- `Application/JobWorkflow` — ações do mecânico; início, fim e falha gravam o
  evento no outbox na mesma transação.
- `Domain/Enums/JobStatus` — máquina de estados da execução.
- Rotas sob `/execution/api` (o ALB compartilhado roteia `/execution` para cá).
