<?php

/**
 * Created by PhpStorm.
 * User: moridrin
 * Date: 6-1-17
 * Time: 6:38
 */
class InputField extends Field
{
    const FIELD_TYPE = 'input';

    public $inputType;
    public $name;
    public $required;
    public $display;
    public $defaultValue;
    public $placeholder;
    public $class;
    public $style;

    /**
     * InputField constructor.
     *
     * @param $id
     * @param $title
     * @param $inputType
     * @param $name
     * @param $required
     * @param $display
     * @param $defaultValue
     * @param $placeholder
     * @param $class
     * @param $style
     */
    protected function __construct($id, $title, $inputType, $name, $required, $display, $defaultValue, $placeholder, $class, $style)
    {
        parent::__construct($id, $title, self::FIELD_TYPE);
        $this->inputType    = $inputType;
        $this->name         = $name;
        $this->required     = $required;
        $this->display      = $display;
        $this->defaultValue = $defaultValue;
        $this->placeholder  = $placeholder;
        $this->class        = $class;
        $this->style        = $style;
    }

    /**
     * @param $json
     *
     * @return InputField
     * @throws Exception
     */
    public static function fromJSON($json)
    {
        $values = json_decode($json);
        if ($values->field_type != self::FIELD_TYPE) {
            throw new Exception('Incorrect field type');
        }
        return new InputField(
            $values->id,
            $values->title,
            $values->input_type,
            $values->name,
            $values->required,
            $values->display,
            $values->default_value,
            $values->placeholder,
            $values->class,
            $values->style
        );
    }

    /**
     * @return string the class as JSON object.
     */
    public function toJSON()
    {
        $values = array(
            'id' => $this->id,
            'title' => $this->title,
            'field_type' => $this->fieldType,
            'input_type' => $this->inputType,
            'name' => $this->name,
            'required' => $this->required,
            'display' => $this->display,
            'default_value' => $this->defaultValue,
            'placeholder' => $this->placeholder,
            'class' => $this->class,
            'style' => $this->style,
        );
        return json_encode($values);
    }
}