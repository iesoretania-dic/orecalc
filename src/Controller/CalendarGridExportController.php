<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\CalendarExportService;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

class CalendarGridExportController extends AbstractController
{
    private const MAX_MONTHS_PER_ROW = 12;

    #[Route('/exportar-calendario-mensual', name: 'export_calendar_grid', methods: ['POST'])]
    public function export(Request $request, CalendarExportService $calendarExportService): Response
    {
        $calendar = json_decode((string) $request->request->get('calendar', ''), true);

        if (!is_array($calendar)) {
            throw new BadRequestHttpException('Calendario inválido.');
        }

        $monthsPerRow = max(1, min(self::MAX_MONTHS_PER_ROW, (int) $request->request->get('months_per_row', 3)));
        $showHoursInCell = (bool) $request->request->get('show_hours_in_cell', false);
        $showWeeklyTotal = (bool) $request->request->get('show_weekly_total', false);
        $showMonthWorkingDaysTotal = (bool) $request->request->get('show_month_working_days_total', false);

        $spreadsheet = $calendarExportService->exportGrid(
            $calendar,
            $monthsPerRow,
            $showHoursInCell,
            $showWeeklyTotal,
            $showMonthWorkingDaysTotal
        );

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="calendario-mensual.xlsx"');

        return $response;
    }
}
