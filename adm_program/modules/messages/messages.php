<?php
/**
 ***********************************************************************************************
 * PM list page
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 ***********************************************************************************************
 */
require_once('../../system/common.php');

// check if the call of the page was allowed
if ($gPreferences['enable_pm_module'] != 1 && $gPreferences['enable_mail_module'] != 1 && $gPreferences['enable_chat_module'] != 1)
{
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}

// check for valid login
if (!$gValidLogin)
{
    $gMessage->show($gL10n->get('SYS_INVALID_PAGE_VIEW'));
}

// Initialize and check the parameters
$getMsgId = admFuncVariableIsValid($_GET, 'msg_id', 'int', array('defaultValue' => 0));

if ($getMsgId > 0)
{
    $delMessage = new TableMessage($gDb, $getMsgId);

    // Function to delete message
    $delete = $delMessage->delete();
    if ($delete)
    {
        echo 'done';
    }
    else
    {
        echo 'delete not OK';
    }
    exit();
}

$headline = $gL10n->get('SYS_MESSAGES');

// add current url to navigation stack
$gNavigation->clear();
$gNavigation->addUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage($headline);
$page->enableModal();

// get module menu for emails
$EmailMenu = $page->getMenu();
// link to write new email
if ($gPreferences['enable_mail_module'] == 1)
{
    // @ptabaden: changed icon
    $EmailMenu->addItem('admMenuItemNewEmail', $g_root_path.'/adm_program/modules/messages/messages_write.php', '<i class="fa fa-pencil-square-o" alt="'.$gL10n->get('MAI_SEND_EMAIL').'" title="'.$gL10n->get('MAI_SEND_EMAIL').'"></i><div class="iconDescription">'.$gL10n->get('MAI_SEND_EMAIL').'</div>', '');
}
// link to write new PM
if ($gPreferences['enable_pm_module'] == 1)
{
    $EmailMenu->addItem('admMenuItemNewPm', $g_root_path.'/adm_program/modules/messages/messages_write.php?msg_type=PM', $gL10n->get('PMS_SEND_PM'), '/pm.png');
}

// link to Chat
// @ptabaden: hide chat!
// if ($gPreferences['enable_chat_module'] == 1)
// {
//    $EmailMenu->addItem('admMenuItemNewChat', $g_root_path.'/adm_program/modules/messages/messages_chat.php', $gL10n->get('MSG_CHAT'), '/chat.png');
// }

if($gCurrentUser->isWebmaster())
{
    // @ptabaden: Changed Icon & renamed to menu_item_preferences
    $EmailMenu->addItem('admMenuItemPreferences', $g_root_path.'/adm_program/modules/preferences/preferences.php?show_option=messages',
                    '<i class="fa fa-cog" alt="'.$gL10n->get('SYS_MODULE_PREFERENCES').'" title="'.$gL10n->get('SYS_MODULE_PREFERENCES').'"></i><div class="iconDescription">'.$gL10n->get('SYS_MODULE_PREFERENCES').'</div>', '', 'right');
}

$table = new HtmlTable('adm_lists_table', $page, true, true);

// @ptabaden deleted two cols #possibleError delete one left more?
$table->setColumnAlignByArray(array('left', 'left', 'right'));
// @ptabaden: deleted category row and changed order
// @ptabaden: deleted two cols
$table->addRowHeadingByArray(array($gL10n->get('MSG_OPPOSITE'),
                                   $gL10n->get('MAI_SUBJECT'),
                                   ''));
$table->disableDatatablesColumnsSort(5);
$key = 0;
$part1 = '<a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal" href="'.$g_root_path.'/adm_program/system/popup_message.php?type=msg&amp;element_id=row_message_';
// @ptabaden: Changed Icon
$part2 = '"><i class="fa fa-times" alt="'.$gL10n->get('MSG_REMOVE').'" title="'.$gL10n->get('MSG_REMOVE').'" /></a>';
$href  = 'href="'.$g_root_path.'/adm_program/modules/messages/messages_write.php?msg_id=';

// open some additonal functions for messages
$modulemessages = new ModuleMessages();

