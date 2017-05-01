<?php
namespace mp_ssv_general\custom_fields\input_fields;
use DateTime;
use Exception;
use mp_ssv_general\custom_fields\InputField;
use mp_ssv_general\Message;
use mp_ssv_general\SSV_General;
use mp_ssv_general\User;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Created by PhpStorm.
 * User: moridrin
 * Date: 10-1-17
 * Time: 12:03
 */
class RoleInputField extends InputField
{
    const INPUT_TYPE = 'role';

    /** @var string $defaultValue */
    public $defaultValue;

    /**
     * CustomInputField constructor.
     *
     * @param int    $id
     * @param string $title
     * @param string $name
     * @param string $defaultValue
     * @param string $class
     * @param string $style
     * @param string $overrideRight
     */
    protected function __construct($id, $title, $name, $defaultValue, $class, $style, $overrideRight)
    {
        parent::__construct($id, $title, self::INPUT_TYPE, $name, $class, $style, $overrideRight);
        $this->defaultValue = $defaultValue;
    }

    /**
     * @param string $json
     *
     * @return RoleInputField
     * @throws Exception
     */
    public static function fromJSON($json)
    {
        $values = json_decode($json);
        return new RoleInputField(
            $values->id,
            $values->title,
            $values->name,
            $values->default_value,
            $values->class,
            $values->style,
            $values->override_right
        );
    }

    /**
     * @param bool $encode
     *
     * @return string the class as JSON object.
     */
    public function toJSON($encode = true)
    {
        $values = array(
            'id'             => $this->id,
            'title'          => $this->title,
            'field_type'     => $this->fieldType,
            'input_type'     => $this->inputType,
            'name'           => $this->name,
            'default_value'  => $this->defaultValue,
            'class'          => $this->class,
            'style'          => $this->style,
            'override_right' => $this->overrideRight,
        );
        if ($encode) {
            $values = json_encode($values);
        }
        return $values;
    }

    /**
     * @param $overrideRight
     *
     * @return string the field as HTML object.
     */
    public function getHTML($overrideRight)
    {
        if ($this->defaultValue == 'NOW') {
            $this->defaultValue = (new DateTime('NOW'))->format('Y-m-d');
        }
        $value       = isset($this->value) ? $this->value : $this->defaultValue;
        $inputType   = 'type="' . esc_html($this->inputType) . '"';
        $name        = 'name="' . esc_html($this->name) . '"';
        $class       = !empty($this->class) ? 'class="' . esc_html($this->class) . '"' : '';
        $style       = !empty($this->style) ? 'style="' . esc_html($this->style) . '"' : '';
        $value       = !empty($value) ? 'value="' . esc_html($value) . '"' : '';
        $disabled    = disabled(current_user_can('edit_roles'), true, false);

        if (isset($overrideRight) && current_user_can($overrideRight)) {
            $disabled = '';
        }

        ob_start();
        if (current_theme_supports('materialize')) {
            ?>
            <div>
                <label for="<?= esc_html($this->id) ?>"><?= esc_html($this->title) ?></label>
                <input <?= $inputType ?> id="<?= esc_html($this->id) ?>" <?= $name ?> <?= $class ?> <?= $style ?> <?= $value ?> <?= $disabled ?>/>
            </div>
            <?php
        }

        return trim(preg_replace('/\s\s+/', ' ', ob_get_clean()));
    }

    /**
     * @return string the filter for this field as HTML object.
     */
    public function getFilterRow()
    {
        ob_start();
        ?><input id="<?= esc_html($this->id) ?>" type="<?= esc_html($this->inputType) ?>" name="<?= esc_html($this->name) ?>" title="<?= esc_html($this->title) ?>"/><?php
        return $this->getFilterRowBase(ob_get_clean());
    }

    /**
     * @return Message[]|bool array of errors or true if no errors.
     */
    public function isValid()
    {
        $errors = array();
        if (($this->required && !$this->disabled) && empty($this->value)) {
            $errors[] = new Message($this->title . ' field is required but not set.', current_user_can($this->overrideRight) ? Message::SOFT_ERROR_MESSAGE : Message::ERROR_MESSAGE);
        }
        switch (strtolower($this->inputType)) {
            case 'iban':
                $this->value = str_replace(' ', '', strtoupper($this->value));
                if (!empty($this->value) && !SSV_General::isValidIBAN($this->value)) {
                    $errors[] = new Message($this->title . ' field is not a valid IBAN.', current_user_can($this->overrideRight) ? Message::SOFT_ERROR_MESSAGE : Message::ERROR_MESSAGE);
                }
                break;
            case 'email':
                if (!filter_var($this->value, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = new Message($this->title . ' field is not a valid email.', current_user_can($this->overrideRight) ? Message::SOFT_ERROR_MESSAGE : Message::ERROR_MESSAGE);
                }
                break;
            case 'url':
                if (!filter_var($this->value, FILTER_VALIDATE_URL)) {
                    $errors[] = new Message($this->title . ' field is not a valid url.', current_user_can($this->overrideRight) ? Message::SOFT_ERROR_MESSAGE : Message::ERROR_MESSAGE);
                }
                break;
        }
        return empty($errors) ? true : $errors;
    }
}
