<?php
require_once('../../config.php');
require_login();
$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
$PAGE->set_url('/mod/digitos/index.php', array('id' => $id));
$PAGE->set_title('Digitos activities');
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
echo $OUTPUT->heading('Digitos activities');
echo $OUTPUT->footer();
