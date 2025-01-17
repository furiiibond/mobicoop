<?php

namespace App\Security;

use App\Action\Event\ActionEvent;
use App\Action\Repository\ActionRepository;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;

class TokenAuthenticator extends AbstractGuardAuthenticator
{
    private $em;
    private $jwtTokenManagerInterface;
    private $refreshTokenManager;
    private $params;
    private $actionRepository;
    private $eventDispatcher;
    private $specialReturnMessage;

    public function __construct(EntityManagerInterface $em, JWTTokenManagerInterface $jwtTokenManagerInterface, RefreshTokenManagerInterface $refreshTokenManager, ParameterBagInterface $params, ActionRepository $actionRepository, EventDispatcherInterface $eventDispatcher)
    {
        $this->em = $em;
        $this->jwtTokenManagerInterface = $jwtTokenManagerInterface;
        $this->refreshTokenManager = $refreshTokenManager;
        $this->params = $params;
        $this->actionRepository = $actionRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->specialReturnMessage = null;
    }

    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning `false` will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request)
    {
        return true;
    }

    /**
     * Called on every request. Return whatever credentials you want to
     * be passed to getUser() as $credentials.
     */
    public function getCredentials(Request $request)
    {
        $decodeRequest = json_decode($request->getContent());
        if (isset($decodeRequest->emailToken) && !empty($decodeRequest->emailToken)) {
            $credentials['email'] = $decodeRequest->email;
            $credentials['emailToken'] = $decodeRequest->emailToken;
        } elseif (isset($decodeRequest->passwordToken) && !empty($decodeRequest->passwordToken)) {
            $credentials['passwordToken'] = $decodeRequest->passwordToken;
        } else {
            return false;
        }

        return $credentials;
    }

    /** We search an user by :
     * Case 1 : the pair email + emailToken
     * Case 2 : the pws reset token.
     *
     * @param mixed $credentials
     */
    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        if (null === $credentials) {
            // The token header was empty, authentication fails with HTTP Status
            // Code 401 "Unauthorized"
            return null;
        }
        if (isset($credentials['emailToken'])) {
            if (!$user = $this->em->getRepository(User::class)->findOneBy(['email' => $credentials['email'], 'emailToken' => $credentials['emailToken']])) {
                $user = $this->em->getRepository(User::class)->findOneBy(['email' => $credentials['email']]);
                if (!is_null($user) && !is_null($user->getValidatedDate())) {
                    $this->specialReturnMessage = new JsonResponse(['message' => 'User already validated']);
                } else {
                    return null;
                }
            }
        } else {
            $user = $this->em->getRepository(User::class)->findOneBy(['pwdToken' => $credentials['passwordToken']]);
        }

        // if a User is returned, checkCredentials() is called
        return $user;
    }

    public function checkCredentials($credentials, UserInterface $user)
    {
        // Check credentials - e.g. make sure the password is valid.
        // In case of an API token, no credential check is needed.

        // Return `true` to cause authentication success
        return true;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        /**
         * @var User $user
         */
        $user = $token->getUser();

        if (!is_null($this->specialReturnMessage)) {
            return $this->specialReturnMessage;
        }

        // Time for valid refresh token, define in gesdinet_jwt_refresh_token, careful to let this value in secondes
        $addTime = 'PT'.$this->params->get('gesdinet_jwt_refresh_token.ttl').'S';

        $now = new \DateTime('now');
        $now->add(new \DateInterval($addTime));

        $refreshToken = $this->refreshTokenManager->create();
        $refreshToken->setUsername($user->getRefresh());
        $refreshToken->setRefreshToken();
        $refreshToken->setValid($now);

        $this->refreshTokenManager->save($refreshToken);

        // Email token is not null = we activate account from email -> we set token at null and the validated date at today
        if (null != $user->getEmailToken()) {
            $user->setValidatedDate(new \DateTime('now'));
            $user->setEmailToken(null);

            //  we dispatch the gamification event associated
            $action = $this->actionRepository->findOneBy(['name' => 'user_mail_validation']);
            $actionEvent = new ActionEvent($action, $user);
            $this->eventDispatcher->dispatch($actionEvent, ActionEvent::NAME);

        // Password token is not null = we reset password -> we set password token and the asking reset date at null
        } elseif (null != $user->getPwdToken()) {
            $user->setPwdToken(null);
            $user->setPwdTokenDate(null);
        }
        $this->em->persist($user);
        $this->em->flush();

        // on success, let the request continue
        return new JsonResponse([
            'token' => $this->jwtTokenManagerInterface->create($token->getUser()),
            'refreshToken' => $refreshToken->getRefreshToken(),
        ]);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $data = [
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Called when authentication is needed, but it's not sent.
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        $data = [
            'message' => 'Authentication Required',
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    public function supportsRememberMe()
    {
        return false;
    }
}
