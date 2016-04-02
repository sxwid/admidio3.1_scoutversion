<?php
/******************************************************************************
 * Modul erstellt ein Datenbankfeld für die Startseite und zeigt dieses
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
 *         && $key != 'sts_description' // ptabaden edit
 *
 *
 *****************************************************************************/
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once(substr(__FILE__, 0,strpos(__FILE__, 'adm_plugins')-1).'/adm_program/system/common.php');
require_once(SERVER_PATH. '/adm_plugins/sts_plugin/sts_classes.php');

unset($_SESSION['sts_request']);

// Initialize and check the parameters
$getHeadline = $gL10n->get('SYS_OVERVIEW');
$getId       = '1';

// create object for announcements
$stss = new ModuleSts();
$stss->setParameter('id', $getId);

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $getHeadline);

// create html page object
$page = new HtmlPage($getHeadline);


// get module menu
$stsMenu = $page->getMenu();

if($gCurrentUser->isWebmaster())
{
    // Datenbank erstellen fall sie nicht existiert
    if(check_db()!=true)
    {
        $sql='CREATE TABLE '.TBL_USER_STS.'
         (sts_id int(10) unsigned NOT NULL AUTO_INCREMENT,
          sts_description text CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
          PRIMARY KEY (sts_id) ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;';
        $result=$gDb->query($sql);
    }

    // show link to edit Startseite
    $stsMenu->addItem('menu_item_edit_sts', $g_root_path.'/adm_plugins/sts_plugin/sts_edit.php?sts_id='.$getId.'&amp;?headline='.$getHeadline, 
                                '<i class="fa fa-pencil" alt="'.$gL10n->get('SYS_EDIT_VAR', $getHeadline).'" title="'.$gL10n->get('SYS_EDIT_VAR', $getHeadline).'"></i><div class="iconDescription">Text bearbeiten</div>', '');
}

// Output Database Entry if available
if(check_db()==true)
{
    // get all recordsets 
    $stssArray = $stss->getDataSet();    
    $sts = new TableAnnouncement($gDb);

    if($stssArray['numResults']!=0)
    {
        /// show all sts
        foreach($stssArray['recordset'] as $row)
        {
            $sts->clear();
            $sts->setArray($row);
            $page->addHtml('
            <div class="panel panel-primary" id="sts_'.$sts->getValue('sts_id').'">
                <div class="panel-body">'.
                    $sts->getValue('sts_description').
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
