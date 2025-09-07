<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/** LearnerScript Reports
 * A Moodle block for creating customizable reports
 * @package blocks
 * @subpackage learnerscript
 * @author: sreekanth<sreekanth@eabyas.in>
 * @date: 2017
 */

use block_learnerscript\local\reportbase;
use block_learnerscript\report;
use block_learnerscript\local\querylib;
use block_learnerscript\local\ls as ls;

class report_useractivities extends reportbase implements report {

    private $aliases;

    private $activitynames;

    private $activities;
    /**
     * [__construct description]
     * @param object $report Report object
     * @param object $reportproperties Report properties object
     */
    public function __construct($report, $reportproperties) {
        global $USER,$DB;
        parent::__construct($report);
        if($this->role != 'student'){
            $this->basicparams = array(['name' => 'users'],['name' => 'courses']);
        } else{
            $this->basicparams = array(['name' => 'courses']);
        }
        $this->parent = false;
        $this->components = array('columns', 'filters', 'permissions', 'calcs', 'plot');
        $columns = ['modulename', 'highestgrade', 'lowestgrade', 'finalgrade', 'firstaccess', 'lastaccess', 'totaltimespent', 'numviews', 'completedon', 'completionstatus'];
        $this->columns = ['activityfield'=> ['activityfield'], 'useractivitiescolumns' => $columns];
        $this->filters = array('modules','activities');
        $this->orderable = array('modulename', 'moduletype', 'highestgrade', 'lowestgrade', 'finalgrade', 'firstaccess', 'lastaccess', 'totaltimespent', 'numviews', 'completedon', 'completionstatus');
        $this->defaultcolumn = 'main.id';
        // $this->excludedroles = array("'student'");
    }
    function init() {
        global $DB;
         $filiteruserlists = array();

        if(!isset($this->params['filter_users'])){
            $this->initial_basicparams('users');
            $this->params['filter_courses'] = isset($this->params['filter_courses']) && $this->params['filter_courses'] > SITEID ? $this->params['filter_courses'] : SITEID;
            $coursecontext = context_course::instance($this->params['filter_courses']);
            $enrolledusers = array_keys(get_enrolled_users($coursecontext));
            $filiteruserlists = array();
            if(!empty($enrolledusers)) {
                $enrolledusers = implode(',', $enrolledusers);
                $filiteruserlists = $DB->get_records_sql_menu("SELECT id, concat(firstname,' ',lastname) as name FROM {user} WHERE deleted = 0 AND confirmed = 1 AND id IN ($enrolledusers)");
            }
            if (is_siteadmin()) {
                $userfilter = array_keys($filiteruserlists);
                $this->params['filter_users'] = array_shift($userfilter);
            } else {
               $this->params['filter_users'] = $this->userid;
            }
        }
        if (!isset($this->params['filter_courses'])){
            $this->initial_basicparams('courses');
            if(is_siteadmin()){
                $userslist = array_keys($filiteruserlists);
                $this->params['filter_users'] = array_shift($userslist);
            }else{
                $this->params['filter_courses'] = $this->courseid;
          }
        }
         if (!$this->scheduling && isset($this->basicparams) && !empty($this->basicparams)) {
            $basicparams = array_column($this->basicparams, 'name');
            foreach ($basicparams as $basicparam) {
                if (empty($this->params['filter_' . $basicparam])) {
                    return false;
                }
            }
        }
        $moduleid = isset($this->params['filter_modules']) ? $this->params['filter_modules'] : 0;
        
        $activityid = isset($this->params['filter_activities']) ? $this->params['filter_activities'] : 0;
        $modules = $DB->get_fieldset_select('modules',  'name','', array('visible'=> 1));
        $this->aliases = [];
        foreach ($modules as $modulename) {
            $this->aliases[] = $modulename;
            $this->activities[] = "'$modulename'";
            $fields1[] = "COALESCE($modulename.name,'')";
        }
        $this->activitynames = implode(',', $fields1);  
    }

    function count() {
        $this->sql   = "SELECT COUNT(DISTINCT main.id)";
    }

