<?php

namespace App\Controller\Api;

use App\Entity\Alert;
use App\Entity\AlertValidation;
use App\Entity\User;
use App\Repository\AlertRepository;
use App\Repository\AlertValidationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/alerts', name: 'api_alerts_')]
class AlertController extends ApiController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AlertRepository $alertRepository,
        private readonly AlertValidationRepository $validationRepository,
    ) {}

    /**
     * GET /api/alerts
     *
     * Query params:
     *   lat      float   (required)
     *   lng      float   (required)
     *   radius   float   default 10 km
     *   category string  optional — one of Alert::CATEGORIES, or "All" (no filter)
     *   filter   string  alias for category (frontend uses both names)
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $lat      = $request->query->get('lat');
        $lng      = $request->query->get('lng');
        $radius   = (float) ($request->query->get('radius', 10));
        // Accept both ?category= and ?filter= (frontend sends ?filter=)
        $category = $request->query->get('category') ?? $request->query->get('filter');

        if ($lat === null || $lng === null) {
            return $this->json(['errors' => ['lat' => ['Required'], 'lng' => ['Required']]], 422);
        }

        // Normalise "All" to null so no category filter is applied
        if ($category === 'All') {
            $category = null;
        }

        $alerts = $this->alertRepository->findNearby(
            (float) $lat,
            (float) $lng,
            $radius,
            $category
        );

        return $this->json([
            'alerts' => array_map(fn(Alert $a) => $a->toArray(), $alerts),
        ]);
    }

    /**
     * POST /api/alerts
     *
     * Accepts multipart/form-data so that media files can be attached.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        // Support both JSON body and multipart form-data
        $isJson = str_contains($request->headers->get('Content-Type', ''), 'application/json');
        if ($isJson) {
            $data = json_decode($request->getContent(), true) ?? [];
        } else {
            $data = $request->request->all();
        }

        $category    = trim($data['category'] ?? '');
        $description = trim($data['description'] ?? '');
        $latitude    = isset($data['latitude'])  ? (float) $data['latitude']  : null;
        $longitude   = isset($data['longitude']) ? (float) $data['longitude'] : null;
        $visibility  = strtolower($data['visibility'] ?? Alert::VISIBILITY_PUBLIC);

        $errors = [];
        if (!$category)  $errors['category'][]  = 'Required';
        if ($latitude  === null) $errors['latitude'][]  = 'Required';
        if ($longitude === null) $errors['longitude'][] = 'Required';
        if (!$description) $errors['description'][] = 'Required';
        if (!in_array($visibility, [Alert::VISIBILITY_PUBLIC, Alert::VISIBILITY_PACK], true)) {
            $errors['visibility'][] = 'Must be "public" or "pack"';
        }

        if (!empty($errors)) {
            return $this->json(['errors' => $errors], 422);
        }

        $alert = new Alert();
        $alert->setUser($currentUser);
        $alert->setCategory($category);
        $alert->setDescription($description);
        $alert->setLatitude($latitude);
        $alert->setLongitude($longitude);
        $alert->setVisibility($visibility);

        // Handle uploaded media files (multipart)
        foreach ($request->files->get('media', []) as $file) {
            // Store in public/uploads/media; in production swap for S3 / CDN
            $filename = uniqid('media_', true) . '.' . $file->guessExtension();
            $file->move($this->getParameter('kernel.project_dir') . '/public/uploads/media', $filename);
            $alert->addMediaUrl('/uploads/media/' . $filename);
        }

        $this->em->persist($alert);

        // Increment contribution count
        $currentUser->incrementContributionCount();

        $this->em->flush();

        return $this->json(['alert' => $alert->toArray()], 201);
    }

    /**
     * GET /api/alerts/{id}
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $alert = $this->alertRepository->find($id);

        if (!$alert) {
            return $this->json(['message' => 'Alert not found'], 404);
        }

        return $this->json(['alert' => $alert->toArray()]);
    }

    /**
     * POST /api/alerts/{id}/validate
     */
    #[Route('/{id}/validate', name: 'validate', methods: ['POST'])]
    public function validate(string $id): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $alert = $this->alertRepository->find($id);
        if (!$alert) {
            return $this->json(['message' => 'Alert not found'], 404);
        }

        if ($alert->getUser()->getId() === $currentUser->getId()) {
            return $this->json(['message' => 'You cannot validate your own alert'], 422);
        }

        $existing = $this->validationRepository->findByUserAndAlert($currentUser, $alert);
        if ($existing) {
            return $this->json(['message' => 'Already validated'], 422);
        }

        $validation = new AlertValidation();
        $validation->setUser($currentUser);
        $validation->setAlert($alert);

        $alert->incrementValidationCount();
        // Reward the alert author with trust score
        $alert->getUser()->setTrustScore($alert->getUser()->getTrustScore() + 1);

        $this->em->persist($validation);
        $this->em->flush();

        return $this->json(['alert' => $alert->toArray()]);
    }

    /**
     * DELETE /api/alerts/{id}
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $alert = $this->alertRepository->find($id);
        if (!$alert) {
            return $this->json(['message' => 'Alert not found'], 404);
        }

        if ($alert->getUser()->getId() !== $currentUser->getId()) {
            return $this->json(['message' => 'Forbidden'], 403);
        }

        $this->em->remove($alert);
        $this->em->flush();

        return $this->json(null, 204);
    }
}
