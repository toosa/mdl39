<?php
require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_digitos_mod_form extends moodleform_mod {
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('digitosname', 'digitos'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $this->standard_intro_elements();
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}
