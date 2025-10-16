<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\DateRange;
use App\Form\DateRangeCalculatorType;
use App\Repository\AcademicYearRepository;
use App\Service\CalculatorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DateRangeCalculatorController extends AbstractController
{
    #[Route('/', name: 'frontpage')]
    public function index(
        Request $request,
        AcademicYearRepository $academicYearRepository,
        CalculatorService $calculatorService
    ): Response
    {
        $dateRange = new DateRange();

        $academicYear = $academicYearRepository->findLatestOrNull();
        if ($academicYear !== null) {
            $dateRange->academicYear = $academicYear;
            $dateRange->start = $academicYear->getStart();
            $dateRange->end = $academicYear->getEnd();
        } else {
            $dateRange->start = new \DateTimeImmutable();
            $dateRange->end = new \DateTimeImmutable();
        }

        $form = $this->createForm(DateRangeCalculatorType::class, $dateRange);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $stats = $calculatorService->calculateWorkingDays($dateRange);
        }

        return $this->render('frontpage/date_range_calculator.html.twig', [
            'form' => $form->createView(),
            'stats' => $stats ?? null,
        ]);
    }
}
