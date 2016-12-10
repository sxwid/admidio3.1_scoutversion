<?php
/******************************************************************************
 * Support Änderungen speichern
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


// Initialize and check the parameters
$getSupportId = admFuncVariableIsValid($_GET, 'support_id', 'numeric');

// Ankuendigungsobjekt anlegen
$support = new TableSupport($gDb);

$support->readDataById($getSupportId);
    

$_SESSION['support_request'] = $_POST;

if(strlen($_POST['support_description']) == 0)
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_TEXT')));
}

// make html in description secure
$_POST['support_description'] = admFuncVariableIsValid($_POST, 'support_description', 'html');

// POST Variablen in das Ankuendigungs-Objekt schreiben
foreach($_POST as $key => $value)
{
    if(strpos($key, 'support_') === 0)
    {
	$support->setValue($key, $value);
    }
}

// Daten in Datenbank schreiben
$return_code = $support->save();

if($return_code < 0)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

unset($_SESSION['support_request']);
$gNavigation->deleteLastUrl();

header('Location: '. $gNavigation->getUrl());
exit();


?>
