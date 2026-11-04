# ---------------------------------------------------------------------------
# Execution Service.
#
# - Banco: database `execution` + usuário próprio na instância RDS
#   compartilhada (credencial em /oficina/rds/execution, criada pelo
#   oficina-db-infra; o CREATE ROLE/DATABASE roda no Job de bootstrap).
# - Fila SQS `oficina-execution-service` + DLQ, assinando o tópico do OS
#   Service só com os comandos `execution.*` (filter policy).
# - Tópico SNS `oficina-execution-events`: eventos para o orquestrador, com a
#   assinatura na fila do OS Service.
# - Role IRSA do ServiceAccount `oficina-execution`.
# - Token Bearer das rotas do mecânico.
# ---------------------------------------------------------------------------

resource "aws_sns_topic" "events" {
  name = local.topic_name
  # Criptografia em repouso com a chave gerenciada da AWS (sem custo de CMK).
  kms_master_key_id = "alias/aws/sns"
}

resource "aws_sqs_queue" "dlq" {
  name                      = "${local.queue_name}-dlq"
  message_retention_seconds = 1209600 # 14 dias para investigar/reprocessar
}

resource "aws_sqs_queue" "inbox" {
  name                       = local.queue_name
  visibility_timeout_seconds = 60
  receive_wait_time_seconds  = 20
  message_retention_seconds  = 345600 # 4 dias

  redrive_policy = jsonencode({
    deadLetterTargetArn = aws_sqs_queue.dlq.arn
    maxReceiveCount     = var.max_receive_count
  })
}

resource "aws_sqs_queue_redrive_allow_policy" "dlq" {
  queue_url = aws_sqs_queue.dlq.id

  redrive_allow_policy = jsonencode({
    redrivePermission = "byQueue"
    sourceQueueArns   = [aws_sqs_queue.inbox.arn]
  })
}

# Só o tópico do OS Service pode entregar na fila.
data "aws_iam_policy_document" "inbox" {
  statement {
    effect    = "Allow"
    actions   = ["sqs:SendMessage"]
    resources = [aws_sqs_queue.inbox.arn]

    principals {
      type        = "Service"
      identifiers = ["sns.amazonaws.com"]
    }

    condition {
      test     = "ArnEquals"
      variable = "aws:SourceArn"
      values   = [data.aws_ssm_parameter.os_topic_arn.value]
    }
  }
}

resource "aws_sqs_queue_policy" "inbox" {
  queue_url = aws_sqs_queue.inbox.id
  policy    = data.aws_iam_policy_document.inbox.json
}

resource "aws_sns_topic_subscription" "os_commands" {
  topic_arn            = data.aws_ssm_parameter.os_topic_arn.value
  protocol             = "sqs"
  endpoint             = aws_sqs_queue.inbox.arn
  raw_message_delivery = true

  # Do tópico do orquestrador, só os comandos destinados ao Execution.
  filter_policy = jsonencode({
    type = [{ prefix = "execution." }]
  })

  depends_on = [aws_sqs_queue_policy.inbox]
}

# Eventos do Execution para o orquestrador: este repositório cria a assinatura do
# próprio tópico na fila do OS Service (a política da fila já aceita os
# tópicos oficina-*-events), então não há ordem de deploy entre os repos.
resource "aws_sns_topic_subscription" "os_events" {
  topic_arn            = aws_sns_topic.events.arn
  protocol             = "sqs"
  endpoint             = data.aws_ssm_parameter.os_queue_arn.value
  raw_message_delivery = true
}

# --- IRSA -----------------------------------------------------------------
data "aws_iam_policy_document" "irsa_trust" {
  statement {
    effect  = "Allow"
    actions = ["sts:AssumeRoleWithWebIdentity"]

    principals {
      type        = "Federated"
      identifiers = [local.oidc_provider_arn]
    }

    condition {
      test     = "StringEquals"
      variable = "${local.oidc_issuer}:sub"
      values   = ["system:serviceaccount:${var.k8s_namespace}:${var.k8s_service_account}"]
    }

    condition {
      test     = "StringEquals"
      variable = "${local.oidc_issuer}:aud"
      values   = ["sts.amazonaws.com"]
    }
  }
}

resource "aws_iam_role" "irsa" {
  name               = "${var.project}-${var.service_name}-irsa"
  assume_role_policy = data.aws_iam_policy_document.irsa_trust.json
}

data "aws_iam_policy_document" "irsa" {
  statement {
    effect    = "Allow"
    actions   = ["sns:Publish"]
    resources = [aws_sns_topic.events.arn]
  }

  statement {
    effect = "Allow"
    actions = [
      "sqs:ReceiveMessage",
      "sqs:DeleteMessage",
      "sqs:ChangeMessageVisibility",
      "sqs:GetQueueAttributes",
    ]
    resources = [aws_sqs_queue.inbox.arn]
  }
}

resource "aws_iam_role_policy" "irsa" {
  name   = "${var.project}-${var.service_name}-messaging"
  role   = aws_iam_role.irsa.id
  policy = data.aws_iam_policy_document.irsa.json
}

# --- Token das rotas do mecânico ---------------------------------------------
resource "random_password" "api_token" {
  length  = 40
  special = false
}

resource "aws_secretsmanager_secret" "api_token" {
  name = "/oficina/execution/api-token"
}

resource "aws_secretsmanager_secret_version" "api_token" {
  secret_id     = aws_secretsmanager_secret.api_token.id
  secret_string = random_password.api_token.result
}
