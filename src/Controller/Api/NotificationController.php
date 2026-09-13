<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/notifications', name: 'api_notifications_')]
class NotificationController extends ApiController
{
    public function __construct(
        private readonly NotificationRepository $notificationRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * GET /api/notifications
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $notifications = $this->notificationRepository->findForUser($currentUser);

        return $this->json([
            'notifications' => array_map(fn($n) => $n->toArray(), $notifications),
        ]);
    }

    /**
     * GET /api/notifications/unread-count
     */
    #[Route('/unread-count', name: 'unread_count', methods: ['GET'])]
    public function unreadCount(): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $count = $this->notificationRepository->countUnread($currentUser);

        return $this->json(['count' => $count]);
    }

    /**
     * POST /api/notifications/mark-read
     */
    #[Route('/mark-read', name: 'mark_read', methods: ['POST'])]
    public function markRead(): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $this->notificationRepository->markAllReadForUser($currentUser);

        return $this->json(['message' => 'All notifications marked as read']);
    }
}
