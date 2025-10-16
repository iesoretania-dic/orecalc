<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\DateRange;
use App\Form\DateRangeCalculatorType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FrontpageController extends AbstractController
{
    #[Route('/', name: 'frontpage')]
    public function index(Request $request): Response
    {
        $dateRange = new DateRange();
        $dateRange->start = new \DateTimeImmutable();
        $dateRange->end = new \DateTimeImmutable();
        $form = $this->createForm(DateRangeCalculatorType::class, $dateRange);
        $form->handleRequest($request);

        return $this->render('frontpage/index.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
