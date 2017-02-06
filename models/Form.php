<?php

/**
 * Created by PhpStorm.
 * User: moridrin
 * Date: 29-1-17
 * Time: 15:42
 */
class Form
{
    #region variables
    /** @var Field[] $fields */
    public $fields;
    /** @var array|User $values */
    public $values;
    /** @var array $values */
    public $errors;
    /** @var User $values */
    public $user;
    #endregion

    #region Construct($fields)
    /**
     * Form constructor.
     *
     * @param Field[] $fields
     */
    public function __construct($fields = array())
    {
        $this->fields = $fields;
        if (isset($_GET['member']) && User::isBoard()) {
            $this->user = User::getByID($_GET['member']);
        } else {
            $this->user = User::getCurrent();
        }
    }
    #endregion

    #region fromDatabase()
    /**
     * This function gets all the Fields from the post metadata.
     *
     * @param bool $setValues
     *
     * @return Form|Message
     */
    public static function fromDatabase($setValues = true)
    {
        $form = new Form();
        global $post;
        if (!$post) {
            return $form;
        }
        if ($setValues) {
            if (isset($_GET['member']) && User::isBoard()) {
                $user = User::getByID($_GET['member']);
                if (!$user) {
                    echo (new Message('User not found.', Message::ERROR_MESSAGE))->getHTML();
                    return $form;
                }
            } else {
                $user = User::getCurrent();
            }
        } else {
            $user = false;
        }
        global $wpdb;
        $table  = SSV_General::CUSTOM_FIELDS_TABLE;
        $postID = $post->ID;
        $fields = $wpdb->get_results("SELECT customField FROM $table WHERE postID = $postID");
        foreach ($fields as $field) {
            $field = Field::fromJSON($field->customField);
            if ($user) {
                if ($field instanceof TabField) {
                    foreach ($field->fields as $childField) {
                        if ($childField instanceof InputField) {
                            $childField->setValue($user->getMeta($childField->name));
                        }
                    }
                } elseif ($field instanceof InputField) {
                    $field->setValue($user->getMeta($field->name));
                }
            }
            $form->fields[] = $field;
        }
        return $form;
    }
    #endregion

    #region addFields($fields)
    /**
     * @param Field[]|Field $fields
     * @param bool          $atEnd if set to false, this will append the fields at the start of the array.
     */
    public function addFields($fields, $atEnd = true)
    {
        if ($fields instanceof Field) {
            $fields = array($fields);
        } elseif (!is_array($fields)) {
            return;
        }
        if ($atEnd) {
            $this->fields = array_merge($this->fields, $fields);
        } else {
            $this->fields = array_merge($fields, $this->fields);
        }
    }
    #endregion

    #region setValues($values)
    /**
     * @param null|array $values if set to null it uses form->user variable.
     */
    public function setValues($values = null)
    {
        if ($values == null) {
            $values = $this->user;
            $values = $values ?: array();
        }
        $this->values = $values;
        $this->loopRecursive(
            function ($field) {
                if ($field instanceof InputField) {
                    $field->setValue($this->values);
                }
            }
        );
    }
    #endregion

    #region getEditor($allowTabs)
    /**
     * @param bool $allowTabs if set true it will display the select option for tab in the Field Type
     *
     * @return string HTML
     */
    public function getEditor($allowTabs)
    {
        ob_start();
        ?>
        <div style="overflow-x: auto;">
            <table id="custom-fields-placeholder" class="sortable"></table>
            <button type="button" onclick="mp_ssv_add_new_custom_field()">Add Field</button>
        </div>
        <script>
            i = <?= Field::getMaxID($this->fields) + 1 ?>;
            mp_ssv_sortable_table('custom-fields-placeholder');
            function mp_ssv_add_new_custom_field() {
                mp_ssv_add_new_field('input', 'text', i, null, <?= $allowTabs ? 'true' : 'false' ?>);
                i++;
            }
            <?php foreach($this->fields as $field): ?>
            mp_ssv_add_new_field('<?= $field->fieldType ?>', '<?= isset($field->inputType) ? $field->inputType : '' ?>', <?= $field->id ?>, <?= $field->toJSON() ?>, <?= $allowTabs ? 'true' : 'false' ?>);
            <?php endforeach; ?>
        </script>
        <?php
        return ob_get_clean();
    }
    #endregion

