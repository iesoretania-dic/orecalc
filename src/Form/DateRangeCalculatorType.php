<?php

namespace App\Form;

use App\Dto\DateRange;
use App\Entity\AcademicYear;
use App\Repository\AcademicYearRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DateRangeCalculatorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('academicYear', EntityType::class, [
                'label' => 'Curso académico',
                'class' => AcademicYear::class,
                'choice_label' => 'name',
                'query_builder' => function (AcademicYearRepository $academicYearRepository) {
                    return $academicYearRepository->findAllOrderedQueryBuilder();
                }
            ])
            ->add('start', DateType::class, [
                'label' => 'Fecha de inicio',
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('end', DateType::class, [
                'label' => 'Fecha de fin',
                'widget' => 'single_text',
                'html5' => true,
            ])
            ->add('mon', IntegerType::class, [
                'label' => 'Horas lunes',
                'attr' => ['min' => 0, 'max' => 24],
            ])
            ->add('tue', IntegerType::class, [
                'label' => 'Horas martes',
                'attr' => ['min' => 0, 'max' => 24],
            ])
            ->add('wed', IntegerType::class, [
                'label' => 'Horas miércoles',
                'attr' => ['min' => 0, 'max' => 24],
            ])
            ->add('thu', IntegerType::class, [
                'label' => 'Horas jueves',
                'attr' => ['min' => 0, 'max' => 24],
            ])
            ->add('fri', IntegerType::class, [
                'label' => 'Horas viernes',
                'attr' => ['min' => 0, 'max' => 24],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => DateRange::class,
        ]);
    }
}
