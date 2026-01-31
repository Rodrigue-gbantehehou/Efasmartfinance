<?php

namespace App\Controller\Dashboard;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    #[Route('', name: 'app_notifications_index')]
    public function index(NotificationRepository $notificationRepo): Response
    {
        $notifications = $notificationRepo->findBy(
            ['user' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        return $this->render('dashboard/pages/notifications/index.html.twig', [
            'notifications' => $notifications,
        ]);
    }

    #[Route('/{id}/show', name: 'app_notifications_show')]
    public function show(Notification $notification, EntityManagerInterface $em): Response
    {
        if ($notification->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$notification->isRead()) {
            $notification->setRead(true);
            $em->flush();
        }

        return $this->render('dashboard/pages/notifications/show.html.twig', [
            'notification' => $notification,
        ]);
    }

    #[Route('/unread-count', name: 'app_notifications_unread_count', methods: ['GET'])]
    public function unreadCount(NotificationRepository $notificationRepo): JsonResponse
    {
        return $this->json([
            'count' => $notificationRepo->countUnread($this->getUser()),
        ]);
    }

    #[Route('/latest', name: 'app_notifications_latest', methods: ['GET'])]
    public function latest(NotificationRepository $notificationRepo): JsonResponse
    {
        $notifications = $notificationRepo->findLatest($this->getUser());
        
        $data = array_map(function(Notification $n) {
            return [
                'id' => $n->getId(),
                'title' => $n->getTitle(),
                'message' => $n->getMessage(),
                'type' => $n->getType(),
                'createdAt' => $n->getCreatedAt()->format('d/m/Y H:i'),
                'isRead' => $n->isRead(),
                'link' => $n->getLink(),
            ];
        }, $notifications);

        return $this->json($data);
    }

    #[Route('/mark-as-read/{id}', name: 'app_notifications_mark_read', methods: ['POST', 'GET'])]
    public function markAsRead(Notification $notification, EntityManagerInterface $em, Request $request): Response
    {
        if ($notification->getUser() !== $this->getUser()) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['error' => 'Access denied'], 403);
            }
            throw $this->createAccessDeniedException();
        }

        $notification->setRead(true);
        $em->flush();

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }

        return $this->redirectToRoute('app_notifications_index');
    }

    #[Route('/mark-all-read', name: 'app_notifications_mark_all_read', methods: ['POST', 'GET'])]
    public function markAllAsRead(NotificationRepository $notificationRepo, EntityManagerInterface $em, Request $request): Response
    {
        $notifications = $notificationRepo->findBy([
            'user' => $this->getUser(),
            'isRead' => false
        ]);

        foreach ($notifications as $notification) {
            $notification->setRead(true);
        }
        
        $em->flush();

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }

        return $this->redirectToRoute('app_notifications_index');
    }

    #[Route('/delete/{id}', name: 'app_notifications_delete', methods: ['POST', 'GET'])]
    public function delete(Notification $notification, EntityManagerInterface $em, Request $request): Response
    {
        if ($notification->getUser() !== $this->getUser()) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['error' => 'Access denied'], 403);
            }
            throw $this->createAccessDeniedException();
        }

        $em->remove($notification);
        $em->flush();

        if ($request->isXmlHttpRequest()) {
            return $this->json(['success' => true]);
        }

        $this->addFlash('success', 'Notification supprimée.');
        return $this->redirectToRoute('app_notifications_index');
    }
}