    #region saveEditorFromPost()
    /**
     * This function removes the old fields from the database and inserts the new fields.
     */
    public static function saveEditorFromPost()
    {
        global $wpdb;
        global $post;
        if (!$post) {
            return;
        }
        $form              = new Form();
        $customFieldValues = array();
        $id                = 0;
        $fieldID           = 0;
        $prefix            = 'custom_field_';
        /** @var TabField $currentTab */
        $currentTab = null;
        foreach ($_POST as $key => $value) {
            if (strpos($key, $prefix) !== false) {
                if (strpos($key, '_start') !== false) {
                    $customFieldValues = array();
                    $fieldID           = str_replace($prefix, '', str_replace('_start', '', $key));
                }
                $fieldKey                     = str_replace($fieldID . '_', '', str_replace($prefix, '', $key));
                $customFieldValues[$fieldKey] = $fieldKey == 'id' ? $id : SSV_General::sanitize($value);
                if (strpos($key, '_end') !== false) {
                    $field = Field::fromJSON(json_encode($customFieldValues));
                    if (!empty($field->title)) {
                        if ($field instanceof TabField) {
                            $currentTab        = $field;
                            $form->fields[$id] = $field;
                        } elseif ($currentTab != null) {
                            $currentTab->addField($id, $field);
                        } else {
                            $form->fields[$id] = $field;
                        }
                        $id++;
                    }
                }
            }
        }

        $wpdb->delete(
            SSV_General::CUSTOM_FIELDS_TABLE,
            array(
                'postID' => $post->ID,
            )
        );
        foreach ($form->fields as $field) {
            $wpdb->insert(
                SSV_General::CUSTOM_FIELDS_TABLE,
                array(
                    'postID'      => $post->ID,
                    'customField' => $field->toJSON(),
                )
            );
        }
    }
    #endregion

    #region getHTML($adminReferer, $buttonText = 'save')
    /**
     * @param string $adminReferer is the admin referer for the form.
     * @param string $buttonText   is the text on the submit button (default = 'save').
     *
     * @return string the field as HTML object.
     */
    public function getHTML($adminReferer, $buttonText = 'save')
    {
        $tabs = array();
        $html = '';
        /** @var Field $field */
        foreach ($this->fields as $field) {
            if ($field instanceof TabField) {
                $tabs[] = $field;
            } elseif (empty($tabs)) {
                $html .= $field->getHTML();
            }
        }
        if (empty($tabs)) {
            ob_start();
            ?>
            <form action="#" method="POST" enctype="multipart/form-data">
                <?= $html ?>
                <button type="submit" name="submit" class="btn waves-effect waves-light btn waves-effect waves-light--primary"><?= $buttonText ?></button
                <?= SSV_General::getFormSecurityFields($adminReferer, false, false); ?>
            </form>
            <?php
            $html = ob_get_clean();
        } else {
            $tabsHTML        = '<ul class="tabs">';
            $tabsContentHTML = '';
            /** @var TabField $tab */
            foreach ($tabs as $tab) {
                $tabsHTML .= $tab->getHTML();
                ob_start();
                ?>
                <div id="<?= $tab->name ?>">
                    <form action="#" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="tab" value="<?= $tab->id ?>">
                        <?php foreach ($tab->fields as $childField): ?>
                            <?= $childField->getHTML() ?>
                        <?php endforeach; ?>
                        <button type="submit" name="submit" class="btn waves-effect waves-light btn waves-effect waves-light--primary"><?= $buttonText ?></button
                        <?= SSV_General::getFormSecurityFields($adminReferer, false, false); ?>
                    </form>
                </div>
                <?php
                $tabsContentHTML .= ob_get_clean();
            }
            $tabsHTML .= '</ul>';
            $html .= $tabsHTML . $tabsContentHTML;
        }
        return $html;
    }

    #endregion

    #region getInputFields()
    /**
     * @return InputField[]
     */
    public function getInputFields()
    {
        return $this->loopRecursive(
            function ($field) {
                if ($field instanceof InputField) {
                    return $field;
                } else {
                    return null;
                }
            }
        );
    }
    #endregion

    #region isValid($tabId)
    /**
     * @param int|null $tabID if set it will only check the fields inside that tab.
     *
     * @return array|bool array of errors or true if no errors.
     */
    public function isValid($tabID = null)
    {
        $this->errors = array();
        $this->loopRecursive(
            function ($field) {
                if ($field instanceof InputField) {
                    $errors = $field->isValid();
                    if ($errors !== true) {
                        $this->errors = $this->errors + $errors;
                    }
                }
            },
            $tabID
        );
        return empty($this->errors) ? true : $this->errors;
    }
    #endregion

