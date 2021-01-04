<?php declare(strict_types=1);

namespace Log\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;
use Laminas\View\Helper\Url;
use Omeka\Form\Element\ResourceSelect;

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

        $urlHelper = $this->getUrlHelper();

        $this
            ->add([
                'type' => Element\Text::class,
                'name' => 'created',
                'options' => [
                    'label' => 'Date', // @translate
                ],
                'attributes' => [
                    'id' => 'created',
                    'placeholder' => 'Set a date with optional comparator…', // @translate
                ],
            ]);

        $valueOptions = [
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
                    'value_options' => $valueOptions,
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
                    'value_options' => $valueOptions,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'severity_max',
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select maximum severity…', // @translate
                ],
            ])

            ->add([
                'type' => Element\Text::class,
                'name' => 'reference',
                'options' => [
                    'label' => 'Reference', // @translate
                ],
                'attributes' => [
                    'id' => 'reference',
                    'placeholder' => 'Set a reference…', // @translate
                ],
            ])

            ->add([
                'type' => Element\Number::class,
                'name' => 'job_id',
                'options' => [
                    'label' => 'Job', // @translate
                ],
                'attributes' => [
                    'id' => 'job_id',
                    'placeholder' => 'Set a job id…', // @translate
                ],
            ])

            /*
            ->add([
                'name' => 'owner_id',
                'type' => ResourceSelect::class,
                'options' => [
                    'label' => 'Owner', // @translate
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
            // TODO Fix issue when the number of users is too big to allow to keep the selector.
            ->add([
                'name' => 'owner_id',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'Owner', // @translate
                ],
                'attributes' => [
                    'id' => 'owner_id',
                ],
            ])

            ->add([
                'type' => Element\Text::class,
                'name' => 'message',
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
                'type' => Element\Text::class,
                'name' => 'message_not',
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

    /**
     * @param Url $urlHelper
     * @return self
     */
    public function setUrlHelper(Url $urlHelper): self
    {
        $this->urlHelper = $urlHelper;
        return $this;
    }

    /**
     * @return \Laminas\View\Helper\Url
     */
    public function getUrlHelper()
    {
        return $this->urlHelper;
    }
}
