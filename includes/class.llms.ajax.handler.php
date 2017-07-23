<?php
/**
 * LifterLMS AJAX Event Handler
 * @since    1.0.0
 * @version  3.9.0
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class LLMS_AJAX_Handler {

	/**
	 * Queue all members of a membership to be enrolled into a specific course
	 * Triggered from the auto-enrollment tab of a membership
	 * @param    array     $request  array of request data
	 * @return   array
	 * @since    3.4.0
	 * @version  3.9.0
	 */
	public static function bulk_enroll_membership_into_course( $request ) {

		if ( empty( $request['post_id'] ) || empty( $request['course_id'] ) ) {
			return new WP_Error( 400, __( 'Missing required parameters', 'lifterlms' ) );
		}

		$args = array(
			'post_id' => $request['post_id'],
			'statuses' => 'enrolled',
			'page' => 1,
			'per_page' => 50,
		);

		$query = new LLMS_Student_Query( $args );

		if ( $query->found_results ) {

			$handler = LLMS()->background_handlers['enrollment'];

			while ( $args['page'] <= $query->max_pages ) {

				$handler->push_to_queue( array(
					'enroll_into_id' => $request['course_id'],
					'query_args' => $args,
					'trigger' => sprintf( 'membership_%d', $request['post_id'] ),
				) );

				$args['page']++;

			}

			$handler->save()->dispatch();

		}

		return array(
			'message' => __( 'Members are being enrolled in the background. You may leave this page.', 'lifterlms' ),
		);

	}

	/**
	 * Add or remove a student from a course or memberhip
	 * @since    3.0.0
	 * @version  3.4.0
	 */
	public static function bulk_enroll_students( $request ) {

		if ( empty( $request['post_id'] ) || empty( $request['student_ids'] ) || ! is_array( $request['student_ids'] ) ) {
			return new WP_Error( 400, __( 'Missing required parameters', 'lifterlms' ) );
		}

		$post_id = intval( $request['post_id'] );

		foreach ( $request['student_ids'] as $id ) {
			llms_enroll_student( intval( $id ), $post_id, 'admin_' . get_current_user_id() );
		}

	}

	/**
	 * Move a Product Access Plan to the trash
	 * @since  3.0.0
	 * @version  3.0.0
	 * @param  array $request $_REQUEST object
	 * @return mixed      WP_Error on error, true if successful
	 */
	public static function delete_access_plan( $request ) {

		// shouldn't be possible
		if ( empty( $request['plan_id'] ) ) {
			die();
		}

		if ( ! wp_trash_post( $request['plan_id'] ) ) {

			$err = new WP_Error();
			$err->add( 'error', __( 'There was a problem deleting your access plan, please try again.', 'lifterlms' ) );
			return $err;

		}

		return true;

	}

	/**
	 * Delete a student's quiz attempt
	 * Called from student quiz reporting screen
	 * @since    3.4.4
	 * @version  3.9.0
	 */
	public static function delete_quiz_attempt( $request ) {

		$required = array( 'attempt', 'lesson', 'quiz', 'user' );
		foreach ( $required as $param ) {
			if ( empty( $request[ $param ] ) ) {
				return new WP_Error( 400, __( 'Error deleting quiz attempt: Missing required parameter!', 'lifterlms' ) );
			}
			$request[ $param ] = intval( $request[ $param ] );
		}

		$student = new LLMS_Student( $request['user'] );
		$student->quizzes()->delete_attempt( $request['quiz'], $request['lesson'], $request['attempt'] );

		return true;

	}

	/**
	 * Reload admin tables
	 * @param    array     $request  post data ($_REQUST)
	 * @return   array
	 * @since    3.2.0
	 * @version  3.2.0
	 */
	public static function get_admin_table_data( $request ) {

		require_once 'admin/reporting/class.llms.admin.reporting.php';

		$handler = 'LLMS_Table_' . $request['handler'];

		LLMS_Admin_Reporting::includes();

		if ( class_exists( $handler ) ) {

			$table = new $handler();
			$table->get_results( $request );
			return array(
				'args'  => json_encode( $table->get_args() ),
				'thead' => trim( $table->get_thead_html() ),
				'tbody' => trim( $table->get_tbody_html() ),
				'tfoot' => trim( $table->get_tfoot_html() ),
			);

		} else {

			return false;

		}

	}


	public static function notifications_heartbeart( $request ) {

		$ret = array(
			'new' => array(),
		);

		if ( ! empty( $request['dismissals'] ) ) {
			foreach ( $request['dismissals'] as $nid ) {
				$noti = new LLMS_Notification( $nid );
				if ( get_current_user_id() == $noti->get( 'subscriber' ) ) {
					$noti->set( 'status', 'read' );
				}
			}
		}

		$query = new LLMS_Notifications_Query( array(
			'per_page' => 5,
			'statuses' => 'new',
			'types' => 'basic',
			'subscriber' => get_current_user_id(),
		) );

		$ret['new'] = $query->get_notifications();

		return $ret;

	}

	/**
	 * Remove a course from the list of membership auto enrollment courses
	 * called from "Auto Enrollment" tab of LLMS Membership Metaboxes
	 * @since    3.0.0
	 * @version  3.0.0
	 */
	public static function membership_remove_auto_enroll_course( $request ) {

		if ( empty( $request['post_id'] ) || empty( $request['course_id'] ) ) {
			return new WP_Error( 'error', __( 'Missing required parameters.', 'lifterlms' ) );
		}

		$membership = new LLMS_Membership( $request['post_id'] );

		if ( ! $membership->remove_auto_enroll_course( intval( $request['course_id'] ) ) ) {
			return new WP_Error( 'error', __( 'There was an error removing the course, please try again.', 'lifterlms' ) );
		}

	}

	/**
	 * Retrieve Students
	 *
	 * Used by Select2 AJAX functions to load paginated student results
	 * Also allows querying by:
	 * 		first name
	 * 		last name
	 * 		email
	 *
	 * @return json
	 */
	public static function query_students() {

		// grab the search term if it exists
		$term = array_key_exists( 'term', $_REQUEST ) ? $_REQUEST['term'] : '';

		$page = array_key_exists( 'page', $_REQUEST ) ? $_REQUEST['page'] : 0;

		global $wpdb;

		$limit = 30;
		$start = $limit * $page;

		// there was a search query
		if ( $term ) {

			// email only
			if ( false !== strpos( $term, '@' ) ) {

				$query = "SELECT
							  ID AS id
							, user_email AS email
							, display_name AS name
						  FROM $wpdb->users
						  WHERE user_email LIKE '%s'
						  ORDER BY display_name
						  LIMIT %d, %d;";

				$vars = array(
					'%' . $term . '%',
					$start,
					$limit,
				);

			} // End if().
			elseif ( false !== strpos( $term, ' ' ) ) {

				$term = explode( ' ', $term );

				$query = "SELECT
							  users.ID AS id
							, users.user_email AS email
							, users.display_name AS name
						  FROM $wpdb->users AS users
						  LEFT JOIN wp_usermeta AS fname ON fname.user_id = users.ID
						  LEFT JOIN wp_usermeta AS lname ON lname.user_id = users.ID
						  WHERE
						  	( fname.meta_key = 'first_name' AND fname.meta_value LIKE '%s' )
						  	AND
						  	( lname.meta_key = 'last_name' AND lname.meta_value LIKE '%s' )
						  ORDER BY users.display_name
						  LIMIT %d, %d;";

				$vars = array(
					'%' . $term[0] . '%', // first name
					'%' . $term[1] . '%', // last name
					$start,
					$limit,
				);

			} // search for login, display name, or email
			else {

				$query = "SELECT
							  ID AS id
							, user_email AS email
							, display_name AS name
						  FROM $wpdb->users
						  WHERE
						  	user_email LIKE '%s'
						  	OR user_login LIKE '%s'
						  	OR display_name LIKE '%s'
						  ORDER BY display_name
						  LIMIT %d, %d;";

				$vars = array(
					'%' . $term . '%',
					'%' . $term . '%',
					'%' . $term . '%',
					$start,
					$limit,
				);

			}
		} // End if().
		else {

			$query = "SELECT
						  ID AS id
						, user_email AS email
						, display_name AS name
					  FROM $wpdb->users
					  ORDER BY display_name
					  LIMIT %d, %d;";

			$vars = array(
				$start,
				$limit,
			);

		}

		$r = $wpdb->get_results( $wpdb->prepare( $query, $vars ) );

		echo json_encode( array(
			'items' => $r,
			'more' => count( $r ) === $limit,
			'success' => true,
		) );

		wp_die();

	}

	/**
	 * Start a Quiz Attempt
	 * @param    array     $request  $_POST data
	 *                               required:
	 *                               	(string) attemptkey
	 *                               or
	 *                               	(int) quiz_id
	 *                               	(int) lesson_id
	 *
	 * @return   obj|array           WP_Error on error or array containing html template of the first question
	 * @since    3.9.0
	 * @version  3.9.0
	 */
	public static function quiz_start( $request ) {

		$err = new WP_Error();

		$student = llms_get_student();
		if ( ! $student ) {
			$err->add( 400, __( 'You must be logged in to take quizzes.', 'lifterlms' ) );
			return $err;
		}

		$attempt = false;
		if ( isset( $request['attempt_key'] ) && $request['attempt_key'] ) {
			$attempt = $student->quizzes()->get_attempt_by_key( $request['attempt_key'] );
		}

		if ( ! $attempt || 'new' !== $attempt->get_status() ) {

			if ( ! isset( $request['quiz_id'] ) || ! isset( $request['lesson_id'] ) ) {
				$err->add( 400, __( 'There was an error starting the quiz. Please return to the lesson and begin again.', 'lifterlms' ) );
				return $err;
			}

			$attempt = LLMS_Quiz_Attempt::init( absint( $request['quiz_id'] ), absint( $request['lesson_id'] ), $student->get( 'id' ) );

		}

		$question_id = $attempt->get_first_question();
		if ( ! $question_id ) {
			$err->add( 404, __( 'Unable to start quiz because the quiz does not contain any questions.', 'lifterlms' ) );
			return $err;
		}

		$attempt->start();
		$html = llms_get_template_ajax( 'content-single-question.php', array(
			'attempt' => $attempt,
			'quiz_id' => $attempt->get( 'quiz_id' ),
			'question_id' => $question_id,
		) );

		return array(
			'html' => $html,
		);

	}

	/**
	 * [quiz_answer_question description]
	 * @param    [type]     $request  [description]
	 * @return   [type]               [description]
	 * @since    3.9.0
	 * @version  3.9.0
	 */
	public static function quiz_answer_question( $request ) {

		$err = new WP_Error();

		$student = llms_get_student();
		if ( ! $student ) {
			$err->add( 400, __( 'You must be logged in to take quizzes.', 'lifterlms' ) );
			return $err;
		}

		$required = array( 'quiz_id', 'question_id', 'question_type', 'answer' );
		foreach ( $required as $key ) {
			if ( ! isset( $request[ $key ] ) ) {
				$err->add( 400, __( 'Missing required parameters. Could not proceed.', 'lifterlms' ) );
				return $err;
			}
		}

		$quiz_id = absint( $request['quiz_id'] );
		$question_id = absint( $request['question_id'] );
		$answer = sanitize_text_field( $request['answer'] );

		$attempt = $student->quizzes()->get_current_attempt( $quiz_id );
		if ( ! $attempt ) {
			$err->add( 500, __( 'There was an error recording your answer the quiz. Please return to the lesson and begin again.', 'lifterlms' ) );
			return $err;
		}

		// record the answe
		$attempt->answer_question( $question_id, $answer );

		// get the next question
		$question_id = $attempt->get_next_question();

		// return html for the next question
		if ( $question_id ) {

			$html = llms_get_template_ajax( 'content-single-question.php', array(
				'attempt' => $attempt,
				'quiz_id' => $attempt->get( 'quiz_id' ),
				'question_id' => $question_id,
			) );

			return array(
				'html' => $html,
			);

		} else {

			return self::quiz_end( $request, $attempt );

		}

	}

	public static function quiz_end( $request, $attempt = null ) {

		$err = new WP_Error();

		if ( ! $attempt ) {

			$student = llms_get_student();
			if ( ! $student ) {
				$err->add( 400, __( 'You must be logged in to take quizzes.', 'lifterlms' ) );
				return $err;
			}

			if ( ! isset( $request['quiz_id'] ) ) {
				$err->add( 400, __( 'Missing required parameters. Could not proceed.', 'lifterlms' ) );
				return $err;
			}

			$attempt = $student->quizzes()->get_current_attempt( $quiz_id );

		}

		// record the attempt's completion
		$attempt->end();

		// setup a redirect
		$url = add_query_arg( array(
			'attempt_key' => $attempt->get_key(),
		), get_permalink( $attempt->get( 'quiz_id' ) ) );

		return array(
			'redirect' => apply_filters( 'llms_quiz_complete_redirect', $url, $attempt ),
		);

	}

	/**
	 * Remove a coupon from an order during checkout
	 * @return  string/json
	 * @since    3.0.0
	 * @version  3.0.0
	 */
	public static function remove_coupon_code( $request ) {

		LLMS()->session->set( 'llms_coupon', false );

		$plan = new LLMS_Access_Plan( $request['plan_id'] );

		ob_start();
		llms_get_template( 'checkout/form-coupon.php' );
		$coupon_html = ob_get_clean();

		ob_start();
		llms_get_template( 'checkout/form-gateways.php', array(
			'coupon' => false,
			'gateways' => LLMS()->payment_gateways()->get_enabled_payment_gateways(),
			'selected_gateway' => LLMS()->payment_gateways()->get_default_gateway(),
			'plan' => $plan,
		) );
		$gateways_html = ob_get_clean();

		ob_start();
		llms_get_template( 'checkout/form-summary.php', array(
			'coupon' => false,
			'plan' => $plan,
			'product' => $plan->get_product(),
		) );
		$summary_html = ob_get_clean();

		return array(
			'coupon_html' => $coupon_html,
			'gateways_html' => $gateways_html,
			'summary_html' => $summary_html,
		);

	}

	/**
	 * Handle Select2 Search boxes for WordPress Posts by Post Type
	 * @since 3.0.0
	 * @version 3.0.0
	 * @return  string/json
	 */
	public static function select2_query_posts() {

		// grab the search term if it exists
		$term = array_key_exists( 'term', $_REQUEST ) ? $_REQUEST['term'] : '';

		$page = array_key_exists( 'page', $_REQUEST ) ? $_REQUEST['page'] : 0;

		global $wpdb;

		$limit = 30;
		$start = $limit * $page;

		if ( $term ) {
			$like = " AND post_title LIKE '%s'";
			$vars = array( $_REQUEST['post_type'], '%' . $term . '%', $start, $limit );
		} else {
			$like = '';
			$vars = array( $_REQUEST['post_type'], $start, $limit );
		}

		$posts = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_title
			 FROM $wpdb->posts
			 WHERE
			 	    post_type = %s
			 	AND post_status = 'publish'
			 	$like
			 ORDER BY post_title
			 LIMIT %d, %d
			",
			$vars
		) );

		$r = array();

		foreach ( $posts as $p ) {

			$r[] = array(
				'id' => $p->ID,
				'name' => $p->post_title . ' (' . __( 'ID#', 'lifterlms' ) . ' ' . $p->ID . ')',
			);

		}

		echo json_encode( array(
			'items' => $r,
			'more' => count( $r ) === $limit,
			'success' => true,
		) );

		wp_die();
	}

	/**
	 * Add or remove a student from a course or memberhip
	 * @since    3.0.0
	 * @version  3.4.0
	 */
	public static function update_student_enrollment( $request ) {

		if ( empty( $request['student_id'] ) || empty( $request['status'] ) ) {
			return new WP_Error( 400, __( 'Missing required parameters', 'lifterlms' ) );
		}

		if ( ! in_array( $request['status'], array( 'add', 'remove' ) ) ) {
			return new WP_Error( 400, __( 'Invalid status', 'lifterlms' ) );
		}

		if ( 'add' === $request['status'] ) {
			llms_enroll_student( $request['student_id'], $request['post_id'], 'admin_' . get_current_user_id() );
		} elseif ( 'remove' === $request['status'] ) {
			llms_unenroll_student( $request['student_id'], $request['post_id'], 'cancelled', 'any' );
		}

	}

	/**
	 * Validate a Coupon via the Checkout Form
	 * @return  string/json
	 * @since    3.0.0
	 * @version  3.0.0
	 */
	public static function validate_coupon_code( $request ) {

		$error = new WP_Error();

		// validate for required fields
		if ( empty( $request['code'] ) ) {

			$error->add( 'error', __( 'Please enter a coupon code.', 'lifterlms' ) );

			// this shouldn't be possible...
		} elseif ( empty( $request['plan_id'] ) ) {

			$error->add( 'error', __( 'Please enter a plan ID.', 'lifterlms' ) );

		} // End if().
		else {

			$cid = llms_find_coupon( $request['code'] );

			if ( ! $cid ) {

				$error->add( 'error', sprintf( __( 'Coupon code "%s" not found.', 'lifterlms' ), $request['code'] ) );

			} else {

				$c = new LLMS_Coupon( $cid );

				$valid = $c->is_valid( $request['plan_id'] );

				if ( is_wp_error( $valid ) ) {

					$error = $valid;

				} else {

					LLMS()->session->set( 'llms_coupon', array(
						'plan_id' => $request['plan_id'],
						'coupon_id' => $c->get( 'id' ),
					) );

					$plan = new LLMS_Access_Plan( $request['plan_id'] );

					ob_start();
					llms_get_template( 'checkout/form-coupon.php', array(
						'coupon' => $c,
					) );
					$coupon_html = ob_get_clean();

					ob_start();
					llms_get_template( 'checkout/form-gateways.php', array(
						'coupon' => $c,
						'gateways' => LLMS()->payment_gateways()->get_enabled_payment_gateways(),
						'selected_gateway' => LLMS()->payment_gateways()->get_default_gateway(),
						'plan' => $plan,
					) );
					$gateways_html = ob_get_clean();

					ob_start();
					llms_get_template( 'checkout/form-summary.php', array(
						'coupon' => $c,
						'plan' => $plan,
						'product' => $plan->get_product(),
					) );
					$summary_html = ob_get_clean();

					$success = array(
						'code' => $c->get( 'title' ),
						'coupon_html' => $coupon_html,
						'gateways_html' => $gateways_html,
						'summary_html' => $summary_html,
					);

				}// End if().
			}// End if().
		}

		// if there are errors, return them
		if ( $error->get_error_messages() ) {

			return $error;

		} else {

			return $success;

		}

	}














	/**
	 * @todo organize and docblock remaining class functions
	 */

	public static function create_section( $request ) {

		$section_id = LLMS_Post_Handler::create_section( $request['post_id'], $request['title'] );

		$html = LLMS_Meta_Box_Course_Outline::section_tile( $section_id );

		return $html;

	}

	public static function get_course_sections( $request ) {

		$course = new LLMS_Course( $request['post_id'] );
		$sections = $course->get_sections( 'posts' );

		return $sections;
	}

	public static function create_lesson( $request ) {

		$lesson_id = LLMS_Post_Handler::create_lesson(
			$request['post_id'],
			$request['section_id'],
			$request['title'],
			$request['excerpt']
		);

		$html = LLMS_Meta_Box_Course_Outline::lesson_tile( $lesson_id, $request['section_id'] );

		return $html;

	}

	public static function get_lesson_options_for_select( $request ) {

		return LLMS_Post_Handler::get_lesson_options_for_select_list();

	}

	public static function add_lesson_to_course( $request ) {

		$lesson_id = LLMS_Lesson_Handler::assign_to_course( $request['post_id'], $request['section_id'], $request['lesson_id'] );

		$html = LLMS_Meta_Box_Course_Outline::lesson_tile( $lesson_id, $request['section_id'] );

		return $html;

	}

	public static function get_course_section( $request ) {

		return new LLMS_Section( $request['section_id'] );
	}

	public static function update_course_section( $request ) {

		$section = new LLMS_Section( $request['section_id'] );
		return $section->set_title( $request['title'] );

	}

	public static function get_course_lesson( $request ) {

		$l = new LLMS_Lesson( $request['lesson_id'] );

		return array(
			'id' => $l->get( 'id' ),
			'title' => $l->get( 'title' ),
			'excerpt' => $l->get( 'excerpt' ),
		);

	}

	public static function update_course_lesson( $request ) {

		$post_data = array(
			'title' => $request['title'],
			'excerpt' => $request['excerpt'],
		);

		$lesson = new LLMS_Lesson( $request['lesson_id'] );

		return $lesson->update( $post_data );

	}

	public static function remove_course_lesson( $request ) {

		$post_data = array(
			'parent_course' => '',
			'parent_section' => '',
			'order'	=> '',
		);

		$lesson = new LLMS_Lesson( $request['lesson_id'] );

		return $lesson->update( $post_data );

	}

	public static function delete_course_section( $request ) {

		$section = new LLMS_Section( $request['section_id'] );
		return $section->delete();
	}

	public static function update_section_order( $request ) {

		$updated_data;

		foreach ( $request['sections'] as $key => $value ) {

			$section = new LLMS_Section( $key );
			$updated_data[ $key ] = $section->update( array(
				'order' => $value,
			) );

		}

		return $updated_data;

	}

	public static function update_lesson_order( $request ) {

		$updated_data;

		foreach ( $request['lessons'] as $key => $value ) {

			$lesson = new LLMS_Lesson( $key );
			$updated_data[ $key ] = $lesson->update(
				array(
					'parent_section' => $value['parent_section'],
					'order' => $value['order'],
				)
			);

		}

		return $updated_data;

	}

}

new LLMS_AJAX_Handler();
