<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Oficina — Execution Service',
    version: '1.0.0',
    description: 'Fila de execução das OS: diagnóstico, reparo e finalização. Os comandos da saga chegam pela fila SQS; estas rotas são usadas pelo mecânico.'
)]
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: 'Servidor principal'
)]
#[OA\SecurityScheme(
    securityScheme: 'staffToken',
    type: 'http',
    scheme: 'bearer',
    description: 'EXECUTION_API_TOKEN'
)]
final class OpenApiSpec {}
