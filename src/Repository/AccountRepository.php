<?php

namespace App\Repository;

use App\Entity\Account;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * AccountRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    /**
     * Find all active user
     *
     * @return User[]
     */
    public function findAllActive()
    {
        return $this->findBy(['isActive' => 1]);
    }

    /**
     * Find a user by email
     *
     * @param String $email
     *
     * @return null|Object|User
     */
    public function findByEmail(String $email)
    {
        return $this->findOneBy(['email' => $email]);
    }

    /**
     * Update last login datetime
     *
     * @param String $email
     */
    public function setLastLogin(String $email)
    {
        $user = $this->findByEmail($email);
        if ($user !== null) {
            $user->setLastLoginAt(new \DateTime());
            $this->_em->persist($user);
            $this->_em->flush();
        }

    }
}
