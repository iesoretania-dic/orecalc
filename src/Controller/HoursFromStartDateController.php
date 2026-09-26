<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\HoursFromStartDate;
use App\Form\HoursFromStartDateType;
use App\Repository\AcademicYearRepository;
use App\Service\CalculatorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HoursFromStartDateController extends AbstractController
{
    #[Route('/jornadas-desde-fecha', name: 'hours_from_start_date')]
    public function index(
        Request $request,
        AcademicYearRepository $academicYearRepository,
        CalculatorService $calculatorService
    ): Response
    {
        $hoursFromStartDate = new HoursFromStartDate();

        $academicYear = $academicYearRepository->findLatestOrNull();
        if ($academicYear !== null) {
            $hoursFromStartDate->academicYear = $academicYear;
            $hoursFromStartDate->start = $academicYear->getStart();
        } else {
            $hoursFromStartDate->start = new \DateTimeImmutable();
        }

        $form = $this->createForm(HoursFromStartDateType::class, $hoursFromStartDate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $stats = $calculatorService->calculateEndDateForHours($hoursFromStartDate);
        }

        return $this->render('frontpage/hours_from_start_date.html.twig', [
            'form' => $form->createView(),
            'stats' => $stats ?? null,
        ]);
    }
}
