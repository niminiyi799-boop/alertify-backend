<?php

namespace App\Controller\Api;

use App\Entity\UserProfile;
use App\Repository\UserProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\JwtProvider;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
class LocationController extends AbstractController
{
    /**
     * GET /api/
     * Health check.
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'status'  => 'success',
            'message' => 'API is working!',
        ]);
    }

    /**
     * POST /api/update-location
     *
     * Saves the authenticated user's location to the database and publishes
     * a real-time update to the Mercure hub.
     *
     * The mobile app should call this endpoint every 5 minutes.
     * Other subscribers (e.g. admin map, nearby-alert logic) receive the
     * update instantly over the persistent Mercure WebSocket/SSE connection.
     *
     * Topic: alertify/location/{userId}
     */
    #[Route('/update-location', name: 'update_location', methods: ['POST'])]
    public function updateLocation(
        Request $request,
        EntityManagerInterface $em,
        UserProfileRepository $repo,
        HubInterface $hub,
    ): JsonResponse {
        $data      = json_decode($request->getContent(), true) ?? [];
        $latitude  = $data['latitude']  ?? null;
        $longitude = $data['longitude'] ?? null;

        if ($latitude === null || $longitude === null) {
            return $this->json([
                'status'  => 'error',
                'message' => 'Missing latitude or longitude',
            ], 400);
        }

        $user   = $this->getUser();
        $userId = $user?->getId() ?? 0;
        $email  = $user?->getUserIdentifier() ?? "user{$userId}@example.com";

        // Upsert UserProfile
        $profile = $repo->findOneByEmail($email);

        if (!$profile) {
            $profile = new UserProfile();
            $profile->setUserEmail($email);
            $profile->setAddress('');
            $em->persist($profile);
        }

        $profile->setLatitude((float) $latitude);
        $profile->setLongitude((float) $longitude);

        // Optional reverse-geocode via OpenCage
        $apiKey = $_ENV['GEOCODING_API_KEY'] ?? '';
        if ($apiKey) {
            $url      = "https://api.opencagedata.com/geocode/v1/json?q={$latitude}+{$longitude}&key={$apiKey}&language=en";
            $response = @file_get_contents($url);
            if ($response) {
                $respData = json_decode($response, true);
                $address  = $respData['results'][0]['formatted'] ?? '';
                $profile->setAddress($address);
            }
        }

        $em->flush();

        // Publish real-time update to Mercure hub
        // Topic is user-scoped so only subscribers for this user receive it
        $topic = "alertify/location/{$userId}";

        $update = new Update(
            $topic,
            json_encode([
                'user_id'   => $userId,
                'latitude'  => $profile->getLatitude(),
                'longitude' => $profile->getLongitude(),
                'address'   => $profile->getAddress(),
                'updated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            ]),
            // private: true means only subscribers with a valid JWT for this topic receive it
            private: true,
        );

        $hub->publish($update);

        return $this->json([
            'status' => 'success',
            'data'   => [
                'latitude'  => $profile->getLatitude(),
                'longitude' => $profile->getLongitude(),
                'address'   => $profile->getAddress(),
            ],
        ]);
    }

    /**
     * GET /api/location/subscribe-token
     *
     * Issues a short-lived Mercure subscriber JWT to the authenticated mobile
     * client. The client uses this token to open the Mercure EventSource
     * connection and subscribe to its own location topic.
     *
     * The token is valid for 6 hours (covers multiple 5-minute polling cycles).
     */
    #[Route('/location/subscribe-token', name: 'location_subscribe_token', methods: ['GET'])]
    public function subscribeToken(): JsonResponse
    {
        $user   = $this->getUser();
        $userId = $user?->getId() ?? 0;

        $secret = $_ENV['MERCURE_JWT_SECRET'] ?? '';

        if (!$secret) {
            return $this->json(['message' => 'Mercure not configured'], 503);
        }

        // Build a JWT manually — lightweight, no extra dependency needed
        $header  = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode([
            'mercure' => [
                'subscribe' => ["alertify/location/{$userId}"],
            ],
            'exp' => time() + (6 * 3600), // 6 hours
        ]));

        $signature = base64_encode(
            hash_hmac('sha256', "{$header}.{$payload}", $secret, true)
        );

        $token      = "{$header}.{$payload}.{$signature}";
        $publicUrl  = rtrim($_ENV['MERCURE_PUBLIC_URL'] ?? '', '/');

        return $this->json([
            'token'      => $token,
            'mercure_url' => $publicUrl . '/.well-known/mercure',
            'topic'      => "alertify/location/{$userId}",
        ]);
    }
}
