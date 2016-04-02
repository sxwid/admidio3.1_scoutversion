<?php
/**
 ***********************************************************************************************
 * Show a list of all downloads
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * folder_id : Id of the current folder that should be shown
 ***********************************************************************************************
 */
require_once('../../system/common.php');
require_once('../../system/file_extension_icons.php');

unset($_SESSION['download_request']);

$buffer = '';

// Initialize and check the parameters
$getFolderId = admFuncVariableIsValid($_GET, 'folder_id', 'int');

// Check if module is activated
if ($gPreferences['enable_download_module'] != 1)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// Only available from master organization
if (strcasecmp($gCurrentOrganization->getValue('org_shortname'), $g_organization) !== 0)
{
    // is not master organization
    $gMessage->show($gL10n->get('SYS_MODULE_ACCESS_FROM_HOMEPAGE_ONLY', $g_organization));
}

try
{
    // get recordset of current folder from database
    $currentFolder = new TableFolder($gDb);
    $currentFolder->getFolderForDownload($getFolderId);
}
catch(AdmException $e)
{
    $e->showHtml();
}

// set headline of the script
if($currentFolder->getValue('fol_fol_id_parent') == null)
{
    $headline = $gL10n->get('DOW_DOWNLOADS');
}
else
{
    $headline = $gL10n->get('DOW_DOWNLOADS').' - '.$currentFolder->getValue('fol_name');
}

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $headline);

$getFolderId = $currentFolder->getValue('fol_id');

// Get folder content for style
$folderContent = $currentFolder->getFolderContentsForDownload();

// Keep navigation link
$navigationBar = $currentFolder->getNavigationForDownload();

// create html page object
$page = new HtmlPage($headline);

