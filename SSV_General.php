<?php

/**
 * Created by PhpStorm.
 * User: moridrin
 * Date: 26-1-17
 * Time: 15:50
 */
class SSV_General
{
    #region Constants
    const PATH = SSV_GENERAL_PATH;
    const URL = SSV_GENERAL_URL;

    const BASE_URL = SSV_GENERAL_BASE_URL;

    const HOOK_USER_PROFILE_URL = 'ssv_general__hook_profile_url';
    const HOOK_GENERAL_OPTIONS_PAGE_CONTENT = 'ssv_general__hook_general_options_page_content';
    const HOOK_RESET_OPTIONS = 'ssv_general__hook_reset_options';

    const OPTION_BOARD_ROLE = 'ssv_general__board_role';
    const OPTION_CUSTOM_FIELD_FIELDS = 'ssv_general__custom_field_fields';
    const OPTIONS_ADMIN_REFERER = 'ssv_general__options_admin_referer';
    #endregion

    #region _init()
    private static $initialized = false;

    public static function _init()
    {
        if (!self::$initialized) {
            require_once 'functions.php';
            require_once 'options/options.php';
            require_once 'models/User.php';
            require_once 'models/Message.php';
            require_once 'models/Form.php';
            self::$initialized = true;
        }
    }
    #endregion

    #region resetOptions()
    /**
     * This function sets all the options for this plugin back to their default value
     */
    public static function resetOptions()
    {
        update_option(self::OPTION_BOARD_ROLE, 'administrator');
        $defaultSelected = json_encode(array('display', 'default', 'placeholder'));
        update_option(SSV_General::OPTION_CUSTOM_FIELD_FIELDS, SSV_General::sanitize($defaultSelected));
    }
    #endregion

    #region Tools

    #region redirect($location)
    /**
     * This function can be called from anywhere and will redirect the page to the given location.
     *
     * @param string $location is the url where the page should be redirected to.
     */
    public static function redirect($location)
    {
        $redirect_script = '<script type="text/javascript">';
        $redirect_script .= 'window.location = "' . $location . '"';
        $redirect_script .= '</script>';
        echo $redirect_script;
    }
    #endregion

