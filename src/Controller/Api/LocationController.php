<?php

namespace App\Controller\Api;

use App\Entity\UserProfile;
use App\Repository\UserProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use WebSocket\Client;

#[Route('/api', name: 'api_')] // Prefix all routes in this controller with /api
class LocationController extends AbstractController
{
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'status' => 'success',
            'message' => 'API is working!'
        ]);
    }

    #[Route('/update-location', name: 'update_location', methods: ['GET', 'POST'])]
    public function updateLocation(
        Request $request,
        EntityManagerInterface $em,
        UserProfileRepository $repo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $latitude = $data['latitude'] ?? null;
        $longitude = $data['longitude'] ?? null;

        if ($latitude === null || $longitude === null) {
            return $this->json([
                'status' => 'error',
                'message' => 'Missing latitude or longitude'
            ], 400);
        }

        // Fetch user profile (replace 2 with authenticated user ID)
        $userId = $this->getUser()?->getId() ?? 2;
        $profile = $repo->find($userId);

        if (!$profile) {
            $profile = new UserProfile();
            $profile->setUserEmail("user{$userId}@example.com"); // set dummy email for now
            $profile->setLatitude($latitude);
            $profile->setLongitude($longitude);
            $profile->setAddress(''); // initial empty address
            $em->persist($profile);
        } else {
            $profile->setLatitude($latitude);
            $profile->setLongitude($longitude);
        }

        // Optional: Reverse Geocode using OpenCage
        $apiKey = $_ENV['GEOCODING_API_KEY'] ?? '';
        if ($apiKey) {
            $url = "https://api.opencagedata.com/geocode/v1/json?q={$latitude}+{$longitude}&key={$apiKey}&language=en";
            $response = @file_get_contents($url);
            if ($response) {
                $respData = json_decode($response, true);
                $address = $respData['results'][0]['formatted'] ?? '';
                $profile->setAddress($address);
            }
        }

        $em->flush();

        // Optional WebSocket push
        try {
            $ws = new Client("ws://localhost:8080");
            $ws->send(json_encode([
                'user_id' => $userId,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'address' => $profile->getAddress()
            ]));
            $ws->close();
        } catch (\Exception $e) {
            // Optional: log error
        }

        return $this->json([
            'status' => 'success',
            'data' => [
                'latitude' => $profile->getLatitude(),
                'longitude' => $profile->getLongitude(),
                'address' => $profile->getAddress(),
            ]
        ]);
    }
}
