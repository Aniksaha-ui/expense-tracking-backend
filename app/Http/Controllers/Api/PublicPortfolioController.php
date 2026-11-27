<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ContactMessages\StorePublicContactMessageRequest;
use App\Services\ContactMessageService;
use App\Services\PublicPortfolioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PublicPortfolioController extends Controller
{
    public function __construct(
        private readonly PublicPortfolioService $publicPortfolioService,
        private readonly ContactMessageService $contactMessageService,
    ) {}

    public function profile(): JsonResponse
    {
        try {
            return $this->successResponse($this->publicPortfolioService->getProfile());
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to fetch portfolio profile.', status: 500);
        }
    }

    public function home(): JsonResponse
    {
        try {
            return $this->successResponse($this->publicPortfolioService->getHome());
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to fetch portfolio home data.', status: 500);
        }
    }

    public function experiences(): JsonResponse
    {
        try {
            return $this->successResponse($this->publicPortfolioService->listExperiences());
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to fetch experiences.', status: 500);
        }
    }

    public function projects(): JsonResponse
    {
        try {
            return $this->successResponse($this->publicPortfolioService->listProjects());
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to fetch projects.', status: 500);
        }
    }

    public function project(int $projectId): JsonResponse
    {
        try {
            return $this->successResponse($this->publicPortfolioService->getProject($projectId));
        } catch (RuntimeException $exception) {
            return $this->errorResponse($exception->getMessage(), status: 404);
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to fetch project.', status: 500);
        }
    }

    public function publications(): JsonResponse
    {
        try {
            return $this->successResponse($this->publicPortfolioService->listPublications());
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to fetch publications.', status: 500);
        }
    }

    public function testimonials(): JsonResponse
    {
        try {
            return $this->successResponse($this->publicPortfolioService->listTestimonials());
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to fetch testimonials.', status: 500);
        }
    }

    public function trainings(): JsonResponse
    {
        try {
            return $this->successResponse($this->publicPortfolioService->listTrainings());
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to fetch trainings.', status: 500);
        }
    }

    public function services(): JsonResponse
    {
        try {
            return $this->successResponse($this->publicPortfolioService->listServices());
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to fetch services.', status: 500);
        }
    }

    public function achievements(Request $request): JsonResponse
    {
        try {
            $type = trim((string) $request->query('type', ''));

            return $this->successResponse(
                $this->publicPortfolioService->listAchievements(null, $type !== '' ? $type : null)
            );
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to fetch achievements.', status: 500);
        }
    }

    public function storeContact(StorePublicContactMessageRequest $request): JsonResponse
    {
        try {
            if ($request->isSpam()) {
                return $this->successResponse([], 'Thank you! Your message has been sent.', 201);
            }

            $validated = $request->validated();

            $this->contactMessageService->createMessage([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'subject' => $validated['subject'] ?? null,
                'message' => $validated['message'],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->successResponse([], 'Thank you! Your message has been sent.', 201);
        } catch (\Throwable $exception) {
            return $this->errorResponse('Unable to send your message right now.', status: 500);
        }
    }
}
