<?php

namespace App\Security;

use App\Entity\User;
use Firebase\JWT\JWT;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Passport\Passport;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Core\Authentication\Passport\Credentials\PasswordCredentials;

class JWTAuthenticator implements AuthenticatorInterface
{
    private $secretKey;

    public function __construct(string $secretKey)
    {
        $this->secretKey = $secretKey;
    }

    /**
     * Cette méthode tente de récupérer et valider le token JWT
     * @param Request $request
     * @return Passport
     * @throws AuthenticationException
     */
    public function authenticate(Request $request): Passport
    {
        // Récupérer le token de l'en-tête Authorization
        $authorizationHeader = $request->headers->get('Authorization');
        
        if (!$authorizationHeader) {
            throw new AuthenticationException('Authorization header not found');
        }

        // Extraire le token Bearer de l'en-tête
        if (!preg_match('/Bearer\s(\S+)/', $authorizationHeader, $matches)) {
            throw new AuthenticationException('Invalid Authorization header format');
        }

        $jwt = $matches[1];

        // Décoder et valider le token JWT
        try {
            $decoded = JWT::decode($jwt, $this->secretKey, ['HS256']);
        } catch (\Exception $e) {
            throw new AuthenticationException('Invalid or expired token');
        }

        // Récupérer l'utilisateur en fonction des données du token
        $username = $decoded->username;

        // Charger l'utilisateur à partir de l'entité User
        $user = $this->loadUserByUsername($username);

        // Créer un Passport pour l'utilisateur authentifié avec les Credentials (ici on passe une chaîne vide car on ne vérifie pas le mot de passe)
        return new Passport($user, new PasswordCredentials(''));
    }

    /**
     * Vérifie si cette authentification doit être utilisée
     * @param Request $request
     * @return bool
     */
    public function supports(Request $request): ?bool
    {
        // Vérifie si l'en-tête Authorization est présent et contient le token
        return $request->headers->has('Authorization');
    }

    /**
     * Charger l'utilisateur depuis la base de données (ou une autre source)
     * @param string $username
     * @return UserInterface
     */
    private function loadUserByUsername(string $username): UserInterface
    {
        // Remplacez cette ligne par votre logique pour récupérer un utilisateur à partir de la base de données
        $userRepository = $this->container->get('doctrine')->getRepository(User::class);
        $user = $userRepository->findOneByUsername($username);

        if (!$user) {
            throw new AuthenticationException('User not found');
        }

        return $user;
    }

    /**
     * Gérer l'échec de l'authentification
     * @param Request $request
     * @param AuthenticationException $exception
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): void
    {
        // Optionnellement, vous pouvez gérer l'échec ici, en renvoyant un message spécifique ou un code HTTP
        $response = new Response(
            json_encode(['message' => $exception->getMessage()]),
            Response::HTTP_UNAUTHORIZED
        );
        $response->headers->set('Content-Type', 'application/json');
        throw new AuthenticationException($response);
    }

    /**
     * Gérer le succès de l'authentification
     * @param Request $request
     * @param TokenInterface $token
     * @param string $firewallName
     * @return void
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }
}
