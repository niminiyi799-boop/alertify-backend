<?php 

// src/Controller/Api/ApiController.php
namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

abstract class ApiController extends AbstractController
{
    protected function success(array $data = [], int $status = 200): JsonResponse
    {
        return $this->json([
            'status' => 'success',
            'data' => $data
        ], $status);
    }

    protected function error(string $message, int $status = 400): JsonResponse
    {
        return $this->json([
            'status' => 'error',
            'message' => $message
        ], $status);
    }
}
