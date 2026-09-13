<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\UserNotificationSettings;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth', name: 'api_auth_')]
class AuthController extends ApiController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly ValidatorInterface $validator,
    ) {}

    /**
     * POST /api/auth/register
     */
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $email    = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $confirm  = $data['password_confirmation'] ?? '';

        $errors = [];

        if (!$email) {
            $errors['email'][] = 'Required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Must be a valid email address';
        } elseif ($this->userRepository->findByEmail($email)) {
            $errors['email'][] = 'Already taken';
        }

        if (!$password) {
            $errors['password'][] = 'Required';
        } elseif (strlen($password) < 8) {
            $errors['password'][] = 'Must be at least 8 characters';
        }

        if ($confirm && $confirm !== $password) {
            $errors['password_confirmation'][] = "Passwords don't match";
        }

        if (!empty($errors)) {
            return $this->json(['errors' => $errors], 422);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        // Create default notification settings
        $settings = new UserNotificationSettings();
        $settings->setUser($user);
        $user->setNotificationSettings($settings);

        $this->em->persist($user);
        $this->em->persist($settings);
        $this->em->flush();

        $token = $this->jwtManager->create($user);

        return $this->json([
            'token' => $token,
            'user'  => $user->toArray(),
        ], 201);
    }

    /**
     * GET /api/auth/me — validate token and return current user
     */
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['message' => 'Unauthorized'], 401);
        }

        return $this->json(['user' => $user->toArray()]);
    }

    /**
     * POST /api/auth/logout
     * With stateless JWT there is no server-side session to invalidate.
     * The client should discard the token. This endpoint exists for API
     * consistency and can be extended with a token denylist later.
     */
    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        return $this->json(['message' => 'Logged out successfully']);
    }

    /**
     * POST /api/auth/forgot-password
     */
    #[Route('/forgot-password', name: 'forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request, MailerInterface $mailer): JsonResponse
    {
        $data  = json_decode($request->getContent(), true) ?? [];
        $email = trim($data['email'] ?? '');

        if (!$email) {
            return $this->json(['errors' => ['email' => ['Required']]], 422);
        }

        $user = $this->userRepository->findByEmail($email);

        // Always return success to avoid email enumeration
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $user->setPasswordResetToken($token);
            $user->setPasswordResetTokenExpiresAt(new \DateTimeImmutable('+1 hour'));
            $this->em->flush();

            $resetUrl = $_ENV['FRONTEND_URL'] ?? 'http://localhost:3000';

            $message = (new Email())
                ->from($_ENV['MAILER_FROM'] ?? 'no-reply@alertify.app')
                ->to($email)
                ->subject('Reset your Alertify password')
                ->html(sprintf(
                    '<p>Click the link below to reset your password (expires in 1 hour):</p>
                     <p><a href="%s/auth/reset-password?token=%s">Reset Password</a></p>',
                    $resetUrl,
                    $token
                ));

            try {
                $mailer->send($message);
            } catch (\Exception) {
                // Log but don't expose mailer errors to the client
            }
        }

        return $this->json(['message' => 'Reset email sent']);
    }

    /**
     * POST /api/auth/reset-password
     */
    #[Route('/reset-password', name: 'reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $data     = json_decode($request->getContent(), true) ?? [];
        $token    = $data['token'] ?? '';
        $password = $data['password'] ?? '';

        $errors = [];
        if (!$token)    $errors['token'][]    = 'Required';
        if (!$password) $errors['password'][] = 'Required';
        elseif (strlen($password) < 8) $errors['password'][] = 'Must be at least 8 characters';

        if (!empty($errors)) {
            return $this->json(['errors' => $errors], 422);
        }

        $user = $this->userRepository->findByResetToken($token);

        if (!$user || $user->getPasswordResetTokenExpiresAt() < new \DateTimeImmutable()) {
            return $this->json(['errors' => ['token' => ['Invalid or expired token']]], 422);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setPasswordResetToken(null);
        $user->setPasswordResetTokenExpiresAt(null);
        $this->em->flush();

        return $this->json(['message' => 'Password reset successfully']);
    }

    /**
     * POST /api/auth/social — OAuth token exchange (stub for Google / Facebook)
     */
    #[Route('/social', name: 'social', methods: ['POST'])]
    public function social(Request $request): JsonResponse
    {
        $data        = json_decode($request->getContent(), true) ?? [];
        $provider    = $data['provider'] ?? '';
        $accessToken = $data['access_token'] ?? '';

        if (!in_array($provider, ['google', 'facebook'], true) || !$accessToken) {
            return $this->json(['errors' => ['provider' => ['Unsupported provider or missing access_token']]], 422);
        }

        // TODO: Verify $accessToken with the respective provider's API,
        //       extract email + name, upsert a User, and return a JWT.
        return $this->json(['message' => 'Social auth not yet implemented'], 501);
    }
}