// find all own Email messages
$statement = $modulemessages->msgGetUserEmails($gCurrentUser->getValue('usr_id'));
if(isset($statement))
{
    while ($row = $statement->fetch())
    {
        $receiverName = '';
        if (strpos($row['user'], '|') > 0)
        {
            $reciversplit = explode('|', $row['user']);
            foreach ($reciversplit as $value)
            {
                if (strpos($value, ':') > 0)
                {
                    $receiverName .= '; ' . $modulemessages->msgGroupNameSplit($value);
                }
                else
                {
                    $user = new User($gDb, $gProfileFields, $value);
                    $receiverName .= '; ' . $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME');
                }
            }
        }
        else
        {
            if (strpos($row['user'], ':') > 0)
            {
                $receiverName .= '; ' . $modulemessages->msgGroupNameSplit($row['user']);
            }
            else
            {
                $user = new User($gDb, $gProfileFields, $row['user']);
                $receiverName .= '; ' . $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME');
            }
        }
        $receiverName = substr($receiverName, 2);

        $message = new TableMessage($gDb, $row['msg_id']);
        ++$key;

        $messageAdministration = $part1 . $key . '&amp;name='.urlencode($message->getValue('msg_subject')).'&amp;database_id=' . $message->getValue('msg_id') . $part2;
	// @ptabaden: changed order and icons, changed order, delted two cols
        $table->addRowByArray(array('<h4 id="mail_timestamp">'.$message->getValue('msg_timestamp').'</h4><div id="mail" class="table_group"><h4 id="mail_receiver">'.$gL10n->get('MSG_OPPOSITE').': '.$receiverName.'</h4><h3 id="event_title"><a '. $href .$message->getValue('msg_id').'">'.$message->getValue('msg_subject').'</a></h3></div>', $messageAdministration),
                'row_message_'.$key);
    }
}

// find all unread PM messages
$statement = $modulemessages->msgGetUserUnread($gCurrentUser->getValue('usr_id'));
if(isset($statement))
{
    while ($row = $statement->fetch())
    {
        if($row['msg_usr_id_sender'] == $gCurrentUser->getValue('usr_id'))
        {
            $user = new User($gDb, $gProfileFields, $row['msg_usr_id_receiver']);
        }
        else
        {
            $user = new User($gDb, $gProfileFields, $row['msg_usr_id_sender']);
        }
        $receiverName = $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME');
        $message = new TableMessage($gDb, $row['msg_id']);
        ++$key;

        $messageAdministration = $part1 . $key . '&amp;name=' . urlencode($message->getValue('msg_subject')) . '&amp;database_id=' . $message->getValue('msg_id') . $part2;

        $table->addRowByArray(array('<a class="admidio-icon-link" '. $href . $message->getValue('msg_id') . '">
                <img class="admidio-icon-info" src="'. THEME_PATH. '/icons/pm.png" alt="'.$gL10n->get('PMS_MESSAGE').'" title="'.$gL10n->get('PMS_MESSAGE').'" />',
                '<a '. $href .$message->getValue('msg_id').'">'.$message->getValue('msg_subject').'</a>',
                $receiverName, $message->getValue('msg_timestamp'), $messageAdministration), 'row_message_'.$key, array('style' => 'font-weight: bold'));
    }
}

// find all read or own PM messages
$statement = $modulemessages->msgGetUser($gCurrentUser->getValue('usr_id'));
if(isset($statement))
{
    while ($row = $statement->fetch())
    {
        if($row['msg_usr_id_sender'] == $gCurrentUser->getValue('usr_id'))
        {
            $user = new User($gDb, $gProfileFields, $row['msg_usr_id_receiver']);
        }
        else
        {
            $user = new User($gDb, $gProfileFields, $row['msg_usr_id_sender']);
        }

        $receiverName = $user->getValue('FIRST_NAME').' '.$user->getValue('LAST_NAME');
        $message = new TableMessage($gDb, $row['msg_id']);
        ++$key;

        $messageAdministration = $part1 . $key . '&amp;name=' . urlencode($message->getValue('msg_subject')) . '&amp;database_id=' . $message->getValue('msg_id') . $part2;

        $table->addRowByArray(array('<a class="admidio-icon-link" '. $href . $message->getValue('msg_id') . '">
                <img class="admidio-icon-info" src="'. THEME_PATH. '/icons/pm.png" alt="'.$gL10n->get('PMS_MESSAGE').'" title="'.$gL10n->get('PMS_MESSAGE').'" />',
                '<a '. $href .$message->getValue('msg_id').'">'.$message->getValue('msg_subject').'</a>',
                $receiverName, $message->getValue('msg_timestamp'), $messageAdministration), 'row_message_'.$key);
    }
}

// special settings for the table
$table->setDatatablesOrderColumns(array(array(4, 'desc')));

// add table to the form
$page->addHtml($table->show(false));

// add form to html page and show page
$page->show();
