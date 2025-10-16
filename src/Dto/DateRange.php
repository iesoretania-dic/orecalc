<?php

namespace App\Dto;

use App\Entity\AcademicYear;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;

class DateRange
{
    #[NotBlank]
    public ?AcademicYear $academicYear = null;

    #[NotBlank]
    public ?\DateTimeImmutable $start;

    #[NotBlank]
    #[GreaterThan(propertyPath: 'start', message: 'La fecha de fin debe ser posterior a la fecha de inicio')]
    public ?\DateTimeImmutable $end;

    #[IsTrue(message: 'Las fechas deben estar dentro del año académico seleccionado')]
    public function isValid()
    {
        if ($this->academicYear && $this->start) {
            $academicYearStart = $this->academicYear->getStart();
            if ($this->start < $academicYearStart) {
                return false;
            }
        }
        if ($this->academicYear && $this->end) {
            $academicYearEnd = $this->academicYear->getEnd();
            if ($this->end > $academicYearEnd) {
                return false;
            }
        }
        return true;
    }
}
