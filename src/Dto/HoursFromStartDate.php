<?php

namespace App\Dto;

use App\Entity\AcademicYear;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class HoursFromStartDate
{
    #[NotBlank]
    public ?AcademicYear $academicYear = null;

    #[NotBlank]
    public ?\DateTimeImmutable $start;

    #[NotBlank]
    #[GreaterThan(0, message: 'El número de horas lectivas totales debe ser mayor que 0')]
    public ?int $totalHours = null;

    #[NotBlank]
    #[GreaterThanOrEqual(0, message: 'El número mínimo de horas por día es 0')]
    #[LessThanOrEqual(24, message: 'El número máximo de horas por día es 24')]
    public int $mon = 0;

    #[NotBlank]
    #[GreaterThanOrEqual(0, message: 'El número mínimo de horas por día es 0')]
    #[LessThanOrEqual(24, message: 'El número máximo de horas por día es 24')]
    public int $tue = 0;

    #[NotBlank]
    #[GreaterThanOrEqual(0, message: 'El número mínimo de horas por día es 0')]
    #[LessThanOrEqual(24, message: 'El número máximo de horas por día es 24')]
    public int $wed = 0;

    #[NotBlank]
    #[GreaterThanOrEqual(0, message: 'El número mínimo de horas por día es 0')]
    #[LessThanOrEqual(24, message: 'El número máximo de horas por día es 24')]
    public int $thu = 0;

    #[NotBlank]
    #[GreaterThanOrEqual(0, message: 'El número mínimo de horas por día es 0')]
    #[LessThanOrEqual(24, message: 'El número máximo de horas por día es 24')]
    public int $fri = 0;

    #[IsTrue(message: 'La fecha de inicio debe estar dentro del año académico seleccionado')]
    public function hasValidStartDate()
    {
        if ($this->academicYear && $this->start) {
            $academicYearStart = $this->academicYear->getStart();
            $academicYearEnd = $this->academicYear->getEnd();
            if ($this->start < $academicYearStart || $this->start > $academicYearEnd) {
                return false;
            }
        }
        return true;
    }

    #[IsTrue(message: 'Debe haber alguna hora asignada al menos un día de la semana')]
    public function hasValidHours()
    {
        return ($this->mon + $this->tue + $this->wed + $this->thu + $this->fri) > 0;
    }
}
