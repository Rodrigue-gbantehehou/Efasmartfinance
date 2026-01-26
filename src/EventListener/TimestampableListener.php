<?php

namespace App\EventListener;

use App\Entity\SecuritySettings;
use App\Entity\UserSettings;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
class TimestampableListener
{
    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        
        if ($entity instanceof UserSettings || $entity instanceof SecuritySettings) {
            $now = new \DateTimeImmutable();
            
            if ($entity->getCreatedAt() === null) {
                $entity->setCreatedAt($now);
            }
            
            $entity->setUpdatedAt($now);
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        
        if ($entity instanceof UserSettings || $entity instanceof SecuritySettings) {
            $entity->setUpdatedAt(new \DateTimeImmutable());
        }
    }
}
