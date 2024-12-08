<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\LiveComponent\Tests\Unit\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\UX\LiveComponent\Form\Type\MultiStepType;

/**
 * @author Silas Joisten <silasjoisten@proton.me>
 */
final class MultiStepTypeTest extends TypeTestCase
{
    public function testConfigureOptionsWithoutStepsThrowsException(): void
    {
        self::expectException(MissingOptionsException::class);

        $this->factory->create(MultiStepType::class);
    }

    public function testConfigureOptionsWithStepsSetsDefaultForCurrentStepName(): void
    {
        $form = $this->factory->create(MultiStepType::class, [], [
            'steps' => [
                'general' => static function (): void {},
                'contact' => static function (): void {},
                'newsletter' => static function (): void {},
            ],
        ]);

        self::assertSame('general', $form->createView()->vars['current_step_name']);
    }

    public function testBuildViewHasStepNames(): void
    {
        $form = $this->factory->create(MultiStepType::class, [], [
            'steps' => [
                'general' => static function (): void {},
                'contact' => static function (): void {},
                'newsletter' => static function (): void {},
            ],
        ]);

        self::assertSame(['general', 'contact', 'newsletter'], $form->createView()->vars['steps_names']);
    }

    public function testFormOnlyHasCurrentStepForm(): void
    {
        $form = $this->factory->create(MultiStepType::class, [], [
            'steps' => [
                'general' => static function (FormBuilderInterface $builder): void {
                    $builder
                        ->add('firstName', TextType::class)
                        ->add('lastName', TextType::class);
                },
                'contact' => static function (FormBuilderInterface $builder): void {
                    $builder
                        ->add('address', TextType::class)
                        ->add('city', TextType::class);
                },
                'newsletter' => static function (): void {},
            ],
        ]);

        self::assertArrayHasKey('firstName', $form->createView()->children);
        self::assertArrayHasKey('lastName', $form->createView()->children);
        self::assertArrayNotHasKey('address', $form->createView()->children);
        self::assertArrayNotHasKey('city', $form->createView()->children);
    }
}
