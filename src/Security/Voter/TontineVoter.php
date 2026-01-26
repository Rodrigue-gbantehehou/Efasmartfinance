<?php

namespace App\Security\Voter;

use App\Entity\Tontine;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Bundle\SecurityBundle\Security;

class TontineVoter extends Voter
{
    public const WITHDRAW = 'WITHDRAW';

    public function __construct(
        private Security $security
    ) {}

    protected function supports(string $attribute, $subject): bool
    {
        return $attribute === self::WITHDRAW && $subject instanceof Tontine;
    }

    protected function voteOnAttribute(string $attribute, $tontine, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // L'utilisateur doit être connecté
        if (!$user instanceof User) {
            return false;
        }

        // Vérifier que la tontine appartient bien à l'utilisateur
        if ($tontine->getUtilisateur() !== $user) {
            return false;
        }

        // Vérifier qu'il y a un montant disponible pour retrait
        $montantTotalDisponible = $tontine->getTotalPay();
        if ($montantTotalDisponible <= 0) {
            return false;
        }

        // Vérifier que la tontine est active ou complétée
        if (!in_array($tontine->getStatut(), ['active', 'completed'])) {
            return false;
        }

        // Calculer le montant total dû pour la tontine
        $totalAPayer = $tontine->getTotalPoints() * $tontine->getAmountPerPoint();

        // Calculer le montant total déjà retiré
        $totalRetire = 0;
        foreach ($tontine->getWithdrawals() as $withdrawal) {
            if ($withdrawal->getStatut() === 'completed') {
                $totalRetire += $withdrawal->getAmount();
            }
        }

        $montantRestantADebourser = $totalAPayer - $totalRetire;

        // Vérifier qu'il reste un montant à débourser
        if ($montantRestantADebourser <= 0) {
            return false;
        }

        // Vérifier qu'il n'y a pas déjà une demande de retrait en cours
        $hasPendingWithdrawal = false;
        foreach ($tontine->getWithdrawals() as $withdrawal) {
            if ($withdrawal->getStatut() === 'pending') {
                $hasPendingWithdrawal = true;
                break;
            }
        }

        return !$hasPendingWithdrawal;
    }
}
