<?php

namespace App\Security;

use App\Entity\User;
use App\Service\PinAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class Authenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private EntityManagerInterface $entityManager,
        private PinAuthService $pinAuthService
    ) {}

    public function authenticate(Request $request): Passport
    {
        $identifier = $request->getPayload()->getString('identifier');
        $password = $request->getPayload()->getString('password');

        $request->getSession()->set(
            SecurityRequestAttributes::LAST_USERNAME,
            $identifier
        );

        return new Passport(
            new UserBadge($identifier, function ($identifier) {
                return $this->loadUser($identifier);
            }),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $request->getPayload()->getString('_csrf_token')),
            ]
        );
    }

    private function loadUser(string $identifier): User
    {
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = $this->entityManager
                ->getRepository(User::class)
                ->findOneBy(['email' => $identifier]);
        } else {
            $user = $this->entityManager
                ->getRepository(User::class)
                ->findOneBy(['uuid' => (int) $identifier]);
        }

        if (!$user) {
            throw new CustomUserMessageAuthenticationException('Identifiants invalides.');
        }

        return $user;
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName
    ): ?Response {
        /** @var User $user */
        $user = $token->getUser();

        // Mettre à jour la date de dernière connexion
        $user->setLastLoginAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        // Reset PIN verification status for every new connection
        $session = $request->getSession();
        $session->remove('pin_verified');
        $session->remove('pin_verified_at');

        // Check if user has PIN configured
        if (!$user->hasPinAuth()) {
            return new RedirectResponse($this->urlGenerator->generate('pin_setup'));
        }

        // Always redirect to PIN verification after password check
        // Store target path for after PIN verification
        if ($targetPath = $this->getTargetPath($session, $firewallName)) {
            // Sanitize targetPath: ignore if it's an API route or special route
            if (str_contains($targetPath, '/api/') || str_contains($targetPath, '/check-session')) {
                $session->set('_security.main.target_path', $this->urlGenerator->generate('app_tontines_index'));
            } else {
                $session->set('_security.main.target_path', $targetPath);
            }
        } else {
            $session->set('_security.main.target_path', $this->urlGenerator->generate('app_tontines_index'));
        }
        
        return new RedirectResponse($this->urlGenerator->generate('pin_verify'));

        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_tontines_index'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
