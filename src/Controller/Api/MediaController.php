<?php

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/media', name: 'api_media_')]
class MediaController extends ApiController
{
    /**
     * POST /api/media/upload
     *
     * Accepts a single file under the "file" key.
     * Returns the publicly accessible URL.
     */
    #[Route('/upload', name: 'upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $file = $request->files->get('file');

        if (!$file) {
            return $this->json(['errors' => ['file' => ['Required']]], 422);
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'video/mp4'];
        if (!in_array($file->getMimeType(), $allowed, true)) {
            return $this->json(['errors' => ['file' => ['Unsupported file type']]], 422);
        }

        // 20 MB limit
        if ($file->getSize() > 20 * 1024 * 1024) {
            return $this->json(['errors' => ['file' => ['File size exceeds 20 MB']]], 422);
        }

        $filename = uniqid('upload_', true) . '.' . $file->guessExtension();
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/media';

        $file->move($uploadDir, $filename);

        // In production replace with a CDN URL
        $baseUrl = $this->getParameter('app.base_url');
        $url = rtrim($baseUrl, '/') . '/uploads/media/' . $filename;

        return $this->json(['url' => $url], 201);
    }
}
