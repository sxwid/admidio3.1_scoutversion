<?php
/**
 ***********************************************************************************************
 * Show and manage all members of the organization
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * members - false : (Default) Show only active members of the current organization
 *           true  : Show active and inactive members of all organizations in database
 ***********************************************************************************************
 */
require_once('../../system/common.php');

unset($_SESSION['import_request']);

// if search field was used then transform the POST parameter into a GET parameter
if (isset($_POST['admSearchMembers']) && strlen($_POST['admSearchMembers']) > 0)
{
    $_GET['search'] = $_POST['admSearchMembers'];
}

// Initialize and check the parameters
$getMembers = admFuncVariableIsValid($_GET, 'members', 'bool', array('defaultValue' => true));

// if only active members should be shown then set parameter
if($gPreferences['members_show_all_users'] == 0)
{
    $getMembers = true;
}

// only legitimate users are allowed to call the user management
if (!$gCurrentUser->editUsers())
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// set headline of the script
$headline = $gL10n->get('MEM_USER_MANAGEMENT');

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $headline);

$memberCondition = '';

// Create condition if only active members should be shown
if($getMembers)
{
    $memberCondition = ' AND EXISTS
        (SELECT 1
           FROM '.TBL_MEMBERS.'
     INNER JOIN '.TBL_ROLES.'
             ON rol_id = mem_rol_id
     INNER JOIN '.TBL_CATEGORIES.'
             ON cat_id = rol_cat_id
          WHERE mem_usr_id = usr_id
            AND mem_begin <= \''.DATE_NOW.'\'
            AND mem_end    > \''.DATE_NOW.'\'
            AND rol_valid  = 1
            AND cat_name_intern <> \'CONFIRMATION_OF_PARTICIPATION\'
            AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                OR cat_org_id IS NULL )) ';
}

// alle Mitglieder zur Auswahl selektieren
// unbestaetigte User werden dabei nicht angezeigt
$sql = 'SELECT usr_id, last_name.usd_value as last_name, first_name.usd_value as first_name,
               email.usd_value as email, gender.usd_value as gender, birthday.usd_value as birthday,
               usr_login_name, COALESCE(usr_timestamp_change, usr_timestamp_create) as timestamp,
               (SELECT COUNT(*)
                  FROM '.TBL_MEMBERS.'
            INNER JOIN '.TBL_ROLES.'
                    ON rol_id = mem_rol_id
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
                 WHERE rol_valid   = 1
                   AND cat_name_intern <> \'CONFIRMATION_OF_PARTICIPATION\'
                   AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                       OR cat_org_id IS NULL )
                   AND mem_begin  <= \''.DATE_NOW.'\'
                   AND mem_end     > \''.DATE_NOW.'\'
                   AND mem_usr_id  = usr_id) as member_this_orga,
                      pfadiname.usd_value as pfadiname,
               (SELECT COUNT(*)
                  FROM '.TBL_MEMBERS.'
            INNER JOIN '.TBL_ROLES.'
                    ON rol_id = mem_rol_id
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
                 WHERE rol_valid   = 1
                   AND cat_name_intern <> \'CONFIRMATION_OF_PARTICIPATION\'
                   AND cat_org_id <> '. $gCurrentOrganization->getValue('org_id'). '
                   AND mem_begin  <= \''.DATE_NOW.'\'
                   AND mem_end     > \''.DATE_NOW.'\'
                   AND mem_usr_id  = usr_id) as member_other_orga,
                      pfadiname.usd_value as pfadiname
          FROM '.TBL_USERS.'
    INNER JOIN '.TBL_USER_DATA.' as last_name
            ON last_name.usd_usr_id = usr_id
           AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
    INNER JOIN '.TBL_USER_DATA.' as first_name
            ON first_name.usd_usr_id = usr_id
           AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
     LEFT JOIN '.TBL_USER_DATA.' as email
            ON email.usd_usr_id = usr_id
           AND email.usd_usf_id = '. $gProfileFields->getProperty('EMAIL', 'usf_id'). '
     LEFT JOIN '.TBL_USER_DATA.' as gender
            ON gender.usd_usr_id = usr_id
           AND gender.usd_usf_id = '. $gProfileFields->getProperty('GENDER', 'usf_id'). '
     LEFT JOIN '.TBL_USER_DATA.' as birthday
            ON birthday.usd_usr_id = usr_id
           AND birthday.usd_usf_id = '. $gProfileFields->getProperty('BIRTHDAY', 'usf_id'). '
     LEFT JOIN '. TBL_USER_DATA. ' as pfadiname
            ON pfadiname.usd_usr_id = usr_id
           AND pfadiname.usd_usf_id = '. $gProfileFields->getProperty('PFADINAME', 'usf_id'). '
         WHERE usr_valid = 1
               '.$memberCondition.'
      ORDER BY last_name.usd_value, first_name.usd_value ';
