<?php

namespace App\Service;

use App\Dto\DateRange;
use App\Repository\EduNonWorkingDayRepository;

class CalculatorService
{
    public function __construct(private readonly EduNonWorkingDayRepository $eduNonWorkingDayRepository)
    {
    }

    public function calculateWorkingDays(DateRange $dateRange): array
    {
        $start = $dateRange->start;
        $end = $dateRange->end;

        assert($start !== null);
        assert($end !== null);

        if ($start > $end) {
            throw new \InvalidArgumentException('Start date must be before or equal to end date.');
        }

        $nonWorkingDays = $this->eduNonWorkingDayRepository->findBetweenDates($start, $end);
        $nonWorkingDates = array_map(static fn($nwd) => $nwd->getDate(), $nonWorkingDays);

        $weekHours = [0, $dateRange->mon, $dateRange->tue, $dateRange->wed, $dateRange->thu, $dateRange->fri, 0];

        $hours = 0;
        $workingDays = 0;
        $totalDays = 0;

        $currentDate = $start;

        while ($currentDate <= $end) {
            $totalDays++;
            $currentHours = $weekHours[$currentDate->format('w')];

            if ($currentHours > 0) {
                if (!in_array($currentDate, $nonWorkingDates, true)) {
                    $hours += $currentHours;
                    $workingDays++;
                }
            }
            $currentDate = $currentDate->modify("+1 days");
        }

        return [
            'working_days' => $workingDays,
            'total_hours' => $hours,
            'total_days' => $totalDays,
        ];
    }
}
