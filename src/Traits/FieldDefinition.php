<?php

namespace Bjerke\Bread\Traits;

use Illuminate\Support\Str;
use Closure;

trait FieldDefinition
{

    public static $FIELD_OPTIONAL = false;
    public static $FIELD_REQUIRED = true;
    public static $DEFAULT_FIELD_GROUP = 'general';

    public static $GROUPS = [
        'general' => [
            'key' => 'general',
            'label' => '',
            'description' => '',
            'tabbed' => false, // Enables/disabled tabbed interface, provide key of tab-group as string to enable
            'order' => 0
        ],
        'relations' => [
            'key' => 'relations',
            'label' => '',
            'description' => '',
            'tabbed' => false, // Enables/disabled tabbed interface, provide key of tab-group as string to enable
            'order' => 100
        ]
    ];

    private $nested = false;

    protected $definition = [];

    protected $flatFieldDefinition = [];

    /**
     * Sets the definition on model
     *
     * @param bool $force
     */
    public function compileDefinition($force = false)
    {
        // Avoid max nesting
        if ($force || !config('bread.is_running_define')) {
            $definition = config('bread.model_definitions.' . strtolower(class_basename($this)), []);

            if (empty($definition) || $force) {
                config([
                    'bread.is_running_define' => true
                ]);
                $this->define();
                config([
                    'bread.is_running_define' => false
                ]);
            } else {
                $this->definition = $definition;
            }

            $this->setFieldDefinition($this->definition);
        }
    }

    /**
     * Checks if current model instance has a compiled field definition attached
     *
     * @return bool
     */
    public function isDefined()
    {
        return !empty($this->definition);
    }

    /**
     * Defines the current model with fields for API
     */
    protected function define()
    {
        //
    }

    /**
     * @internal
     *
     * @param array $options
     *              [
     *                  'name' => (string)
     *                  'label' => (string)
     *                  'description' => (string) ''
     *                  'type' => (string)
     *                  'input_type' => (string)
     *                  'required' => (null|int) null
     *                  'default' => (null|mixed) null
     *                  'validation' => (string) ''
     *                  'group' => (null|string) null
     *                  'hidden' => (bool) false
     *                  'input_hidden' => (bool) false
     *                  'fillable' => (bool) true
     *                  'show_in_lists' => (bool) true
     *                  'extra_data' => (array)
     *              ]
     */
    private function addField(array $options)
    {

        $name = $options['name'];
        $label = $options['label'];
        $description = $options['description'] ?? '';
        $placeholder = $options['placeholder'] ?? '';
        $showLabel = $options['show_label'] ?? true;
        $type = $options['type'];
        $inputType = $options['input_type'];

        $required = $options['required'] ?? null;
        $default = $options['default'] ?? null;
        $validationKey = $options['validation_key'] ?? $name;
        $validation = $options['validation'] ?? '';
        $group = $options['group'] ?? null;
        $hidden = $options['hidden'] ?? false;
        $inputHidden = $options['input_hidden'] ?? false;
        $fillable = $options['fillable'] ?? true;
        $showInLists = $options['show_in_lists'] ?? true;
        $hiddenOn = (isset($options['hidden_on']) && is_array($options['hidden_on'])) ? $options['hidden_on'] : [];
        $extraData = $options['extra_data'] ?? [];

        if (!isset($this->definition['model_info']['name'])) {
            $this->definition['model_info']['name'] = (\Lang::has('models.' . Str::lower(class_basename($this) . '.singular'))) ? \Lang::get('models.' . Str::lower(class_basename($this) . '.singular')) : class_basename($this);
        }
        if (!isset($this->definition['model_info']['plural_name'])) {
            $this->definition['model_info']['plural_name'] = (\Lang::has('models.' . Str::lower(class_basename($this) . '.plural'))) ? \Lang::get('models.' . Str::lower(class_basename($this) . '.plural')) : Str::plural(class_basename($this));
        }

        if ($group === null) {
            $group = $this::$DEFAULT_FIELD_GROUP;
        }

        if ($required === null) {
            $required = ($fillable) ? $this::$FIELD_REQUIRED : $this::$FIELD_OPTIONAL;
        }

        if ($hidden && !in_array($name, $this->hidden, true)) {
            $this->hidden[] = $name;
            $this->definition['guards']['hidden'][] = $name;
        }

        if ($inputHidden) {
            $hidden = true;
        }

        if ($fillable && !in_array($name, $this->fillable, true)) {
            $this->fillable[] = $name;
            $this->definition['guards']['fillable'][] = $name;
        }

        $requiredString = $options['required_validation_override'] ?? 'required';

        $ruleString = (($required) ? $requiredString : 'nullable');

        if ($validation) {
            $ruleString .= '|';
        }

        $ruleString .= $validation;

        if ($required || $validation) {
            $this->rules[$validationKey] = $ruleString;
            $this->definition['rules'][$validationKey] = $ruleString;
        }

        if (isset($options['rules']) && is_array($options['rules'])) {
            foreach ($options['rules'] as $ruleKey => $rule) {
                $this->rules[$ruleKey] = $rule;
                $this->definition['rules'][$ruleKey] = $rule;
            }
        }

        if (!isset($this->definition['field_groups'][$group])) {
            $this->definition['field_groups'][$group] = [
                'key' => self::$GROUPS[$group]['key'] ?? $group,
                'label' => self::$GROUPS[$group]['label'] ?? $group,
                'description' => self::$GROUPS[$group]['description'] ?? '',
                'tabbed' => self::$GROUPS[$group]['tabbed'] ?? false,
                'order' => self::$GROUPS[$group]['order'] ?? 100,
                'fields' => array(),
            ];
        }

        $fieldDefinition = [
            'name' => $name,
            'label' => $label,
            'show_label' => $showLabel,
            'description' => $description,
            'placeholder' => $placeholder,
            'input_type' => $inputType,
            'type' => $type,
            'required' => $required,
            'default' => $default,
            'rule_string' => $ruleString,
            'hidden' => $hidden,
            'hidden_on' => $hiddenOn,
            'fillable' => $fillable,
            'show_in_lists' => $showInLists,
            'extra_data' => $extraData
        ];

        $this->definition['field_groups'][$group]['fields'][] = $fieldDefinition;
        $this->flatFieldDefinition[$name] = $fieldDefinition;
    }

