<?php

namespace Log\Form;

use Zend\Form\Element\Select;
use Zend\Form\Element\Submit;
use Zend\Form\Element\Text;
use Zend\Form\Form;

class SearchForm extends Form
{
    public function init()
    {
        $this->setAttribute('method', 'get');

        // No csrf: see main search form.
        $this->remove('csrf');

        $this->add([
            'type' => Text::class,
            'name' => 'created',
            'options' => [
                'label' => 'Created', // @translate
            ],
            'attributes' => [
                'placeholder' => 'Set a date with optional comparator...', // @translate
            ],
        ]);

        $valueOptions = [
            '0' => 'Emergency',  // @translate
            '1' => 'Alert', // @translate
            '2' => 'Critical', // @translate
            '3' => 'Error', // @translate
            '4' => 'Warning', // @translate
            '5' => 'Notice', // @translate
            '6' => 'Info', // @translate
            '7' => 'Debug', // @translate
        ];
        $this->add([
            'name' => 'severity_min',
            'type' => Select::class,
            'options' => [
                'label' => 'Minimum severity', // @translate
                'value_options' => $valueOptions,
                'empty_option' => 'Select minimum severity below...', // @translate
            ],
            'attributes' => [
                'placeholder' => 'Select minimum severity below...', // @translate
            ],
        ]);
        $this->add([
            'name' => 'severity_max',
            'type' => Select::class,
            'options' => [
                'label' => 'Maximum severity', // @translate
                'value_options' => $valueOptions,
                'empty_option' => 'Select maximum severity below...', // @translate
            ],
            'attributes' => [
                'placeholder' => 'Select maximum severity below...', // @translate
            ],
        ]);

        $this->add([
            'type' => Text::class,
            'name' => 'reference',
            'options' => [
                'label' => 'Reference', // @translate
            ],
            'attributes' => [
                'placeholder' => 'Set a reference...', // @translate
            ],
        ]);

        $this->add([
            'type' => Text::class,
            'name' => 'job_id',
            'options' => [
                'label' => 'Job', // @translate
            ],
            'attributes' => [
                'placeholder' => 'Set a job id...', // @translate
            ],
        ]);

        $this->add([
            'type' => Text::class,
            'name' => 'user_id',
            'options' => [
                'label' => 'User', // @translate
            ],
            'attributes' => [
                'placeholder' => 'Set a user id...', // @translate
            ],
        ]);

        $this->add([
            'name' => 'submit',
            'type' => Submit::class,
            'attributes' => [
                'value' => 'Search', // @translate
                'type' => 'submit',
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'severity',
            'required' => false,
        ]);
    }
}
