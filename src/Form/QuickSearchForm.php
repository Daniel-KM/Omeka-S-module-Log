<?php declare(strict_types=1);

namespace Log\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Form;

class QuickSearchForm extends Form
{
    public function init(): void
    {
        $this->setAttribute('method', 'get');
        $this->setAttribute('id', 'quick-search-form');

        // GET form: no csrf token in the query string.
        $this->remove('csrf');

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
                'type' => CommonElement\OptionalSelect::class,
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
                'type' => CommonElement\OptionalSelect::class,
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
                'type' => CommonElement\OptionalNumber::class,
                'options' => [
                    'label' => 'Job', // @translate
                ],
                'attributes' => [
                    'id' => 'job_id',
                    'placeholder' => 'Set a job id…', // @translate
                ],
            ])

            ->add([
                'name' => 'job_class',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Job class', // @translate
                ],
                'attributes' => [
                    'id' => 'job_class',
                    'placeholder' => 'Set a job class…', // @translate
                ],
            ])

            // TODO Switch to a ResourceSelect when the number of users is
            // small enough to render without pagination.
            ->add([
                'name' => 'owner_id',
                'type' => CommonElement\OptionalNumber::class,
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
                'type' => Element\Button::class,
                'options' => [
                    'label' => 'Search', // @translate
                ],
                'attributes' => [
                    'id' => 'submit',
                    'type' => 'submit',
                    'class' => 'button',
                ],
            ]);

    }
}
