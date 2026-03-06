<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\LogPublisher;
use App\Service\LogValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

class LogIngestionController extends AbstractController
{
    public function __construct(
        private readonly LogValidator  $validator,
        private readonly LogPublisher  $publisher,
    ) {}

    /**
     * @throws ExceptionInterface
     */
    #[Route('/api/logs/ingest', name: 'log_ingest', methods: ['POST'])]
    public function ingest(Request $request): JsonResponse
    {
        $contentType = $request->headers->get('Content-Type', '');
        if (!str_contains($contentType, 'application/json')) {
            return $this->json(
                ['status' => 'error', 'message' => 'Content-Type must be application/json.'],
                Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
            );
        }

        $payload = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json(
                ['status' => 'error', 'message' => 'Invalid JSON: ' . json_last_error_msg()],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $result = $this->validator->validateBatch($payload ?? []);

        if (!$result['valid']) {
            return $this->json(
                ['status' => 'error', 'errors' => $result['errors']],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $batchId = 'batch_' . str_replace('-', '', Uuid::v4()->toRfc4122());

        $this->publisher->publishBatch($result['entries'], $batchId);

        return $this->json([
            'status'     => 'accepted',
            'batch_id'   => $batchId,
            'logs_count' => count($result['entries']),
        ], Response::HTTP_ACCEPTED);
    }
}
