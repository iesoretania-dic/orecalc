<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CalendarExportService
{
    private const DATE_COLUMN = 1;
    private const HOURS_COLUMN = 2;
    private const NOTES_COLUMN = 3;
    private const FIRST_EXTRA_COLUMN = 4;

    public function export(array $calendar, int $extraColumns, bool $showAllDates): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Calendario');

        $lastColumn = self::FIRST_EXTRA_COLUMN + $extraColumns - 1;

        $this->configureColumns($sheet, $extraColumns, $lastColumn);
        $this->writeHeaderRow($sheet, $extraColumns, $lastColumn);

        $row = 2;
        $extraColumnsFirstDataRow = null;

        foreach ($this->sortedMonths($calendar) as $monthKey => $weeks) {
            $monthRows = $this->collectMonthRows($monthKey, $weeks, $showAllDates);

            if ($monthRows === []) {
                continue;
            }

            $titleRow = $row;
            $sheet->setCellValue([self::DATE_COLUMN, $titleRow], $this->monthLabel($monthKey));
            $sheet->mergeCells(Coordinate::stringFromColumnIndex(self::DATE_COLUMN) . $titleRow . ':' . Coordinate::stringFromColumnIndex($lastColumn) . $titleRow);
            $sheet->getStyle([self::DATE_COLUMN, $titleRow, $lastColumn, $titleRow])->getFont()->setBold(true);
            $row++;

            $firstDataRow = $row;
            $extraColumnsFirstDataRow ??= $firstDataRow;

            foreach ($monthRows as [$date, $hours]) {
                $this->writeDayRow($sheet, $row, $date, $hours);
                $row++;
            }
            $lastDataRow = $row - 1;

            $this->writeSubtotalRow($sheet, $row, $firstDataRow, $lastDataRow);
            $row++;

            $row++; // Blank spacer row before the next month.
        }

        $extraColumnsLastDataRow = $row - 2;

        if ($extraColumns > 0 && $extraColumnsFirstDataRow !== null) {
            $this->writeExtraColumnsGrandTotal($sheet, $row, $extraColumnsFirstDataRow, $extraColumnsLastDataRow, $extraColumns);
        }

        $sheet->freezePane('A2');

        return $spreadsheet;
    }

    private function configureColumns(Worksheet $sheet, int $extraColumns, int $lastColumn): void
    {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex(self::DATE_COLUMN))->setWidth(11);
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex(self::HOURS_COLUMN))->setWidth(6);

        $notesColumn = Coordinate::stringFromColumnIndex(self::NOTES_COLUMN);
        $sheet->getColumnDimension($notesColumn)->setWidth(70);
        $sheet->getStyle($notesColumn . ':' . $notesColumn)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);

        for ($column = self::FIRST_EXTRA_COLUMN; $column <= $lastColumn; $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth(6);
        }
    }

    private function writeHeaderRow(Worksheet $sheet, int $extraColumns, int $lastColumn): void
    {
        $sheet->setCellValue([self::DATE_COLUMN, 1], 'Fecha');
        $sheet->setCellValue([self::HOURS_COLUMN, 1], 'Horas');
        $sheet->setCellValue([self::NOTES_COLUMN, 1], 'Previsto / realizado');

        for ($i = 1; $i <= $extraColumns; $i++) {
            $sheet->setCellValue([self::FIRST_EXTRA_COLUMN + $i - 1, 1], 'Extra ' . $i);
        }

        $sheet->getStyle([self::DATE_COLUMN, 1, $lastColumn, 1])->getFont()->setBold(true);
    }

    /**
     * @return array<int, array<int, array<int, array{day: int, hours: int}|null>>>
     */
    private function sortedMonths(array $calendar): array
    {
        ksort($calendar);

        return $calendar;
    }

    /**
     * @return list<array{0: \DateTimeImmutable, 1: int}>
     */
    private function collectMonthRows(int $monthKey, array $weeks, bool $showAllDates): array
    {
        $year = intdiv($monthKey, 12);
        $month = $monthKey % 12 + 1;

        $rows = [];

        foreach ($weeks as $week) {
            if (!is_array($week)) {
                continue;
            }

            foreach ($week as $day) {
                if (!is_array($day) || !isset($day['day'])) {
                    continue;
                }

                $hours = (int) ($day['hours'] ?? 0);

                if ($hours <= 0 && !$showAllDates) {
                    continue;
                }

                try {
                    $date = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, (int) $day['day']));
                } catch (\Exception) {
                    continue;
                }

                $rows[] = [$date, $hours];
            }
        }

        return $rows;
    }

    private function monthLabel(int $monthKey): string
    {
        $months = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        return $months[$monthKey % 12] . ' ' . intdiv($monthKey, 12);
    }

    private function writeDayRow(Worksheet $sheet, int $row, \DateTimeImmutable $date, int $hours): void
    {
        $dateCell = $sheet->getCell([self::DATE_COLUMN, $row]);
        $dateCell->setValue(ExcelDate::dateTimeToExcel($date));
        $dateCell->getStyle()->getNumberFormat()->setFormatCode('dd-mm-yy');

        if ($hours > 0) {
            $sheet->setCellValue([self::HOURS_COLUMN, $row], $hours);
        }

        if ($hours <= 0) {
            $sheet->getStyle([self::DATE_COLUMN, $row, self::NOTES_COLUMN, $row])->getFont()->setColor(new Color('FF999999'));
        }
    }

    private function writeSubtotalRow(Worksheet $sheet, int $row, int $firstDataRow, int $lastDataRow): void
    {
        $hoursColumn = Coordinate::stringFromColumnIndex(self::HOURS_COLUMN);

        $sheet->setCellValue([self::DATE_COLUMN, $row], 'Total');
        $sheet->setCellValue([self::HOURS_COLUMN, $row], "=SUM({$hoursColumn}{$firstDataRow}:{$hoursColumn}{$lastDataRow})");
        $sheet->setCellValue([self::NOTES_COLUMN, $row], "=COUNT({$hoursColumn}{$firstDataRow}:{$hoursColumn}{$lastDataRow}) & \" jornadas con horas\"");

        $sheet->getStyle([self::DATE_COLUMN, $row, self::NOTES_COLUMN, $row])->getFont()->setBold(true);
    }

    private function writeExtraColumnsGrandTotal(Worksheet $sheet, int $row, int $firstDataRow, int $lastDataRow, int $extraColumns): void
    {
        $sheet->setCellValue([self::DATE_COLUMN, $row], 'Total columnas extra');
        $sheet->getStyle([self::DATE_COLUMN, $row])->getFont()->setBold(true);

        for ($i = 0; $i < $extraColumns; $i++) {
            $column = self::FIRST_EXTRA_COLUMN + $i;
            $columnLetter = Coordinate::stringFromColumnIndex($column);
            $sheet->setCellValue([$column, $row], "=SUM({$columnLetter}{$firstDataRow}:{$columnLetter}{$lastDataRow})");
            $sheet->getStyle([$column, $row])->getFont()->setBold(true);
        }
    }
}
