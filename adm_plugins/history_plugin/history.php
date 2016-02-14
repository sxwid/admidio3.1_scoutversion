<?php
/******************************************************************************
 * Modul erstellt ein Datenbankfeld für die Vereinsgeschichte und zeigt dieses
 * an.
 *
 *
 * Copyright    : (c) 2015 PTABaden
 * Homepage     : http://www.ptabaden.ch
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 * Parameters:
 *
 * add at line 49 /adm_program/system/string.php 
 *         && $key != 'hist_description' // ptabaden edit
 *
 *
 *****************************************************************************/
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once(substr(__FILE__, 0,strpos(__FILE__, 'adm_plugins')-1).'/adm_program/system/common.php');
require_once(SERVER_PATH. '/adm_plugins/history_plugin/history_classes.php');

unset($_SESSION['historys_request']);

// Initialize and check the parameters
$getHeadline = 'Porträt';
$getId       = '1';

// create object for announcements
$historys = new ModuleHistory();
$historys->setParameter('id', $getId);

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $getHeadline);

// create html page object
$page = new HtmlPage($getHeadline);


// get module menu
$historyMenu = $page->getMenu();

if($gCurrentUser->isWebmaster())
{
    // Datenbank erstellen fall sie nicht existiert
    if(check_db()!=true)
    {
        $sql='CREATE TABLE '.TBL_USER_HISTORY.'
         (hist_id int(10) unsigned NOT NULL AUTO_INCREMENT,
          hist_description text CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
          PRIMARY KEY (hist_id) ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;';
        $result=$gDb->query($sql);
    }

    // show link to edit history
    $historyMenu->addItem('menu_item_edit_history', $g_root_path.'/adm_plugins/history_plugin/history_edit.php?hist_id='.$getId.'&amp;?headline='.$getHeadline, 
                                $gL10n->get('SYS_EDIT_VAR', $getHeadline), '');
}

// Output Database Entry if available
if(check_db()==true)
{
    // get all recordsets 
    $historysArray = $historys->getDataSet();    
    $history = new TableAnnouncement($gDb);

    if($historysArray['numResults']!=0)
    {
        /// show all history
        foreach($historysArray['recordset'] as $row)
        {
            $history->clear();
            $history->setArray($row);
            $page->addHtml('
            <div class="panel panel-primary" id="hist_'.$history->getValue('hist_id').'">
                <div class="panel-body">'.
                    $history->getValue('hist_description').
                '</div>
            </div>');
        }  // Ende foreach
    }
    else
    {
        if(!$gCurrentUser->isWebmaster())
        {
            $gMessage->show($gL10n->get('SYS_NO_DATA_FOUND'));
        }
    }
}
else
{
    $gMessage->show($gL10n->get('SYS_NO_DATA_FOUND'));
}
// show html of complete page
$page->show();

?>