$mglStatement = $gDb->query($sql);

// Link mit dem alle Benutzer oder nur Mitglieder angezeigt werden setzen
if($getMembers)
{
    $flagShowMembers = 0;
    $htmlShowMembers = '';

}
else
{
    $flagShowMembers = 1;
    $htmlShowMembers = 'checked';
}

// create html page object
$page = new HtmlPage($headline);
$page->enableModal();

$page->addJavascript('
    $("#menu_item_create_user").attr("data-toggle", "modal");
    $("#menu_item_create_user").attr("data-target", "#admidio_modal");

    // change mode of users that should be shown
    $("#mem_show_all").click(function() {
        window.location.replace("'.$g_root_path.'/adm_program/modules/members/members.php?members='.$flagShowMembers.'");
    });', true);

// get module menu
$membersAdministrationMenu = $page->getMenu();

// @ptabaden: Changed icon
$membersAdministrationMenu->addItem('menu_item_create_user', $g_root_path.'/adm_program/modules/members/members_new.php', '<i class="fa fa-plus" alt="'.$gL10n->get('MEM_CREATE_USER').'" title="'.$gL10n->get('MEM_CREATE_USER').'"></i><div class="iconDescription">'.$gL10n->get('MEM_CREATE_USER').'</div>', '');

if($gPreferences['profile_log_edit_fields'] == 1)
{
    // show link to view profile field change history
    // @ptabaden: Changed icon
    $membersAdministrationMenu->addItem('menu_item_change_history', $g_root_path.'/adm_program/modules/members/profile_field_history.php',
                                '<i class="fa fa-history" alt="'.$gL10n->get('MEM_CHANGE_HISTORY').'" title="'.$gL10n->get('MEM_CHANGE_HISTORY').'"></i><div class="iconDescription">'.$gL10n->get('MEM_CHANGE_HISTORY').'</div>', '');
}

// show checkbox to select all users or only active members
if($gPreferences['members_show_all_users'] == 1)
{
    $navbarForm = new HtmlForm('navbar_show_all_users_form', '', $page, array('type' => 'navbar', 'setFocus' => false));
    $navbarForm->addCheckbox('mem_show_all', $gL10n->get('MEM_SHOW_ALL_USERS'), $flagShowMembers, array('helpTextIdLabel' => 'MEM_SHOW_USERS_DESC'));
    $membersAdministrationMenu->addForm($navbarForm->show(false));
}

$membersAdministrationMenu->addItem('menu_item_extras', null, $gL10n->get('SYS_MORE_FEATURES'), null, 'right');

// show link to import users
$membersAdministrationMenu->addItem('menu_item_import_users', $g_root_path.'/adm_program/modules/members/import.php',
                            $gL10n->get('MEM_IMPORT_USERS'), 'database_in.png', 'right', 'menu_item_extras');

if($gCurrentUser->isWebmaster())
{
    // show link to maintain profile fields
    $membersAdministrationMenu->addItem('menu_item_maintain_profile_fields', $g_root_path. '/adm_program/modules/preferences/fields.php',
                                $gL10n->get('PRO_MAINTAIN_PROFILE_FIELDS'), 'application_form_edit.png', 'right', 'menu_item_extras');

    // show link to system preferences of weblinks
    $membersAdministrationMenu->addItem('menu_item_preferences_links', $g_root_path.'/adm_program/modules/preferences/preferences.php?show_option=user_management',
                        $gL10n->get('SYS_MODULE_PREFERENCES'), 'options.png', 'right', 'menu_item_extras');
}

// Create table object
$membersTable = new HtmlTable('tbl_members', $page, true, true, 'table table-condensed');

// create array with all column heading values
// @ptabaden: Removed text "Funktionen" and Status an Mem updated on and image, added sys_vulgo
$columnHeading = array(
    '',
    $gL10n->get('SYS_NAME'),
    $gL10n->get('SYS_VULGO'),
    '',
    $gL10n->get('SYS_BIRTHDAY'),
    ''
);

// @ptabaden: removed two collumn (new 8) and hidd collumn 6 and mem update info
$membersTable->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'left', 'left', 'left', 'right'));
$membersTable->disableDatatablesColumnsSort(8);
$membersTable->addRowHeadingByArray($columnHeading);
$membersTable->setDatatablesRowsPerPage($gPreferences['members_users_per_page']);
$membersTable->setMessageIfNoRowsFound('SYS_NO_ENTRIES');
// set alternative order column for member status icons
$membersTable->setDatatablesAlternativOrderColumns(2, 3);
// $membersTable->setDatatablesColumnsHide(2);
// set alternative order column for gender icons
$membersTable->setDatatablesAlternativOrderColumns(5, 6);
// $membersTable->setDatatablesColumnsHide(6);

