<?php
/**
 ***********************************************************************************************
 * Validate login data, create cookie and sign in the user to Admidio
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */
require_once('common.php');

// Initialize parameters
$bAutoLogin = false;
$loginname  = '';
$password   = '';
$organizationId = $gCurrentOrganization->getValue('org_id');

// Filter parameters
// parameters could be from login dialog or login plugin !!!
/**
 * @param string $prefix
 */
function initLoginParams($prefix = '')
{
    global $bAutoLogin, $loginname, $password, $organizationId, $gPreferences;

    $loginname = $_POST[$prefix.'usr_login_name'];
    $password  = $_POST[$prefix.'usr_password'];

    if($gPreferences['enable_auto_login'] == 1 && array_key_exists($prefix.'auto_login', $_POST) && $_POST[$prefix.'auto_login'] == 1)
    {
        $bAutoLogin = true;
    }
    // if user can choose organization then save the selection
    if(array_key_exists($prefix.'org_id', $_POST) && is_numeric($_POST[$prefix.'org_id']) && $_POST[$prefix.'org_id'] > 0)
    {
        $organizationId = $_POST[$prefix.'org_id'];
    }
}

if(array_key_exists('usr_login_name', $_POST) && $_POST['usr_login_name'] !== '')
{
    initLoginParams('');
}

if(array_key_exists('plg_usr_login_name', $_POST) && $_POST['plg_usr_login_name'] !== '')
{
    initLoginParams('plg_');
}

if($loginname === '')
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_USERNAME')));
}

if($password === '')
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_PASSWORD')));
}

// TODO Future: check Password min/max Length
//if(strlen($password) < 8)
//{
//    $gMessage->show($gL10n->get('PRO_PASSWORD_LENGTH', $gL10n->get('SYS_PASSWORD')));
//}

// Search for username
$sql = 'SELECT usr_id
          FROM '.TBL_USERS.'
         WHERE UPPER(usr_login_name) = UPPER(\''.$loginname.'\')';
$userStatement = $gDb->query($sql);

if ($userStatement->rowCount() === 0)
{
    $gMessage->show($gL10n->get('SYS_LOGIN_USERNAME_PASSWORD_INCORRECT'));
}
else
{
    // if login organization is different to organization of config file then create new session variables
    if($organizationId != $gCurrentOrganization->getValue('org_id'))
    {
        // read organization of config file with their preferences
        $gCurrentOrganization->readDataById($organizationId);
        $gPreferences = $gCurrentOrganization->getPreferences();

        // read new profile field structure for this organization
        $gProfileFields->readProfileFields($organizationId);

        // save new organization id to session
        $gCurrentSession->setValue('ses_org_id', $organizationId);
        $gCurrentSession->save();
    }

    $userRow = $userStatement->fetch();

    // create user object
    $gCurrentUser = new User($gDb, $gProfileFields, $userRow['usr_id']);

    $checkLoginReturn = $gCurrentUser->checkLogin($password, $bAutoLogin);

    if (is_string($checkLoginReturn))
    {
        $gMessage->show($gL10n->get($checkLoginReturn));
    }
    else
    {
        // show successful login message
        $loginMessage = 'SYS_LOGIN_SUCCESSFUL';

        // bei einer Beta-Version noch einen Hinweis ausgeben !
        if(ADMIDIO_VERSION_BETA > 0 && !$gDebug)
        {
            $loginMessage = 'SYS_BETA_VERSION';
        }

        // falls noch keine Forward-Url gesetzt wurde, dann nach dem Login auf die Startseite verweisen
        if(!array_key_exists('login_forward_url', $_SESSION))
        {
            $_SESSION['login_forward_url'] = $g_root_path . '/' . $gPreferences['homepage_login'];
        }

        // bevor zur entsprechenden Seite weitergeleitet wird, muss noch geprueft werden,
        // ob der Browser Cookies setzen darf -> sonst kein Login moeglich
        $location = 'Location: ' . $g_root_path . '/adm_program/system/cookie_check.php?message_code=' . $loginMessage;
        header($location);
        exit();
    }
}
