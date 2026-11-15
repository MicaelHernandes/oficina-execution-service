variable "region" {
  description = "Região AWS."
  type        = string
  default     = "us-east-1"
}

variable "project" {
  description = "Prefixo do projeto."
  type        = string
  default     = "oficina"
}

variable "service_name" {
  description = "Nome do microsserviço (fila, role e parâmetros SSM)."
  type        = string
  default     = "execution-service"
}

variable "k8s_namespace" {
  description = "Namespace do Deployment no EKS."
  type        = string
  default     = "oficina"
}

variable "k8s_service_account" {
  description = "ServiceAccount dos pods do Execution (IRSA)."
  type        = string
  default     = "oficina-execution"
}

variable "max_receive_count" {
  description = "Tentativas antes de a mensagem ir para a DLQ."
  type        = number
  default     = 5
}
