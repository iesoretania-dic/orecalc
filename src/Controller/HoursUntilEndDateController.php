<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\HoursUntilEndDate;
use App\Form\HoursUntilEndDateType;
use App\Repository\AcademicYearRepository;
use App\Service\CalculatorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HoursUntilEndDateController extends AbstractController
{
    #[Route('/jornadas-hasta-fecha', name: 'hours_until_end_date')]
    public function index(
        Request $request,
        AcademicYearRepository $academicYearRepository,
        CalculatorService $calculatorService
    ): Response
    {
        $hoursUntilEndDate = new HoursUntilEndDate();

        $academicYear = $academicYearRepository->findLatestOrNull();
        if ($academicYear !== null) {
            $hoursUntilEndDate->academicYear = $academicYear;
            $hoursUntilEndDate->end = $academicYear->getEnd();
        } else {
            $hoursUntilEndDate->end = new \DateTimeImmutable();
        }

        $form = $this->createForm(HoursUntilEndDateType::class, $hoursUntilEndDate);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $stats = $calculatorService->calculateStartDateForHours($hoursUntilEndDate);
        }

        return $this->render('frontpage/hours_until_end_date.html.twig', [
            'form' => $form->createView(),
            'stats' => $stats ?? null,
        ]);
    }
}
