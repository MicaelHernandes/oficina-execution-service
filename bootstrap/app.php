<?php

use App\Http\Middleware\ForceJsonResponse;
use App\Http\Middleware\PrometheusMetrics;
use App\Http\Middleware\RequestId;
use App\Http\Middleware\StaffToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        // O ALB compartilhado roteia app.codefive.com.br/execution/* para este
        // serviço, então as rotas da API já nascem com o prefixo.
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'execution/api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(RequestId::class);
        $middleware->append(ForceJsonResponse::class);
        $middleware->append(PrometheusMetrics::class);
        $middleware->alias(['staff' => StaffToken::class]);
        // Atrás do ALB (TLS termina nele): confia no X-Forwarded-* para gerar
        // URLs com https.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
