<?php

namespace App\Repository;

use App\Entity\Alert;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Alert>
 */
class AlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Alert::class);
    }

    /**
     * Find alerts within a radius (km) using the Haversine formula,
     * optionally filtered by category.
     *
     * @return Alert[]
     */
    public function findNearby(float $lat, float $lng, float $radiusKm, ?string $category = null): array
    {
        // Use raw SQL for the Haversine distance calculation
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT a.id
            FROM alert a
            WHERE (
                6371 * acos(
                    cos(radians(:lat)) * cos(radians(a.latitude)) *
                    cos(radians(a.longitude) - radians(:lng)) +
                    sin(radians(:lat)) * sin(radians(a.latitude))
                )
            ) <= :radius
        ';

        $params = ['lat' => $lat, 'lng' => $lng, 'radius' => $radiusKm];

        if ($category !== null && $category !== 'All') {
            $sql .= ' AND a.category = :category';
            $params['category'] = $category;
        }

        $sql .= ' ORDER BY a.created_at DESC LIMIT 200';

        $ids = array_column($conn->fetchAllAssociative($sql, $params), 'id');

        if (empty($ids)) {
            return [];
        }

        return $this->createQueryBuilder('a')
            ->andWhere('a.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
