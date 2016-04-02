<?php
/**
 ***********************************************************************************************
 * Show a list of all events
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 * Parameters:
 *
 * mode      - actual : (Default) shows actual dates and all events in future
 *             old    : shows events in the past
 *             all    : shows all events in past and future
 * start     - Position of query recordset where the visual output should start
 * headline  - Headline shown over events
 *             (Default) Events
 * cat_id    - show all events of calendar with this id
 * id        - Show only one event
 * show      - all               : (Default) show all events
 *           - maybe_participate : Show only events where the current user participates or could participate
 *           - only_participate  : Show only events where the current user participates
 * date_from - is set to actual date,
 *             if no date information is delivered
 * date_to   - is set to 31.12.9999,
 *             if no date information is delivered
 * view_mode - Content output in 'html' or 'print' view
 * view      - Content output in different views like 'detail', 'list'
 *             (Default: according to preferences)
 *****************************************************************************/
require_once('../../system/common.php');

unset($_SESSION['dates_request']);

// Initialize and check the parameters
$getMode     = admFuncVariableIsValid($_GET, 'mode',      'string', array('defaultValue' => 'actual', 'validValues' => array('actual', 'old', 'all')));
$getStart    = admFuncVariableIsValid($_GET, 'start',     'int');
$getHeadline = admFuncVariableIsValid($_GET, 'headline',  'string', array('defaultValue' => $gL10n->get('DAT_DATES')));
$getCatId    = admFuncVariableIsValid($_GET, 'cat_id',    'int');
$getId       = admFuncVariableIsValid($_GET, 'id',        'int');
$getShow     = admFuncVariableIsValid($_GET, 'show',      'string', array('defaultValue' => 'all', 'validValues' => array('all', 'maybe_participate', 'only_participate')));
$getDateFrom = admFuncVariableIsValid($_GET, 'date_from', 'date');
$getDateTo   = admFuncVariableIsValid($_GET, 'date_to',   'date');
$getViewMode = admFuncVariableIsValid($_GET, 'view_mode', 'string', array('defaultValue' => 'html', 'validValues' => array('html', 'print')));
$getView     = admFuncVariableIsValid($_GET, 'view',      'string', array('defaultValue' => $gPreferences['dates_view'], 'validValues' => array('detail', 'compact', 'room', 'participants', 'description')));

// check if module is active
if($gPreferences['enable_dates_module'] == 0)
{
    // Module is not active
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}
elseif($gPreferences['enable_dates_module'] == 2)
{
    // module only for valid Users
    require_once('../../system/login_valid.php');
}

// create object and get recordset of available dates

try
{
    $dates = new ModuleDates();
    $dates->setParameter('mode', $getMode);
    $dates->setParameter('cat_id', $getCatId);
    $dates->setParameter('id', $getId);
    $dates->setParameter('show', $getShow);
    $dates->setParameter('view_mode', $getViewMode);
    $dates->setDateRange($getDateFrom, $getDateTo);
}
catch(AdmException $e)
{
    $e->showHtml();
}

if($getCatId > 0)
{
    $calendar = new TableCategory($gDb, $getCatId);
}

// Number of events each page for default view 'html' or 'compact' view
if($gPreferences['dates_per_page'] > 0 && $getViewMode === 'html')
{
    $datesPerPage = $gPreferences['dates_per_page'];
}
else
{
    $datesPerPage = $dates->getDataSetCount();
}

// read relevant events from database
$datesResult     = $dates->getDataset($getStart, $datesPerPage);
$datesTotalCount = $dates->getDataSetCount();

if($getViewMode !== 'print' && $getId === 0)
{
    // Navigation of the module starts here
    $gNavigation->addStartUrl(CURRENT_URL, $dates->getHeadline($getHeadline));
}

// create html page object
// @ptabaden: detailed event view with main title, added calendar name (roverstufe, leiter etc.)
$page = new HtmlPage($dates->getHeadline($getHeadline));
$page->enableModal();