$page->enableModal();
$page->addJavascript('
    $("body").on("hidden.bs.modal", ".modal", function () { $(this).removeData("bs.modal"); location.reload(); });
    $("#menu_item_upload_files").attr("data-toggle", "modal");
    $("#menu_item_upload_files").attr("data-target", "#admidio_modal");
    ', true);

// get module menu
$DownloadsMenu = $page->getMenu();

if ($gCurrentUser->editDownloadRight())
{
    // upload only possible if upload filesize > 0
    if ($gPreferences['max_file_upload_size'] > 0)
    {
        // show links for upload, create folder and folder configuration
	// @ptabaden: Changed Icons and Description
        $DownloadsMenu->addItem('menu_item_create_folder', $g_root_path.'/adm_program/modules/downloads/folder_new.php?folder_id='.$getFolderId,
                            '<i class="fa fa-plus" alt="'.$gL10n->get('DOW_CREATE_FOLDER').'" title="'.$gL10n->get('DOW_CREATE_FOLDER').'"></i><div class="iconDescription">'.$gL10n->get('DOW_CREATE_FOLDER').'</div>', '');
	// @ptabaden: Changed Icons and Description
        $DownloadsMenu->addItem('menu_item_upload_files', $g_root_path.'/adm_program/system/file_upload.php?module=downloads&id='.$getFolderId, '<i class="fa fa-upload" alt="'.$gL10n->get('DOW_UPLOAD_FILE').'" title="'.$gL10n->get('DOW_UPLOAD_FILE').'"></i><div class="iconDescription">'.$gL10n->get('DOW_UPLOAD_FILE').'</div>', '');
    }
	// @ptabaden: Changed Icons and Description
    $DownloadsMenu->addItem('menu_item_config_folder', $g_root_path.'/adm_program/modules/downloads/folder_config.php?folder_id='.$getFolderId,
                        '<i class="fa fa-lock" alt="'.$gL10n->get('SYS_AUTHORIZATION').'" title="'.$gL10n->get('SYS_AUTHORIZATION').'"></i><div class="iconDescription">'.$gL10n->get('SYS_AUTHORIZATION').'</div>', '');
};

if($gCurrentUser->isWebmaster())
{
    // show link to system preferences of weblinks
    // @ptabaden: Changed Icons and Description & renamed to menu_item_preferences
    $DownloadsMenu->addItem('admMenuItemPreferencesLinks', $g_root_path.'/adm_program/modules/preferences/preferences.php?show_option=downloads', 
                        '<i class="fa fa-cog" alt="'.$gL10n->get('SYS_MODULE_PREFERENCES').'" title="'.$gL10n->get('SYS_MODULE_PREFERENCES').'"></i><div class="iconDescription">'.$gL10n->get('SYS_MODULE_PREFERENCES').'</div>', '', 'right');
}

// Create table object
$downloadOverview = new HtmlTable('tbl_downloads', $page, true, true);

// create array with all column heading values
// @ptabaden: Removed counter and separate col for file-type icon
$columnHeading = array(
    $gL10n->get('SYS_TYPE').
    '<img class="admidio-icon-info" src="'. THEME_PATH. '/icons/download.png" alt="'.$gL10n->get('SYS_FOLDER').' / '.$gL10n->get('DOW_FILE_TYPE').'" title="'.$gL10n->get('SYS_FOLDER').' / '.$gL10n->get('DOW_FILE_TYPE').'" />',
    $gL10n->get('SYS_NAME'),
    $gL10n->get('SYS_DATE_MODIFIED'),
    $gL10n->get('SYS_SIZE')
);

if ($gCurrentUser->editDownloadRight())
{
    $columnHeading[] = '&nbsp;';
    // @ptabaden: Removed counter (changed from 7 to 5) and separate col for file-type icon
    $downloadOverview->disableDatatablesColumnsSort(5);
}

// @ptabaden: Removed counter and separate col for file-type icon
$downloadOverview->setColumnAlignByArray(array('left', 'left', 'left', 'right', 'right'));
$downloadOverview->addRowHeadingByArray($columnHeading);
$downloadOverview->setMessageIfNoRowsFound('DOW_FOLDER_NO_FILES', 'warning');

// Get folder content
if (isset($folderContent['folders']))
{
    // First get possible sub folders
    for($i=0; $i<count($folderContent['folders']); ++$i)
    {
        $nextFolder = $folderContent['folders'][$i];

        $folderDescription = '';
        if(strlen($nextFolder['fol_description']) > 0)
        {
            $folderDescription = '<img class="admidio-icon-help" src="'. THEME_PATH. '/icons/info.png" data-toggle="popover" data-trigger="hover"
                data-placement="right" title="'.$gL10n->get('SYS_DESCRIPTION').'" data-content="'.$nextFolder['fol_description'].'" alt="Info" />';
        }

        // create array with all column values
        // @ptabaden: Removed counter and separate col for file-type icon
        // @ptabaden: added h3
        $columnValues = array(
            1, // Type folder
            '<a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/downloads/downloads.php?folder_id='. $nextFolder['fol_id']. '">
                <i class="fa fa-folder file-icon" alt="'.$gL10n->get('SYS_FOLDER').'" title="'.$gL10n->get('SYS_FOLDER').'"></i><h3>'. $nextFolder['fol_name']. '</h3></a>'.$folderDescription,
            '',
            ''
        );

        if ($gCurrentUser->editDownloadRight())
        {
            // Links for change and delete
	    // @ptabaden: Changed mutiple Icons
            $additionalFolderFunctions = '';

            if($nextFolder['fol_exists'] === true)
            {
                $additionalFolderFunctions = '
                <a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/downloads/rename.php?folder_id='. $nextFolder['fol_id']. '"><i class="fa fa-pencil" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'"></i></a>';
            }
            else
            {
                $additionalFolderFunctions = '
                <img class="admidio-icon-help" src="'. THEME_PATH. '/icons/warning.png" data-toggle="popover" data-trigger="hover" data-placement="left"
                    title="'.$gL10n->get('SYS_WARNING').'" data-content="'.$gL10n->get('DOW_FOLDER_NOT_EXISTS').'" alt="'.$gL10n->get('SYS_WARNING').'" /></a>';
            }

            $columnValues[] = $additionalFolderFunctions.'
                                <a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                                    href="'.$g_root_path.'/adm_program/system/popup_message.php?type=fol&amp;element_id=row_folder_'.
                                    $nextFolder['fol_id'].'&amp;name='.urlencode($nextFolder['fol_name']).'&amp;database_id='.$nextFolder['fol_id'].'"><i class="fa fa-times" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'"></i></a>';
        }
        $downloadOverview->addRowByArray($columnValues, 'row_folder_'.$nextFolder['fol_id']);
    }
}

// Get contained files
if (isset($folderContent['files']))
{
    for($i=0; $i<count($folderContent['files']); ++$i)
    {
        $nextFile = $folderContent['files'][$i];

        // Check filetyp
        $fileExtension  = admStrToLower(substr($nextFile['fil_name'], strrpos($nextFile['fil_name'], '.')+1));

        // Choose icon for the file
        $iconFile = 'page_white_question.png';
        if(array_key_exists($fileExtension, $icon_file_extension))
        {
            $iconFile = $icon_file_extension[$fileExtension];
        }

        // Format timestamp
        $timestamp = new DateTimeExtended($nextFile['fil_timestamp'], 'Y-m-d H:i:s');

        $fileDescription = '';
        if($nextFile['fil_description'] != '')
        {
            $fileDescription = '<img class="admidio-icon-help" src="'. THEME_PATH. '/icons/info.png" data-toggle="popover" data-trigger="hover"
                data-placement="right" title="'.$gL10n->get('SYS_DESCRIPTION').'" data-content="'.$nextFile['fil_description'].'" alt="Info" />';
        }

        // create array with all column values
        // @ptabaden: Changed file icons to Font Awesome icons
        // @ptabaden: Removed counter and separate col for file-type icon
        // @ptabaden: added h3
        $columnValues = array(
            2, // Type file
            '<a href="'.$g_root_path.'/adm_program/modules/downloads/get_file.php?file_id='. $nextFile['fil_id']. '">
                <i class="'.$iconFile.' file-icon" alt="'.$gL10n->get('SYS_FILE').'" title="'.$gL10n->get('SYS_FILE').'"></i><h3>'. $nextFile['fil_name']. '</h3></a>'.$fileDescription,
            $timestamp->format($gPreferences['system_date'].' '.$gPreferences['system_time']),
            $nextFile['fil_size']. ' kB&nbsp;'
        );

        if ($gCurrentUser->editDownloadRight())
        {
            // Links for change and delete
	    // @ptabaden: changed icons
            $additionalFileFunctions = '';

            if($nextFile['fil_exists'] === true)
            {
                $additionalFileFunctions = '
                <a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/downloads/rename.php?file_id='. $nextFile['fil_id']. '">
                <i class="fa fa-pencil" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'"></i></a>';
            }
            else
            {
                $additionalFileFunctions = '
                <img class="admidio-icon-link" src="'. THEME_PATH. '/icons/warning.png" data-toggle="popover" data-trigger="hover" data-placement="left"
                    title="'.$gL10n->get('SYS_WARNING').'" data-content="'.$gL10n->get('DOW_FILE_NOT_EXISTS').'" alt="'.$gL10n->get('SYS_WARNING').'" /></a>';
            }
            $columnValues[] = $additionalFileFunctions.'
            <a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                href="'.$g_root_path.'/adm_program/system/popup_message.php?type=fil&amp;element_id=row_file_'.
                $nextFile['fil_id'].'&amp;name='.urlencode($nextFile['fil_name']).'&amp;database_id='.$nextFile['fil_id'].'"><i class="fa fa-times" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'"></i></a>';
        }
        $downloadOverview->addRowByArray($columnValues, 'row_file_'.$nextFile['fil_id']);
    }
}

// Create download table
$downloadOverview->setDatatablesColumnsHide(array(1));
$downloadOverview->setDatatablesOrderColumns(array(1, 3));
$htmlDownloadOverview = $downloadOverview->show(false);

/**************************************************************************/
// Add Admin table to html page
/**************************************************************************/

// If user is download Admin show further files contained in this folder.
if ($gCurrentUser->editDownloadRight())
{
    // Check whether additional content was found in the folder
    if (isset($folderContent['additionalFolders']) || isset($folderContent['additionalFiles']))
    {
        $htmlAdminTableHeadline = '<h2>'.$gL10n->get('DOW_UNMANAGED_FILES').HtmlForm::getHelpTextIcon('DOW_ADDITIONAL_FILES').'</h2>';

        // Create table object
        $adminTable = new HtmlTable('tbl_downloads', $page, true);
        $adminTable->setColumnAlignByArray(array('left', 'left', 'left', 'right'));

        // create array with all column heading values
        $columnHeading = array('<img class="admidio-icon-info" src="'. THEME_PATH. '/icons/download.png" alt="'.$gL10n->get('SYS_FOLDER').' / '.$gL10n->get('DOW_FILE_TYPE').'" title="'.$gL10n->get('SYS_FOLDER').' / '.$gL10n->get('DOW_FILE_TYPE').'" />',
                               $gL10n->get('SYS_NAME'),
                               $gL10n->get('SYS_SIZE'),
                               '&nbsp;');
        $adminTable->addRowHeadingByArray($columnHeading);

        // Get folders
        if (isset($folderContent['additionalFolders']))
        {
            for($i=0; $i<count($folderContent['additionalFolders']); ++$i)
            {

                $nextFolder = $folderContent['additionalFolders'][$i];

                $columnValues = array('<img src="'. THEME_PATH. '/icons/download.png" alt="'.$gL10n->get('SYS_FOLDER').'" title="'.$gL10n->get('SYS_FOLDER').'" />',
                                      $nextFolder['fol_name'],
                                      '',
                                      '<a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/downloads/download_function.php?mode=6&amp;folder_id='.$getFolderId.'&amp;name='. urlencode($nextFolder['fol_name']). '">
                                          <img src="'. THEME_PATH. '/icons/database_in.png" alt="'.$gL10n->get('DOW_ADD_TO_DATABASE').'" title="'.$gL10n->get('DOW_ADD_TO_DATABASE').'" /></a>');
                $adminTable->addRowByArray($columnValues);
            }
        }

        // Get files
        if (isset($folderContent['additionalFiles']))
        {
            for($i=0; $i<count($folderContent['additionalFiles']); ++$i)
            {

                $nextFile = $folderContent['additionalFiles'][$i];

                // Get filetyp
                $fileExtension  = admStrToLower(substr($nextFile['fil_name'], strrpos($nextFile['fil_name'], '.')+1));

                // Choose icon for the file
                $iconFile = 'page_white_question.png';
                if(array_key_exists($fileExtension, $icon_file_extension))
                {
                    $iconFile = $icon_file_extension[$fileExtension];
                }

                $columnValues = array('<img src="'. THEME_PATH. '/icons/'.$iconFile.'" alt="'.$gL10n->get('SYS_FILE').'" title="'.$gL10n->get('SYS_FILE').'" /></a>',
                                      $nextFile['fil_name'],
                                      $nextFile['fil_size']. ' kB&nbsp;',
                                      '<a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/downloads/download_function.php?mode=6&amp;folder_id='.$getFolderId.'&amp;name='. urlencode($nextFile['fil_name']). '">
                                          <img src="'. THEME_PATH. '/icons/database_in.png" alt="'.$gL10n->get('DOW_ADD_TO_DATABASE').'" title="'.$gL10n->get('DOW_ADD_TO_DATABASE').'" /></a>');
                $adminTable->addRowByArray($columnValues);
            }
        }
        $htmlAdminTable = $adminTable->show(false);
    }
}

// Output module html to client

$page->addHtml($navigationBar);

$page->addHtml($htmlDownloadOverview);

// if user has admin download rights, then show admin table for undefined files in folders
if(isset($htmlAdminTable))
{
    $page->addHtml($htmlAdminTableHeadline);
    $page->addHtml($htmlAdminTable);
}

// show html of complete page
$page->show();
