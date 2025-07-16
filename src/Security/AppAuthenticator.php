<?php

namespace App\Security;

use App\Service\ErpNextService;
use App\Security\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Psr\Log\LoggerInterface;

class AppAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    private ErpNextService $erpNextService;
    private UrlGeneratorInterface $urlGenerator;
    private LoggerInterface $logger;

    public function __construct(ErpNextService $erpNextService, UrlGeneratorInterface $urlGenerator, LoggerInterface $logger)
    {
        $this->erpNextService = $erpNextService;
        $this->urlGenerator = $urlGenerator;
        $this->logger = $logger;
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email', '');
        $password = $request->request->get('password', '');

        $this->logger->info('Authentication attempt', ['email' => $email]);

        // 1. Appelez l'API pour vérifier les identifiants
        $apiUser = $this->erpNextService->login($email, $password);

        // 2. Si l'API dit que les identifiants sont mauvais, levez une exception
        if (null === $apiUser) {
            $this->logger->warning('Authentication failed', ['email' => $email]);
            throw new CustomUserMessageAuthenticationException('Invalid credentials.');
        }

        $this->logger->info('Authentication successful', ['email' => $email]);

        // 3. Si tout va bien, créez le passeport auto-validé
        return new SelfValidatingPassport(new UserBadge($email));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        $this->logger->info('Authentication success, redirecting', [
            'user' => $user->getUserIdentifier(),
            'firewall' => $firewallName
        ]);

        // Vérifier s'il y a une URL cible (par exemple, si l'utilisateur a essayé d'accéder à une page protégée)
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            $this->logger->info('Redirecting to target path', ['path' => $targetPath]);
            return new RedirectResponse($targetPath);
        }
        
        // Forcer la redirection vers la page d'accueil avec une URL absolue
        $homeUrl = '/';
        $this->logger->info('Redirecting to home page', ['url' => $homeUrl]);
        return new RedirectResponse($homeUrl);
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}