    /**
     * Sets options array on definition
     *
     * @param array $options
     */
    protected function setOptions(array $options)
    {
        $this->definition['options'] = $options;
    }

    /**
     * Add a text field to the model (text input)
     *
     * @param string   $name
     * @param string   $label
     * @param null|int $required
     * @param array    $options
     *                 [
     *                     'description' => (string) ''
     *                     'default' => (null|mixed) null
     *                     'validation' => (string) '' Validation rule without 'required|'
     *                     'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                     'hidden' => (bool) false
     *                     'fillable' => (bool) true
     *                     'show_in_lists' => (bool) true
     *                     'extra_data' => (array)
     *                 ]
     */
    protected function addFieldText($name, $label, $required = null, array $options = array())
    {
        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'type' => 'TEXT',
            'input_type' => 'text',
            'required' => $required,
            'validation' => 'string|max:255',
            'extra_data' => [
                'max' => 255
            ]
        ], $options));
    }

    /**
     * Add a textarea field to the model (textarea input)
     *
     * @param string   $name
     * @param string   $label
     * @param null|int $required
     * @param array    $options
     *                 [
     *                     'description' => (string) ''
     *                     'default' => (null|mixed) null
     *                     'validation' => (string) '' Validation rule without 'required|'
     *                     'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                     'hidden' => (bool) false
     *                     'fillable' => (bool) true
     *                     'show_in_lists' => (bool) true
     *                     'extra_data' => (array)
     *                 ]
     */
    protected function addFieldTextArea($name, $label, $required = null, array $options = array())
    {
        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'type' => 'TEXT',
            'input_type' => 'textarea',
            'required' => $required,
            'validation' => 'string'
        ], $options));
    }

    /**
     * Add a wysiwyg field to the model (supposed to allow some kind of editor)
     *
     * @param string   $name
     * @param string   $label
     * @param null|int $required
     * @param array    $options
     *                 [
     *                     'description' => (string) ''
     *                     'default' => (null|mixed) null
     *                     'validation' => (string) '' Validation rule without 'required|'
     *                     'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                     'hidden' => (bool) false
     *                     'fillable' => (bool) true
     *                     'show_in_lists' => (bool) true
     *                     'extra_data' => (array)
     *                 ]
     */
    protected function addFieldWysiwyg($name, $label, $required = null, array $options = array())
    {
        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'type' => 'JSON',
            'input_type' => 'wysiwyg',
            'required' => $required
        ], $options));
    }

    /**
     * Add an email field to the model (email input)
     *
     * @param string   $name
     * @param string   $label
     * @param null|int $required
     * @param array    $options
     *                 [
     *                     'description' => (string) ''
     *                     'default' => (null|mixed) null
     *                     'validation' => (string) '' Validation rule without 'required|'
     *                     'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                     'hidden' => (bool) false
     *                     'fillable' => (bool) true
     *                     'show_in_lists' => (bool) true
     *                     'extra_data' => (array)
     *                 ]
     */
    protected function addFieldEmail($name, $label, $required = null, array $options = array())
    {
        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'type' => 'TEXT',
            'input_type' => 'email',
            'required' => $required,
            'validation' => 'email'
        ], $options));
    }

    /**
     * Add a phone number field to the model (tel input)
     *
     * @param string   $name
     * @param string   $label
     * @param null|int $required
     * @param array    $options
     *                 [
     *                     'description' => (string) ''
     *                     'default' => (null|mixed) null
     *                     'validation' => (string) '' Validation rule without 'required|'
     *                     'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                     'hidden' => (bool) false
     *                     'fillable' => (bool) true
     *                     'show_in_lists' => (bool) true
     *                     'extra_data' => (array)
     *                 ]
     */
    protected function addFieldTel($name, $label, $required = null, array $options = array())
    {
        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'type' => 'TEXT',
            'input_type' => 'tel',
            'required' => $required,
            'validation' => 'string|regex:/^\+?[1-9]\d{1,14}$/',
            'extra_data' => [
                'max' => 255
            ]
        ], $options));
    }

    /**
     * Add a date field to the model (date input)
     *
     * @param string        $name
     * @param string        $label
     * @param null|string   $min            Minimum date allowed (Y-m-d)
     * @param null|string   $max            Maximum date allowed (Y-m-d)
     * @param null|int      $required
     * @param array         $options
     *                      [
     *                          'description' => (string) ''
     *                          'default' => (null|mixed) null
     *                          'validation' => (string) '' Validation rule without 'required|'
     *                          'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                          'hidden' => (bool) false
     *                          'fillable' => (bool) true
     *                          'show_in_lists' => (bool) true
     *                          'extra_data' => (array)
     *                      ]
     */
    protected function addFieldDate($name, $label, $min = null, $max = null, $required = null, array $options = array())
    {
        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'type' => 'TIMESTAMP',
            'input_type' => 'date',
            'required' => $required,
            'validation' => 'date',
            'extra_data' => [
                'min' => $min,
                'max' => $max,
                'timezone' => 'UTC'
            ]
        ], $options));
    }

    /**
     * Add a datetime field to the model (datetime input)
     *
     * @param string        $name
     * @param string        $label
     * @param null|string   $min            Minimum date allowed (Y-m-d H:i:s)
     * @param null|string   $max            Maximum date allowed (Y-m-d H:i:s)
     * @param null|int      $required
     * @param array         $options
     *                      [
     *                          'description' => (string) ''
     *                          'default' => (null|mixed) null
     *                          'validation' => (string) '' Validation rule without 'required|'
     *                          'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                          'hidden' => (bool) false
     *                          'fillable' => (bool) true
     *                          'show_in_lists' => (bool) true
     *                          'extra_data' => (array)
     *                      ]
     */
    protected function addFieldDateTime($name, $label, $min = null, $max = null, $required = null, array $options = array())
    {
        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'type' => 'TIMESTAMP',
            'input_type' => 'datetime',
            'required' => $required,
            'validation' => 'date',
            'extra_data' => [
                'min' => $min,
                'max' => $max,
                'timezone' => 'UTC',
                'constraints' => false
            ]
        ], $options));
    }

    /**
     * Add a time field to the model (time input)
     *
     * @param string        $name
     * @param string        $label
     * @param null|string   $min            Minimum time allowed (H:i:s)
     * @param null|string   $max            Maximum time allowed (H:i:s)
     * @param null|int      $required
     * @param array         $options
     *                      [
     *                          'description' => (string) ''
     *                          'default' => (null|mixed) null
     *                          'validation' => (string) '' Validation rule without 'required|'
     *                          'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                          'hidden' => (bool) false
     *                          'fillable' => (bool) true
     *                          'show_in_lists' => (bool) true
     *                          'extra_data' => (array)
     *                      ]
     */
    protected function addFieldTime($name, $label, $min = null, $max = null, $required = null, array $options = array())
    {
        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'type' => 'TEXT',
            'input_type' => 'time',
            'required' => $required,
            'validation' => 'date_format:H:i:s',
            'extra_data' => [
                'min' => $min,
                'max' => $max,
                'timezone' => 'UTC'
            ]
        ], $options));
    }

    /**
     * Add a float field to the model (number input)
     *
     * @param string   $name
     * @param string   $label
     * @param null|int $required
     * @param array    $options
     *                 [
     *                     'description' => (string) ''
     *                     'default' => (null|mixed) null
     *                     'validation' => (string) 'numeric' Validation rule without 'required|'. Default 'numeric'
     *                     'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                     'hidden' => (bool) false
     *                     'fillable' => (bool) true
     *                     'show_in_lists' => (bool) true
     *                     'extra_data' => (array)
     *                 ]
     */
    protected function addFieldFloat($name, $label, $required = null, array $options = array())
    {
        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'type' => 'FLOAT',
            'input_type' => 'number',
            'required' => $required,
            'validation' => 'numeric'
        ], $options));
    }

    /**
     * Add an integer field to the model (number input)
     *
     * @param string   $name
     * @param string   $label
     * @param null|int $required
     * @param array    $options
     *                 [
     *                     'description' => (string) ''
     *                     'default' => (null|mixed) null
     *                     'validation' => (string) 'numeric' Validation rule without 'required|'. Default 'numeric'
     *                     'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                     'hidden' => (bool) false
     *                     'fillable' => (bool) true
     *                     'show_in_lists' => (bool) true
     *                     'extra_data' => (array) [
     *                         'min' => (int) null Define a minimum value,
     *                         'max' => (int) null Define a maximum value
     *                     ]
     *                 ]
     */
    protected function addFieldInt($name, $label, $required = null, array $options = array())
    {
        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'type' => 'INT',
            'input_type' => 'number',
            'required' => $required,
            'validation' => 'numeric'
        ], $options));
    }

    /**
     * Add a boolean field to the model (checkbox)
     *
     * @param string   $name
     * @param string   $label
     * @param null|int $required
     * @param array    $options
     *                 [
     *                     'description' => (string) ''
     *                     'default' => (null|mixed) null
     *                     'validation' => (string) 'numeric' Validation rule without 'required|'. Default 'numeric'
     *                     'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                     'hidden' => (bool) false
     *                     'fillable' => (bool) true
     *                     'show_in_lists' => (bool) true
     *                     'extra_data' => (array)
     *                 ]
     */
    protected function addFieldBool($name, $label, $required = null, array $options = array())
    {
        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'type' => 'BOOL',
            'input_type' => 'boolean',
            'required' => $required,
            'validation' => 'boolean',
            'show_label' => false
        ], $options));
    }

    /**
     * Add an option select to the model (select box)
     *
     * @param string        $name
     * @param string        $label
     * @param null|int      $required
     * @param array         $selectOptions        Options to select from (key => value)
     * @param array         $options
     *                      [
     *                          'description' => (string) ''
     *                          'default' => (null|mixed) null
     *                          'validation' => (string) '' Validation rule without 'required|'
     *                          'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                          'hidden' => (bool) false
     *                          'fillable' => (bool) true
     *                          'show_in_lists' => (bool) true
     *                          'extra_data' => (array) [
     *                              'multiple' => (boolean) false
     *                              'styling_format' => (string) 'table'|'tags' Styling option for multiple values
     *                          ]
     *                      ]
     */
    protected function addFieldSelect($name, $label, $required = null, array $selectOptions = array(), array $options = array())
    {
        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'type' => 'ENUM',
            'input_type' => 'select',
            'required' => $required,
            'validation' => 'in:' . implode(',', array_keys($selectOptions)),
            'extra_data' => [
                'options' => $selectOptions
            ]
        ], $options));
    }

    /**
     * Add an radio select to the model (radio buttons),
     * optionally add styling settings to make it look like toggle buttons
     *
     * @param string        $name
     * @param string        $label
     * @param null|int      $required
     * @param array         $selectOptions        Options to select from (key => value)
     * @param array         $options
     *                      [
     *                          'description' => (string) ''
     *                          'default' => (null|mixed) null
     *                          'validation' => (string) '' Validation rule without 'required|'
     *                          'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                          'hidden' => (bool) false
     *                          'fillable' => (bool) true
     *                          'show_in_lists' => (bool) true
     *                          'extra_data' => (array) [
     *                              'styling_format' => (string) 'buttons'|'toggle'|'normal'
     *                          ]
     *                      ]
     */
    protected function addFieldRadio($name, $label, $required = null, array $selectOptions = array(), array $options = array())
    {
        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'type' => 'ENUM',
            'input_type' => 'radio',
            'required' => $required,
            'validation' => 'in:' . implode(',', array_keys($selectOptions)),
            'extra_data' => [
                'options' => $selectOptions
            ]
        ], $options));
    }

    /**
     * Add an checkbox select to the model (checkbox inputs)
     *
     * @param string        $name
     * @param string        $label
     * @param null|int      $required
     * @param array         $selectOptions        Options to select from (key => value)
     * @param array         $options
     *                      [
     *                          'description' => (string) ''
     *                          'default' => (null|mixed) null
     *                          'validation' => (string) '' Validation rule without 'required|'
     *                          'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                          'hidden' => (bool) false
     *                          'fillable' => (bool) true
     *                          'show_in_lists' => (bool) true
     *                          'extra_data' => (array) [
     *                              'styling_format' => (string) 'normal'
     *                          ]
     *                      ]
     */
    protected function addFieldCheckbox($name, $label, $required = null, array $selectOptions = array(), array $options = array())
    {
        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'type' => 'ENUM',
            'input_type' => 'checkbox',
            'required' => $required,
            'validation_key' => $name . '.*',
            'validation' => 'in:' . implode(',', array_keys($selectOptions)),
            'extra_data' => [
                'options' => $selectOptions
            ]
        ], $options));
    }

    /**
     * Add a password field to the model (password input)
     *
     * @param string   $name
     * @param string   $label
     * @param null|int $required
     * @param array    $options
     *                 [
     *                     'description' => (string) ''
     *                     'default' => (null|mixed) null
     *                     'validation' => (string) '' Validation rule without 'required|'
     *                     'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                     'hidden' => (bool) false
     *                     'fillable' => (bool) true
     *                     'show_in_lists' => (bool) false
     *                     'extra_data' => (array)
     *                 ]
     */
    protected function addFieldPassword($name, $label, $required = null, array $options = array())
    {
        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'type' => 'TEXT',
            'input_type' => 'password',
            'required' => $required,
            'validation' => 'string',
            'show_in_lists' => false,
            'hidden_on' => [
                'view'
            ]
        ], $options));
    }

    /**
     * Add a password confirm field to the model (password input)
     *
     * @param string   $name
     * @param string   $label
     * @param string   $matchField Field name to validate against
     * @param array    $options
     *                 [
     *                     'description' => (string) ''
     *                     'default' => (null|mixed) null
     *                     'validation' => (string) '' Validation rule
     *                     'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                     'hidden' => (bool) false
     *                     'fillable' => (bool) false
     *                     'show_in_lists' => (bool) false
     *                     'extra_data' => (array)
     *                 ]
     */
    protected function addFieldPasswordConfirm($name, $label, $matchField, array $options = array())
    {
        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'type' => 'TEXT',
            'input_type' => 'password-confirmation',
            'required' => self::$FIELD_REQUIRED,
            'required_validation_override' => 'required_with:password',
            'validation' => 'same:' . $matchField,
            'fillable' => false,
            'show_in_lists' => false,
            'hidden_on' => [
                'view'
            ],
            'extra_data' => [
                'match_field' => $matchField
            ]
        ], $options));
    }

    /**
     * Add a HasMany relation field to the model
     *
     * @param string        $relationName
     * @param string        $label
     * @param null|int      $required
     * @param null|string   $fieldName     Optionally set a field name manually
     * @param array         $options
     *                      [
     *                          'description' => (string) ''
     *                          'default' => (null|mixed) null
     *                          'validation' => (string) 'numeric' Validation rule without 'required|'. Default 'numeric'
     *                          'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                          'hidden' => (bool) false
     *                          'fillable' => (bool) true
     *                          'show_in_lists' => (bool) false
     *                          'extra_data' => (array)
     *                      ]
     */
    protected function addFieldHasMany($relationName, $label, $required = null, $fieldName = null, array $options = array())
    {
        $relation = $this->getRelationType($relationName);

        $name = ($fieldName) ?: Str::snake($relation['method']);

        //Creates a relation tab with add/edit/delete in frontend (returns URL to endpoint for pagination)
        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'type' => 'HASMANY',
            'input_type' => 'relation-list',
            'required' => $required,
            'validation' => 'array',
            'group' => 'relations',
            'show_in_lists' => false,
            'fillable' => false,
            'extra_data' => [
                'relation' => $relation['method'],
                'related_to' => $relation['model'],
                'endpoint' => '/' . Str::lower(Str::plural(Str::kebab($relation['model'])))
            ]
        ], $options));
    }

    /**
     * Add a HasMany relation field (search select) to the model
     *
     * @param string        $relationName
     * @param string        $label
     * @param null|int      $required
     * @param string        $searchField
     * @param null|string   $fieldName     Optionally set a field name manually
     * @param array         $options
     *                      [
     *                          'description' => (string) ''
     *                          'default' => (null|mixed) null
     *                          'validation' => (string) '' Validation rule without 'required|'
     *                          'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                          'hidden' => (bool) false
     *                          'fillable' => (bool) true
     *                          'show_in_lists' => (bool) true
     *                          'extra_data' => (array)
     *                      ]
     */
    protected function addFieldHasManySelect($relationName, $label, $required = null, $searchField = 'title', $fieldName = null, array $options = array())
    {
        $relation = $this->getRelationType($relationName);

        $name = ($fieldName) ?: Str::snake($relation['method']);

        //Creates a relation tab with add/edit/delete in frontend (returns URL to endpoint for pagination)
        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'placeholder' => \Lang::get('actions.search') . '..',
            'type' => 'HASMANY',
            'input_type' => 'model-search',
            'required' => $required,
            'validation' => 'array',
            'fillable' => false,
            'extra_data' => [
                'multiple' => true,
                'prefetch' => false,
                'relation' => $relation['method'],
                'related_to' => $relation['model'],
                'parent_model' => class_basename($this),
                'search_field' => $searchField,
                'display_field' => $searchField,
                'extra_display_field' => false, // String of model field key to also show when displaying results
                'display_field_labels' => false, // Array of fieldKey => label to provide frontend proper labels
                'endpoint' => '/' . Str::lower(Str::plural(Str::kebab($relation['model'])))
            ]
        ], $options));
    }

    /**
     * Add a HasOne relation field to the model
     *
     * @param string        $relationName
     * @param string        $label
     * @param null|int      $required
     * @param string        $searchField
     * @param null|string   $fieldName     Optionally set a field name manually
     * @param array         $options
     *                      [
     *                          'description' => (string) ''
     *                          'default' => (null|mixed) null
     *                          'validation' => (string) '' Validation rule without 'required|'
     *                          'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                          'hidden' => (bool) false
     *                          'fillable' => (bool) true
     *                          'show_in_lists' => (bool) true
     *                          'extra_data' => (array)
     *                      ]
     */
    protected function addFieldHasOne($relationName, $label, $required = null, $searchField = 'title', $fieldName = null, array $options = array())
    {
        $relation = $this->getRelationType($relationName);

        $name = ($fieldName) ?: Str::snake($relation['method']) . '_id';

        //Creates ajax search select
        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'placeholder' => \Lang::get('actions.search') . '..',
            'type' => 'HASONE',
            'input_type' => 'model-search',
            'required' => $required,
            'validation' => 'numeric',
            'extra_data' => [
                'multiple' => false,
                'prefetch' => false,
                'relation' => $relation['method'],
                'related_to' => $relation['model'],
                'search_field' => $searchField,
                'display_field' => $searchField,
                'extra_display_field' => false, // String of model field key to also show when displaying results
                'display_field_labels' => false, // Array of fieldKey => label to provide frontend proper labels
                'endpoint' => '/' . Str::lower(Str::plural(Str::kebab($relation['model'])))
            ]
        ], $options));
    }

    /**
     * Add a ManyToMany relation field to the model
     *
     * @param string        $relationName
     * @param string        $label
     * @param null|int      $required
     * @param null|string   $fieldName     Optionally set a field name manually
     * @param array         $options
     *                      [
     *                          'description' => (string) ''
     *                          'default' => (null|mixed) null
     *                          'validation' => (string) 'numeric' Validation rule without 'required|'. Default 'numeric'
     *                          'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                          'hidden' => (bool) false
     *                          'fillable' => (bool) true
     *                          'show_in_lists' => (bool) false
     *                          'extra_data' => (array)
     *                      ]
     */
    protected function addFieldManyToMany($relationName, $label, $required = null, $fieldName = null, array $options = array())
    {
        $relation = $this->getRelationType($relationName);

        $name = ($fieldName) ?: Str::snake($relation['method']);

        //Creates a relation tab with add/edit/delete in frontend (returns URL to endpoint for pagination)
        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'type' => 'MANYTOMANY',
            'input_type' => 'relation-list',
            'required' => $required,
            'validation' => 'array',
            'group' => 'relations',
            'show_in_lists' => false,
            'fillable' => false,
            'extra_data' => [
                'relation' => $relation['method'],
                'related_to' => $relation['model'],
                'endpoint' => '/' . Str::lower(Str::plural(Str::kebab($relation['model'])))
            ]
        ], $options));
    }

    /**
     * Add an image upload field
     *
     * @param string        $name
     * @param string        $label
     * @param boolean       $multiple
     * @param null|int      $required
     * @param array         $options
     *                      [
     *                          'description' => (string) ''
     *                          'default' => (null|mixed) null
     *                          'validation' => (string) 'numeric' Validation rule without 'required|'. Default 'numeric'
     *                          'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                          'hidden' => (bool) false
     *                          'fillable' => (bool) true
     *                          'show_in_lists' => (bool) false
     *                          'extra_data' => (array)
     *                      ]
     */
    protected function addFieldImageUpload($name, $label, $multiple = true, $required = null, array $options = array())
    {
        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'type' => 'MEDIA',
            'default' => [],
            'input_type' => 'image-upload',
            'required' => $required,
            'validation' => 'array',
            'show_in_lists' => false,
            'fillable' => false,
            'extra_data' => [
                'media_type' => 'images',
                'multiple' => $multiple,
                'collection' => $name,
                'mime_types' => (property_exists($this, 'allowedImageMimeTypes'))
                    ? $this->allowedImageMimeTypes : ['image/jpeg'],
                'tus' => false,
                'tus_endpoint' => ''
            ]
        ], $options));
    }

    /**
     * Add a file upload field
     *
     * @param string        $name
     * @param string        $label
     * @param boolean       $multiple
     * @param null|int      $required
     * @param array         $options
     *                      [
     *                          'description' => (string) ''
     *                          'default' => (null|mixed) null
     *                          'validation' => (string) 'numeric' Validation rule without 'required|'. Default 'numeric'
     *                          'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                          'hidden' => (bool) false
     *                          'fillable' => (bool) true
     *                          'show_in_lists' => (bool) false
     *                          'extra_data' => (array)
     *                      ]
     */
    protected function addFieldFileUpload($name, $label, $multiple = true, $required = null, array $options = array())
    {
        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'type' => 'MEDIA',
            'default' => [],
            'input_type' => 'file-upload',
            'required' => $required,
            'validation' => 'array',
            'show_in_lists' => false,
            'fillable' => false,
            'extra_data' => [
                'media_type' => 'files',
                'multiple' => $multiple,
                'collection' => $name,
                'mime_types' => (property_exists($this, 'allowedFileMimeTypes')) ? $this->allowedFileMimeTypes : ['*'],
                'tus' => false,
                'tus_endpoint' => ''
            ]
        ], $options));
    }

    /**
     * Adds a nested JSON field
     *
     * @param string        $name
     * @param string        $label
     * @param null|int      $required
     * @param bool          $useNestedGroups
     * @param Closure       $fields
     * @param array         $options
     *                      [
     *                          'description' => (string) ''
     *                          'default' => (null|mixed) null
     *                          'validation' => (string) '' Validation rule without 'required|'
     *                          'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                          'hidden' => (bool) false
     *                          'fillable' => (bool) true
     *                          'show_in_lists' => (bool) false
     *                          'extra_data' => (array)
     *                      ]
     */
    protected function addFieldJSON($name, $label, $required = null, $useNestedGroups = false, $fields, array $options = array())
    {
        if ($fields instanceof Closure) {
            $fieldData = $this->processNestedInstance($name, $useNestedGroups, $fields);
        }

        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'type' => 'JSON',
            'input_type' => 'nested-fields',
            'required' => $required,
            'show_in_lists' => false,
            'rules' => $fieldData['rules'],
            'extra_data' => [
                'fields' => $fieldData['fields']
            ]
        ], $options));
    }

    /**
     * Adds a repeatable nested JSON field,
     * declared fields will be appended to its own row in an array
     *
     * @param string        $name
     * @param string        $label
     * @param null|int      $required
     * @param bool          $useNestedGroups
     * @param Closure       $fields
     * @param array         $options
     *                      [
     *                          'description' => (string) ''
     *                          'default' => (null|mixed) null
     *                          'validation' => (string) '' Validation rule without 'required|'
     *                          'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                          'hidden' => (bool) false
     *                          'fillable' => (bool) true
     *                          'show_in_lists' => (bool) false
     *                          'extra_data' => (array)
     *                      ]
     */
    protected function addFieldRepeatableJSON($name, $label, $required = null, $useNestedGroups = false, $fields, array $options = array())
    {
        if ($fields instanceof Closure) {
            $fieldData = $this->processNestedInstance($name, $useNestedGroups, $fields, true);
        }

        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'type' => 'JSON',
            'input_type' => 'repeatable-nested-fields',
            'required' => $required,
            'show_in_lists' => false,
            'rules' => $fieldData['rules'],
            'extra_data' => [
                'fields' => $fieldData['fields']
            ]
        ], $options));
    }

    /**
     * Adds a meta relation to add nested fields to
     *
     * @param string        $name
     * @param string        $label
     * @param string        $relationName
     * @param bool          $useNestedGroups
     * @param Closure       $fields
     * @param array         $options
     *                      [
     *                          'description' => (string) ''
     *                          'default' => (null|mixed) null
     *                          'validation' => (string) '' Validation rule without 'required|'
     *                          'group' => (null|string) null Field group, must correspond with the added groups in the models' $GROUPS
     *                          'hidden' => (bool) false
     *                          'fillable' => (bool) true
     *                          'show_in_lists' => (bool) false
     *                          'extra_data' => (array)
     *                      ]
     */
    protected function addFieldMeta($name, $label, $relationName, $useNestedGroups = false, $fields, array $options = array())
    {
        if ($fields instanceof Closure) {
            $fieldData = $this->processNestedInstance($name, $useNestedGroups, $fields);
        }

        $this->addField(array_replace_recursive([
            'name' => $name,
            'label' => $label,
            'type' => 'META',
            'input_type' => 'nested-fields',
            'required' => self::$FIELD_OPTIONAL,
            'show_in_lists' => false,
            'fillable' => false,
            'rules' => $fieldData['rules'],
            'extra_data' => [
                'relation' => $relationName,
                'fields' => $fieldData['fields']
            ]
        ], $options));
    }

    /**
     * Executes closure for nested field instance and mutates the result to fit the parent structure
     *
     * @param string  $parentName
     * @param bool    $useNestedGroups
     * @param Closure $callable
     * @param bool    $isRepeatable
     *
     * @return array
     */
    private function processNestedInstance($parentName, $useNestedGroups, Closure $callable, $isRepeatable = false)
    {
        $callable($Model = $this->generateNestedInstance());

        $rules = [];
        if (!empty($Model->rules)) {
            foreach ($Model->rules as $key => $rule) {
                $validationKey = ($isRepeatable) ? ($parentName . '.*.' . $key) : ($parentName . '.' . $key);
                $this->rules[$validationKey] = $rule;
            }
        }

        $fields = [];
        $definition = $Model->definition;

        if ($useNestedGroups) {
            $fields = $definition['field_groups'];
        } else {
            foreach ($definition['field_groups'] as $group) {
                $fields = array_merge($fields, $group['fields']);
            }
        }

        return [
            'rules' => $rules,
            'fields' => $fields
        ];
    }

    /**
     * Generates a new instance from current one, with cleaned field properties
     *
     * @return FieldDefinition
     */
    private function generateNestedInstance()
    {
        $NestedInstance = clone $this;
        $NestedInstance->nested = true;
        $NestedInstance->rules = [];
        $NestedInstance->fillable = [];
        $NestedInstance->hidden = [];
        $NestedInstance->visible = [];
        $NestedInstance->setFieldDefinition([
            'model_info' => [],
            'field_groups' => []
        ], false);
        return $NestedInstance;
    }

    /**
     * Retrieve this models' field definition
     *
     * @return array
     */
    public function getFieldDefinition()
    {
        $cachedDefinition = config('bread.model_definitions.' . strtolower(class_basename($this)));

        if (!$cachedDefinition) {
            $definition = $cachedDefinition;
        } else {
            $this->compileDefinition();
            $definition = $this->definition;
        }

        return $definition;
    }

    /**
     * Retrieve this models flat field definition
     *
     * @return array
     */
    public function getFlatFieldDefinition()
    {
        if (empty($this->flatFieldDefinition)) {
            $this->compileDefinition(true);
        }

        return $this->flatFieldDefinition;
    }

    /**
     * Returns remote relation fields (hasMany, ManyToMany etc)
     *
     * @return array
     */
    public function getRemoteRelationFields()
    {
        $flatDefinition = $this->getFlatFieldDefinition();

        return array_filter($flatDefinition, static function ($field) {
            return \in_array($field['type'], ['HASMANY', 'MANYTOMANY']);
        });
    }

    /**
     * Manually set the definition of the model
     *
     * @param array $definition
     * @param bool $cache
     */
    public function setFieldDefinition(array $definition, $cache = true)
    {
        if (empty($definition)) {
            $definition = [
                'model_info' => [],
                'field_groups' => [],
                'guards' => [],
                'rules' => []
            ];
        }

        if ($cache) {
            config([
                'bread.model_definitions.' . strtolower(class_basename($this)) => $definition
            ]);
        }

        $this->setFieldGuards($definition);
        $this->setFieldRules($definition);

        $this->definition = $definition;
    }

    public function setFieldGuards($definition)
    {
        if (isset($definition['guards']) && !empty($definition['guards'])) {
            foreach ($definition['guards'] as $guardType => $fields) {
                if (!empty($fields)) {
                    foreach ($fields as $field) {
                        if (!in_array($field, $this->{$guardType}, true)) {
                            $this->{$guardType}[] = $field;
                        }
                    }
                }
            }
        }
    }

    public function setFieldRules($definition)
    {
        if (isset($definition['rules']) && !empty($definition['rules'])) {
            $this->rules = array_replace($definition['rules'], $this->rules);
        }
    }

    /**
     * Get default values for fields
     *
     * @param null|string $fieldName Optionally get default value for a specific field, null if all
     *
     * @return array|mixed|null
     */
    public function getFieldDefaults($fieldName = null)
    {
        if (empty($this->flatFieldDefinition)) {
            $this->compileDefinition();
        }

        if (!$fieldName) {
            //Return all
            $defaults = [];

            foreach ($this->flatFieldDefinition as $key => $definition) {
                $defaults[$key] = $definition['default'];
            }

            return $defaults;
        }

        //Return default for specific field
        if (isset($this->flatFieldDefinition[$fieldName])) {
            return $this->flatFieldDefinition[$fieldName]['default'];
        }

        return null;
    }

    /**
     * Returns array with relation type as string
     * (BelongsToMany, HasMany, BelongsTo etc), related model and relation method name
     *
     * @param string $relationName
     *
     * @return array
     */
    public function getRelationType($relationName)
    {
        $method = $relationName;

        if (!method_exists($this, $relationName)) {
            $method = Str::plural($relationName);
        }

        $relation = $this->{$method}();

        return [
            'method' => $method,
            'model' => class_basename($relation->getModel()),
            'type'   => class_basename($relation)
        ];
    }

}
