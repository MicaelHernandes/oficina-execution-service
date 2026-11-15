<?php

namespace Domain\Execution\Presentation\Controllers;

use App\Http\Controllers\Controller;
use Domain\Execution\Application\JobWorkflow;
use Domain\Execution\Domain\Entities\ExecutionJob;
use Domain\Execution\Domain\Enums\JobStatus;
use Domain\Execution\Domain\Exceptions\DomainException;
use Domain\Execution\Domain\Exceptions\NotFoundException;
use Domain\Execution\Domain\Repositories\ExecutionJobRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Execução', description: 'Fila de execução e ações do mecânico (Bearer EXECUTION_API_TOKEN)')]
class JobController extends Controller
{
    public function __construct(
        private readonly ExecutionJobRepositoryInterface $jobs,
        private readonly JobWorkflow $workflow,
    ) {}

    #[OA\Get(
        path: '/execution/api/jobs',
        summary: 'Fila de execução: maior prioridade e mais antigas primeiro. Sem filtro, só as ativas (na fila, em diagnóstico, em reparo).',
        security: [['staffToken' => []]],
        tags: ['Execução'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['queued', 'diagnosing', 'repairing', 'finished', 'failed', 'cancelled'])),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [new OA\Response(response: 200, description: 'Lista paginada'), new OA\Response(response: 401, description: 'Token inválido')]
    )]
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(JobStatus::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = $this->jobs->queue($validated['status'] ?? null, (int) ($validated['per_page'] ?? 15));

        return response()->json([
            'data' => array_map(fn (ExecutionJob $j) => $this->present($j), $page->items()),
            'meta' => ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    #[OA\Get(
        path: '/execution/api/jobs/{id}',
        summary: 'Execução com a linha do tempo (diagnóstico, reparo, anotações)',
        security: [['staffToken' => []]],
        tags: ['Execução'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Execução'), new OA\Response(response: 404, description: 'Não encontrada')]
    )]
    public function show(int $id): JsonResponse
    {
        $job = $this->jobs->findById($id);

        return $job
            ? response()->json(['data' => $this->present($job, withTimeline: true)])
            : response()->json(['message' => "Execução '{$id}' não encontrada."], 404);
    }

    #[OA\Post(
        path: '/execution/api/jobs/{id}/start-diagnosis',
        summary: 'Mecânico assume a OS: queued → diagnosing. Publica execution.job.started (OS vai para "em execução").',
        security: [['staffToken' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [
            new OA\Property(property: 'mechanic_user_id', type: 'integer', nullable: true),
            new OA\Property(property: 'note', type: 'string', nullable: true),
        ])),
        tags: ['Execução'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Em diagnóstico'), new OA\Response(response: 404, description: 'Não encontrada'), new OA\Response(response: 422, description: 'Transição inválida')]
    )]
    public function startDiagnosis(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'mechanic_user_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->act(fn () => $this->workflow->startDiagnosis($id, $data['mechanic_user_id'] ?? null, $data['note'] ?? null));
    }

    #[OA\Post(
        path: '/execution/api/jobs/{id}/start-repair',
        summary: 'Diagnóstico concluído: diagnosing → repairing',
        security: [['staffToken' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [new OA\Property(property: 'note', type: 'string', nullable: true)])),
        tags: ['Execução'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Em reparo'), new OA\Response(response: 404, description: 'Não encontrada'), new OA\Response(response: 422, description: 'Transição inválida')]
    )]
    public function startRepair(Request $request, int $id): JsonResponse
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;

        return $this->act(fn () => $this->workflow->startRepair($id, $note));
    }

    #[OA\Post(
        path: '/execution/api/jobs/{id}/notes',
        summary: 'Anotação na linha do tempo, sem mudar de etapa',
        security: [['staffToken' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['note'], properties: [new OA\Property(property: 'note', type: 'string')])),
        tags: ['Execução'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Anotação registrada'), new OA\Response(response: 404, description: 'Não encontrada')]
    )]
    public function addNote(Request $request, int $id): JsonResponse
    {
        $note = $request->validate(['note' => ['required', 'string', 'max:2000']])['note'];

        return $this->act(fn () => $this->workflow->addNote($id, $note));
    }

    #[OA\Post(
        path: '/execution/api/jobs/{id}/finish',
        summary: 'Reparo concluído: repairing → finished. Publica execution.job.finished (OS em "execução finalizada").',
        security: [['staffToken' => []]],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(properties: [new OA\Property(property: 'note', type: 'string', nullable: true)])),
        tags: ['Execução'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Finalizada'), new OA\Response(response: 404, description: 'Não encontrada'), new OA\Response(response: 422, description: 'Transição inválida')]
    )]
    public function finish(Request $request, int $id): JsonResponse
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:2000']])['note'] ?? null;

        return $this->act(fn () => $this->workflow->finish($id, $note));
    }

    #[OA\Post(
        path: '/execution/api/jobs/{id}/fail',
        summary: 'Execução não pôde ser concluída. Publica execution.job.failed: a saga devolve as peças, estorna o pagamento e cancela a OS.',
        security: [['staffToken' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['reason'], properties: [new OA\Property(property: 'reason', type: 'string', example: 'Elevador hidráulico quebrado')])),
        tags: ['Execução'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Falhou'), new OA\Response(response: 404, description: 'Não encontrada'), new OA\Response(response: 422, description: 'Transição inválida')]
    )]
    public function fail(Request $request, int $id): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:2000']])['reason'];

        return $this->act(fn () => $this->workflow->fail($id, $reason));
    }

    private function act(callable $action): JsonResponse
    {
        try {
            return response()->json(['data' => $this->present($action(), withTimeline: true)]);
        } catch (NotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** @return array<string, mixed> */
    private function present(ExecutionJob $job, bool $withTimeline = false): array
    {
        $data = [
            'id' => $job->getId(),
            'os_id' => $job->getOsId(),
            'saga_id' => $job->getSagaId(),
            'correlation_id' => $job->getCorrelationId(),
            'status' => $job->getStatus()->value,
            'status_label' => $job->getStatus()->label(),
            'priority' => $job->getPriority(),
            'vehicle_id' => $job->getVehicleId(),
            'mechanic_user_id' => $job->getMechanicUserId(),
            'complaint' => $job->getComplaint(),
            'services' => $job->getServices(),
            'parts' => $job->getParts(),
            'failure_reason' => $job->getFailureReason(),
            'enqueued_at' => $job->getEnqueuedAt()->format(DATE_ATOM),
            'started_at' => $job->getStartedAt()?->format(DATE_ATOM),
            'finished_at' => $job->getFinishedAt()?->format(DATE_ATOM),
        ];

        if ($withTimeline) {
            $data['timeline'] = $this->jobs->timeline((int) $job->getId());
        }

        return $data;
    }
}
