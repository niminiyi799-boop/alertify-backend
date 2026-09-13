<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\UserNotificationSettings;
use App\Repository\UserNotificationSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/user', name: 'api_user_')]
class UserController extends ApiController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserNotificationSettingsRepository $settingsRepository,
    ) {}

    /**
     * PATCH /api/user/profile
     */
    #[Route('/profile', name: 'profile_update', methods: ['PATCH'])]
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $data = json_decode($request->getContent(), true) ?? [];

        $errors = [];

        if (isset($data['name'])) {
            $currentUser->setName(trim($data['name']) ?: null);
        }

        if (isset($data['email'])) {
            $newEmail = trim($data['email']);
            if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                $errors['email'][] = 'Must be a valid email address';
            } elseif ($newEmail !== $currentUser->getEmail()) {
                // Check if email is taken by another user
                $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $newEmail]);
                if ($existing) {
                    $errors['email'][] = 'Already taken';
                } else {
                    $currentUser->setEmail($newEmail);
                }
            }
        }

        if (!empty($errors)) {
            return $this->json(['errors' => $errors], 422);
        }

        if (isset($data['emergency_contact'])) {
            $currentUser->setEmergencyContact(trim($data['emergency_contact']) ?: null);
        }

        $this->em->flush();

        return $this->json(['user' => $currentUser->toArray()]);
    }

    /**
     * GET /api/user/notification-settings
     */
    #[Route('/notification-settings', name: 'notification_settings_get', methods: ['GET'])]
    public function getNotificationSettings(): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $settings = $this->getOrCreateSettings($currentUser);

        return $this->json(['settings' => $settings->toArray()]);
    }

    /**
     * PATCH /api/user/notification-settings
     */
    #[Route('/notification-settings', name: 'notification_settings_update', methods: ['PATCH'])]
    public function updateNotificationSettings(Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $data     = json_decode($request->getContent(), true) ?? [];
        $settings = $this->getOrCreateSettings($currentUser);

        if (isset($data['alert_radius_km'])) {
            $radius = (int) $data['alert_radius_km'];
            if (in_array($radius, [1, 5, 10, 25, 50], true)) {
                $settings->setAlertRadiusKm($radius);
            }
        }

        if (isset($data['night_mode'])) {
            $settings->setNightMode((bool) $data['night_mode']);
        }

        if (isset($data['enabled_categories']) && is_array($data['enabled_categories'])) {
            $valid = array_filter(
                $data['enabled_categories'],
                fn($c) => in_array($c, \App\Entity\Alert::CATEGORIES, true)
            );
            $settings->setEnabledCategories(array_values($valid));
        }

        $this->em->flush();

        return $this->json(['settings' => $settings->toArray()]);
    }

    private function getOrCreateSettings(User $user): UserNotificationSettings
    {
        $settings = $user->getNotificationSettings();

        if (!$settings) {
            $settings = new UserNotificationSettings();
            $settings->setUser($user);
            $user->setNotificationSettings($settings);
            $this->em->persist($settings);
            $this->em->flush();
        }

        return $settings;
    }
}
