<?php
// Minimal lib.php for digitos activity

function digitos_supports($feature) {
    switch($feature) {
        case FEATURE_MOD_ARCHETYPE: return MOD_ARCHETYPE_OTHER;
        case FEATURE_COMPLETION_TRACKS_VIEWS: return true;
        case FEATURE_GRADE_HAS_GRADE: return false;
        case FEATURE_GRADE_OUTCOMES: return false;
        case FEATURE_BACKUP_MOODLE2: return true;
        default: return null;
    }
}

function digitos_add_instance($data, $mform) {
    global $DB;
    $data->timecreated = time();
    return $DB->insert_record('digitos', $data);
}

function digitos_update_instance($data, $mform) {
    global $DB;
    $data->timemodified = time();
    $data->id = $data->instance;
    return $DB->update_record('digitos', $data);
}

function digitos_delete_instance($id) {
    global $DB;
    return $DB->delete_records('digitos', array('id'=>$id));
}
