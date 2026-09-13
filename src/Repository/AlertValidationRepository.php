<?php

namespace App\Repository;

use App\Entity\Alert;
use App\Entity\AlertValidation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AlertValidation>
 */
class AlertValidationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlertValidation::class);
    }

    public function findByUserAndAlert(User $user, Alert $alert): ?AlertValidation
    {
        return $this->findOneBy(['user' => $user, 'alert' => $alert]);
    }
}
