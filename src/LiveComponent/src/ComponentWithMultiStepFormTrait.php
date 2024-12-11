<?php


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
 * Trait for managing multistep forms in Symfony UX LiveComponent.
 *
 * This trait simplifies the implementation of multistep forms by handling
 * step transitions, form validation, data persistence, and state management.
 * It provides a structured API for developers to integrate multistep forms
 * into their components with minimal boilerplate.
 *
 * @author Silas Joisten <silasjoisten@proton.me>
 * @author Patrick Reimers <preimers@pm.me>
 * @author Jules Pietri <heahdude@yahoo.fr>
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

    /**
     * Checks if the current form has validation errors.
     */
    public function hasValidationErrors(): bool
    {
        return $this->form->isSubmitted() && !$this->form->isValid();
    }

    /**
     * @internal
     *
     * Initializes the form and restores the state from storage.
     *
     * This method must be executed after `ComponentWithFormTrait::initializeForm()`.
     */
    #[PostMount(priority: -250)]
    public function initialize(): void
    {
        $this->currentStepName = $this->getStorage()->get(\sprintf('%s_current_step_name', self::prefix()), $this->formView->vars['current_step_name']);

        $this->form = $this->instantiateForm();

        $formData = $this->getStorage()->get(\sprintf('%s_form_values_%s', self::prefix(), $this->currentStepName));

        $this->form->setData($formData);

        $this->formValues = [] === $formData
            ? $this->extractFormValues($this->getFormView())
            : $formData;

        $this->stepNames = $this->formView->vars['steps_names'];

        // Do not move this. The order is important.
        $this->formView = null;
    }

    /**
     * Advances to the next step in the form.
     *
     * Validates the current step, saves its data, and moves to the next step.
     * Throws a RuntimeException if no next step is available.
     */
    #[LiveAction]
    public function next(): void
    {
        $this->submitForm();

        if ($this->hasValidationErrors()) {
            return;
        }

        $this->getStorage()->persist(\sprintf('%s_form_values_%s', self::prefix(), $this->currentStepName), $this->form->getData());

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

        $formData = $this->getStorage()->get(\sprintf('%s_form_values_%s', self::prefix(), $this->currentStepName));

        $this->formValues = [] === $formData
            ? $this->extractFormValues($this->getFormView())
            : $formData;

        $this->form->setData($formData);
    }

    /**
     * Moves to the previous step in the form.
     *
     * Retrieves the previous step's data and updates the form state.
     * Throws a RuntimeException if no previous step is available.
     */
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

    /**
     * Checks if the current step is the first step.
     *
     * @return bool true if the current step is the first; false otherwise
     */
    #[ExposeInTemplate]
    public function isFirst(): bool
    {
        return $this->currentStepName === $this->stepNames[array_key_first($this->stepNames)];
    }

    /**
     * Checks if the current step is the last step.
     *
     * @return bool true if the current step is the last; false otherwise
     */
    #[ExposeInTemplate]
    public function isLast(): bool
    {
        return $this->currentStepName === $this->stepNames[array_key_last($this->stepNames)];
    }

    /**
     * Submits the form and triggers the `onSubmit` callback if valid.
     */
    #[LiveAction]
    public function submit(): void
    {
        $this->submitForm();

        if ($this->hasValidationErrors()) {
            return;
        }

        $this->getStorage()->persist(\sprintf('%s_form_values_%s', self::prefix(), $this->currentStepName), $this->form->getData());

        $this->onSubmit();
    }

    /**
     * Abstract method to be implemented by the component for custom submission logic.
     */
    abstract public function onSubmit();

    /**
     * Retrieves all data from all steps.
     *
     * @return array<string, mixed> an associative array of step names and their data
     */
    public function getAllData(): array
    {
        $data = [];

        foreach ($this->stepNames as $stepName) {
            $data[$stepName] = $this->getStorage()->get(\sprintf('%s_form_values_%s', self::prefix(), $stepName));
        }

        return $data;
    }

    /**
     * Resets the form, clearing all stored data and returning to the first step.
     */
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

    /**
     * Abstract method to retrieve the storage implementation.
     *
     * @return StorageInterface the storage instance
     */
    abstract protected function getStorage(): StorageInterface;

    /**
     * Abstract method to specify the form class for the component.
     *
     * @return class-string<FormInterface> the form class name
     */
    abstract protected static function formClass(): string;

    /**
     * Abstract method to retrieve the form factory instance.
     *
     * @return FormFactoryInterface the form factory
     */
    abstract protected function getFormFactory(): FormFactoryInterface;

    /**
     * @internal
     *
     * Instantiates the form for the current step
     *
     * @return FormInterface the form instance
     */
    protected function instantiateForm(): FormInterface
    {
        $options = [];

        if (null !== $this->currentStepName) {
            $options['current_step_name'] = $this->currentStepName;
        }

        return $this->getFormFactory()->create(static::formClass(), null, $options);
    }

    /**
     * @internal
     *
     * Generates a unique prefix based on the component's class name
     *
     * @return string the generated prefix in snake case
     */
    private static function prefix(): string
    {
        return u(static::class)->afterLast('\\')->snake()->toString();
    }
}