$irow = 1;  // Zahler fuer die jeweilige Zeile

while($row = $mglStatement->fetch())
{
    $timestampChange = new DateTimeExtended($row['timestamp'], 'Y-m-d H:i:s');

    // Icon fuer Orgamitglied und Nichtmitglied auswaehlen
    if($row['member_this_orga'] > 0)
    {
        $icon = 'profile.png';
        $iconText = $gL10n->get('SYS_MEMBER_OF_ORGANIZATION', $gCurrentOrganization->getValue('org_longname'));
    }
    else
    {
        $icon = 'no_profile.png';
        $iconText = $gL10n->get('SYS_NOT_MEMBER_OF_ORGANIZATION', $gCurrentOrganization->getValue('org_longname'));
    }

    if($row['member_this_orga'] > 0)
    {
        $memberOfThisOrganization = '1';
    }
    else
    {
        $memberOfThisOrganization = '0';
    }

    // create array with all column values
    // @ptabaden: Deleted link to member of the organisation, and emptied one collumn
    $columnValues = array(
        $irow,
        '<a href="'.$g_root_path.'/adm_program/modules/profile/profile.php?user_id='. $row['usr_id']. '">'. $row['last_name']. ',&nbsp;'. $row['first_name'].'</a>'
        );

    // @ptabaden: Changed to scout name
    if(strlen($row['pfadiname']) > 0)
    {
        $columnValues[] = $row['pfadiname'];
    }
    else
    {
        $columnValues[] = '&ndash;';
    }

    if(strlen($row['gender']) > 0)
    {
        // show selected text of optionfield or combobox
        $arrListValues  = $gProfileFields->getProperty('GENDER', 'usf_value_list');
        $columnValues[] = array('value' => $arrListValues[$row['gender']], 'order' => $row['gender']);
    }
    else
    {
        $columnValues[] = array('value' => '', 'order' => '0');
    }

    if(strlen($row['birthday']) > 0)
    {
        // date must be formated
        $date = new DateTimeExtended($row['birthday'], 'Y-m-d');
        $columnValues[] = $date->format($gPreferences['system_date']);
    }
    else
    {
        $columnValues[] = '';
    }
    
// @ptabaden: Removed edit date (not important for this view)
//    $columnValues[] = $timestampChange->format($gPreferences['system_date'].' '.$gPreferences['system_time']);

    $userAdministration = '';

    // Webmasters can change or send password if login is configured and user is member of current organization
    if($row['member_this_orga'] > 0
    && $gCurrentUser->isWebmaster()
    && strlen($row['usr_login_name']) > 0
    && $row['usr_id'] != $gCurrentUser->getValue('usr_id'))
    {
        if(strlen($row['email']) > 0 && $gPreferences['enable_system_mails'] == 1)
        {
            // if email is set and systemmails are activated then webmaster can send a new password to user
            // @ptabaden: Changed icon
            $userAdministration = '
            <a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/members/members_function.php?usr_id='. $row['usr_id']. '&amp;mode=5"><i class="fa fa-unlock-alt" alt="'.$gL10n->get('MEM_SEND_USERNAME_PASSWORD').'" title="'.$gL10n->get('MEM_SEND_USERNAME_PASSWORD').'"></i></a>';
        }
        else
        {
            // if user has no email or send email is disabled then webmaster could set a new password
            // @ptabaden: Changed icon
            $userAdministration = '
            <a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal" href="'.$g_root_path. '/adm_program/modules/profile/password.php?usr_id='. $row['usr_id']. '"><i class="fa fa-unlock-alt" alt="'.$gL10n->get('SYS_CHANGE_PASSWORD').'" title="'.$gL10n->get('SYS_CHANGE_PASSWORD').'"></i></a>';
        }
    }

    if(strlen($row['email']) > 0)
    {
        if($gPreferences['enable_mail_module'] != 1)
        {
            $mail_link = 'mailto:'. $row['email'];
        }
        else
        {
            $mail_link = $g_root_path.'/adm_program/modules/messages/messages_write.php?usr_id='. $row['usr_id'];
        }
	// @ptabaden: Changed icon
        $userAdministration .= '<a class="admidio-icon-link" href="'.$mail_link.'"><i class="fa fa-envelope" alt="'.$gL10n->get('SYS_SEND_EMAIL_TO', $row['email']).'" title="'.$gL10n->get('SYS_SEND_EMAIL_TO', $row['email']).'"></i></a>';
    }

    // Link um User zu editieren
    // es duerfen keine Nicht-Mitglieder editiert werden, die Mitglied in einer anderen Orga sind
    if($row['member_this_orga'] > 0 || $row['member_other_orga'] == 0)
    {
	// @ptabaden: Changed Icon
        $userAdministration .= '<a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/profile/profile_new.php?user_id='. $row['usr_id']. '"><i class="fa fa-pencil" alt="'.$gL10n->get('MEM_EDIT_USER').'" title="'.$gL10n->get('MEM_EDIT_USER').'"></i></a>';
    }

    // Mitglieder entfernen
    if((($row['member_other_orga'] == 0 && $gCurrentUser->isWebmaster()) // kein Mitglied einer anderen Orga, dann duerfen Webmaster loeschen
        || $row['member_this_orga'] > 0)                              // aktive Mitglieder duerfen von berechtigten Usern entfernt werden
        && $row['usr_id'] != $gCurrentUser->getValue('usr_id'))       // das eigene Profil darf keiner entfernen
    {
        // @ptabaden: Changed icon
        $userAdministration .= '<a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/members/members_function.php?usr_id='.$row['usr_id'].'&amp;mode=6"><i class="fa fa-times" alt="'.$gL10n->get('MEM_REMOVE_USER').'" title="'.$gL10n->get('MEM_REMOVE_USER').'"></i></a>';
    }

    $columnValues[] = $userAdministration;

    $membersTable->addRowByArray($columnValues);

    ++$irow;
}

$page->addHtml($membersTable->show(false));

// show html of complete page
$page->show();
