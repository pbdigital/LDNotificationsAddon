<?php
/*
Plugin Name: LearnDash Notifications Addon
Description: A plugin to handle LearnDash notifications based on completing last course. Designed to work alongside AutomatorWP or Uncanny Automator.
Version: 1.01
Author: PB Digital
Author URI: https://pbdigital.com.au
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require 'plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$myUpdateChecker = PucFactory::buildUpdateChecker(
	'https://github.com/pbdigital/LDNotificationsAddon/',
	__FILE__,
	'ld-notifications-addon'
);

//Set the branch that contains the stable release.
$myUpdateChecker->setBranch('main');


// PBD_LD_Notification class
class PBD_LD_Notification {
	// Constructor
	public function __construct() {
		// Add action to handle cron hook
		add_action( 'pbd_ld_notification_cron_hook', [ $this, 'pbd_ld_notification_cron_function' ] );
	}

	// Function to handle notification
	public function pbd_ld_notification( $id = null, $delay = null ) {
		// Return if id or delay is not set
		if ( $id == null || $delay == null ) {
			return;
		}

		// Get course ID, user ID, and next step ID
		$course_id = learndash_get_course_id( $id );
		$user_id = get_current_user_id();
		$next_step_id = learndash_user_progress_get_first_incomplete_step( $user_id, $course_id );

		// If next step ID is not 0
		if ( $next_step_id != 0 ) {
			// Get post items and course
			
			$current_item = get_post( $id );
			$item = get_post( $next_step_id );
			$course = get_post( $course_id );
			
			// Clear scheduled hook, schedule new event and update user meta data
			wp_clear_scheduled_hook( 'pbd_ld_notification_cron_hook', array( $user_id ) );
			wp_schedule_single_event( time() + $delay, 'pbd_ld_notification_cron_hook', array( $user_id ) );
			update_user_meta( $user_id, 'ld_notification_triggered', "false" );
			update_user_meta( $user_id, 'ld_notification_next_step_id', $next_step_id );
			update_user_meta( $user_id, 'ld_notification_next_lesson_title', $item->post_title );
			update_user_meta( $user_id, 'ld_notification_course', $course->post_title );
			update_user_meta( $user_id, 'ld_notification_last_item_completed', $current_item->post_title );
			update_user_meta( $user_id, 'ld_notification_next_lesson_url', get_permalink( $next_step_id ) );

		} else {
			// Clear scheduled hook if next step ID is 0
			wp_clear_scheduled_hook( 'pbd_ld_notification_cron_hook', array( $user_id ) );
		}
	}

	// Function to handle cron function
	public function pbd_ld_notification_cron_function( $user_id ) {
		// Update user meta data if user_id is set
		if ( $user_id ) {
			update_user_meta( $user_id, 'ld_notification_triggered', "true" );
		}
	}
}

// Initialize PBD_LD_Notification class
if ( ! function_exists( 'pbd_ld_notification_init' ) ) {
	function pbd_ld_notification_init() {
		$GLOBALS['pbd_ld_notification'] = new PBD_LD_Notification();
	}
}
add_action( 'plugins_loaded', 'pbd_ld_notification_init' );

// Function to trigger PBD_LD_Notification
if ( ! function_exists( 'pbd_ld_notification_trigger' ) ) {
	function pbd_ld_notification_trigger( $id = null, $delay = null ) {
		global $pbd_ld_notification;
		if ( isset( $pbd_ld_notification ) ) {
			$pbd_ld_notification->pbd_ld_notification( $id, $delay );
		}
	}
}
