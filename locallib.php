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

/**
 * Internal library of functions for module studentquiz
 *
 * All the studentquiz specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    mod_studentquiz
 * @copyright  2016 HSR (http://www.hsr.ch)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/questionlib.php');

/*
 * Does something really useful with the passed things
 *
 * @param array $things
 * @return object
 *function studentquiz_do_something_useful(array $things) {
 *    return new stdClass();
 *}
 */
function get_options_behaviour($cm) {
    global $DB, $CFG;
    $behaviour = $DB->get_record('studentquiz', array('id' => $cm->instance), 'behaviour');
    $comma = explode(",", $behaviour->behaviour);
    $currentbehaviour = '';
    $behaviours = question_engine::get_behaviour_options($currentbehaviour);
    $showbehaviour = array();
    foreach ($comma as $id => $values) {

        foreach ($behaviours as $key => $langstring) {
            if ($values == $key) {
                $showbehaviour[$key] = $langstring;
            }
        }
    }
    return $showbehaviour;
}

function get_quiz_ids($rawdata) {
    $ids = array();
    foreach ($rawdata as $key => $value) { // Parse input for question ids.
        if (preg_match('!^q([0-9]+)$!', $key, $matches)) {
            $ids[] = $matches[1];
        }
    }
    return $ids;
}
function quiz_add_selected_questions($rawdata, $quba){
    $questionids = get_quiz_ids($rawdata);
    $questions = question_preload_questions($questionids);

    get_question_options($questions);
    foreach ($questionids as $id) {
        $questionstoprocess = question_bank::make_question($questions[$id]);

        $quba->add_question($questionstoprocess);
    }

    return count($questions);
}

function quiz_practice_create_session($data, $quba_id) {
    global $DB, $USER;

    $quiz_practice = new stdClass();
    $quiz_practice->practice_date = time();
    $quiz_practice->question_category_id = $data->categoryid;
    $quiz_practice->user_id = $USER->id;
    $quiz_practice->studentquiz_id = $data->instanceid;
    $quiz_practice->total_marks = $data->total_marks;
    $quiz_practice->total_no_of_questions = $data->total_no_of_questions;
    $quiz_practice->question_usage_id = $quba_id;
    return $DB->insert_record('studentquiz_practice_session', $quiz_practice);
}
function quiz_practice_create_quiz($data, $context, $rawdata) {
    $quba = question_engine::make_questions_usage_by_activity('mod_studentquiz', $context);
    $quba->set_preferred_behaviour($data->behaviour);

    $count = quiz_add_selected_questions($rawdata, $quba);
    $quba->start_all_questions();

    question_engine::save_questions_usage_by_activity($quba);


    $data->total_marks = quiz_practice_get_max_marks($quba);
    $data->total_no_of_questions = $count;

    $sessionid = quiz_practice_create_session($data, $quba->get_id());

    return $sessionid;
}

function quiz_practice_update_points($quba, $sessionid) {
    global $DB;
    $totalnoofquestionsright = 0;
    $marks_obtained = 0;
    foreach($quba->get_slots() as $slot) {
        $fraction = $quba->get_question_fraction($slot);
        $maxmarks = $quba->get_question_max_mark($slot);
        $marks_obtained += $fraction * $maxmarks;

        if ($fraction > 0) {
            $totalnoofquestionsright += 1;
        }
    }

    $updatesql = "UPDATE {studentquiz_practice_session}
                          SET marks_obtained = ?, total_no_of_questions_right = ?
                        WHERE id=?";
    $DB->execute($updatesql, array($marks_obtained, $totalnoofquestionsright, $sessionid));
}

function quiz_practice_get_max_marks($quba) {
    $max_marks = 0;
    foreach($quba->get_slots() as $slot) {
        $max_marks += $quba->get_question_max_mark($slot);
    }
    return $max_marks;
}
