<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class UniqueCodeGenerator
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function generateUserCode(): int
    {
        do {
            $code = random_int(1000000, 9999999);
            $exists = $this->entityManager->getRepository(User::class)
                ->findOneBy(['uuid' => $code]);
        } while ($exists);

        return $code;
    }
}
