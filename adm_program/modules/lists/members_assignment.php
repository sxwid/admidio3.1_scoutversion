<?php
/**
 ***********************************************************************************************
 * Assign or remove members to role
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Parameters:
 *
 * mode        - html   : Default mode to show a html list with all users to add them to the role
 *               assign : Add membership of a specific user to the role.
 * rol_id               : Id of role to which members should be assigned or removed
 * usr_id               : Id of the user whose membership should be assigned or removed
 * mem_show_all - true  : (Default) Show only active members of the current organization
 *                false : Show active and inactive members of all organizations in database
 *
 *****************************************************************************/
require_once('../../system/common.php');
require_once('../../system/login_valid.php');

if(isset($_GET['mode']) && $_GET['mode'] === 'assign')
{
    // ajax mode then only show text if error occurs
    $gMessage->showTextOnly(true);
}

// Initialize and check the parameters
$getMode           = admFuncVariableIsValid($_GET, 'mode',          'string', array('defaultValue' => 'html', 'validValues' => array('html', 'assign')));
$getRoleId         = admFuncVariableIsValid($_GET, 'rol_id',        'int',    array('requireValue' => true, 'directOutput' => true));
$getUserId         = admFuncVariableIsValid($_GET, 'usr_id',        'int',    array('directOutput' => true));
$getFilterRoleId   = admFuncVariableIsValid($_GET, 'filter_rol_id', 'int');
$getMembersShowAll = admFuncVariableIsValid($_GET, 'mem_show_all',  'bool');

$_SESSION['set_rol_id'] = $getRoleId;

// create object of the commited role
$role = new TableRoles($gDb, $getRoleId);

// roles of other organizations can't be edited
if($role->getValue('cat_org_id') != $gCurrentOrganization->getValue('org_id') && $role->getValue('cat_org_id') > 0)
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

// check if user is allowed to assign members to this role
if(!$role->allowedToAssignMembers($gCurrentUser))
{
    $gMessage->show($gL10n->get('SYS_NO_RIGHTS'));
}

if($getMembersShowAll)
{
    $getFilterRoleId = 0;
}

if($getFilterRoleId > 0)
{
    if(!$gCurrentUser->hasRightViewRole($getFilterRoleId))
    {
        $gMessage->show($gL10n->get('LST_NO_RIGHTS_VIEW_LIST'));
    }
}

