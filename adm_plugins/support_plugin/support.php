<?php
/******************************************************************************
 * Modul erstellt ein Datenbankfeld für die Supportseite und zeigt dieses
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
 *         && $key != 'support_description' // ptabaden edit
 *
 *
 *****************************************************************************/
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once(substr(__FILE__, 0,strpos(__FILE__, 'adm_plugins')-1).'/adm_program/system/common.php');
require_once(SERVER_PATH. '/adm_plugins/support_plugin/support_classes.php');

unset($_SESSION['support_request']);

// Initialize and check the parameters
$getHeadline = 'PTA unterst&uuml;tzen';
$getId       = '1';

// create object for announcements
$supports = new ModuleSupport();
$supports->setParameter('id', $getId);

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $getHeadline);

// create html page object
$page = new HtmlPage($getHeadline);


// get module menu
$supportMenu = $page->getMenu();

if($gCurrentUser->isWebmaster())
{
    // Datenbank erstellen fall sie nicht existiert
    if(check_db()!=true)
    {
        $sql='CREATE TABLE '.TBL_USER_SUPPORT.'
         (support_id int(10) unsigned NOT NULL AUTO_INCREMENT,
          support_description text CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
          PRIMARY KEY (support_id) ) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;';
        $result=$gDb->query($sql);
    }

    // show link to edit Support
    $supportMenu->addItem('menu_item_edit_support', $g_root_path.'/adm_plugins/support_plugin/support_edit.php?support_id='.$getId.'&amp;?headline='.$getHeadline, 
                                '<i class="fa fa-pencil" alt="'.$gL10n->get('SYS_EDIT_VAR', $getHeadline).'" title="'.$gL10n->get('SYS_EDIT_VAR', $getHeadline).'"></i><div class="iconDescription">Text bearbeiten</div>', '');
}

// Output Database Entry if available
if(check_db()==true)
{
    // get all recordsets 
    $supportsArray = $supports->getDataSet();    
    $support = new TableAnnouncement($gDb);

    if($supportsArray['numResults']!=0)
    {
        /// show all support
        foreach($supportsArray['recordset'] as $row)
        {
            $support->clear();
            $support->setArray($row);
            $page->addHtml('
            <div class="panel panel-primary" id="support_'.$support->getValue('support_id').'">
                <div class="panel-body">'.
                    $support->getValue('support_description').
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
