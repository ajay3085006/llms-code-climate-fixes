<?php
/**
 * Notification Controller Abstract
 * @since    3.8.0
 * @version  3.8.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

abstract class LLMS_Abstract_Notification_Controller extends LLMS_Abstract_Options_Data implements LLMS_Interface_Notification_Controller {

	/**
	 * Trigger Identifier
	 * @var  [type]
	 */
	public $id;

	/**
	 * Number of accepted arguments passed to the callback function
	 * @var  integer
	 */
	protected $action_accepted_args = 1;

	/**
	 * Action hooks used to trigger sending of the notification
	 * @var  array
	 */
	protected $action_hooks = array();

	/**
	 * Priority used when adding action hook
	 * @var  integer
	 */
	protected $action_priority = 15;

	/**
	 * WP Post ID associated with the triggering action
	 * @var  null
	 */
	protected $post_id = null;

	/**
	 * Array of subscriptions for the notification
	 * @var  array
	 */
	protected $subscriptions = array();

	/**
	 * Array of supported notification types
	 * @var  array
	 */
	protected $supported_types = array();

	/**
	 * WP User ID associated with the triggering action
	 * @var  null
	 */
	protected $user_id = null;

	/**
	 * Takes a subscriber type (student, author, etc) and retrieves a User ID
	 * @param    string     $subscriber  subscriber type string
	 * @return   int|false
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	abstract protected function get_subscriber( $subscriber );

	/**
	 * Get the translateable title for the notification
	 * used on settings screens
	 * @return   string
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	abstract public function get_title();

	/**
	 * Setup the subscriber options for the notification
	 * @param    string     $type  notification type id
	 * @return   array
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	abstract protected function set_subscriber_options( $type );


	/**
	 * Holds singletons for extending classes
	 * @var  array
	 */
	private static $_instances = array();

	/**
	 * Get the singleton instance for the extending class
	 * @return   obj
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	public static function instance() {

		$class = get_called_class();

		if ( ! isset( self::$_instances[ $class ] ) ) {
			self::$_instances[ $class ] = new $class();
		}

		return self::$_instances[ $class ];

	}

	/**
	 * Constrcutor
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	private function __construct() {

		$this->add_actions();

	}

	/**
	 * Add an action to trigger the notification to send
	 * @return   void
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	protected function add_actions() {

		foreach ( $this->action_hooks as $hook ) {
			add_action( $hook, array( $this, 'action_callback' ), $this->action_accepted_args, $this->action_priority );
		}

	}

	private function add_custom_subscriptions( $type ) {
		$option = $this->get_option( $type . '_custom_subscribers' );
		$subscribers = explode( ',', $option );
		foreach ( $subscribers as $subscriber ) {
			$subscriber = trim( $subscriber );
			if ( $subscriber ) {
				$this->subscribe( $subscriber, $type );
			}
		}
	}

	/**
	 * Adds subscribers before sending a notifications
	 * @return   void
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	private function add_subscriptions() {

		foreach ( array_keys( $this->get_supported_types() ) as $type ) {

			foreach ( $this->get_subscribers_settings( $type ) as $subscriber_key => $enabled ) {

				if ( 'no' === $enabled ) {
					continue;
				} elseif ( 'custom' === $subscriber_key ) {
					$this->add_custom_subscriptions( $type );
				}

				$subscriber = $this->get_subscriber( $subscriber_key );

				if ( $subscriber ) {

					$this->subscribe( $subscriber, $type );

				}
			}
		}

	}

	/**
	 * Get a fake instance of a view, used for managing options & customization on the admin panel
	 * @param    string   $type        notification type
	 * @param    int      $subscriber  subscriber id
	 * @param    int      $user_id     user id
	 * @param    int      $post_id     post id
	 * @return   obj
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	public function get_mock_view( $type = 'basic', $subscriber = null, $user_id = null, $post_id = null ) {

		$notification = new LLMS_Notification();
		$notification->set( 'type', $type );
		$notification->set( 'subscriber', $subscriber ? $subscriber : get_current_user_id() );
		$notification->set( 'user_id', $user_id ? $user_id : get_current_user_id() );
		$notification->set( 'post_id', $post_id );
		$notification->set( 'trigger_id', $this->id );

		return LLMS()->notifications()->get_view( $notification );

	}

	/**
	 * Retrieve a prefix for options related to the notification
	 * This overrides the LLMS_Abstract_Options_Data method
	 * @return   string
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	protected function get_option_prefix() {
		return sprintf( '%1$snotification_%2$s_', $this->option_prefix, $this->id );
	}

	/**
	 * Retrieve get an array of subscriber options for the current notification by type
	 * @param    string     $type    notification type [basic|email]
	 * @return   array
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	public function get_subscriber_options( $type ) {
		return apply_filters( 'llms_notification_' . $this->id . '_subscriber_options', $this->set_subscriber_options( $type ), $type, $this );
	}

	/**
	 * Get an array of saved subscriber settings prefilled with defaults for the current notificaton
	 * @param    string     $type  notification type
	 * @return   array
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	public function get_subscribers_settings( $type ) {
		$defaults = wp_list_pluck( $this->get_subscriber_options( $type ), 'enabled', 'id' );
		return $this->get_option( $type . '_subscribers', $defaults );
	}

	/**
	 * Get an array of prebuilt subscriber option settings for common subscriptions
	 * @param    string     $id       id of the subscriber type
	 * @param    string     $enabled  whether or not the subscription should be enabled by default [yes|no]
	 * @return   array
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	public function get_subscriber_option_array( $id, $enabled = 'yes' ) {

		$defaults = array(
			'author' => array(
				'title' => __( 'Author', 'lifterlms' ),
			),
			'student' => array(
				'title' => __( 'Student', 'lifterlms' ),
			),
			'lesson_author' => array(
				'title' => __( 'Lesson Author', 'lifterlms' ),
			),
			'course_author' => array(
				'title' => __( 'Course Author', 'lifterlms' ),
			),
			'custom' => array(
				'description' => __( 'Enter additional email addresses which will recieve this notification. Separate multiple addresses with commas.', 'lifterlms' ),
				'title' => __( 'Additional Recipients', 'lifterlms' ),
			),
		);

		if ( isset( $defaults[ $id ] ) ) {
			$arr = $defaults[ $id ];
			$arr['id'] = $id;
			$arr['enabled'] = $enabled;
			return $arr;
		}

	}

	/**
	 * Get a subscriptions array for a specific subscriber
	 * @param    mixed     $subscriber  WP User ID, email address, etc...
	 * @return   array
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	public function get_subscriber_subscriptions( $subscriber ) {
		$subscriptions = $this->get_subscriptions();
		return isset( $subscriptions[ $subscriber ] ) ? $subscriptions[ $subscriber ] : array();
	}

	/**
	 * Retrieve subscribers
	 * @return   array
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	public function get_subscriptions() {
		return $this->subscriptions;
	}

	/**
	 * Get an array of supported notification types
	 * @return   array
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	public function get_supported_types() {
		return apply_filters( 'llms_notification_' . $this->id . '_supported_types', $this->set_supported_types(), $this );
	}

	/**
	 * Send all the subscriptions
	 * @return   void
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	public function send() {

		$this->add_subscriptions();

		foreach ( $this->get_subscriptions() as $subscriber => $types ) {

			foreach ( $types as $type ) {

				$this->send_one( $type, $subscriber );

			}
		}

		// cleanup subscriptions so if the notification
		// is triggered again we don't have incorrect subscribers
		// on the next trigger
		// this happens when receipts are triggered in bulk by action scheduler
		$this->unset_subscriptions();

	}

	/**
	 * Send a notification for a subscriber
	 * @param    string     $type           notification type id
	 * @param    mixed      $subscriber     WP User ID for the subscriber, email address, phone number, etc...
	 * @return   int|false
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	private function send_one( $type, $subscriber ) {

		$notification = new LLMS_Notification();
		$id = $notification->create( array(
			'post_id' => $this->post_id,
			'subscriber' => $subscriber,
			'type' => $type,
			'trigger_id' => $this->id,
			'user_id' => $this->user_id,
		) );

		// if sucessful, push to the processor where processing is supported
		if ( $id ) {

			$processor = LLMS()->notifications()->get_processor( $type );
			if ( $processor ) {

				$processor->log( sprintf( 'Queuing %1$s notification ID #%2$d', $type, $id ) );
				$processor->push_to_queue( $id );
				LLMS()->notifications()->schedule_processing( $type );

			}
		}

		return $id;

	}

	/**
	 * Determine what types are supported
	 * Extending classes can override this function in order to add or remove support
	 * 3rd parties should add support via filter on $this->get_supported_types()
	 * @return   array        associative array, keys are the ID/db type, values should be translated display types
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	protected function set_supported_types() {
		return array(
			'basic' => __( 'Basic', 'lifterlms' ),
			'email' => __( 'Email', 'lifterlms' ),
		);
	}

	/**
	 * Subscribe a user to a notification type
	 * @param    mixed     $subscriber  WP User ID, email address, etc...
	 * @param    string    $type        Identifier for a subscription type eg: basic
	 * @return   void
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	public function subscribe( $subscriber, $type ) {

		// prevent unsupported types from being subscribed
		if ( ! $this->supports( $type ) ) {
			return;
		}

		$subscriptions = $this->get_subscriber_subscriptions( $subscriber );

		if ( ! in_array( $type, $subscriptions ) ) {
			array_push( $subscriptions, $type );
		}

		$this->subscriptions[ $subscriber ] = $subscriptions;

	}

	/**
	 * Determine if a given notification type is supported
	 * @param    string     $type  notification type id
	 * @return   boolean
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	public function supports( $type ) {
		return in_array( $type, array_keys( $this->get_supported_types() ) );
	}

	/**
	 * Reset the subscriptions array
	 * @return   void
	 * @since    3.8.0
	 * @version  3.8.0
	 */
	public function unset_subscriptions() {
		$this->subscriptions = array();
	}

}