if($getMode === 'assign')
{
    // change membership of that user
    // this must be called as ajax request

    try
    {
        $membership = false;
        $leadership = false;

        if(isset($_POST['member_'.$getUserId]) && $_POST['member_'.$getUserId] === 'true')
        {
            $membership = true;
        }
        if(isset($_POST['leader_'.$getUserId]) && $_POST['leader_'.$getUserId] === 'true')
        {
            $membership = true;
            $leadership = true;
        }

        // Member
        $member = new TableMembers($gDb);

        // Datensatzupdate
        $mem_count = $role->countMembers($getUserId);

        // Wenn Rolle weniger mitglieder hätte als zugelassen oder Leiter hinzugefügt werden soll
        if($leadership || (!$leadership && $membership && ($role->getValue('rol_max_members') > $mem_count || $role->getValue('rol_max_members') == 0 || $role->getValue('rol_max_members') == 0)))
        {
            $member->startMembership($role->getValue('rol_id'), $getUserId, $leadership);

            // find the parent roles and assign user to parent roles
            $dependencies = RoleDependency::getParentRoles($gDb, $role->getValue('rol_id'));
            $parentRoles  = array();

            foreach($dependencies as $tmpRole)
            {
                $member->startMembership($tmpRole, $getUserId);
            }
            echo 'success';
        }
        elseif(!$leadership && !$membership)
        {
            $member->stopMembership($role->getValue('rol_id'), $getUserId);
            echo 'success';
        }
        else
        {
            $gMessage->show($gL10n->get('SYS_ROLE_MAX_MEMBERS', $role->getValue('rol_name')));
        }
    }
    catch(AdmException $e)
    {
        $e->showText();
    }
}
else
{
    // show html list with all users and their membership to this role

    // set headline of the script
    // @ptabaden: Changed to rol_description and added dat_date
    $headline = $role->getValue('rol_description').'<br>'.$gL10n->get('LST_MEMBER_ASSIGNMENT');

    // add current url to navigation stack if last url was not the same page
    if(strpos($gNavigation->getUrl(), 'members_assignment.php') === false)
    {
        $gNavigation->addUrl(CURRENT_URL, $headline);
    }

    // create sql for all relevant users
    $memberCondition = '';

    if($getMembersShowAll)
    {
        // Falls gefordert, aufrufen alle Benutzer aus der Datenbank
        $memberCondition = ' usr_valid = 1 ';
    }
    else
    {
        // Falls gefordert, nur Aufruf von aktiven Mitgliedern der Organisation
        $roleCondition = '';

        if($getFilterRoleId > 0)
        {
            $roleCondition = ' AND mem_rol_id = '.$getFilterRoleId.' ';
        }

        $memberCondition = ' EXISTS
            (SELECT 1
               FROM '.TBL_MEMBERS.'
         INNER JOIN '.TBL_ROLES.'
                 ON rol_id = mem_rol_id
         INNER JOIN '.TBL_CATEGORIES.'
                 ON cat_id = rol_cat_id
              WHERE mem_usr_id = usr_id
                    '.$roleCondition.'
                AND mem_begin <= \''.DATE_NOW.'\'
                AND mem_end    > \''.DATE_NOW.'\'
                AND rol_valid  = 1
                AND cat_name_intern <> \'CONFIRMATION_OF_PARTICIPATION\'
                AND (  cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                    OR cat_org_id IS NULL )) ';
    }

     // SQL-Statement zusammensetzen
    // @ptabaden: Added pfadiname
    $sql = 'SELECT DISTINCT usr_id, last_name.usd_value as last_name, first_name.usd_value as first_name, birthday.usd_value as birthday,
                   city.usd_value as city, address.usd_value as address, zip_code.usd_value as zip_code, country.usd_value as country,
                   mem_usr_id as member_this_role, mem_leader as leader_this_role,
                      (SELECT COUNT(*)
                         FROM '.TBL_ROLES.' rol2, '.TBL_CATEGORIES.' cat2, '.TBL_MEMBERS.' mem2
                        WHERE rol2.rol_valid   = 1
                          AND rol2.rol_cat_id  = cat2.cat_id
                          AND cat2.cat_name_intern <> \'CONFIRMATION_OF_PARTICIPATION\'
                          AND (  cat2.cat_org_id = '. $gCurrentOrganization->getValue('org_id'). '
                              OR cat2.cat_org_id IS NULL )
                          AND mem2.mem_rol_id  = rol2.rol_id
                          AND mem2.mem_begin  <= \''.DATE_NOW.'\'
                          AND mem2.mem_end     > \''.DATE_NOW.'\'
                          AND mem2.mem_usr_id  = usr_id) as member_this_orga,
                              pfadiname.usd_value as pfadiname
              FROM '.TBL_USERS.'
         LEFT JOIN '.TBL_USER_DATA.' as last_name
                ON last_name.usd_usr_id = usr_id
               AND last_name.usd_usf_id = '. $gProfileFields->getProperty('LAST_NAME', 'usf_id'). '
         LEFT JOIN '.TBL_USER_DATA.' as first_name
                ON first_name.usd_usr_id = usr_id
               AND first_name.usd_usf_id = '. $gProfileFields->getProperty('FIRST_NAME', 'usf_id'). '
         LEFT JOIN '.TBL_USER_DATA.' as birthday
                ON birthday.usd_usr_id = usr_id
               AND birthday.usd_usf_id = '. $gProfileFields->getProperty('BIRTHDAY', 'usf_id'). '
         LEFT JOIN '.TBL_USER_DATA.' as city
                ON city.usd_usr_id = usr_id
               AND city.usd_usf_id = '. $gProfileFields->getProperty('CITY', 'usf_id'). '
         LEFT JOIN '.TBL_USER_DATA.' as address
                ON address.usd_usr_id = usr_id
               AND address.usd_usf_id = '. $gProfileFields->getProperty('ADDRESS', 'usf_id'). '
         LEFT JOIN '.TBL_USER_DATA.' as zip_code
                ON zip_code.usd_usr_id = usr_id
               AND zip_code.usd_usf_id = '. $gProfileFields->getProperty('POSTCODE', 'usf_id'). '
         LEFT JOIN '.TBL_USER_DATA.' as country
                ON country.usd_usr_id = usr_id
               AND country.usd_usf_id = '. $gProfileFields->getProperty('COUNTRY', 'usf_id'). '
         LEFT JOIN '.TBL_ROLES.' rol
                ON rol.rol_valid   = 1
               AND rol.rol_id      = '.$getRoleId.'
         LEFT JOIN '.TBL_MEMBERS.' mem
                ON mem.mem_rol_id  = rol.rol_id
               AND mem.mem_begin  <= \''.DATE_NOW.'\'
               AND mem.mem_end     > \''.DATE_NOW.'\'
               AND mem.mem_usr_id  = usr_id
         LEFT JOIN '. TBL_USER_DATA. ' as pfadiname
                ON pfadiname.usd_usr_id = usr_id
               AND pfadiname.usd_usf_id = '. $gProfileFields->getProperty('PFADINAME', 'usf_id'). '
             WHERE '. $memberCondition. '
          ORDER BY last_name, first_name ';
    $userStatement = $gDb->query($sql);

    // create html page object
    $page = new HtmlPage($headline);
    $page->enableModal();

    $javascriptCode = '';

    if($getMembersShowAll)
    {
        $javascriptCode .= '$("#mem_show_all").prop("checked", true);';
    }

    $javascriptCode .= '
        $("#menu_item_create_user").attr("data-toggle", "modal");
        $("#menu_item_create_user").attr("data-target", "#admidio_modal");

        // change mode of users that should be shown
        $("#filter_rol_id").change(function() {
            window.location.replace("'.$g_root_path.'/adm_program/modules/lists/members_assignment.php?rol_id='.$getRoleId.'&filter_rol_id=" + $("#filter_rol_id").val() + "&mem_show_all=0");
        });

        // change mode of users that should be shown
        $("#mem_show_all").click(function() {
            if($("#mem_show_all").is(":checked")) {
                window.location.replace("'.$g_root_path.'/adm_program/modules/lists/members_assignment.php?rol_id='.$getRoleId.'&mem_show_all=1");
            } else {
                window.location.replace("'.$g_root_path.'/adm_program/modules/lists/members_assignment.php?rol_id='.$getRoleId.'&mem_show_all=0");
            }
        });

        // if checkbox of user is clicked then change membership
        $("input[type=checkbox].memlist_checkbox").click(function() {
            var checkbox = $(this);
            // get user id
            var row_id = $(this).parent().parent().attr("id");
            var pos = row_id.search("_");
            var userid = row_id.substring(pos+1);

            var member_checked = $("input[type=checkbox]#member_"+userid).prop("checked");
            var leader_checked = $("input[type=checkbox]#leader_"+userid).prop("checked");

            // Bei Leiter Checkbox setzten, muss Member mit gesetzt werden
            if(checkbox.hasClass("memlist_leader") && leader_checked) {
                $("input[type=checkbox]#member_"+userid).prop("checked", true);
                member_checked = true;
            }

            // Bei entfernen der Mitgliedschaft endet auch das Leiterdasein
            if(checkbox.hasClass("memlist_member") && member_checked == false) {
                $("input[type=checkbox]#leader_"+userid).prop("checked", false);
                leader_checked = false;
            }

            // change data in database
            $.post("'.$g_root_path.'/adm_program/modules/lists/members_assignment.php?mode=assign&rol_id='.$getRoleId.'&usr_id="+userid,
                "member_"+userid+"="+member_checked+"&leader_"+userid+"="+leader_checked,
                function(data) {
                    // check if error occurs
                    if(data !== "success") {
                        // reset checkbox status
                        if(checkbox.prop("checked")) {
                            checkbox.prop("checked", false);
                            if(checkbox.hasClass("memlist_leader")) {
                                $("input[type=checkbox]#member_"+userid).prop("checked", false);
                            }
                        } else {
                            checkbox.prop("checked", true);
                        }

                        alert(data);
                        return false;
                    }
                    return true;
                }
            );
        });';

    $page->addJavascript($javascriptCode, true);

    // get module menu
    $membersAssignmentMenu = $page->getMenu();
    // @ptabaden: Changed Icon
    $membersAssignmentMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), '<i class="fa fa-arrow-left" alt="'.$gL10n->get('SYS_BACK').'" title="'.$gL10n->get('SYS_BACK').'"></i><div class="iconDescription">'.$gL10n->get('SYS_BACK').'</div>', '');
    // @ptabaden: Not important on this page
    // if ($gCurrentUser->editUsers())
    // {
    //     // @ptabaden: Changed Icon
    //     $membersAssignmentMenu->addItem('menu_item_create_user', $g_root_path.'/adm_program/modules/members/members_new.php', '<i class="fa fa-plus" alt="'.$gL10n->get('MEM_CREATE_USER').'" title="'.$gL10n->get('MEM_CREATE_USER').'"></i><div class="iconDescription">'.$gL10n->get('MEM_CREATE_USER').'</div>', '');
    // }
    $navbarForm = new HtmlForm('navbar_show_all_users_form', '', $page, array('type' => 'navbar', 'setFocus' => false));
    $sql = 'SELECT rol_id, rol_name, cat_name
              FROM '.TBL_ROLES.'
        INNER JOIN '.TBL_CATEGORIES.'
                ON cat_id = rol_cat_id
             WHERE rol_valid   = 1
               AND rol_visible = 1
               AND (  cat_org_id  = '.$gCurrentOrganization->getValue('org_id').'
                   OR cat_org_id IS NULL )
          ORDER BY cat_sequence, rol_name';
    $navbarForm->addSelectBoxFromSql('filter_rol_id', '', $gDb, $sql, array('defaultValue' => $getFilterRoleId, 'firstEntry' => $gL10n->get('SYS_ALL')));
    $navbarForm->addCheckbox('mem_show_all', $gL10n->get('MEM_SHOW_ALL_USERS'), 0, array('helpTextIdLabel' => 'MEM_SHOW_USERS_DESC'));
    $membersAssignmentMenu->addForm($navbarForm->show(false));

    // create table object
    // @ptabaden: Renamed to fit to member table
    $table = new HtmlTable('tbl_members', $page, true, true, 'table table-condensed');
    $table->setMessageIfNoRowsFound('SYS_NO_ENTRIES_FOUND');

    // create column header to assign role leaders
    // @ptabaden: Added new language value
    $htmlLeaderColumn = $gL10n->get('SYS_LEADER_PARTICIPANT');

    // show icon that leaders have no additional rights
    if($role->getValue('rol_leader_rights') == ROLE_LEADER_NO_RIGHTS)
    {
        $htmlLeaderColumn .= '<img class="admidio-icon-info" src="'.THEME_PATH.'/icons/info.png"
            alt="'.$gL10n->get('ROL_LEADER_NO_ADDITIONAL_RIGHTS').'" title="'.$gL10n->get('ROL_LEADER_NO_ADDITIONAL_RIGHTS').'" />';
    }

    // show icon with edit user right if leader has this right
    if($role->getValue('rol_leader_rights') == ROLE_LEADER_MEMBERS_EDIT
    || $role->getValue('rol_leader_rights') == ROLE_LEADER_MEMBERS_ASSIGN_EDIT)
    {
        $htmlLeaderColumn .= '<img class="admidio-icon-info" src="'.THEME_PATH.'/icons/profile_edit.png"
            alt="'.$gL10n->get('ROL_LEADER_EDIT_MEMBERS').'" title="'.$gL10n->get('ROL_LEADER_EDIT_MEMBERS').'" />';
    }

    // show icon with assign role right if leader has this right
    if($role->getValue('rol_leader_rights') == ROLE_LEADER_MEMBERS_ASSIGN
    || $role->getValue('rol_leader_rights') == ROLE_LEADER_MEMBERS_ASSIGN_EDIT)
    {
        $htmlLeaderColumn .= '<img class="admidio-icon-info" src="'.THEME_PATH.'/icons/roles.png"
            alt="'.$gL10n->get('ROL_LEADER_ASSIGN_MEMBERS').'" title="'.$gL10n->get('ROL_LEADER_ASSIGN_MEMBERS').'" />';
    }

    // create array with all column heading values
    // @ptabaden: removed first row, removed firstname, moved both checkboxes to end, added pfadiname, added sys_participant value
    $columnHeading = array(
        '',
        $gL10n->get('SYS_LASTNAME'),
        $gL10n->get('SYS_VULGO'),
        $gL10n->get('SYS_BIRTHDAY'),
        $gL10n->get('SYS_PARTICIPANT'),
        $htmlLeaderColumn);

    // @ptabaden: removed first row, and firstname, changed sortvalue and disable sort
    $table->setColumnAlignByArray(array('left', 'left', 'left', 'left', 'center', 'center'));
    $table->setDatatablesOrderColumns(array(2, 3));
    $table->addRowHeadingByArray($columnHeading);
    $table->disableDatatablesColumnsSort(array(5, 6));

    $irow = 1;  // @ptabaden: Zahler fuer die jeweilige Zeile

    // show rows with all organization users
    while($user = $userStatement->fetch())
    {
        $addressText  = ' ';
        $htmlAddress  = '&nbsp;';
        $htmlBirthday = '&nbsp;';
        // @ptabaden: Added htmlpfadiname
        $htmlPfadiname = '&nbsp;';

        if($user['member_this_orga'] > 0)
        {
            $memberOfThisOrganization = '1';
        }
        else
        {
            $memberOfThisOrganization = '0';
        }

        // create string with user address
        if(strlen($user['country']) > 0)
        {
            $addressText .= $gL10n->getCountryByCode($user['country']);
        }
        if(strlen($user['zip_code']) > 0 || strlen($user['city']) > 0)
        {
            $addressText .= ' - '. $user['zip_code']. ' '. $user['city'];
        }
        if(strlen($user['address']) > 0)
        {
            $addressText .= ' - '. $user['address'];
        }

        // Icon fuer Orgamitglied und Nichtmitglied auswaehlen
        if($user['member_this_orga'] > 0)
        {
            $icon = 'profile.png';
            $iconText = $gL10n->get('SYS_MEMBER_OF_ORGANIZATION', $gCurrentOrganization->getValue('org_longname'));
        }
        else
        {
            $icon = 'no_profile.png';
            $iconText = $gL10n->get('SYS_NOT_MEMBER_OF_ORGANIZATION', $gCurrentOrganization->getValue('org_longname'));
        }

        // Haekchen setzen ob jemand Mitglied ist oder nicht
        if($user['member_this_role'])
        {
            $htmlMemberStatus = '<input type="checkbox" id="member_'.$user['usr_id'].'" name="member_'.$user['usr_id'].'" checked="checked" class="memlist_checkbox memlist_member" /><b id="loadindicator_member_'.$user['usr_id'].'"></b>';
        }
        else
        {
            $htmlMemberStatus = '<input type="checkbox" id="member_'.$user['usr_id'].'" name="member_'.$user['usr_id'].'" class="memlist_checkbox memlist_member" /><b id="loadindicator_member_'.$user['usr_id'].'"></b>';
        }

        if(strlen($addressText) > 1)
        {
            $htmlAddress = '<img class="admidio-icon-info" src="'. THEME_PATH.'/icons/map.png" alt="'.$addressText.'" title="'.$addressText.'" />';
        }

        // Haekchen setzen ob jemand Leiter ist oder nicht
        if($user['leader_this_role'])
        {
            $htmlRoleLeader = '<input type="checkbox" id="leader_'.$user['usr_id'].'" name="leader_'.$user['usr_id'].'" checked="checked" class="memlist_checkbox memlist_leader" />';
        }
        else
        {
            $htmlRoleLeader = '<input type="checkbox" id="leader_'.$user['usr_id'].'" name="leader_'.$user['usr_id'].'" class="memlist_checkbox memlist_leader" />';
        }

        // Geburtstag nur ausgeben wenn bekannt
        if(strlen($user['birthday']) > 0)
        {
            $birthdayDate = new DateTimeExtended($user['birthday'], 'Y-m-d');
            $htmlBirthday = $birthdayDate->format($gPreferences['system_date']);
        }

        // @ptabaden: Added pfadiname
        if(strlen($user['pfadiname']) > 0)
        {
            $htmlPfadiname = $user['pfadiname'];
        }
        else
        {
            $htmlPfadiname = '&ndash;';
        }

        // create array with all column values
        // @ptabaden: removed first row and firstname row, added Pfadiname
        $columnValues = array(
            $irow,
            '<a href="'.$g_root_path.'/adm_program/modules/profile/profile.php?user_id='.$user['usr_id'].'">'.$user['last_name'].'</a>, <a href="'.$g_root_path.'/adm_program/modules/profile/profile.php?user_id='.$user['usr_id'].'">'.$user['first_name'].'</a>',
            $htmlPfadiname,
            $htmlBirthday,
            $htmlMemberStatus,
            $htmlRoleLeader.'<b id="loadindicator_leader_'.$user['usr_id'].'"></b>');

        $table->addRowByArray($columnValues, 'userid_'.$user['usr_id']);
        // @ptabaden: Added counter
        ++$irow;
    }//End While

    $page->addHtml($table->show(false));
    $page->addHtml('<p>'.$gL10n->get('SYS_CHECKBOX_AUTOSAVE').'</p>');

    $page->show();
}
