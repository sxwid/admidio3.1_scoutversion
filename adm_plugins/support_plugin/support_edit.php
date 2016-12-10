<?php
/******************************************************************************
 * Supportseite ändern
 *
 * Copyright    : (c) 2015 PTABaden
 * Homepage     : http://www.ptabaden.ch
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once(substr(__FILE__, 0,strpos(__FILE__, 'adm_plugins')-1).'/adm_program/system/common.php');
require_once(substr(__FILE__, 0,strpos(__FILE__, 'adm_plugins')-1).'/adm_program/system/login_valid.php');
require_once(SERVER_PATH. '/adm_plugins/support_plugin/support_classes.php');


if(!$gCurrentUser->isWebmaster())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// Initialize and check the parameters
$getSupportId    = admFuncVariableIsValid($_GET, 'support_id', 'numeric');
$headline = 'Supportseite editieren';

// add current url to navigation stack
$gNavigation->addUrl(CURRENT_URL, $headline);

// Create announcements object
$support = new TableSupport($gDb);

if($getSupportId > 0)
{
    $support->readDataById($getSupportId);

}

if(isset($_SESSION['support_request']))
{
    // durch fehlerhafte Eingabe ist der User zu diesem Formular zurueckgekehrt
    // nun die vorher eingegebenen Inhalte ins Objekt schreiben
    $support->setArray($_SESSION['support_request']);
    unset($_SESSION['support_request']);
}

// create html page object
$page = new HtmlPage($headline);

// add back link to module menu
$supportMenu = $page->getMenu();
$supportMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), $gL10n->get('SYS_BACK'), 'back.png');

// show form
$form = new HtmlForm('support_edit_form', $g_root_path.'/adm_plugins/support_plugin/support_save.php?support_id='.$getSupportId.'&amp;headline='. $headline. '&amp', $page);
$form->addEditor('support_description', $headline, $support->getValue('support_description'), array('property' => FIELD_REQUIRED));
$form->addSubmitButton('btn_save', $gL10n->get('SYS_SAVE'), array('icon' => THEME_PATH.'/icons/disk.png'));

// add form to html page and show page
$page->addHtml($form->show(false));
$page->show();

?>
