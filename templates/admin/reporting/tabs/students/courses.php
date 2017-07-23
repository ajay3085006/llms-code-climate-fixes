<?php
/**
 * Single Student View: Courses Tab
 * This routes to the following templates based on present query vars
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! is_admin() ) { exit; }

if ( empty( $_GET['course_id'] ) ) {

	$table = new LLMS_Table_Student_Courses();
	$table->get_results( array(
		'student' => $student,
	) );
	echo $table->get_table_html();

} elseif ( ! empty( $_GET['course_id'] ) ) {

	if ( ! empty( $_GET['quiz_id'] ) && ! empty( $_GET['lesson_id'] ) ) {

		$quiz_id = intval( $_GET['quiz_id'] );
		$lesson_id = intval( $_GET['lesson_id'] );

		llms_get_template( 'admin/reporting/tabs/students/courses-quiz.php', array(
			'attempts' => $student->quizzes()->get_all( $quiz_id, $lesson_id ),
			'best_attempt' => $student->quizzes()->get_best_attempt( $quiz_id, $lesson_id ),
			'quiz_id' => $quiz_id,
			'student' => $student,
		) );

	} else {

		llms_get_template( 'admin/reporting/tabs/students/courses-course.php', array(
			'student' => $student,
		) );

	}
}

