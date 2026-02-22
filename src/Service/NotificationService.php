<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $router
    ) {}

    /**
     * Ajoute une notification générale pour un utilisateur
     */
    public function addNotification(
        User $user,
        string $title,
        string $message,
        string $type = 'info',
        ?string $source = 'Efa Smart Finance',
        ?string $link = null
    ): Notification {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setType($type);
        $notification->setSource($source);
        $notification->setLink($link);
        $notification->setCreatedAt(new \DateTimeImmutable());
        $notification->setRead(false);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    /**
     * Envoie une notification de bienvenue après l'inscription
     */
    public function sendWelcomeNotification(User $user): void
    {
        $this->addNotification(
            $user,
            'Bienvenue chez Efa Smart Finance !',
            "Nous sommes ravis de vous compter parmi nous. Votre compte est prêt, vous pouvez dès à présent créer une tontine ou effectuer un dépôt.",
            'success',
            'Équipe Accueil',
            $this->router->generate('app_tontines_index')
        );
    }

    /**
     * Notification lors d'un paiement réussi
     */
    public function sendPaymentNotification(User $user, float $amount, string $tontineName): void
    {
        $this->addNotification(
            $user,
            'Paiement confirmé',
            "Votre paiement de " . number_format($amount, 0, ',', ' ') . " FCFA pour la tontine '" . $tontineName . "' a été validé avec succès.",
            'success',
            'Service Comptabilité'
        );
    }

    /**
     * Notification lors d'une demande de retrait
     */
    public function sendWithdrawalRequestNotification(User $user, float $amount): void
    {
        $this->addNotification(
            $user,
            'Demande de retrait enregistrée',
            "Votre demande de retrait de " . number_format($amount, 0, ',', ' ') . " FCFA a bien été reçue et est en cours de traitement.",
            'info',
            'Service Client',
            $this->router->generate('app_withdrawals_index')
        );
    }

    /**
     * Notification lors de l'approbation d'un retrait
     */
    public function sendWithdrawalApprovedNotification(User $user, float $amount): void
    {
        $this->addNotification(
            $user,
            'Retrait approuvé',
            "Bonne nouvelle ! Votre retrait de " . number_format($amount, 0, ',', ' ') . " FCFA a été approuvé et les fonds sont en cours de transfert.",
            'success',
            'Service Caisse',
            $this->router->generate('app_withdrawals_index')
        );
    }

    /**
     * Notification lors du rejet d'un retrait
     */
    public function sendWithdrawalRejectedNotification(User $user, float $amount, string $reason): void
    {
        $this->addNotification(
            $user,
            'Demande de retrait rejetée',
            "Votre demande de retrait de " . number_format($amount, 0, ',', ' ') . " FCFA a été rejetée pour la raison suivante : " . $reason,
            'danger',
            'Service Caisse',
            $this->router->generate('app_withdrawals_index')
        );
    }

    /**
     * Notification lors de l'approbation du compte
     */
    public function sendAccountApprovedNotification(User $user): void
    {
        $this->addNotification(
            $user,
            'Compte vérifié !',
            "Félicitations ! Votre compte a été vérifié avec succès. Vous avez maintenant accès à toutes les fonctionnalités premium de Efa Smart Finance.",
            'success',
            'Équipe Sécurité',
            $this->router->generate('app_compte_verification')
        );
    }

    /**
     * Notification lors du rejet du compte
     */
    public function sendAccountRejectedNotification(User $user, string $reason): void
    {
        $this->addNotification(
            $user,
            'Demande de vérification rejetée',
            "Votre demande de vérification a malheureusement été rejetée pour la raison suivante : " . $reason . ". Veuillez soumettre à nouveau vos documents.",
            'danger',
            'Équipe Sécurité',
            $this->router->generate('app_compte_verification')
        );
    }
}
