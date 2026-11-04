<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Rotas do mecânico: exige Bearer igual a EXECUTION_API_TOKEN. Sem token configurado, fecha a rota. */
class StaffToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('execution.api_token');

        if ($expected === '' || ! hash_equals($expected, (string) $request->bearerToken())) {
            return response()->json(['message' => 'Não autorizado.'], 401);
        }

        return $next($request);
    }
}
