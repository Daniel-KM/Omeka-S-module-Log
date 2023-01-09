<?php declare(strict_types=1);

namespace Log\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;
use Laminas\View\Helper\Url;

// use Omeka\Form\Element as OmekaElement;

class QuickSearchForm extends Form
{
    /**
     * @var Url
     */
    protected $urlHelper;

    public function init(): void
    {
        $this->setAttribute('method', 'get');

        // No csrf: see main search form.
        $this->remove('csrf');

        // $urlHelper = $this->getUrlHelper();

        $severityValueOptions = [
            '0' => 'Emergency', // @translate
            '1' => 'Alert', // @translate
            '2' => 'Critical', // @translate
            '3' => 'Error', // @translate
            '4' => 'Warning', // @translate
            '5' => 'Notice', // @translate
            '6' => 'Info', // @translate
            '7' => 'Debug', // @translate
        ];

        $this
            ->add([
                'name' => 'severity_min',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Minimum severity', // @translate
                    'value_options' => $severityValueOptions,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'severity_min',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select minimum severity…', // @translate
                ],
            ])
            ->add([
                'name' => 'severity_max',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Maximum severity', // @translate
                    'value_options' => $severityValueOptions,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'severity_max',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select maximum severity…', // @translate
                ],
            ])

            ->add([
                'name' => 'created',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Date', // @translate
                ],
                'attributes' => [
                    'id' => 'created',
                    'placeholder' => 'Set a date with optional comparator…', // @translate
                ],
            ])

            ->add([
                'name' => 'reference',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Reference', // @translate
                ],
                'attributes' => [
                    'id' => 'reference',
                    'placeholder' => 'Set a reference…', // @translate
                ],
            ])

            ->add([
                'name' => 'job_id',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'Job', // @translate
                ],
                'attributes' => [
                    'id' => 'job_id',
                    'placeholder' => 'Set a job id…', // @translate
                ],
            ])

            /*
            // TODO Fix issue when the number of users is too big to allow to keep the selector.
            ->add([
                'name' => 'owner_id',
                'type' => OmekaElement\ResourceSelect::class,
                'options' => [
                    'label' => 'User', // @translate
                    'resource_value_options' => [
                        'resource' => 'users',
                        'query' => [],
                        'option_text_callback' => function ($user) {
                            return $user->name();
                        },
                    ],
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'owner_id',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a user…', // @translate
                    'data-api-base-url' => $urlHelper('api/default', ['resource' => 'users']),
                ],
            ])
            */
            ->add([
                'name' => 'owner_id',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'User by id', // @translate
                ],
                'attributes' => [
                    'id' => 'owner_id',
                ],
            ])

            ->add([
                'name' => 'message',
                'type' => Element\Text::class,
                'options' => [
                    // TODO Manage search in translated messages as they are displayed.
                    'label' => 'Untranslated message', // @translate
                ],
                'attributes' => [
                    'id' => 'message',
                    'placeholder' => 'Set an untranslated string…', // @translate
                ],
            ])
            ->add([
                'name' => 'message_not',
                'type' => Element\Text::class,
                'options' => [
                    // TODO Manage search in translated messages as they are displayed.
                    'label' => 'Not in untranslated message', // @translate
                ],
                'attributes' => [
                    'id' => 'message_not',
                    'placeholder' => 'Set an untranslated string…', // @translate
                ],
            ])

            ->add([
                'name' => 'submit',
                'type' => Element\Submit::class,
                'attributes' => [
                    'id' => 'submit',
                    'value' => 'Search', // @translate
                    'type' => 'submit',
                ],
            ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter
            ->add([
                'name' => 'severity',
                'required' => false,
            ]);
    }

    public function setUrlHelper(Url $urlHelper): self
    {
        $this->urlHelper = $urlHelper;
        return $this;
    }

    public function getUrlHelper(): Url
    {
        return $this->urlHelper;
    }
}
