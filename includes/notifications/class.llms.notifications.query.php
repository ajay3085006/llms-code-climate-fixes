<?php
/**
* Query LifterLMS Students for a given course / membership
* @since    3.8.0
* @version  3.9.4
*/

if ( ! defined( 'ABSPATH' ) ) { exit; }

class LLMS_Notifications_Query extends LLMS_Database_Query {

	/**
	 * Identify the extending query
	 * @var  string
	 */
	protected $id = 'notifications';

	/**
	 * Get an array of allowed notification statuses
	 * @return   array
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	private function get_available_statuses() {
		return array( 'new', 'sent', 'read', 'unread', 'deleted', 'failed' );
	}

	/**
	 * Get the available notification types
	 * @return   array
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	private function get_available_types() {
		return array( 'basic', 'email' );
	}

	/**
	 * Retrieve default arguments for a student query
	 * @return   array
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	protected function get_default_args() {

		$args = array(
			'subscriber' => null,
			'sort' => array(
				'updated' => 'DESC',
				'id' => 'DESC',
			),
			'statuses' => array(),
			'types' => array(),
		);

		$args = wp_parse_args( $args, parent::get_default_args() );

		return apply_filters( $this->get_filter( 'default_args' ), $args );

	}

	public function get_notifications() {

		$notifications = array();
		$results = $this->get_results();

		if ( $results ) {

			foreach ( $results as $result ) {
				$obj = new LLMS_Notification( $result->id );
				$notifications[] = $obj->load();
			}
		}

		if ( $this->get( 'suppress_filters' ) ) {
			return $notifications;
		}

		return apply_filters( $this->get_filter( 'get_notifications' ), $notifications, $this );

	}

	/**
	 * Parse arguments needed for the query
	 * @return   void
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	protected function parse_args() {

		$this->parse_statuses();
		$this->parse_types();

	}

	/**
	 * Parse submitted statuses
	 * @return   void
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	private function parse_statuses() {

		$statuses = $this->arguments['statuses'];

		// allow strings to be submitted when only requesting one status
		if ( is_string( $statuses ) ) {
			$statuses = array( $statuses );
		}

		// ensure only valid statuses are used
		$statuses = array_intersect( $statuses, $this->get_available_statuses() );

		$this->arguments['statuses'] = $statuses;

	}

	/**
	 * Parse submitted types
	 * @return   void
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	private function parse_types() {

		$types = $this->arguments['types'];

		// allow strings to be submitted when only requesting one status
		if ( is_string( $types ) ) {
			$types = array( $types );
		}

		// ensure only valid types are used
		$types = array_intersect( $types, $this->get_available_types() );
		$this->arguments['types'] = $types;

	}

	/**
	 * Prepare the SQL for the query
	 * @return   void
	 * @since    3.8.0
	 * @version  3.9.4
	 */
	protected function preprare_query() {

		global $wpdb;

		$vars = array(
			$this->get_skip(),
			$this->get( 'per_page' ),
		);

		$sql = $wpdb->prepare(
			"SELECT SQL_CALC_FOUND_ROWS *

			FROM {$wpdb->prefix}lifterlms_notifications AS n

			LEFT JOIN {$wpdb->posts} AS p on p.ID = n.post_id

			{$this->sql_where()}

			{$this->sql_orderby()}

			LIMIT %d, %d
			;",
			$vars
		);

		return $sql;

	}

	/**
	 * Retrieve the prepared SQL for the ORDER clase
	 * Slightly modified from abstract to include the table name to prevent ambiguous errors
	 * @return   string
	 * @since    3.9.2
	 * @version  3.9.2
	 */
	protected function sql_orderby() {

		$sql = 'ORDER BY';

		$comma = false;

		foreach ( $this->get( 'sort' ) as $orderby => $order ) {
			$pre = ( $comma ) ? ', ' : ' ';
			$sql .= $pre . "n.{$orderby} {$order}";
			$comma = true;
		}

		if ( $this->get( 'suppress_filters' ) ) {
			return $sql;
		}

		return apply_filters( $this->get_filter( 'orderby' ), $sql, $this );

	}

	/**
	 * Retrieve the prepared SQL for the WHERE clause
	 * @return   string
	 * @since    3.8.0
	 * @version  3.9.3
	 */
	private function sql_where() {

		global $wpdb;

		$where = 'WHERE 1';

		$where .= " AND p.post_status = 'publish'";

		$statuses = $this->get( 'statuses' );
		if ( $statuses ) {
			$statuses = array_map( array( $this, 'escape_and_quote_string' ), $statuses );
			$where .= sprintf( ' AND n.status IN( %s )', implode( ', ', $statuses ) );
		}

		$types = $this->get( 'types' );
		if ( $types ) {
			$types = array_map( array( $this, 'escape_and_quote_string' ), $types );
			$where .= sprintf( ' AND n.type IN( %s )', implode( ', ', $types ) );
		}

		$subsciber = $this->get( 'subscriber' );
		if ( $subsciber ) {
			$where .= $wpdb->prepare( ' AND n.subscriber = %s', $subsciber );
		}

		return $where;

	}

}
