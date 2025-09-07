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
 * @author: sreekanth
 * @date: 2017
 */
use block_learnerscript\local\querylib;
use block_learnerscript\local\reportbase;
use block_learnerscript\report;

class report_myforums extends reportbase implements report {
	/**
	 * @param object $report Report object
	 * @param object $reportproperties Report properties object
	 */
	public function __construct($report, $reportproperties) {
        global $DB;
		parent::__construct($report, $reportproperties);
		$this->columns = array('myforums' => array('forumname', 'coursename', 'noofdisscussions', 'noofreplies','wordcount'));
		if (isset($this->role) && $this->role == 'student') {
			$this->parent = true;
		} else {
			$this->parent = false;
		}
		if ($this->role != 'student') {
			$this->basicparams = [['name' => 'users']];
		}
		$this->courselevel = false;
		$this->components = array('columns', 'filters', 'permissions', 'plot');
		$this->filters = array('courses');
		$this->orderable = array('forumname','coursename', 'noofdisscussions', 'noofreplies','wordcount');
        $this->defaultcolumn = 'f.id';
	}

    public function init() {
       if($this->role != 'student' && !isset($this->params['filter_users'])){
            $this->initial_basicparams('users');
            $fusers = array_keys($this->filterdata);
            $this->params['filter_users'] = array_shift($fusers);
        }
        if (!$this->scheduling && isset($this->basicparams) && !empty($this->basicparams)) {
            $basicparams = array_column($this->basicparams, 'name');
            foreach ($basicparams as $basicparam) {
                if (empty($this->params['filter_' . $basicparam])) {
                    return false;
                }
            }
        }
        $this->courseid = isset($this->params['filter_courses']) ? $this->params['filter_courses'] : array();
        $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        $this->params['userid'] = $userid;
    }

    public function count() {
        $this->sql = "SELECT COUNT(DISTINCT f.id)";
    }

   public function select() {
        $this->sql = "SELECT DISTINCT f.id, f.name as forumname, cm.course as courseid, c.fullname as coursename,  m.id as module, m.name as type, cm.id AS activityid ";
         parent::select();
    }

    public function from() {
        $this->sql .= " FROM {modules} as m";
    }

    public function joins() {
         $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        $this->sql .= "  JOIN {course_modules} as cm ON cm.module = m.id
                         JOIN {forum} as f ON f.id = cm.instance
                         JOIN {course} as c ON c.id = cm.course
                         JOIN {context} AS ctx ON c.id = ctx.instanceid
                         JOIN {role_assignments} as ra ON ctx.id = ra.contextid AND ra.userid = $userid  ";
        parent::joins();
    }

    public function where() {
          $mycourses = (new block_learnerscript\local\querylib)->get_rolecourses($this->params['userid'], 'student', $_SESSION['ls_contextlevel'], SITEID, '', '', '', false, false);
          $mycourseids = implode(',', array_keys($mycourses));

          $this->sql .=" WHERE c.visible = 1 AND cm.visible = 1 AND cm.deletioninprogress = 0 AND f.type ='general'";
        if (!empty($mycourses)) {
           $this->sql .= " AND c.id IN ($mycourseids)";
        }
        if (!empty($this->courseid) && $this->courseid != '_qf__force_multiselect_submission') {
            $courseid = $this->courseid;
            $this->sql .= " AND cm.course = $courseid";
        }  
        parent::where();
    }

    public function search() {
        if (isset($this->search) && $this->search) {
            $fields = array('f.name', 'c.fullname');
            $fields = implode(" LIKE '%" . $this->search . "%' OR ", $fields);
            $fields .= " LIKE '%" . $this->search . "%' ";
            $this->sql .= " AND ($fields) ";
        }
    }

    public function filters() {
        if ($this->ls_startdate >= 0 && $this->ls_enddate) {
            $this->sql .= " AND ra.timemodified BETWEEN $this->ls_startdate AND $this->ls_enddate ";
        }       
    }  
    	/**
	 * [get_rows description]
	 * @param  array  $assignments [description]
	 * @param  string $sqlorder    [description]
	 * @return [type]              [description]
	 */
	 public function get_rows($forums = array()) {
        return $forums;
    }
    public function column_queries($columnname, $forumid, $courseid = null) {
        global $DB;
         $userid = isset($this->params['filter_users']) && $this->params['filter_users'] > 0
                    ? $this->params['filter_users'] : $this->userid;
        $where = " AND %placeholder% = $forumid";

        switch ($columnname) {
            case 'noofdisscussions':
                $identy = 'fd.forum';
                $query = "SELECT COUNT(fd.id) AS noofdisscussions 
                                FROM {forum_discussions} fd WHERE 1 = 1 $where ";
                break;
            case 'noofreplies':
                $identy = 'fd.forum';
                $query = "SELECT COUNT(fp.id) AS noofreplies 
                                FROM {forum_posts} fp 
                                JOIN {forum_discussions} fd ON fp.discussion = fd.id 
                                WHERE fp.subject LIKE '%Re:%' AND fp.userid = $userid $where ";
            break;
            case 'wordcount':
                $identy = 'fd.forum';
                $query = "SELECT sum(LENGTH(fp.message) - LENGTH(REPLACE(fp.message, ' ', '')) + 1)  AS wordcount 
                                FROM {forum_posts} fp 
                                JOIN {forum_discussions} fd ON fp.discussion = fd.id 
                                WHERE fp.userid = $userid $where ";
            break;
            default:
                return false;
                break;
        }
        $query = str_replace('%placeholder%', $identy, $query);
        return $query;
    }
}