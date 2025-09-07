<?php
require_once('../../config.php');
require_login();
$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('digitos', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$digitos = $DB->get_record('digitos', array('id' => $cm->instance), '*', MUST_EXIST);

$PAGE->set_url('/mod/digitos/view.php', array('id' => $id));
$PAGE->set_title(format_string($digitos->name));
$PAGE->set_heading(format_string($course->fullname));

// Completion logic
$completion = new completion_info($course);
if (isloggedin() && !isguestuser()) {
    if (optional_param('complete', 0, PARAM_BOOL)) {
        $completion->set_module_viewed($cm);
        $completion->update_state($cm, COMPLETION_COMPLETE);
        redirect($PAGE->url, get_string('completed', 'digitos'));
    }
}

echo $OUTPUT->header();
echo html_writer::tag('h2', 'Digitos');
echo html_writer::start_tag('form', array('method'=>'post'));
echo html_writer::checkbox('complete', 1, false, 'Tandai sebagai complete');
echo html_writer::empty_tag('input', array('type'=>'submit', 'value'=>'Submit'));
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
