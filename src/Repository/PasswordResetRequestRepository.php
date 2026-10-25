<?php

namespace App\Repository;

use App\Entity\PasswordResetRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordResetRequest>
 */
final class PasswordResetRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetRequest::class);
    }

    /**
     * Looks a link up by the hash of its token; an expired row is simply not found, so the caller
     * has one branch ("not usable") instead of two.
     */
    public function findUsable(string $tokenHash, \DateTimeImmutable $now): ?PasswordResetRequest
    {
        return $this->createQueryBuilder('request')
            ->andWhere('request.tokenHash = :hash')
            ->andWhere('request.expiresAt > :now')
            ->setParameter('hash', $tokenHash)
            ->setParameter('now', $now)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function deleteFor(User $user): void
    {
        $this->createQueryBuilder('request')
            ->delete()
            ->andWhere('request.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    public function save(PasswordResetRequest $request, bool $flush = true): void
    {
        $this->getEntityManager()->persist($request);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(PasswordResetRequest $request, bool $flush = true): void
    {
        $this->getEntityManager()->remove($request);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
