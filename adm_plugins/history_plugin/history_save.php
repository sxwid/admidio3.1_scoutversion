<?php
/******************************************************************************
 * Geschichte Änderungen speichern
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
require_once(SERVER_PATH. '/adm_plugins/history_plugin/history_classes.php');


// Initialize and check the parameters
$getHistId = admFuncVariableIsValid($_GET, 'hist_id', 'numeric');

// Ankuendigungsobjekt anlegen
$history = new TableHistory($gDb);

$history->readDataById($getHistId);
    

$_SESSION['historys_request'] = $_POST;

if(strlen($_POST['hist_description']) == 0)
{
    $gMessage->show($gL10n->get('SYS_FIELD_EMPTY', $gL10n->get('SYS_TEXT')));
}

// make html in description secure
$_POST['hist_description'] = admFuncVariableIsValid($_POST, 'hist_description', 'html');

// POST Variablen in das Ankuendigungs-Objekt schreiben
foreach($_POST as $key => $value)
{
    if(strpos($key, 'hist_') === 0)
    {
	$history->setValue($key, $value);
    }
}

// Daten in Datenbank schreiben
$return_code = $history->save();

if($return_code < 0)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

unset($_SESSION['historys_request']);
$gNavigation->deleteLastUrl();

header('Location: '. $gNavigation->getUrl());
exit();


?>
