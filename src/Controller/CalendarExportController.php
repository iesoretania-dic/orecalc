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

class CalendarExportController extends AbstractController
{
    private const MAX_EXTRA_COLUMNS = 20;

    #[Route('/exportar-calendario', name: 'export_calendar', methods: ['POST'])]
    public function export(Request $request, CalendarExportService $calendarExportService): Response
    {
        $calendar = json_decode((string) $request->request->get('calendar', ''), true);

        if (!is_array($calendar)) {
            throw new BadRequestHttpException('Calendario inválido.');
        }

        $extraColumns = max(0, min(self::MAX_EXTRA_COLUMNS, (int) $request->request->get('extra_columns', 0)));
        $showAllDates = (bool) $request->request->get('show_all_dates', false);

        $spreadsheet = $calendarExportService->export($calendar, $extraColumns, $showAllDates);

        $response = new StreamedResponse(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        });

        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="calendario.xlsx"');

        return $response;
    }
}
