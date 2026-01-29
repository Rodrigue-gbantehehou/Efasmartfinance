<?php

namespace App\Service;

use App\Entity\Facture;
use Doctrine\ORM\EntityManagerInterface;

class NumerotationFactureService
{
    private $entityManager;
    private $prefixe;
    private $longueurNumero;

    public function __construct(EntityManagerInterface $entityManager, string $prefixe = 'FACT', int $longueurNumero = 6)
    {
        $this->entityManager = $entityManager;
        $this->prefixe = $prefixe;
        $this->longueurNumero = $longueurNumero;
    }

    public function genererNumero(): string
    {
        $annee = date('Y');
        $derniereFacture = $this->entityManager->getRepository(Facture::class)
            ->createQueryBuilder('f')
            ->where('f.numero LIKE :prefixe')
            ->setParameter('prefixe', $this->prefixe . '-' . $annee . '-%')
            ->orderBy('f.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $numero = 1;
        if ($derniereFacture) {
            // Extraire le numéro de la dernière facture et incrémenter
            $dernierNumero = substr(
                $derniereFacture->getNumero(),
                strlen($this->prefixe) + 6 // Longueur du préfixe + année + 2 tirets
            );
            $numero = (int)$dernierNumero + 1;
        }

        return sprintf(
            '%s-%s-%s',
            $this->prefixe,
            $annee,
            str_pad($numero, $this->longueurNumero, '0', STR_PAD_LEFT)
        );
    }
}
