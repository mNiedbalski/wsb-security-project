<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'dashboard', methods: ['GET', 'POST', 'UPDATE'])]
    public function index(Request $request, EntityManagerInterface $entityManager, Connection $connection): Response
    {
        $result = null;

        if ($request->isMethod('POST')) {
            $login = $request->request->get('login');

            // Dla ulatwienia sobie ustawiam sztywno siebie jako usera ktorego modyfikuje (id = 6), bo mialem male problemy z Security które nie przechowuje dobrze zalogowanego usera
            $sql = "UPDATE user SET login = '$login' WHERE id = 6";
            $stmt = $connection->prepare($sql);
            $stmt->executeStatement();
        }

        return $this->render('dashboard/index.html.twig', [
            'result' => $result,
        ]);
    }
}