<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Form\TwoFactorCodeType;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\BuilderInterface;
use Endroid\QrCodeBundle\Response\QrCodeResponse;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Google\GoogleAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;
class SecurityController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly BuilderInterface $qrCodeBuilder
    ) {}

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/register', name: 'register')]
    public function register(Request $request, GoogleAuthenticatorInterface $authenticator): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $existingUser = $this->entityManager->getRepository(User::class)->findOneBy([
                'email' => $form->get('email')->getData(),
            ]);

            if ($existingUser) {
                $form->get('email')->addError(new \Symfony\Component\Form\FormError('This email is already registered.'));
                return $this->render('security/register.html.twig', [
                    'registrationForm' => $form->createView(),
                ]);
            }
            $password = $form->get('password')->getData();
            $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($hashedPassword);

            $secret = $authenticator->generateSecret();
            $user->setGoogleAuthenticatorSecret($secret);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_enable_2fa', ['user' => $user->getId()]);
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    #[Route('/verify-2fa', name: 'app_verify_2fa')]
    public function verifyTwoFactor(Request $request): Response
    {
        $form = $this->createForm(TwoFactorCodeType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $session = $request->getSession();
            $submittedCode = $form->get('code')->getData();

            if ($submittedCode === $session->get('2fa_code') && time() <= $session->get('2fa_expires_at')) {
                $session->remove('2fa_code');
                $session->remove('2fa_expires_at');

                return $this->redirectToRoute('dashboard');
            } else {
                $this->addFlash('error', 'Invalid or expired verification code.');
            }
        }

        return $this->redirectToRoute('dashboard');
    }

    #[Route('/enable-2fa/{user}', name: 'app_enable_2fa')]
    public function enableTwoFactor(User $user, GoogleAuthenticatorInterface $authenticator): Response
    {
        if (!$user->isGoogleAuthenticatorEnabled()) {
            $secret = $authenticator->generateSecret();
            $user->setGoogleAuthenticatorSecret($secret);
            $this->entityManager->flush();
        }

        $qrCodeContent = $authenticator->getQRContent($user);

        // Renderowanie kodu QR za pomocą endroid/qr-code
        $result = $this->qrCodeBuilder->build(
            data: $qrCodeContent,
            size: 200,
            margin: 20,
        );
        $qrCodeImage = $result->getString(); // Pobiera obraz QR w formie PNG
        $qrCodeDataUri = 'data:image/png;base64,' . base64_encode($qrCodeImage); // Tworzy Data URI

        return $this->render('security/enable_2fa.html.twig', [
            'qrCode' => $qrCodeDataUri,
        ]);
    }

}