    #region isValidPOST($adminReferer)
    /**
     * @param $adminReferer
     *
     * @return bool true if the request is POST, it isn't a reset request and it has the correct admin referer.
     */
    public static function isValidPOST($adminReferer)
    {
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            return false;
        }
        if (!check_admin_referer($adminReferer)) {
            return false;
        }
        return true;
    }
    #endregion

    #region isValidIBAN($iban)
    public static function isValidIBAN($iban)
    {
        $iban      = strtolower(str_replace(' ', '', $iban));
        $Countries = array('al' => 28, 'ad' => 24, 'at' => 20, 'az' => 28, 'bh' => 22, 'be' => 16, 'ba' => 20, 'br' => 29, 'bg' => 22, 'cr' => 21, 'hr' => 21, 'cy' => 28, 'cz' => 24, 'dk' => 18, 'do' => 28, 'ee' => 20, 'fo' => 18, 'fi' => 18, 'fr' => 27, 'ge' => 22, 'de' => 22, 'gi' => 23, 'gr' => 27, 'gl' => 18, 'gt' => 28, 'hu' => 28, 'is' => 26, 'ie' => 22, 'il' => 23, 'it' => 27, 'jo' => 30, 'kz' => 20, 'kw' => 30, 'lv' => 21, 'lb' => 28, 'li' => 21, 'lt' => 20, 'lu' => 20, 'mk' => 19, 'mt' => 31, 'mr' => 27, 'mu' => 30, 'mc' => 27, 'md' => 24, 'me' => 22, 'nl' => 18, 'no' => 15, 'pk' => 24, 'ps' => 29, 'pl' => 28, 'pt' => 25, 'qa' => 29, 'ro' => 24, 'sm' => 27, 'sa' => 24, 'rs' => 22, 'sk' => 24, 'si' => 19, 'es' => 24, 'se' => 24, 'ch' => 21, 'tn' => 24, 'tr' => 26, 'ae' => 23, 'gb' => 22, 'vg' => 24);
        $Chars     = array('a' => 10, 'b' => 11, 'c' => 12, 'd' => 13, 'e' => 14, 'f' => 15, 'g' => 16, 'h' => 17, 'i' => 18, 'j' => 19, 'k' => 20, 'l' => 21, 'm' => 22, 'n' => 23, 'o' => 24, 'p' => 25, 'q' => 26, 'r' => 27, 's' => 28, 't' => 29, 'u' => 30, 'v' => 31, 'w' => 32, 'x' => 33, 'y' => 34, 'z' => 35);

        if (empty($iban)) {
            return false;
        }

        try {
            if (strlen($iban) == $Countries[substr($iban, 0, 2)]) {

                $MovedChar      = substr($iban, 4) . substr($iban, 0, 4);
                $MovedCharArray = str_split($MovedChar);
                $NewString      = '';

                foreach ($MovedCharArray AS $key => $value) {
                    if (!is_numeric($MovedCharArray[$key])) {
                        $MovedCharArray[$key] = $Chars[$MovedCharArray[$key]];
                    }
                    $NewString .= $MovedCharArray[$key];
                }

                if (self::bcmod($NewString, '97') == 1) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } catch (Exception $ex) {
            return false;
        }
    }

    public static function bcmod($x, $y)
    {
        $take = 5;
        $mod  = '';

        do {
            $a   = (int)$mod . substr($x, 0, $take);
            $x   = substr($x, $take);
            $mod = $a % $y;
        } while (strlen($x));

        return (int)$mod;
    }
    #endregion

    #region getFormSecurityFields($adminReferer, $save, $reset)
    /**
     * @param string $adminReferer should be defined by a constant from the class you want to use this form in.
     * @param bool   $saveButton   set to false if you don't want the save button to be displayed.
     * @param bool   $resetButton  set to false if you don't want the reset button to be displayed.
     *
     * @return string HTML
     */
    public static function getFormSecurityFields($adminReferer, $saveButton = true, $resetButton = true)
    {
        ob_start();
        ?><input type="hidden" name="admin_referer" value="<?= $adminReferer ?>"><?php
        wp_nonce_field($adminReferer);
        if ($saveButton) {
            submit_button();
        }
        if ($resetButton) {
            ?><input type="submit" name="reset" id="reset" class="button button-primary" value="Reset to Default"><?php
        }
        return ob_get_clean();
    }
    #endregion

    #region sanitize($value)
    /**
     * @param $value
     *
     * @return string
     */
    public static function sanitize($value)
    {
        if (is_array($value)) {
            return $value;
        }
        $value = stripslashes($value);
        $value = esc_attr($value);
        $value = sanitize_text_field($value);
        return $value;
    }
    #endregion

    #region var_export($variable, $die)
    /**
     * This function is for development purposes only and lets the developer print a variable in the PHP formatting to inspect what the variable is set to.
     *
     * @param mixed $variable any variable that you want to be printed.
     * @param bool  $die      set true if you want to call die() after the print. $die is ignored if $return is true.
     *
     * @return mixed|null|string returns the print in string if $return is true, returns null if $return is false, and doesn't return if $die is true.
     */
    public static function var_export($variable, $die = false)
    {
        if (is_string($variable) && strpos($variable, 'FROM') !== false && strpos($variable, 'WHERE') !== false) {
            ob_start();
            echo $variable . ';';
            $query = ob_get_clean();
            include_once('lib/SqlFormatter.php');
            $print = SqlFormatter::highlight($query);
            $print = trim(preg_replace('/\s+/', ' ', $print));
        } else {
            if (self::_hasCircularReference($variable)) {
                $print = highlight_string("<?php " . var_dump($variable, true), true);
            } else {
                $print = highlight_string("<?php " . var_export($variable, true), true);
            }
            $print = trim($print);
            $print = preg_replace("|^\\<code\\>\\<span style\\=\"color\\: #[a-fA-F0-9]{0,6}\"\\>|", '', $print, 1);  // remove prefix
            $print = preg_replace("|\\</code\\>\$|", '', $print, 1);
            $print = trim($print);
            $print = preg_replace("|\\</span\\>\$|", '', $print, 1);
            $print = trim($print);
            $print = preg_replace("|^(\\<span style\\=\"color\\: #[a-fA-F0-9]{0,6}\"\\>)(&lt;\\?php&nbsp;)(.*?)(\\</span\\>)|", "\$1\$3\$4", $print);
            $print .= ';';
        }
        echo $print;
        echo '<br/>';

        if ($die) {
            die();
        }
        return null;
    }
    #endregion

    #region _hasCircularReference($variable)
    /**
     * This function checks if the given $variable is recursive.
     *
     * @param mixed $variable is the variable to be checked.
     *
     * @return bool true if the $variable contains circular reference.
     */
    private static function _hasCircularReference($variable)
    {
        $dump = print_r($variable, true);
        if (strpos($dump, '*RECURSION*') !== false) {
            return true;
        } else {
            return false;
        }
    }

    public static function eventsPluginActive()
    {
        return function_exists('mp_ssv_events_register_plugin');
    }

    public static function usersPluginActive()
    {
        return function_exists('mp_ssv_users_register_plugin');
    }

    public static function mailchimpPluginActive()
    {
        return function_exists('mp_ssv_mailchimp_register_plugin');
    }
    #endregion

    #endregion
}