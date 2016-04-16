<?php
/**
 ***********************************************************************************************
 * ical - Feed for events
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/******************************************************************************
 * Erzeugt einen RSS 2.0 - Feed mit Hilfe der RSS-Klasse fuer die 10 naechsten Termine
 *
 * Spezifikation von RSS 2.0: http://www.feedvalidator.org/docs/rss2.html
 *
 * Parameters:
 *
 * headline - Headline für Ics-Feed
 *            (Default) Events
 * mode   1 - Textausgabe
 *        2 - Download
 * cat_id   - show all dates of calendar with this id
 *
 *****************************************************************************/

require_once('../../system/common.php');

unset($_SESSION['dates_request']);

// Initialize and check the parameters
$getMode     = admFuncVariableIsValid($_GET, 'mode',     'string', array('defaultValue' => 'actual', 'validValues' => array('actual', 'old', 'all')));
$getHeadline = admFuncVariableIsValid($_GET, 'headline', 'string', array('defaultValue' => $gL10n->get('DAT_DATES')));
$getCatId    = admFuncVariableIsValid($_GET, 'cat_id',   'int');
// Daterange defined in preferences
$startDate = date('Y-m-d', time() - $gPreferences['dates_ical_days_past']   * 60*60*24);
$endDate   = date('Y-m-d', time() + $gPreferences['dates_ical_days_future'] * 60*60*24);

// Message if module is disabled
if($gPreferences['enable_dates_module'] == 0)
{
    // Module disabled
    $gMessage->show($gL10n->get('SYS_MODULE_DISABLED'));
}
elseif($gPreferences['enable_dates_module'] == 2)
{
    // only with valid login
    require_once('../../system/login_valid.php');
}

// If Ical enabled and module is public
if ($gPreferences['enable_dates_ical'] != 1)
{
    $gMessage->setForwardUrl($gHomepage);
    $gMessage->show($gL10n->get('SYS_ICAL_DISABLED'));
}

// create Object
$dates = new ModuleDates();
// set mode, viewmode, calendar, startdate and enddate manually
$dates->setParameter('mode', 2);
$dates->setParameter('view_mode', 'period');
$dates->setParameter('cat_id', $getCatId);
$dates->setDaterange($startDate, $endDate);
// read events for output
$datesResult = $dates->getDataset(0, 0);
// get parameters fom $_GET Array stored in class
$parameter = $dates->getParameters();

// Headline for file name
// @ptabaden: Admidio 3.1.3 change
if($getCatId > 0)
{
    $calendar = new TableCategory($gDb, $getCatId);
    $getHeadline.= '_'. $calendar->getValue('cat_name');
}

$date = new TableDate($gDb);
$iCal = $date->getIcalHeader();

if($datesResult['numResults'] > 0)
{
    $date = new TableDate($gDb);
    foreach($datesResult['recordset'] as $row)
    {
        $date->clear();
        $date->setArray($row);
        $iCal .= $date->getIcalVEvent($_SERVER['HTTP_HOST']);
    }
}

$iCal .= $date->getIcalFooter();

if($parameter['mode'] == 2)
{
    // for IE the filename must have special chars in hexadecimal
    if (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/MSIE/', $_SERVER['HTTP_USER_AGENT']))
    {
        $getHeadline = urlencode($getHeadline);
    }

    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="'. $getHeadline. '.ics"');

    // necessary for IE, because without it the download with SSL has problems
    header('Cache-Control: private');
    header('Pragma: public');
}
echo $iCal;
