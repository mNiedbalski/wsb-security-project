<?php /** @noinspection ALL */

namespace App\Controller;

use App\Form\LoginFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'login')]
    public function login(Request $request): Response
    {
        // Tworzymy formularz logowania
        $form = $this->createForm(LoginFormType::class);

        // Sprawdzamy, czy formularz został wysłany i jeśli tak, sprawdzamy dane
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // W tym miejscu można dodać logikę autoryzacji (np. sprawdzenie loginu)
        }

        return $this->render('security/login.html.twig', [
            'loginForm' => $form->createView(),
        ]);
    }

    #[Route('/logout', name: 'logout')]
    public function logout()
    {
        // Symfony automatycznie obsługuje tę trasę
    }
}

