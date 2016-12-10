<?php
/******************************************************************************
 * Support zusätzliche Klassen
 *
 * Copyright    : (c) 2015 PTABaden
 * Homepage     : http://www.ptabaden.ch
 * License      : GNU Public License 2 http://www.gnu.org/licenses/gpl-2.0.html
 *
 *****************************************************************************/

$tablename=$g_tbl_praefix.'_user_support';
define("TBL_USER_SUPPORT",$tablename);
unset($tablename);

require_once(substr(__FILE__, 0,strpos(__FILE__, 'adm_plugins')-1).'/adm_program/system/common.php');

// check if database is installed
function check_db()
{
    global $gDb;
    $sql_select="SHOW TABLES LIKE '".TBL_USER_SUPPORT."'"; 
    $statement = $gDb->query($sql_select);
    $row = $statement->fetch();
    if($row>=0)
    {
        //Datenbank vorhanden
        return true;
    }
    else
    {        
        return false;
    }
}

/**
 * Get all records and push it to the array 
 * @return Returns the Array with results, recordsets and validated parameters from $_GET Array
 */
class ModuleSupport extends Modules
{
    protected $getConditions;   ///< String with SQL condition

    public function getDataSetCount(){}
    public function setDateRange($dateRangeStart, $dateRangeEnd){}

    public function getDataSet($startElement = 0, $limit = NULL)
    {
        global $gCurrentOrganization;
        global $gDb;
        
        //Bedingungen
        if($this->getParameter('id') > 0)
        {
            $this->getConditions = 'support_id ='. $this->getParameter('id');
        }
        // TODO only working global, could add for each organisation   
        //read support from database
        $sql = 'SELECT * FROM '. TBL_USER_SUPPORT. ' WHERE '.$this->getConditions.'';
        $result = $gDb->query($sql);

        //array for results       
        $support= array('numResults'=>$gDb->num_rows($result), 'limit' => $limit, 'totalCount'=>$this->getDataSetCount());
        
        //Ergebnisse auf Array pushen
        while($row = $gDb->fetch_array($result))
        {       
            $support['recordset'][] = $row; 
        }
       
        // Push parameter to array
        $support['parameter'] = $this->getParameters();
        return $support;
    }  
}

class TableSupport extends TableAccess
{    
    /** Constuctor that will create an object of a recordset of the table. 
     *  If the id is set than the specific announcement will be loaded.
     *  @param $db Object of the class database. This should be the default object $gDb.
     *  @param $ann_id The recordset of the announcement with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(&$db, $support_id = 0)
    {
        parent::__construct($db, TBL_USER_SUPPORT, 'support', $support_id);
    }
    

    /** Get the value of a column of the database table.
     *  If the value was manipulated before with @b setValue than the manipulated value is returned.
     *  @param $columnName The name of the database column whose value should be read
     *  @param $format For date or timestamp columns the format should be the date/time format e.g. @b d.m.Y = '02.04.2011'. @n
     *                 For text columns the format can be @b database that would return the original database value without any transformations
     *  @return Returns the value of the database column.
     *          If the value was manipulated before with @b setValue than the manipulated value is returned.
     */ 
    public function getValue($columnName, $format = '')
    {
        if($columnName == 'support_description')
        {
            if(isset($this->dbColumns['support_description']) == false)
            {
                $value = '';
            }

            elseif($format == 'database')
            {
                $value = html_entity_decode(strStripTags($this->dbColumns['support_description']), ENT_QUOTES, 'UTF-8');
            }
            else
            {
                $value = $this->dbColumns['support_description'];
            }
        }
        else
        {
            $value = parent::getValue($columnName, $format);
        }

        return $value;
    }

    /** Save all changed columns of the recordset in table of database. Therefore the class remembers if it's 
     *  a new record or if only an update is neccessary. The update statement will only update
     *  the changed columns. If the table has columns for creator or editor than these column
     *  with their timestamp will be updated.
     *  The current organization will be set per default.
     *  @param $updateFingerPrint Default @b true. Will update the creator or editor of the recordset if table has columns like @b usr_id_create or @b usr_id_changed
     */
    public function save($updateFingerPrint = true)
    {
        global $gCurrentOrganization;
        
        if($this->new_record)
        {
            $this->setValue('support_org_shortname', $gCurrentOrganization->getValue('org_shortname'));
        }

        parent::save($updateFingerPrint);
    }
    
    /** Set a new value for a column of the database table.
     *  The value is only saved in the object. You must call the method @b save to store the new value to the database
     *  @param $columnName The name of the database column whose value should get a new value
     *  @param $newValue The new value that should be stored in the database field
     *  @param $checkValue The value will be checked if it's valid. If set to @b false than the value will not be checked.  
     *  @return Returns @b true if the value is stored in the current object and @b false if a check failed
     */ 
    public function setValue($columnName, $newValue, $checkValue = true)
    {
        if($columnName == 'support_description')
        {
            return parent::setValue($columnName, $newValue, false);
        }
        return parent::setValue($columnName, $newValue, $checkValue);
    }
}
