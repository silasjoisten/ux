<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\UX\LiveComponent;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Storage\StorageInterface;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;
use Symfony\UX\TwigComponent\Attribute\PostMount;

use function Symfony\Component\String\u;

/**
 * @author Silas Joisten <silasjoisten@proton.me>
 * @author Patrick Reimers <preimers@pm.me>
 * @author Jules Pietri <jules@heahprod.com>
 */
trait ComponentWithMultiStepFormTrait
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    #[LiveProp]
    public ?string $currentStepName = null;

    /**
     * @var string[]
     */
    #[LiveProp]
    public array $stepNames = [];

    public function hasValidationErrors(): bool
    {
        return $this->form->isSubmitted() && !$this->form->isValid();
    }

    /**
     * @internal
     *
     * Must be executed after ComponentWithFormTrait::initializeForm()
     */
    #[PostMount(priority: -250)]
    public function initialize(): void
    {
        $this->currentStepName = $this->getStorage()->get(
            \sprintf('%s_current_step_name', self::prefix()),
            $this->formView->vars['current_step_name'],
        );

        $this->form = $this->instantiateForm();

        $formData = $this->getStorage()->get(\sprintf(
            '%s_form_values_%s',
            self::prefix(),
            $this->currentStepName,
        ));

        $this->form->setData($formData);

        if ([] === $formData) {
            $this->formValues = $this->extractFormValues($this->getFormView());
        } else {
            $this->formValues = $formData;
        }

        $this->stepNames = $this->formView->vars['steps_names'];

        // Do not move this. The order is important.
        $this->formView = null;
    }

    #[LiveAction]
    public function next(): void
    {
        $this->submitForm();

        if ($this->hasValidationErrors()) {
            return;
        }

        $this->getStorage()->persist(
            \sprintf('%s_form_values_%s', self::prefix(), $this->currentStepName),
            $this->form->getData(),
        );

        $found = false;
        $next = null;

        foreach ($this->stepNames as $stepName) {
            if ($this->currentStepName === $stepName) {
                $found = true;

                continue;
            }

            if ($found) {
                $next = $stepName;

                break;
            }
        }

        if (null === $next) {
            throw new \RuntimeException('No next forms available.');
        }

        $this->currentStepName = $next;
        $this->getStorage()->persist(\sprintf('%s_current_step_name', self::prefix()), $this->currentStepName);

        // If we have a next step, we need to resinstantiate the form and reset the form view and values.
        $this->form = $this->instantiateForm();
        $this->formView = null;

        $formData = $this->getStorage()->get(\sprintf(
            '%s_form_values_%s',
            self::prefix(),
            $this->currentStepName,
        ));

        // I really don't understand why we need to do that. But what I understood is extractFormValues creates
        // an array of initial values.
        if ([] === $formData) {
            $this->formValues = $this->extractFormValues($this->getFormView());
        } else {
            $this->formValues = $formData;
        }

        $this->form->setData($formData);
    }

    #[LiveAction]
    public function previous(): void
    {
        $found = false;
        $previous = null;

        foreach (array_reverse($this->stepNames) as $stepName) {
            if ($this->currentStepName === $stepName) {
                $found = true;

                continue;
            }

            if ($found) {
                $previous = $stepName;

                break;
            }
        }

        if (null === $previous) {
            throw new \RuntimeException('No previous forms available.');
        }

        $this->currentStepName = $previous;
        $this->getStorage()->persist(\sprintf('%s_current_step_name', self::prefix()), $this->currentStepName);

        $this->form = $this->instantiateForm();
        $this->formView = null;

        $formData = $this->getStorage()->get(\sprintf(
            '%s_form_values_%s',
            self::prefix(),
            $this->currentStepName,
        ));

        $this->formValues = $formData;
        $this->form->setData($formData);
    }

    #[ExposeInTemplate]
    public function isFirst(): bool
    {
        return $this->currentStepName === $this->stepNames[array_key_first($this->stepNames)];
    }

    #[ExposeInTemplate]
    public function isLast(): bool
    {
        return $this->currentStepName === $this->stepNames[array_key_last($this->stepNames)];
    }

    #[LiveAction]
    public function submit(): void
    {
        $this->submitForm();

        if ($this->hasValidationErrors()) {
            return;
        }

        $this->getStorage()->persist(
            \sprintf('%s_form_values_%s', self::prefix(), $this->currentStepName),
            $this->form->getData(),
        );

        $this->onSubmit();
    }

    abstract public function onSubmit();

    /**
     * @return array<string, mixed>
     */
    public function getAllData(): array
    {
        $data = [];

        foreach ($this->stepNames as $stepName) {
            $data[$stepName] = $this->getStorage()->get(\sprintf(
                '%s_form_values_%s',
                self::prefix(),
                $stepName,
            ));
        }

        return $data;
    }

    public function resetForm(): void
    {
        foreach ($this->stepNames as $stepName) {
            $this->getStorage()->remove(\sprintf('%s_form_values_%s', self::prefix(), $stepName));
        }

        $this->getStorage()->remove(\sprintf('%s_current_step_name', self::prefix()));

        $this->currentStepName = $this->stepNames[array_key_first($this->stepNames)];
        $this->form = $this->instantiateForm();
        $this->formView = null;
        $this->formValues = $this->extractFormValues($this->getFormView());
    }

    abstract protected function getStorage(): StorageInterface;

    /**
     * @return class-string<FormInterface>
     */
    abstract protected static function formClass(): string;

    abstract protected function getFormFactory(): FormFactoryInterface;

    /**
     * @internal
     */
    protected function instantiateForm(): FormInterface
    {
        $options = [];

        if (null !== $this->currentStepName) {
            $options['current_step_name'] = $this->currentStepName;
        }

        return $this->getFormFactory()->create(
            type: static::formClass(),
            options: $options,
        );
    }

    /**
     * @internal
     */
    private static function prefix(): string
    {
        return u(static::class)
            ->afterLast('\\')
            ->snake()
            ->toString();
    }
}
