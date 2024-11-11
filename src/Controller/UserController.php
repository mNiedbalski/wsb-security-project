<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UserController extends AbstractController
{
    #[Route('/user', name: 'user_dashboard')]
    public function index(): Response
    {
        return $this->render('user/index.html.twig', [
            'username' => $this->getUser()->getEmail(),
        ]);
    }
}
