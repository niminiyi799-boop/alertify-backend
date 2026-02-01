<?php

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Doctrine\DBAL\Connection;

#[Route('/api')]
class NearbyMailController extends AbstractController
{
    #[Route('/notify-nearby', methods: ['POST'])]
    public function notify(
        Request $request,
        Connection $conn,
        MailerInterface $mailer
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $currentLat = $data['latitude'];
        $currentLng = $data['longitude'];
        $currentUserId = $this->getUser()?->getId() ?? 2;

        $sql = "
            SELECT id, user_email, latitude, longitude, address,
            (6371 * acos(
                cos(radians(:lat)) * cos(radians(latitude)) *
                cos(radians(longitude) - radians(:lng)) +
                sin(radians(:lat)) * sin(radians(latitude))
            )) AS distance
            FROM user_profile
            WHERE id != :userId
            ORDER BY distance ASC
            LIMIT 5
        ";

        $users = $conn->fetchAllAssociative($sql, [
            'lat' => $currentLat,
            'lng' => $currentLng,
            'userId' => $currentUserId,
        ]);

        if (!$users) {
            return new JsonResponse(['message' => 'No nearby users found'], 404);
        }

        foreach ($users as $user) {
            $email = (new Email())
                ->from('no-reply@yourapp.com')
                ->to($user['user_email'])
                ->subject('Message from Nearby User')
                ->html(
                    "<p>Hello!</p>
                     <p>A user near <strong>{$user['address']}</strong> just came online.</p>"
                );

            $mailer->send($email);
        }

        return new JsonResponse([
            'status' => 'emails sent',
            'recipients' => array_column($users, 'user_email')
        ]);
    }
}