    #region save($tabId)
    /**
     * This function saves all the field values to the user meta.
     * This function does not validate fields.
     *
     * @param int|null $tabID if set it will only save the fields inside that tab.
     *
     * @return Message[]
     */
    public function save($tabID = null)
    {
        //Fields
        $messages = $this->loopRecursive(
            function ($field) {
                if ($field instanceof ImageInputField) {
                    //Do Nothing
                } elseif ($field instanceof InputField) {
                    if (!$field->isDisabled() || User::isBoard()) {
                        return $this->user->updateMeta($field->name, $field->value);
                    }
                }
                return true;
            },
            $tabID
        );

        //Files
        foreach ($_FILES as $name => $file) {
            if ($file['size'] == 0) {
                continue;
            }
            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }
            $overrides     = array('test_form' => false, 'mimes' => array('jpg' => 'image/jpg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'png' => 'image/png'));
            $file_location = wp_handle_upload($file, $overrides);
            if ($file_location && !isset($file_location['error'])) {
                $currentURL      = $this->user->getMeta($name);
                $currentLocation = $this->user->getMeta($name . '_path');
                if ($currentURL != '' && starts_with($currentURL, SSV_General::BASE_URL) && file_exists($currentLocation)) {
                    unlink($currentLocation);
                }
                $this->user->updateMeta($name, $file_location["url"]);
                $this->user->updateMeta($name . '_path', $file_location["file"]);
            } else {
                $messages[] = new Message($file_location['error'], User::isBoard() ? Message::SOFT_ERROR_MESSAGE : Message::ERROR_MESSAGE);
            }
        }

        $messages = array_diff($messages, array(true));
        return $messages;
    }
    #endregion

    #region getValue($name)
    /**
     * @param string $name of the field to return the value
     *
     * @return string|null
     */
    public function getValue($name)
    {
        $values = $this->loopRecursive(
            function ($field, $args) {
                if ($field instanceof InputField) {
                    if ($field->name == $args['field_name']) {
                        return $field->value;
                    }
                }
                return null;
            },
            null,
            array('field_name' => $name)
        );
        return count($values) ? reset($values) : null;
    }
    #endregion

    #region getInputFieldProperty($_property)
    /**
     * @param string $_property
     *
     * @return string[] array of all properties for the fields that have that property
     */
    public function getFieldProperty($_property)
    {
        global $property;
        $property   = $_property;
        $properties = $this->loopRecursive(
            function ($field) {
                global $property;
                if (isset($field->$property)) {
                    return $field->$property;
                }
                return null;
            }
        );
        return $properties;
    }

    #endregion

    #region getEmail($_hidePasswordFields)
    /**
     * @param bool $_hidePasswordFields if true, the passwords will be replaced with ******.
     *
     * @return string email body in HTML.
     */
    public function getEmail($_hidePasswordFields = true)
    {
        global $hidePasswordFields;
        $hidePasswordFields = $_hidePasswordFields;
        $rows               = $this->loopRecursive(
            function ($field) {
                if ($field instanceof TabField) {
                    return '<tr><td colspan="2"><h1>' . $field->title . '</h1></td></tr>';
                } elseif ($field instanceof HeaderField) {
                    return '<tr><td colspan="2"><h3>' . $field->title . '</h3></td></tr>';
                } elseif ($field instanceof InputField) {
                    if ($field->name == 'password_confirm') {
                        return null;
                    }
                    global $hidePasswordFields;
                    if ($hidePasswordFields && $field->name == 'password') {
                        return '<tr><td>' . $field->title . '</td><td>********</td></tr>';
                    } else {
                        return '<tr><td>' . $field->title . '</td><td>' . $field->value . '</td></tr>';
                    }
                } elseif ($field instanceof LabelField) {
                    return '<tr><td>' . $field->text . '</td></tr>';
                }
                return null;
            }
        );
        return '<table>' . implode('', $rows) . '</table>';
    }
    #endregion

    #region loopRecursive($callback)
    /**
     * This function runs the callable for all fields (including all the sub-fields in tabs).
     *
     * @param callable $callback The function to be called with the field as parameter.
     * @param int|null $tabID    if set it will only run the callback on the fields inside that tab.
     * @param array    $args
     *
     * @return array
     */
    public function loopRecursive($callback, $tabID = null, $args = array())
    {
        $return = array();
        /** @var Field $field */
        foreach ($this->fields as $field) {
            if ($tabID === null) {
                $return[] = $callback($field, $args);
            } elseif ($field->id != $tabID) {
                continue;
            }
            if ($field instanceof TabField) {
                foreach ($field->fields as $childField) {
                    $return[] = $callback($childField, $args);
                }
            }
        }
        $return = array_diff($return, array(null));
        return $return;
    }
    #endregion
}
