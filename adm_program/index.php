<?php
/**
 ***********************************************************************************************
 * List of all modules and administration pages of Admidio
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// if config file doesn't exists, than show installation dialog
if(!file_exists('../adm_my_files/config.php'))
{
    header('Location: installation/index.php');
    exit();
}

require_once('system/common.php');

$headline = 'Platzhalter Startseite';

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage($headline);

// main menu of the page
$mainMenu = $page->getMenu();

if($gValidLogin)
{
    // show link to own profile
    $mainMenu->addItem('adm_menu_item_my_profile', $g_root_path.'/adm_program/modules/profile/profile.php',
                       $gL10n->get('PRO_MY_PROFILE'), 'profile.png');
    // show logout link
    $mainMenu->addItem('adm_menu_item_logout', $g_root_path.'/adm_program/system/logout.php',
                       $gL10n->get('SYS_LOGOUT'), 'door_in.png');
}
else
{
    // show login link
    $mainMenu->addItem('adm_menu_item_login', $g_root_path.'/adm_program/system/login.php',
                       $gL10n->get('SYS_LOGIN'), 'key.png');

    if($gPreferences['registration_mode'] > 0)
    {
        // show registration link
        $mainMenu->addItem('adm_menu_item_registration',
                           $g_root_path.'/adm_program/modules/registration/registration.php',
                           $gL10n->get('SYS_REGISTRATION'), 'new_registrations.png');
    }
}



$page->show();
