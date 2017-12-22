<?php

namespace AuthBundle\Security;

use Adldap\Adldap;
use AuthBundle\Entity\User;
use AuthBundle\Form\Type\LoginForm;
use AuthBundle\Service\ActiveDirectory;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoder;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AuthenticatorInterface;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;

/**
 * Class AdldapAuthenticator
 *
 * @package AuthBundle\Security
 * @author  Damien Lagae <damienlagae@gmail.com>
 */
class AdldapAuthenticator implements AuthenticatorInterface
{

    private $formFactory;
    private $em;
    private $router;
    /**
     * @var ActiveDirectory
     */
    private $activeDirectory;
    /**
     * @var UserPasswordEncoder
     */
    private $passwordEncoder;

    /**
     * AdldapAuthenticator
     *
     * @param FormFactoryInterface $formFactory The form factory
     * @param EntityManager        $em          The entity manager
     * @param RouterInterface      $router      The router
     * @param UserPasswordEncoder  $passwordEncoder The password encoder
     * @param ActiveDirectory      $activeDirectory The active directory connection service
     */
    public function __construct(
        FormFactoryInterface $formFactory,
        EntityManager $em,
        RouterInterface $router,
        UserPasswordEncoder $passwordEncoder,
        ActiveDirectory $activeDirectory
    ) {
        $this->formFactory = $formFactory;
        $this->em = $em;
        $this->router = $router;
        $this->activeDirectory = $activeDirectory;
        $this->passwordEncoder = $passwordEncoder;
    }

    public function supports(Request $request)
    {
        $checkPath = $request->getPathInfo() === '/login';
        $checkMethod = $request->isMethod('POST');

        if (!($checkMethod && $checkPath)) {
            return null;
        }

        return true;
    }

    /**
     * Called on every request. Return whatever credentials you want to
     * be passed to getUser(). Returning null will cause this authenticator
     * to be skipped.
     *
     * @param Request $request Th request
     *
     * @return mixed
     * @throws \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function getCredentials(Request $request)
    {
        $form = $this->formFactory->create(LoginForm::class);
        $form->handleRequest($request);
        $data = $form->getData();

        $request->getSession()->set(
            Security::LAST_USERNAME,
            $data['_username']
        );

        return $data;
    }

    /**
     * Return a UserInterface object based on the credentials.
     *
     * The *credentials* are the return value from getCredentials()
     *
     * You may throw an AuthenticationException if you wish. If you return
     * null, then a UsernameNotFoundException is thrown for you.
     *
     * @param mixed                 $credentials  The credentials [return value from getCredentials()]
     * @param UserProviderInterface $userProvider The UserProvider
     *
     * @throws \Symfony\Component\Security\Core\Exception\UsernameNotFoundException
     * @throws \Doctrine\ORM\ORMInvalidArgumentException
     * @throws AuthenticationException
     * @throws \Doctrine\ORM\OptimisticLockException
     *
     * @return UserInterface|null
     */
    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $username = $credentials['_username'];

        if (null === $username) {
            throw new UsernameNotFoundException('Username can\'t be empty!');
        }

        $user = $this->em->getRepository('AuthBundle:User')
            ->findOneBy(['email' => $username]);
        if ($user === null) {
            $adUser = $this->activeDirectory->getUser($username);

            if ($adUser !== null) {
                $user = new User();
                $user->createFromAD($adUser);
                $this->em->persist($user);
                $this->em->flush();
            }
        }
        $user = $this->em->getRepository('AuthBundle:User')
            ->findOneBy(['email' => $username]);

        if ($user === null) {
            throw new UsernameNotFoundException('User not found!');
        }

        return $user;
    }

    /**
     * Returns true if the credentials are valid.
     *
     * If any value other than true is returned, authentication will
     * fail. You may also throw an AuthenticationException if you wish
     * to cause authentication to fail.
     *
     * @param mixed         $credentials The credentials [return value from getCredentials()]
     * @param UserInterface $user        The User
     *
     * @return bool
     *
     * @throws AuthenticationException
     */
    public function checkCredentials($credentials, UserInterface $user): bool
    {
        $password = $credentials['_password'];
        $username = $credentials['_username'];

        if ($this->activeDirectory->checkUserExistByUsername($username) !== false) {
            return $this->activeDirectory->checkCredentials($username, $password);
        } else {
            if ($this->passwordEncoder->isPasswordValid($user, $password)) {
                return true;
            } else {
                throw new BadCredentialsException('Invalid password');
            }
        }
    }

    /**
     * Override to change what happens after successful authentication.
     *
     * @param Request        $request
     * @param TokenInterface $token
     * @param string         $providerKey
     *
     * @return RedirectResponse
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        $url = $this->router->generate('homepage');

        return new RedirectResponse($url);
    }

    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $request->getSession()->set(Security::AUTHENTICATION_ERROR, $exception);
        $url = $this->router->generate('security_login');
        return new RedirectResponse($url);
    }

    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     * @throws \Symfony\Component\Routing\Exception\RouteNotFoundException
     * @throws \Symfony\Component\Routing\Exception\MissingMandatoryParametersException
     * @throws \Symfony\Component\Routing\Exception\InvalidParameterException
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        $url = $this->router->generate('security_login');
        return new RedirectResponse($url);
    }

    /**
     * {@inheritDoc}
     */
    public function supportsRememberMe()
    {
        return true;
    }

    /**
     * Create an authenticated token for the given user.
     *
     * If you don't care about which token class is used or don't really
     * understand what a "token" is, you can skip this method by extending
     * the AbstractGuardAuthenticator class from your authenticator.
     *
     * @see AbstractGuardAuthenticator
     *
     * @param UserInterface $user
     * @param string        $providerKey The provider (i.e. firewall) key
     *
     * @return PostAuthenticationGuardToken
     * @throws \InvalidArgumentException
     */
    public function createAuthenticatedToken(UserInterface $user, $providerKey)
    {
        return new PostAuthenticationGuardToken(
            $user,
            $providerKey,
            $user->getRoles()
        );
    }
}