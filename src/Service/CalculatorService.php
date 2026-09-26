<?php

namespace App\Service;

use App\Dto\DateRange;
use App\Dto\HoursFromStartDate;
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

        $nonWorkingDates = $this->findNonWorkingDates($start, $end);
        $weekHours = $this->buildWeekHours($dateRange->mon, $dateRange->tue, $dateRange->wed, $dateRange->thu, $dateRange->fri);

        $hours = 0;
        $workingDays = 0;
        $totalDays = 0;
        $calendar = [];

        $currentDate = $start;

        while ($currentDate <= $end) {
            $totalDays++;
            $dayHours = $this->accumulateDay($currentDate, $weekHours, $nonWorkingDates, $calendar);

            if ($dayHours > 0) {
                $hours += $dayHours;
                $workingDays++;
            }

            $currentDate = $currentDate->modify('+1 days');
        }

        return [
            'working_days' => $workingDays,
            'total_hours' => $hours,
            'total_days' => $totalDays,
            'calendar' => $calendar,
        ];
    }

    public function calculateEndDateForHours(HoursFromStartDate $hoursFromStartDate): array
    {
        $start = $hoursFromStartDate->start;
        $academicYear = $hoursFromStartDate->academicYear;

        assert($start !== null);
        assert($academicYear !== null);

        $limit = $academicYear->getEnd();

        $nonWorkingDates = $this->findNonWorkingDates($start, $limit);
        $weekHours = $this->buildWeekHours(
            $hoursFromStartDate->mon,
            $hoursFromStartDate->tue,
            $hoursFromStartDate->wed,
            $hoursFromStartDate->thu,
            $hoursFromStartDate->fri
        );

        $targetHours = $hoursFromStartDate->totalHours;

        $hours = 0;
        $workingDays = 0;
        $totalDays = 0;
        $calendar = [];
        $completed = false;

        $currentDate = $start;
        $end = $start;

        while ($currentDate <= $limit) {
            $totalDays++;
            $dayHours = $this->accumulateDay($currentDate, $weekHours, $nonWorkingDates, $calendar);

            if ($dayHours > 0) {
                $hours += $dayHours;
                $workingDays++;
            }

            $end = $currentDate;

            if ($hours >= $targetHours) {
                $completed = true;
                break;
            }

            $currentDate = $currentDate->modify('+1 days');
        }

        return [
            'end' => $end,
            'completed' => $completed,
            'working_days' => $workingDays,
            'total_hours' => $hours,
            'total_days' => $totalDays,
            'calendar' => $calendar,
        ];
    }

    private function buildWeekHours(int $mon, int $tue, int $wed, int $thu, int $fri): array
    {
        return [0, $mon, $tue, $wed, $thu, $fri, 0];
    }

    private function findNonWorkingDates(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $nonWorkingDays = $this->eduNonWorkingDayRepository->findBetweenDates($start, $end);

        return array_map(static fn($nwd) => $nwd->getDate(), $nonWorkingDays);
    }

    private function accumulateDay(\DateTimeImmutable $currentDate, array $weekHours, array $nonWorkingDates, array &$calendar): int
    {
        $currentHours = $weekHours[$currentDate->format('w')];

        if ($currentHours > 0 && in_array($currentDate, $nonWorkingDates, false) === false) {
            // Working day, keep the computed hours.
        } else {
            $currentHours = 0;
        }

        $weekNumber = (int) $currentDate->format('W');
        $monthNumber = (int) $currentDate->format('n') - 1 +
            (int) $currentDate->format('Y') * 12;
        $dayNumber = (int) $currentDate->format('d');

        if (!isset($calendar[$monthNumber][$weekNumber])) {
            $weekDayNumber = (int) $currentDate->format('N') - 1;
            for ($i = 0; $i < $weekDayNumber; $i++) {
                $calendar[$monthNumber][$weekNumber][] = null;
            }
        }

        $calendar[$monthNumber][$weekNumber][] = ['day' => $dayNumber, 'hours' => $currentHours];

        return $currentHours;
    }
}
