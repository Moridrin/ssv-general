<?php

/**
 * Created by PhpStorm.
 * User: moridrin
 * Date: 6-1-17
 * Time: 6:38
 */
class LabelField extends Field
{
    const FIELD_TYPE = 'label-field';

    public $text;

    /**
     * TabField constructor.
     *
     * @param $id
     * @param $title
     * @param $text
     */
    protected function __construct($id, $title, $text)
    {
        parent::__construct($id, $title, self::FIELD_TYPE);
        $this->text = $text;
    }

    /**
     * @param $json
     *
     * @return LabelField
     * @throws Exception
     */
    public static function fromJSON($json)
    {
        $values = json_decode($json);
        if ($values->fieldType != self::FIELD_TYPE) {
            throw new Exception('Incorrect field type');
        }
        return new LabelField(
            $values->id,
            $values->title,
            $values->text
        );
    }

    /**
     * @return string the class as JSON object.
     */
    public function toJSON()
    {
        $values = array(
            $this->id,
            $this->title,
            $this->fieldType,
            $this->text,
        );
        return json_encode($values);
    }
}