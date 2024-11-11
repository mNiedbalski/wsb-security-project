<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Form\TwoFactorCodeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Get the login error if there is one
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
    public function register(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check if the email already exists in the database
            $existingUser = $this->entityManager->getRepository(User::class)->findOneBy([
                'email' => $form->get('email')->getData(),
            ]);

            if ($existingUser) {
                // If email exists, add an error message to the form
                $form->get('email')->addError(new \Symfony\Component\Form\FormError('This email is already registered.'));
                return $this->render('security/register.html.twig', [
                    'registrationForm' => $form->createView(),
                ]);
            }

            // If no existing user found, proceed with password hashing
            $plainPassword = $form->get('plainPassword')->getData();
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);

            $user->setPassword($hashedPassword);

            // Save the user to the database
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $this->redirectToRoute('app_login'); // Redirect to login page after successful registration
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

            // Check if code matches and is still valid
            if ($submittedCode === $session->get('2fa_code') && time() <= $session->get('2fa_expires_at')) {
                $session->remove('2fa_code');
                $session->remove('2fa_expires_at');

                return $this->redirectToRoute('dashboard'); // or any authenticated route
            } else {
                $this->addFlash('error', 'Invalid or expired verification code.');
            }
        }

        return $this->render('security/verify_2fa.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