if($getViewMode === 'html')
{
    $datatable  = true;
    $hoverRows  = true;
    $classTable = 'table';

    if($gPreferences['enable_rss'] == 1 && $gPreferences['enable_dates_module'] == 1)
    {
        $page->addRssFile($g_root_path.'/adm_program/modules/dates/rss_dates.php?headline='.$getHeadline,
                          $gL10n->get('SYS_RSS_FEED_FOR_VAR',
                          $gCurrentOrganization->getValue('org_longname').' - '.$getHeadline));
    };

    $page->addJavascript('
        $("#sel_change_view").change(function () {
            self.location.href = "dates.php?view=" + $("#sel_change_view").val() + "&mode='.$getMode.'&headline='.$getHeadline.'&date_from='.$dates->getParameter('dateStartFormatAdmidio').'&date_to='.$dates->getParameter('dateEndFormatAdmidio').'&cat_id='.$getCatId.'";
        });

        $("#menu_item_print_view").click(function () {
            window.open("'.$g_root_path.'/adm_program/modules/dates/dates.php?view_mode=print&view='.$getView.'&mode='.$getMode.'&headline='.$getHeadline.'&cat_id='.$getCatId.'&date_from='.$dates->getParameter('dateStartFormatEnglish').'&date_to='.$dates->getParameter('dateEndFormatEnglish').'", "_blank");
        });', true);

    // If default view mode is set to compact we need a back navigation if one date is selected for detail view
    if($getId > 0)
    {
        // add back link to module menu
        // @ptabaden: changed icon
        $datesMenu = $page->getMenu();
        $datesMenu->addItem('menu_item_back', $gNavigation->getPreviousUrl(), '<i class="fa fa-arrow-left" alt="'.$gL10n->get('SYS_BACK').'" title="'.$gL10n->get('SYS_BACK').'"></i><div class="iconDescription">'.$gL10n->get('SYS_BACK').'</div>', '');
    }

    // get module menu
    $DatesMenu = $page->getMenu();

        //Add new event
        // @ptabaden: delted icons addItem
        if($gCurrentUser->editDates())
        {
            $DatesMenu->addItem('admMenuItemAdd', $g_root_path.'/adm_program/modules/dates/dates_new.php?headline='.$getHeadline, '<i class="fa fa-calendar-plus-o" alt="'.$gL10n->get('SYS_CREATE_VAR').'" title="'.$gL10n->get('SYS_CREATE_VAR').'"></i><div class="iconDescription">Neue Aktivit&auml;t</div>', '');
        }

    if($getId === 0)
    {
        $form = new HtmlForm('navbar_change_view_form', '', $page, array('type' => 'navbar', 'setFocus' => false));
        if($gPreferences['dates_show_rooms'])
        {
            $selectBoxEntries = array('detail' => $gL10n->get('DAT_VIEW_MODE_DETAIL'), 'compact' => $gL10n->get('DAT_VIEW_MODE_COMPACT'), 'room' => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_ROOM'), 'participants' => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_PARTICIPANTS'), 'description' => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_DESCRIPTION'));
        }
        else
        {
            $selectBoxEntries = array('detail' => $gL10n->get('DAT_VIEW_MODE_DETAIL'), 'compact' => $gL10n->get('DAT_VIEW_MODE_COMPACT'), 'participants' => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_PARTICIPANTS'), 'description' => $gL10n->get('DAT_VIEW_MODE_COMPACT').' - '.$gL10n->get('SYS_DESCRIPTION'));
        }
	// @ptabaden: No Change-View Box #todo
         // $form->addSelectBox('sel_change_view', $gL10n->get('SYS_VIEW'), $selectBoxEntries, array('defaultValue' => $getView, 'showContextDependentFirstEntry' => false));
         // $DatesMenu->addForm($form->show(false));


        // show print button
	// @ptabaden: Changed Icon
        $DatesMenu->addItem('menu_item_print_view', '#', '<i class="fa fa-print" alt="'.$gL10n->get('LST_PRINT_PREVIEW').'" title="'.$gL10n->get('LST_PRINT_PREVIEW').'"></i><div class="iconDescription">'.$gL10n->get('LST_PRINT_PREVIEW').'</div>', '');

        if($gPreferences['enable_dates_ical'] == 1 || $gCurrentUser->isWebmaster() || $gCurrentUser->editDates())
        {
            $DatesMenu->addItem('menu_item_extras', null, $gL10n->get('SYS_MORE_FEATURES'), null, 'right');
        }

        // ical Download
	// @ptabaden: Chagend Icon
        if($gPreferences['enable_dates_ical'] == 1)
        {
            $DatesMenu->addItem('admMenuItemICal',
                                $g_root_path.'/adm_program/modules/dates/ical_dates.php?headline='.$getHeadline.'&amp;cat_id='.$getCatId,
                                '<i class="fa fa-download" alt="'.$gL10n->get('DAT_EXPORT_ICAL').'" title="'.$gL10n->get('DAT_EXPORT_ICAL').'"></i><div class="iconDescription">'.$gL10n->get('DAT_EXPORT_ICAL').'</div>', '');
        }

            if($gCurrentUser->isWebmaster())
            {
                // show link to system preferences of current module
                // @ptabaden: delted icons Preferences & renamed to menu_item_preferences
                $DatesMenu->addItem('menu_item_preferences', $g_root_path.'/adm_program/modules/preferences/preferences.php?show_option=events',
                                    '<i class="fa fa-cog" alt="'.$gL10n->get('SYS_MODULE_PREFERENCES').'" title="'.$gL10n->get('SYS_MODULE_PREFERENCES').'"></i><div class="iconDescription">'.$gL10n->get('SYS_MODULE_PREFERENCES').'</div>', '', 'right');
            }
            elseif($gCurrentUser->editDates())
            {
                // if no calendar selectbox is shown, then show link to edit calendars
                // @ptabaden: Removed. Webmaster-only management
            }
        }

    // create filter menu with elements for calendar and start-/enddate
    $FilterNavbar = new HtmlNavbar('menu_dates_filter', null, null, 'filter');

    // @ptabaden: Removed SelectBoxForCat
    $form = new HtmlForm('navbar_filter_form', $g_root_path.'/adm_program/modules/dates/dates.php?headline='.$getHeadline.'&view='.$getView, $page, array('type' => 'navbar', 'setFocus' => false));
    // $form->addSelectBoxForCategories('cat_id', '', $gDb, 'DAT', 'FILTER_CATEGORIES', array('defaultValue' => $dates->getParameter('cat_id')));
    // @ptabaden: Deleted filter from to
    $FilterNavbar->addForm($form->show(false));
    $page->addHtml($FilterNavbar->show(false));
    
}
elseif($getViewMode === 'print')
{
    $datatable  = false;
    $hoverRows  = false;
    $classTable = 'table table-condensed table-striped';

    // create html page object without the custom theme files
    $page->hideThemeHtml();
    $page->hideMenu();
    $page->setPrintMode();
    $page->addHtml('<h3>'.$gL10n->get('DAT_PERIOD_FROM_TO', $dates->getParameter('dateStartFormatAdmidio'), $dates->getParameter('dateEndFormatAdmidio')).'</h3>');
}

if($datesTotalCount == 0)
{
    // No events found
    if($getId > 0)
    {
        $page->addHtml('<p>'.$gL10n->get('SYS_NO_ENTRY').'</p>');
    }
    else
    {
        $page->addHtml('<p>'.$gL10n->get('SYS_NO_ENTRIES').'</p>');
    }
}
else
{
    // Output table header for compact view
    // @ptabaden: Changed titles to match the content of the table
    if ($getView === 'compact' || $getView === 'room' || $getView === 'participants' || $getView === 'description')
    {
        $compactTable = new HtmlTable('events_compact_table', $page, $hoverRows, $datatable, $classTable);

        switch ($getView)
        {
            case 'compact':
                $columnHeading = array('&nbsp;','&nbsp;');
                $columnAlign   = array('left', 'left');
                $compactTable->disableDatatablesColumnsSort(3);
                break;
            case 'room':
                $columnHeading = array('&nbsp;', $gL10n->get('SYS_PERIOD'), $gL10n->get('DAT_DATE'), $gL10n->get('SYS_ROOM'), $gL10n->get('SYS_LEADERS'), $gL10n->get('SYS_PARTICIPANTS'));
                $columnAlign   = array('center', 'left', 'left', 'left', 'left', 'left');
                $compactTable->disableDatatablesColumnsSort(7);
                break;
            case 'participants':
                $columnHeading = array('&nbsp;', $gL10n->get('SYS_PERIOD'), $gL10n->get('DAT_DATE'), $gL10n->get('SYS_PARTICIPANTS'));
                $columnAlign   = array('center', 'left', 'left', 'left');
                $compactTable->disableDatatablesColumnsSort(5);
                $compactTable->setColumnWidth(4, '35%');
                break;
            case 'description':
                $columnHeading = array('&nbsp;', $gL10n->get('SYS_PERIOD'), $gL10n->get('DAT_DATE'), $gL10n->get('SYS_DESCRIPTION'));
                $columnAlign   = array('center', 'left', 'left', 'left');
                $compactTable->disableDatatablesColumnsSort(5);
                $compactTable->setColumnWidth(4, '35%');
                break;
        }

        if($getViewMode === 'html')
        {
            $columnHeading[] = '&nbsp;';
            $columnAlign[]   = 'right';
        }

        $compactTable->setColumnAlignByArray($columnAlign);
        $compactTable->addRowHeadingByArray($columnHeading);
    }

    // create dummy date object
    $date = new TableDate($gDb);

    foreach($datesResult['recordset'] as $row)
    {
        // write of current event data to date object
        $date->setArray($row);

        // initialize all output elements
        $outputEndDate      = '';
        $outputButtonIcal   = '';
        $outputButtonEdit   = '';
        $outputButtonDelete = '';
        $outputButtonCopy   = '';
        $outputButtonParticipation      = '';
        $outputButtonParticipants       = '';
        $outputButtonParticipantsEmail  = '';
        $outputButtonParticipantsAssign = '';
        $outputLinkLocation  = '';
        $outputLinkRoom      = '';
        $outputNumberMembers = '';
        $outputNumberLeaders = '';
        $dateElements        = array();
        $participantsArray   = array();

        // @ptabaden: added two values:
        $locationHtmlValue = '';        

        // set end date of event
        if($date->getValue('dat_begin', $gPreferences['system_date']) != $date->getValue('dat_end', $gPreferences['system_date']))
        {
            $outputEndDate = ' - '.$date->getValue('dat_end', $gPreferences['system_date']);
        }

        if($getViewMode === 'html')
        {
            // ical Download
	    // @ptabaden: Changed Icon
            if($gPreferences['enable_dates_ical'] == 1)
            {
                $outputButtonIcal = '
                    <a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/dates/dates_function.php?dat_id='.$date->getValue('dat_id'). '&amp;mode=6">
                        <i class="fa fa-download" alt="'.$gL10n->get('DAT_EXPORT_ICAL').'" title="'.$gL10n->get('DAT_EXPORT_ICAL').'"></i></a>';
            }

            // change and delete is only for users with additional rights
	    // @ptabaden: Changed Icons
            if ($gCurrentUser->editDates())
            {
                if($date->editRight())
                {
                    $outputButtonCopy = '
                        <a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/dates/dates_new.php?dat_id='.$date->getValue('dat_id'). '&amp;copy=1&amp;headline='.$getHeadline.'">
                            <i class="fa fa-files-o" alt="'.$gL10n->get('SYS_COPY').'" title="'.$gL10n->get('SYS_COPY').'"></i></a>';
                    $outputButtonEdit = '
                        <a class="admidio-icon-link" href="'.$g_root_path.'/adm_program/modules/dates/dates_new.php?dat_id='.$date->getValue('dat_id'). '&amp;headline='.$getHeadline.'">
                            <i class="fa fa-pencil" alt="'.$gL10n->get('SYS_EDIT').'" title="'.$gL10n->get('SYS_EDIT').'"></i></a>';
                }

                // Deleting events is only allowed for group members
		// @ptabaden: Changed Icons
                if($date->getValue('cat_org_id') == $gCurrentOrganization->getValue('org_id'))
                {
                    $outputButtonDelete = '
                        <a class="admidio-icon-link" data-toggle="modal" data-target="#admidio_modal"
                            href="'.$g_root_path.'/adm_program/system/popup_message.php?type=dat&amp;element_id=dat_'.
                            $date->getValue('dat_id').'&amp;name='.
                            urlencode($date->getValue('dat_begin', $gPreferences['system_date']).' '.
                            $date->getValue('dat_headline')).'&amp;database_id='.$date->getValue('dat_id').'">
                            <i class="fa fa-times" alt="'.$gL10n->get('SYS_DELETE').'" title="'.$gL10n->get('SYS_DELETE').'"></i></a>';
                }
            }
        }

        if ($date->getValue('dat_location') !== '')
        {
            // @ptabaden: added location text without link
            $outputLinkLocation = $date->getValue("dat_location");

            // Show map link, when at least 2 words available
            // having more than 3 characters each
            $countLocationWords = 0;
            foreach(preg_split('/[,; ]/', $date->getValue('dat_location')) as $key => $value)
            {
		// @ptabaden: changed min string length to bigger than 1 (so AG works)
                if(strlen($value) > 1)
                {
                    ++$countLocationWords;
                }
            }

            if($gPreferences['dates_show_map_link'] && $countLocationWords > 1 && $getViewMode === 'html')
            {
                // Create Google-Maps-Link for location
                $location_url = 'https://maps.google.ch/?q='.$date->getValue('dat_location');

                if($date->getValue('dat_country') !== '')
                {
                    // Better results with additional country information
                    $location_url .= ',%20'.$date->getValue('dat_country');
                }
		/* @ptabaden: added map icon, deleted strong */
        // @ptabaden: Added .= to outputlinkloc

                $outputLinkLocation .= '
                    <div class="date-info-icon"><a href="'.$location_url.'" target="_blank" title="'.$gL10n->get('DAT_SHOW_ON_MAP').'"/>
                        <i class="fa fa-map" alt="'.$gL10n->get('DAT_SHOW_ON_MAP').'" title="'.$gL10n->get('DAT_SHOW_ON_MAP').'"></i></a>';

                // if valid login and enough information about address exist - calculate the route
                if($gValidLogin && $gCurrentUser->getValue('ADDRESS') !== ''
                && ($gCurrentUser->getValue('POSTCODE') !== '' || $gCurrentUser->getValue('CITY') !== ''))
                {
		    // @ptabaden: changed to Google Switzerland
                    $route_url = 'https://maps.google.ch/?f=d&amp;saddr='.urlencode($gCurrentUser->getValue('ADDRESS'));

                    if($gCurrentUser->getValue('POSTCODE') !== '')
                    {
                        $route_url .= ',%20'.urlencode($gCurrentUser->getValue('POSTCODE'));
                    }
                    if($gCurrentUser->getValue('CITY') !== '')
                    {
                        $route_url .= ',%20'.urlencode($gCurrentUser->getValue('CITY'));
                    }
                    if($gCurrentUser->getValue('COUNTRY') !== '')
                    {
                        $route_url .= ',%20'.urlencode($gCurrentUser->getValue('COUNTRY'));
                    }

                    $route_url .= '&amp;daddr='.urlencode($date->getValue('dat_location'));

                    if($date->getValue('dat_country') !== '')
                    {
                        // With information about country Google finds the location much better
                        $route_url .= ',%20'.$date->getValue('dat_country');
                    }
		    // @ptabanden: added map route icon
                    $outputLinkLocation .= '
                        <a class="admidio-icon-link" href="'.$route_url.'" target="_blank">
                            <i class="fa fa-map-signs" alt="'.$gL10n->get('SYS_SHOW_ROUTE').'" title="'.$gL10n->get('SYS_SHOW_ROUTE').'"></i></a>';
                }
		    $outputLinkLocation .= '</div>';
            }
            else
            {
                $outputLinkLocation = $date->getValue('dat_location');
            }
        }

        // if active, then show room information
        if($date->getValue('dat_room_id') > 0)
        {
            $room = new TableRooms($gDb, $date->getValue('dat_room_id'));

            if($getViewMode === 'html')
            {
                $roomLink = $g_root_path. '/adm_program/system/msg_window.php?message_id=room_detail&amp;message_title=DAT_ROOM_INFORMATIONS&amp;message_var1='.$date->getValue('dat_room_id').'&amp;inline=true';
                $outputLinkRoom = '<strong><a data-toggle="modal" data-target="#admidio_modal" href="'.$roomLink.'">'.$room->getValue('room_name').'</a></strong>';
            }
            else
            {
                $outputLinkRoom = $room->getValue('room_name');
            }
        }

        // count participants of the date
        if($date->getValue('dat_rol_id') > 0)
        {
            $participants = new Participants($gDb, $date->getValue('dat_rol_id'));
            $outputNumberMembers = $participants->getCount();
            $outputNumberLeaders = $participants->getNumLeaders();

            if($getView === 'participants')
            {
                $participantsArray = $participants->getParticipantsArray($date->getValue('dat_rol_id'));
            }
        }

        // Links for the participation only in html mode
        if($date->getValue('dat_rol_id') > 0 && $getViewMode === 'html')
        {
            if($row['member_date_role'] > 0)
            {
                $buttonURL = $g_root_path.'/adm_program/modules/dates/dates_function.php?mode=4&amp;dat_id='.$date->getValue('dat_id');
		// @ptabaden: Changed attend / do not attend button
                if ($getView === 'detail')
                {
                    $outputButtonParticipation = '
                        <button class="btn btn-default attend no" onclick="window.location.href=\''.$buttonURL.'\'"><i class="fa fa-times" alt="'.$gL10n->get('DAT_CANCEL').'" title="'.$gL10n->get('DAT_CANCEL').'"></i>'.'<div id="icon-text">'.$gL10n->get('DAT_CANCEL').'</div>'.'</button>';
                }
                else
                {
                    $outputButtonParticipation = '
                        <a class="admidio-icon-link" href="'.$buttonURL.'"><div class="checked in" alt="'.$gL10n->get('DAT_CANCEL').'" title="'.$gL10n->get('DAT_CANCEL').'" ></div></a>';
                }
            }
            else
            {
                $participationPossible = true;

                if($date->getValue('dat_max_members'))
                {
                    // Check limit of participants
                    if($participants->getCount($date->getValue('dat_rol_id')) >= $date->getValue('dat_max_members'))
                    {
                        $participationPossible = false;
                    }
                }

                if($participationPossible)
                {
                    $buttonURL = $g_root_path.'/adm_program/modules/dates/dates_function.php?mode=3&amp;dat_id='.$date->getValue('dat_id');

		    // @ptabaden: Changed attend / do not attend button
                    if ($getView === 'detail')
                    {
			// @ptabaden: changed icon
                        $outputButtonParticipation = '
                            <button class="btn btn-default attend yes" onclick="window.location.href=\''.$buttonURL.'\'"><i class="fa fa-check" alt="'.$gL10n->get('DAT_ATTEND').'" title="'.$gL10n->get('DAT_ATTEND').'"></i>'.'<div id="icon-text">'.$gL10n->get('DAT_ATTEND').'</div>'.'</button>';
                    }
                    else
                    {
                        $outputButtonParticipation = '
                            <a class="admidio-icon-link" href="'.$buttonURL.'"><div class="checked out" alt="'.$gL10n->get('DAT_ATTEND').'" title="'.$gL10n->get('DAT_ATTEND').'" ></div></a>';
                    }
                }
                else
                {
                    $outputButtonParticipation = $gL10n->get('DAT_REGISTRATION_NOT_POSSIBLE');
                }
            }

            // Link to participants list
            if($gValidLogin)
            {
                if($outputNumberMembers > 0 || $outputNumberLeaders > 0)
                {
                    $buttonURL = $g_root_path.'/adm_program/modules/lists/lists_show.php?mode=html&amp;rol_ids='.$date->getValue('dat_rol_id');

		    	// @ptabaden: changed icon / deleted detail viewmode
                        $outputButtonParticipants = '<li><a class="admidio-icon-link" href="'.$buttonURL.'"><i class="fa fa-users" alt="'.$gL10n->get('DAT_SHOW_PARTICIPANTS').'" title="'.$gL10n->get('DAT_SHOW_PARTICIPANTS').'"></i><div class="iconDescription">'.$gL10n->get('DAT_SHOW_PARTICIPANTS').'</div></a></li>';

                }
            }

            // Link to send email to participants
            if($gValidLogin && $gCurrentUser->hasRightSendMailToRole($date->getValue('dat_rol_id')))
            {
                if($outputNumberMembers > 0 || $outputNumberLeaders > 0)
                {
                    $buttonURL = $g_root_path.'/adm_program/modules/messages/messages_write.php?rol_id='.$date->getValue('dat_rol_id');
		    // @ptabaden: changed icon and button type
                    if ($getView === 'detail')
                    {
                        $outputButtonParticipantsEmail = '
                            <li><a class="admidio-icon-link" href="'.$buttonURL.'"><i class="fa fa-envelope" alt="'.$gL10n->get('MAI_SEND_EMAIL').'" title="'.$gL10n->get('MAI_SEND_EMAIL').'"></i><div class="iconDescription">E-Mail an Teilnehmer</div></a></li>';
                    }
		    // @ptabaden: deleted display in html mode

                }
            }

            // Link for managing new participants
	    // @ptabaden: Changed Icon
            if($row['mem_leader'] == 1)
            {
                $buttonURL = $g_root_path.'/adm_program/modules/lists/members_assignment.php?rol_id='.$date->getValue('dat_rol_id');

                if ($getView === 'detail')
                {
                    $outputButtonParticipantsAssign = '
                        <li><a class="admidio-icon-link" href="'.$buttonURL.'"><i class="fa fa-user-plus" alt="'.$gL10n->get('DAT_ASSIGN_PARTICIPANTS').'" title="'.$gL10n->get('DAT_ASSIGN_PARTICIPANTS').'"></i><div class="iconDescription">'.$gL10n->get('DAT_ASSIGN_PARTICIPANTS').'</div></a></li>';
                }
		// @ptabaden: deleted display in html mode

            }
        }

        if($getView === 'detail')
        {
            if ($date->getValue('dat_all_day') == 0)
            {
                // Write start in array
                $dateElements[] = array($gL10n->get('SYS_START'), '<strong>'.$date->getValue('dat_begin', $gPreferences['system_time']).'</strong> '.$gL10n->get('SYS_CLOCK'));
                // Write end in array
                $dateElements[] = array($gL10n->get('SYS_END'), '<strong>'.$date->getValue('dat_end', $gPreferences['system_time']).'</strong> '.$gL10n->get('SYS_CLOCK'));
            }

            $dateElements[] = array($gL10n->get('DAT_CALENDAR'), '<strong>'.$date->getValue('cat_name').'</strong>');
            if($outputLinkLocation !== '')
            {
                $dateElements[] = array($gL10n->get('DAT_LOCATION'), $outputLinkLocation);
            }
            if($outputLinkRoom !== '')
            {
                $dateElements[] = array($gL10n->get('SYS_ROOM'), $outputLinkRoom);
            }
            if($outputNumberLeaders !== '')
            {
                $dateElements[] = array($gL10n->get('SYS_LEADERS'), '<strong>'.$outputNumberLeaders.'</strong>');
            }
            if($outputNumberMembers !== '')
            {
                $dateElements[] = array($gL10n->get('SYS_PARTICIPANTS'), '<strong>'.$outputNumberMembers.'</strong>');
            }

            // show panel view of events

            $cssClassHighlight = '';

            // Change css if date is highlighted
            if($row['dat_highlight'] == 1)
            {
                $cssClassHighlight = ' admidio-event-highlight';
            }

            // Output of elements
            // always 2 then line break
            $firstElement = true;
            $htmlDateElements = '';

            foreach($dateElements as $element)
            {
                if($element[1] !== '')
                {
                    if($firstElement)
                    {
                        $htmlDateElements .= '<div class="row">';
                    }

                    $htmlDateElements .= '<div class="col-sm-2 col-xs-4">'.$element[0].'</div>
                        <div class="col-sm-4 col-xs-8">'.$element[1].'</div>';

                    if($firstElement)
                    {
                        $firstElement = false;
                    }
                    else
                    {
                        $htmlDateElements .= '</div>';
                        $firstElement = true;
                    }
                }
            }

            if(!$firstElement)
            {
                $htmlDateElements .= '</div>';

            }

	    // @ptabaden: Changed content and order
            $page->addHtml('
                <div class="panel panel-primary'.$cssClassHighlight.'" id="dat_'.$date->getValue('dat_id').'">
                    <div class="panel-heading">
                        <h2>
                            '.$date->getValue('dat_headline').'
                        </h2>
			            <div class="date-info"><h4>' .
                            $date->getValue('dat_begin', $gPreferences['system_date']).$outputEndDate.'<div class="date-actions">'.$outputButtonIcal . $outputButtonCopy . $outputButtonEdit . $outputButtonDelete . '
                        </h4></div>
			            <h4>'.$date->getValue('dat_begin', $gPreferences['system_time']).' &ndash; '.$date->getValue('dat_end', $gPreferences['system_time']). ' '.$gL10n->get('SYS_CLOCK').'</h4><h4>'.$outputLinkLocation.'</h4>
                    </div>');
                    if($date->getValue('dat_rol_id') > 0 && $gValidLogin)
                    {
                        $page->addHtml('<h4>');
                        
                        if($outputNumberLeaders > 0 ) 
                        {
                        $page->addHtml($outputNumberLeaders.' '.$gL10n->get('SYS_LEADER'));
                        }
                        
                        if($outputNumberLeaders > 0 && $outputNumberMembers > 0)
                        {
                        $page->addHtml(', ');
                        }
                        
                        if($outputNumberMembers > 0) 
                        {
                        $page->addHtml($outputNumberMembers.' '.$gL10n->get('SYS_PARTICIPANTS'));
                        }
                        $page->addHtml('</h4>');
                    }
                    $page->addHtml('
                    

                    <div class="panel-body">
                        <p>' . $date->getValue('dat_description') . '</p>');

            if($outputButtonParticipation !== '' || $outputButtonParticipants !== ''
            || $outputButtonParticipantsEmail !== '' || $outputButtonParticipantsAssign !== '')
            {
		// @ptabaden: removed button-group
                $page->addHtml('<div class="btn-placement-group">'.$outputButtonParticipation.$outputButtonParticipants.$outputButtonParticipantsEmail.$outputButtonParticipantsAssign.'</div>');
            }
            $page->addHtml('
                </div>
                <div class="panel-footer">'.
                    // @ptabaden: show only create user name and date, not changed by
                    // @ptabaden change Scoutname
                    admFuncShowCreateChangeInfoById($date->getValue('dat_usr_id_create'), $date->getValue('dat_timestamp_create'),
                                                    $date->getValue('dat_usr_id_change'), $date->getValue('dat_timestamp_change')).'
                    </div>
                </div>');
        }
        else
        {
            // show table view of events

            // Change css class if date is highlighted
            $cssClass = '';
            if($row['dat_highlight'])
            {
                $cssClass = 'admidio-event-highlight';
            }

            // date beginn
            $objDateBegin = new DateTime($row['dat_begin']);
            $dateBegin = $objDateBegin->format($gPreferences['system_date']);
            $timeBegin = $date->getValue('dat_begin', $gPreferences['system_time']). ' '.$gL10n->get('SYS_CLOCK');

            // date beginn
            $objDateEnd = new DateTime($row['dat_end']);
            $dateEnd = $objDateEnd->format($gPreferences['system_date']);
            $timeEnd = $date->getValue('dat_end', $gPreferences['system_time']);

            $dateTimeValue = '';
            // @ptabaden: Added Var dateBeginAndEnd
            $dateBeginAndEnd = '';
            $columnValues = array();
            // @ptabaden: Rewrite of whole section to fit to compact pta display

            if($outputButtonParticipation !== '')
            {
                $columnValues[] = $outputButtonParticipation;
            }
            else
            {
                $columnValues[] = '&nbsp;';
            }

            if($dateBegin === $dateEnd)
            {
                $dateBeginAndEnd = '<h4 id="event_date">'.$dateBegin.'</h4>';
            }
            else
            {
                $dateBeginAndEnd = '<h4 id="event_date">'.$dateBegin.'<br>'. $dateEnd.'</h4>';
            }

            if($getViewMode === 'html')
            {
                // @ptabaden: Placed back the link to detailed view
                $columnValues[] = $dateBeginAndEnd.'<div id="event" class="table_group"><h2 id="event_title"><a href="'.$g_root_path.'/adm_program/modules/dates/dates.php?id='.$date->getValue('dat_id').'&amp;view_mode=html&view=detail&amp;headline='.$date->getValue('dat_headline').'">'.$date->getValue('cat_name').': '.$date->getValue('dat_headline').'</a></h2>'
                .$timeBegin.'<div>'.$outputLinkLocation.'</div>';

            }
            else
            {
                $columnValues[] = $date->getValue('dat_headline');
            }

            if($getView === 'room')
            {
                $columnValues[] = $outputLinkRoom;
                $columnValues[] = $outputNumberLeaders;
            }

            if($getView === 'compact' || $getView === 'room')
            {
                if($date->getValue('dat_rol_id') > 0)
                {
                    if($date->getValue('dat_max_members') > 0)
                    {
                        $htmlParticipants = $outputNumberMembers.' / '.$date->getValue('dat_max_members');
                    }
                    else
                    {
                        $htmlParticipants = $outputNumberMembers.'&nbsp;';
                    }

                    if($outputNumberMembers > 0)
                    {
                        $htmlParticipants .= $outputButtonParticipants.$outputButtonParticipantsEmail;
                    }
                    // @ptabaden: No Participants showing here please
                    // $columnValues[] = $htmlParticipants;
                }
                // @ptabaden: Removed Col Value
                // else
                // {
                    // $columnValues[] = '';
                // }
            }
            elseif($getView === 'participants')
            {
                $columnValue = '';

                if(is_array($participantsArray))
                {
                    foreach($participantsArray as $participant)
                    {
                        $columnValue .= $participant['firstname']. ' '. $participant['surname']. ', ';
                    }
                }
                $columnValues[] = substr($columnValue, 0, strlen($columnValue) - 2);
            }
            elseif($getView === 'description')
            {
                $columnValues[] = $date->getValue('dat_description');
            }

            if($getViewMode === 'detailed')
            {
                $columnValues[] = $outputButtonIcal . $outputButtonCopy . $outputButtonEdit . $outputButtonDelete;
            }

            $compactTable->addRowByArray($columnValues, null, array('class' => $cssClass));
        }
    }  // End foreach

    // Output table bottom for compact view
    if ($getView === 'compact' || $getView === 'room' || $getView === 'participants' || $getView === 'description')
    {
        $page->addHtml($compactTable->show(false));
    }
}

// If necessary show links to navigate to next and previous recordsets of the query
$base_url = $g_root_path.'/adm_program/modules/dates/dates.php?view='.$getView.'&mode='.$getMode.'&headline='.$getHeadline.'&cat_id='.$getCatId.'&date_from='.$dates->getParameter('dateStartFormatEnglish').'&date_to='.$dates->getParameter('dateEndFormatEnglish').'&view_mode='.$getViewMode;
$page->addHtml(admFuncGeneratePagination($base_url, $datesTotalCount, $datesResult['limit'], $getStart, true));
$page->show();
