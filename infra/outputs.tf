# ---------------------------------------------------------------------------
# Outputs + SSM (/oficina/messaging/execution-service/...): a pipeline lê para
# configurar os pods.
# ---------------------------------------------------------------------------

output "topic_arn" {
  description = "ARN do tópico SNS do Execution."
  value       = aws_sns_topic.events.arn
}

output "queue_url" {
  description = "URL da fila SQS do Execution."
  value       = aws_sqs_queue.inbox.id
}

output "dlq_url" {
  description = "URL da DLQ do Execution."
  value       = aws_sqs_queue.dlq.id
}

output "irsa_role_arn" {
  description = "Role IRSA do ServiceAccount oficina-execution."
  value       = aws_iam_role.irsa.arn
}

resource "aws_ssm_parameter" "topic_arn" {
  name  = "/oficina/messaging/${var.service_name}/topic_arn"
  type  = "String"
  value = aws_sns_topic.events.arn
}

resource "aws_ssm_parameter" "queue_url" {
  name  = "/oficina/messaging/${var.service_name}/queue_url"
  type  = "String"
  value = aws_sqs_queue.inbox.id
}

resource "aws_ssm_parameter" "irsa_role_arn" {
  name  = "/oficina/messaging/${var.service_name}/irsa_role_arn"
  type  = "String"
  value = aws_iam_role.irsa.arn
}
