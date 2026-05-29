<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DTOs\NotificationData;
use App\DTOs\NotificationFilterData;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListNotificationsRequest;
use App\Http\Requests\PublishNotificationRequest;
use App\Http\Resources\NotificationResource;
use App\Http\Resources\NotificationSummaryResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $service,
    ) {}

    /**
     * POST /api/v1/notifications
     */
    public function publish(PublishNotificationRequest $request): JsonResponse
    {
        $notification = $this->service->publish(
            NotificationData::fromArray($request->validated()),
        );

        return response()->json([
            'success' => true,
            'message' => 'Notification queued successfully.',
            'data'    => new NotificationResource($notification),
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * GET /api/v1/notifications
     */
    public function index(ListNotificationsRequest $request): AnonymousResourceCollection
    {
        $paginator = $this->service->paginate(
            NotificationFilterData::fromArray($request->validated()),
        );

        return NotificationResource::collection($paginator);
    }

    /**
     * GET /api/v1/notifications/summary
     */
    public function summary(): JsonResponse
    {
        $summary = $this->service->getSummary();

        return response()->json([
            'success' => true,
            'message' => 'Summary retrieved successfully.',
            'data'    => new NotificationSummaryResource($summary),
        ]);
    }
}
