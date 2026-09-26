<?php

namespace App\Service;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CalendarExportService
{
    private const DATE_COLUMN = 1;
    private const HOURS_COLUMN = 2;
    private const NOTES_COLUMN = 3;
    private const FIRST_EXTRA_COLUMN = 4;

    /** Columns in a month block's day grid: Lun..Dom. */
    private const WEEKDAY_COLUMNS = 7;

    /** Character width picked for a weekday column, comfortable for a two-digit day plus an "N h." line. */
    private const WEEKDAY_COLUMN_WIDTH = 9;

    /** A month usually spans this many calendar weeks; used to size rows so the day grid reads as roughly square. */
    private const TYPICAL_WEEKS_PER_MONTH = 6;

    private const WEEKDAY_LABELS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];

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

    public function exportGrid(
        array $calendar,
        int $monthsPerRow,
        bool $showHoursInCell,
        bool $showWeeklyTotal,
        bool $showMonthWorkingDaysTotal
    ): Spreadsheet {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Calendario');
        $sheet->getSheetView()->setZoomScale(85);

        $months = $this->sortedMonths($calendar);
        [$startDate, $endDate, $totalHours, $totalWorkingDays] = $this->summarize($months);

        $row = $this->writeSummary($sheet, $startDate, $endDate, $totalHours, $totalWorkingDays);
        $row += 2;

        $blockWidth = self::WEEKDAY_COLUMNS + ($showWeeklyTotal ? 1 : 0);
        $this->configureGridColumns($sheet, $monthsPerRow, $blockWidth, $showWeeklyTotal);

        $weekRowHeight = $this->squareWeekRowHeight();

        foreach (array_chunk(array_keys($months), max(1, $monthsPerRow)) as $monthKeysInBatch) {
            $column = 1;
            $batchBottom = $row;

            foreach ($monthKeysInBatch as $monthKey) {
                $blockBottom = $this->writeMonthGridBlock(
                    $sheet,
                    $column,
                    $row,
                    $monthKey,
                    $months[$monthKey],
                    $showHoursInCell,
                    $showWeeklyTotal,
                    $showMonthWorkingDaysTotal,
                    $weekRowHeight
                );
                $batchBottom = max($batchBottom, $blockBottom);
                $column += $blockWidth + 1;
            }

            $row = $batchBottom + 2;
        }

        return $spreadsheet;
    }

    /**
     * @return array{0: ?\DateTimeImmutable, 1: ?\DateTimeImmutable, 2: int, 3: int}
     */
    private function summarize(array $months): array
    {
        $totalHours = 0;
        $totalWorkingDays = 0;
        $startDate = null;
        $endDate = null;

        foreach ($months as $monthKey => $weeks) {
            if (!is_array($weeks)) {
                continue;
            }

            foreach ($weeks as $week) {
                if (!is_array($week)) {
                    continue;
                }

                foreach ($week as $day) {
                    if (!is_array($day) || !isset($day['day'])) {
                        continue;
                    }

                    $date = $this->reconstructDate($monthKey, (int) $day['day']);

                    if ($date === null) {
                        continue;
                    }

                    $hours = (int) ($day['hours'] ?? 0);
                    $totalHours += $hours;

                    if ($hours > 0) {
                        $totalWorkingDays++;
                    }

                    if ($startDate === null || $date < $startDate) {
                        $startDate = $date;
                    }

                    if ($endDate === null || $date > $endDate) {
                        $endDate = $date;
                    }
                }
            }
        }

        return [$startDate, $endDate, $totalHours, $totalWorkingDays];
    }

    private function writeSummary(
        Worksheet $sheet,
        ?\DateTimeImmutable $startDate,
        ?\DateTimeImmutable $endDate,
        int $totalHours,
        int $totalWorkingDays
    ): int {
        $labels = [
            'Fecha inicial:' => $startDate,
            'Fecha final:' => $endDate,
        ];

        $row = 1;

        foreach ($labels as $label => $date) {
            $sheet->setCellValue([1, $row], $label);

            if ($date !== null) {
                $cell = $sheet->getCell([2, $row]);
                $cell->setValue(ExcelDate::dateTimeToExcel($date));
                $cell->getStyle()->getNumberFormat()->setFormatCode('dd-mm-yy');
            }

            $row++;
        }

        $sheet->setCellValue([1, $row], 'Total jornadas lectivas:');
        $sheet->setCellValue([2, $row], $totalWorkingDays);
        $row++;

        $sheet->setCellValue([1, $row], 'Total horas lectivas:');
        $sheet->setCellValue([2, $row], $totalHours);
        $row++;

        $sheet->getStyle([1, 1, 1, $row - 1])->getFont()->setBold(true);

        return $row;
    }

    private function configureGridColumns(Worksheet $sheet, int $monthsPerRow, int $blockWidth, bool $showWeeklyTotal): void
    {
        $column = 1;

        for ($i = 0; $i < $monthsPerRow; $i++) {
            for ($w = 0; $w < self::WEEKDAY_COLUMNS; $w++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column + $w))->setWidth(self::WEEKDAY_COLUMN_WIDTH);
            }

            if ($showWeeklyTotal) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column + self::WEEKDAY_COLUMNS))->setWidth(8);
            }

            $column += $blockWidth;

            if ($i < $monthsPerRow - 1) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth(3);
                $column++;
            }
        }
    }

    /**
     * Excel column widths (characters) and row heights (points) use different scales; this converts
     * both to pixels (the standard 7px-per-character, 96/72 px-per-point approximations) to pick a row
     * height that makes a WEEKDAY_COLUMNS x TYPICAL_WEEKS_PER_MONTH day grid read as roughly square.
     */
    private function squareWeekRowHeight(): float
    {
        $columnWidthPixels = self::WEEKDAY_COLUMN_WIDTH * 7 + 5;
        $blockWidthPixels = $columnWidthPixels * self::WEEKDAY_COLUMNS;
        $rowHeightPixels = $blockWidthPixels / self::TYPICAL_WEEKS_PER_MONTH;

        return round($rowHeightPixels / 1.3333, 1);
    }

    private function writeMonthGridBlock(
        Worksheet $sheet,
        int $startColumn,
        int $startRow,
        int $monthKey,
        array $weeks,
        bool $showHoursInCell,
        bool $showWeeklyTotal,
        bool $showMonthWorkingDaysTotal,
        float $weekRowHeight
    ): int {
        $blockWidth = self::WEEKDAY_COLUMNS + ($showWeeklyTotal ? 1 : 0);
        $lastColumn = $startColumn + $blockWidth - 1;
        $totalColumn = $startColumn + self::WEEKDAY_COLUMNS;

        $titleRow = $startRow;
        $sheet->setCellValue([$startColumn, $titleRow], $this->monthLabel($monthKey));
        $sheet->mergeCells([$startColumn, $titleRow, $lastColumn, $titleRow]);
        $sheet->getStyle([$startColumn, $titleRow, $lastColumn, $titleRow])
            ->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle([$startColumn, $titleRow, $lastColumn, $titleRow])
            ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $this->fill($sheet, $startColumn, $titleRow, $lastColumn, $titleRow, 'FF4472C4');
        $sheet->getStyle([$startColumn, $titleRow, $lastColumn, $titleRow])->getFont()->getColor()->setRGB('FFFFFFFF');

        $headerRow = $titleRow + 1;

        foreach (self::WEEKDAY_LABELS as $offset => $label) {
            $sheet->setCellValue([$startColumn + $offset, $headerRow], $label);
        }

        if ($showWeeklyTotal) {
            $sheet->setCellValue([$totalColumn, $headerRow], 'Sem.');
        }

        $sheet->getStyle([$startColumn, $headerRow, $lastColumn, $headerRow])->getFont()->setBold(true);
        $sheet->getStyle([$startColumn, $headerRow, $lastColumn, $headerRow])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $this->fill($sheet, $startColumn, $headerRow, $lastColumn, $headerRow, 'FFD9E2F3');
        $this->shadeWeekend($sheet, $startColumn, $headerRow, 'FFC6D4EC');

        $row = $headerRow + 1;
        $monthHours = 0;
        $monthWorkingDays = 0;

        foreach ($weeks as $week) {
            if (!is_array($week)) {
                continue;
            }

            $sheet->getRowDimension($row)->setRowHeight($weekRowHeight);
            $weekHours = 0;

            for ($offset = 0; $offset < self::WEEKDAY_COLUMNS; $offset++) {
                $day = $week[$offset] ?? null;

                if (is_array($day) && isset($day['day'])) {
                    $hours = (int) ($day['hours'] ?? 0);
                    $this->writeDayCell($sheet, $startColumn + $offset, $row, (int) $day['day'], $hours, $showHoursInCell);
                    $weekHours += $hours;
                    $monthHours += $hours;

                    if ($hours > 0) {
                        $monthWorkingDays++;
                    }
                }
            }

            if ($showWeeklyTotal) {
                $sheet->setCellValue([$totalColumn, $row], $weekHours);
                $sheet->getStyle([$totalColumn, $row])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
            }

            $this->shadeWeekend($sheet, $startColumn, $row, 'FFF5F7FB');

            $row++;
        }

        $lastWeekRow = $row - 1;

        $this->border($sheet, $startColumn, $titleRow, $lastColumn, $lastWeekRow);

        $sheet->mergeCells([$startColumn, $row, $lastColumn, $row]);
        $sheet->setCellValue([$startColumn, $row], sprintf('Total horas lectivas: %d h.', $monthHours));
        $sheet->getStyle([$startColumn, $row])->getFont()->setBold(true);
        $sheet->getStyle([$startColumn, $row])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $row++;

        if ($showMonthWorkingDaysTotal) {
            $sheet->mergeCells([$startColumn, $row, $lastColumn, $row]);
            $sheet->setCellValue([$startColumn, $row], sprintf('Total jornadas lectivas: %d', $monthWorkingDays));
            $sheet->getStyle([$startColumn, $row])->getFont()->setBold(true);
            $sheet->getStyle([$startColumn, $row])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        return $row - 1;
    }

    private function writeDayCell(Worksheet $sheet, int $column, int $row, int $day, int $hours, bool $showHoursInCell): void
    {
        if ($showHoursInCell && $hours > 0) {
            $richText = new RichText();
            $dayRun = $richText->createTextRun((string) $day);
            $dayRun->getFont()->setBold(true)->setSize(11);
            $richText->createTextRun("\n");
            $hoursRun = $richText->createTextRun($hours . ' h.');
            $hoursRun->getFont()->setSize(9)->setItalic(true)->getColor()->setRGB('FF31708F');
            $sheet->setCellValue([$column, $row], $richText);
        } else {
            $sheet->setCellValue([$column, $row], $day);
        }

        $style = $sheet->getStyle([$column, $row]);
        $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    }

    private function shadeWeekend(Worksheet $sheet, int $startColumn, int $row, string $rgb): void
    {
        $this->fill($sheet, $startColumn + 5, $row, $startColumn + 6, $row, $rgb);
    }

    private function fill(Worksheet $sheet, int $fromColumn, int $fromRow, int $toColumn, int $toRow, string $rgb): void
    {
        $sheet->getStyle([$fromColumn, $fromRow, $toColumn, $toRow])
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
    }

    private function border(Worksheet $sheet, int $fromColumn, int $fromRow, int $toColumn, int $toRow): void
    {
        $sheet->getStyle([$fromColumn, $fromRow, $toColumn, $toRow])
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('FFB7C3D6');
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

                $date = $this->reconstructDate($monthKey, (int) $day['day']);

                if ($date === null) {
                    continue;
                }

                $rows[] = [$date, $hours];
            }
        }

        return $rows;
    }

    private function reconstructDate(int $monthKey, int $day): ?\DateTimeImmutable
    {
        $year = intdiv($monthKey, 12);
        $month = $monthKey % 12 + 1;

        try {
            return new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
        } catch (\Exception) {
            return null;
        }
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
