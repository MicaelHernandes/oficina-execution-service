<?php

namespace App\Http\Controllers;

use Domain\Execution\Domain\Enums\JobStatus;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;

/**
 * /metrics (formato texto do Prometheus): latência por rota (middleware),
 * execuções por status, tempo médio de execução e outbox pendente,
 * calculados no momento do scrape.
 */
class MetricsController extends Controller
{
    public function __construct(private readonly CollectorRegistry $registry) {}

    public function __invoke(): Response
    {
        $ns = config('metrics.namespace');

        try {
            $jobs = $this->registry->getOrRegisterGauge($ns, 'jobs', 'Execuções por status', ['status']);
            $totals = DB::table('execution_jobs')
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');

            foreach (JobStatus::cases() as $status) {
                $jobs->set((float) ($totals[$status->value] ?? 0), [$status->value]);
            }

            $avg = DB::table('execution_jobs')
                ->where('status', JobStatus::Finished->value)
                ->selectRaw('avg(extract(epoch from (finished_at - started_at))) as avg_seconds')
                ->value('avg_seconds');

            if ($avg !== null) {
                $this->registry
                    ->getOrRegisterGauge($ns, 'avg_execution_seconds', 'Tempo médio de execução (diagnóstico ao fim), em segundos', [])
                    ->set((float) $avg, []);
            }

            $this->registry
                ->getOrRegisterGauge($ns, 'outbox_pending_messages', 'Mensagens do outbox ainda não publicadas', [])
                ->set((float) DB::table('outbox_messages')->whereNull('published_at')->count(), []);
        } catch (\Throwable $e) {
            // Sem banco disponível: segue com as métricas de requisição.
        }

        $body = (new RenderTextFormat)->render($this->registry->getMetricFamilySamples());

        return response($body, 200, ['Content-Type' => RenderTextFormat::MIME_TYPE]);
    }
}