    function select() {
         global $DB;
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        $modules = $DB->get_fieldset_select('modules',  'name','', array('visible'=> 1));
        $this->aliases = [];
        foreach ($modules as $modulename) {
            $this->aliases[] = $modulename;
            $this->activities[] = "'$modulename'";
            $fields1[] = "COALESCE($modulename.name,'')";
        }
        $this->activitynames = implode(',', $fields1);  
        $this->sql  = "SELECT DISTINCT main.id, m.id as module, main.instance, main.section, c.id as courseid, u.id as userid, c.category AS categoryid ";
        if (!empty($this->selectedcolumns)) {
            if (in_array('modulename', $this->selectedcolumns)) {
                $this->sql .= ", CONCAT($this->activitynames) AS modulename";
            }
            if (in_array('moduletype', $this->selectedcolumns)) {
                $this->sql .= ", m.name AS moduletype";
            }
            if (in_array('firstaccess', $this->selectedcolumns)) {
                $this->sql .= ", (SELECT MIN(lsl.timecreated) FROM {logstore_standard_log} lsl WHERE lsl.contextinstanceid = main.id AND lsl.userid = u.id ) AS firstaccess";
            }
            if (in_array('lastaccess', $this->selectedcolumns)) {
                $this->sql .= ", (SELECT MAX(lsl.timecreated) FROM {logstore_standard_log} lsl WHERE lsl.contextinstanceid = main.id AND lsl.userid = u.id ) AS lastaccess";
            }
            if (in_array('completedon', $this->selectedcolumns)) {
                $this->sql .= ", (SELECT timemodified FROM {course_modules_completion}
                            WHERE completionstate <> 0 AND userid= $userid AND coursemoduleid = main.id) as completedon";
            }
            if (in_array('completionstatus', $this->selectedcolumns)) {
                $this->sql .= ", cmc.completionstate as completionstatus";
            }
        }
        if (in_array('moduletype', $this->selectedcolumns)) {
            $this->sql .= ", m.name AS moduletype";
        }
        if (in_array('firstaccess', $this->selectedcolumns)) {
            $this->sql .= ", (SELECT MIN(lsl.timecreated) FROM {logstore_standard_log} lsl WHERE lsl.contextinstanceid = main.id AND lsl.userid = u.id ) AS firstaccess";
        }
        if (in_array('lastaccess', $this->selectedcolumns)) {
            $this->sql .= ", (SELECT MAX(lsl.timecreated) FROM {logstore_standard_log} lsl WHERE lsl.contextinstanceid = main.id AND lsl.userid = u.id ) AS lastaccess";
        }
        if (in_array('completedon', $this->selectedcolumns)) {
            $this->sql .= ", (SELECT timemodified FROM {course_modules_completion}
                        WHERE completionstate <> 0 AND userid= $userid AND coursemoduleid = main.id) as completedon";
        }
        if (in_array('completionstatus', $this->selectedcolumns)) {
            $this->sql .= ", cmc.completionstate as completionstatus";
        }

        parent::select();
    }

    function from() {
        $this->sql .= " FROM {course_modules} main";
    }

    function joins() {
        $this->sql .=" JOIN {modules} m ON main.module = m.id
                       JOIN {course} c ON c.id = main.course
                       JOIN {enrol} e ON e.courseid = c.id AND e.status = 0
                       JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
                       JOIN {user} u ON u.id = ue.userid";
        foreach ($this->aliases as $alias) {
            $this->sql .= " LEFT JOIN {".$alias."} AS $alias ON $alias.id = main.instance AND m.name = '$alias'";
        }
        $activitieslist = implode(',', $this->activities);
        $this->sql  .= "LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = main.id AND cmc.userid = u.id"; 

        parent::joins();
    }

    function where() {
         global $DB;
        $modules = $DB->get_fieldset_select('modules',  'name','', array('visible'=> 1));
        $this->aliases = [];
        foreach ($modules as $modulename) {
            $this->aliases[] = $modulename;
            $this->activities[] = "'$modulename'";
            $fields1[] = "COALESCE($modulename.name,'')";
        }
        $status = isset($this->params['filter_status']) ? $this->params['filter_status'] : '';
        $userid = $this->params['filter_users'];
        $activitieslist = implode(',', $this->activities);
        $this->sql .=" WHERE u.deleted = 0 AND u.confirmed = 1 AND c.visible = 1 AND
                        e.status = 0 AND u.deleted = 0 AND m.name IN ($activitieslist) AND main.visible = 1 ";
        if($status == 'notcompleted'){
            $this->sql .= " AND main.id NOT IN (SELECT coursemoduleid FROM {course_modules_completion} 
                                                  WHERE completionstate <> 0 AND userid= $userid ) ";
        }
        if($status == 'completed'){
            $this->sql .= " AND main.id IN (SELECT coursemoduleid FROM {course_modules_completion}
                                              WHERE completionstate <> 0 AND userid= $userid ) ";
        }

        $this->sql .= " AND m.visible = 1";
        
        if ((!is_siteadmin() || $this->scheduling) && !(new ls)->is_manager()) {
            if ($this->rolewisecourses != '') {
                $this->sql .= " AND c.id IN ($this->rolewisecourses) ";
            }
        }
        parent::where();
    }

    function search() {
        global $DB;
        if (isset($this->search) && $this->search) {
            $modules = $DB->get_fieldset_select('modules',  'name','', array('visible'=> 1));
            foreach ($modules as $modulename) {
                $fields1[] = "COALESCE($modulename.name,'')";
            }
            $fields2 = array('m.name',  'gi.itemname', 'c.fullname');
            $fields = $fields1 + $fields2;
            $fields = implode(" LIKE '%$this->search%' OR ", $fields );
            $fields .= " LIKE '%$this->search%' ";
            $this->sql .= " AND ($fields) ";
        }
    }

    function filters() {
        if ($this->params['filter_courses'] <> SITEID) {
            $courseid = $this->params['filter_courses'];
            $this->sql .= " AND main.course = $courseid";
        }
        if ($this->params['filter_users'] > 0) {
            $userid = $this->params['filter_users'];
            $this->sql .= " AND u.id = $userid";
        }
        if (isset($this->params['filter_modules']) && $this->params['filter_modules'] > 0) {
            $this->sql .= " AND main.module = :moduleid";
            $this->params['moduleid'] = $this->params['filter_modules'];
        }
        if (isset($this->params['filter_activities']) && $this->params['filter_activities'] > 0) {
            $activitiesid = $this->params['filter_activities'];
            $this->sql .= " AND main.id = $activitiesid";
            $this->params['activityid'] = $this->params['filter_activities'];
        }
        if($this->ls_startdate >= 0 && $this->ls_enddate) {
            $this->sql .= " AND main.added BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        }
    }

    /**
     * @param  array $activites Activites
     * @return array $reportarray Activities information
     */
    public function get_rows($activites) {
        return $activites;
    }
    public function column_queries($columnname, $cmid, $courseid = null) {
        $where = " AND %placeholder% = $cmid";
        $filteruserid = isset($this->params['filter_users']) ? $this->params['filter_users'] : 0; 

        switch ($columnname) {
            case 'finalgrade':
                $identy = 'cm1.id';
                $query = "SELECT ROUND(SUM(gg.finalgrade), 2)  AS finalgrade 
                            FROM {grade_grades} gg  
                            JOIN {grade_items} gi ON gg.itemid = gi.id 
                            JOIN {course_modules} cm1 ON gi.iteminstance = cm1.instance 
                            JOIN {modules} m ON m.id = cm1.module 
                           WHERE 1 = 1 AND gi.itemtype = 'mod' AND gi.itemmodule = m.name AND gg.userid = $filteruserid $where ";
            break;
            case 'highestgrade':
                $identy = 'cm.id';
                $query = "SELECT ROUND(IF(MAX(gg.finalgrade), MAX(gg.finalgrade), 0), 2)  AS highestgrade 
                            FROM {grade_grades} gg  
                            JOIN {grade_items} gi ON gg.itemid = gi.id 
                            JOIN {course_modules} cm ON gi.iteminstance = cm.instance 
                            JOIN {modules} m ON m.id = cm.module 
                           WHERE 1 = 1 AND gi.itemtype = 'mod' AND gi.itemmodule = m.name $where ";
            break;
            case 'lowestgrade':
                $identy = 'cm.id';
                $query = "SELECT ROUND(IF(MIN(gg.finalgrade), MIN(gg.finalgrade), 0), 2)  AS lowestgrade 
                            FROM {grade_grades} gg  
                            JOIN {grade_items} gi ON gg.itemid = gi.id 
                            JOIN {course_modules} cm ON gi.iteminstance = cm.instance 
                            JOIN {modules} m ON m.id = cm.module 
                           WHERE 1 = 1 AND gi.itemtype = 'mod' AND gi.itemmodule = m.name $where ";
            break;
            case 'totaltimespent':
                $identy = 'mt.activityid';
                $query = "SELECT SUM(mt.timespent) AS totaltimespent FROM {block_ls_modtimestats} mt WHERE mt.userid = $filteruserid $where ";
            break;
            case 'numviews':
                $identy = 'lsl.contextinstanceid';
                $query = "SELECT COUNT(lsl.id) AS numviews 
                              FROM {logstore_standard_log} lsl 
                         WHERE lsl.crud = 'r' AND lsl.contextlevel = 70 AND
                           lsl.userid = $filteruserid $where";
            break;
            
            default:
                return false;
                break;
        }
        $query = str_replace('%placeholder%', $identy, $query);
        return $query;
    }
}
