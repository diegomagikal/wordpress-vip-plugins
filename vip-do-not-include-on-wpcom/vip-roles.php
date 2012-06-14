<?php
/**
 * Helper functions that make it easy to add roles for WordPress.com sites.
 *
 * We use the core API functions as well as modifying the $wp_user_roles global
 * in case roles are re-initialized and our mods are lost.
 */

if ( ! function_exists( 'wpcom_vip_get_role_caps' ) ) :
/**
 * Get a list of capabilities for a role.
 */
function wpcom_vip_get_role_caps( $role ) {
	$caps = array();
	$role_obj = get_role( $role );

	if ( $role_obj && isset( $role_obj->capabilities ) )
		$caps = $role_obj->capabilities;

	return $caps;
}
endif;

if ( ! function_exists( 'wpcom_vip_add_role' ) ) :
/**
 * Add a new role
 *
 * Usage:
 *     wpcom_vip_add_role( 'super-editor', 'Super Editor', array( 'level_0' => true ) );
 */
function wpcom_vip_add_role( $role, $name, $capabilities ) {
	global $wp_user_roles;

	$role_obj = get_role( $role );

	if ( ! $role_obj ) {
		add_role( $role, $name, $capabilities );

		if ( ! isset( $wp_user_roles[ $role ] ) ) {
			$wp_user_roles[ $role ] = array(
				'name' => $name,
				'capabilities' => $capabilities,
			);
		}

		_wpcom_vip_maybe_refresh_current_user_caps( $role );
	} else {
		wpcom_vip_merge_role_caps( $role, $capabilities );
	}
}
endif;

if ( ! function_exists( 'wpcom_vip_merge_role_caps' ) ) :
/**
 * Add new or change existing capabilities for a given role
 *
 * Usage:
 *     wpcom_vip_merge_role_caps( 'author', array( 'publish_posts' => false ) );
 */
function wpcom_vip_merge_role_caps( $role, $caps ) {
	global $wp_user_roles;

	$role_obj = get_role( $role );

	if ( ! $role_obj )
		return;

	$current_caps = (array) wpcom_vip_get_role_caps( $role );
	$new_caps = array_merge( $current_caps, (array) $caps );

	foreach ( $new_caps as $cap => $role_can ) {
		if ( $role_can )
			$role_obj->add_cap( $cap );
		else
			$role_obj->remove_cap( $cap );
	}

	if ( isset( $wp_user_roles[ $role ] ) ) {
		$wp_user_roles[ $role ][ 'capabilities' ] = array_merge( $current_caps, (array) $caps );
	}

	_wpcom_vip_maybe_refresh_current_user_caps( $role );
}
endif;

if ( ! function_exists( 'wpcom_vip_override_role_caps' ) ) :
/**
 * Completely override capabilities for a given role
 *
 * Usage:
 *     wpcom_vip_override_role_caps( 'editor', array( 'level_0' => false) );
 */
function wpcom_vip_override_role_caps( $role, $caps ) {
	global $wp_user_roles;

	$role_obj = get_role( $role );

	if ( ! $role_obj )
		return;

	$role_obj->capabilities = (array) $caps;

	if ( isset( $wp_user_roles[ $role ] ) ) {
		$wp_user_roles[ $role ][ 'capabilities' ] = (array) $caps;
	}

	_wpcom_vip_maybe_refresh_current_user_caps( $role );
}
endif;

if ( ! function_exists( 'wpcom_vip_duplicate_role' ) ) :
/**
 * Duplicate an existing role and modify some caps
 * 
 * Usage:
 *     wpcom_vip_duplicate_role( 'administrator', 'station-administrator', 'Station Administrator', array( 'manage_categories' => false ) );
 */
function wpcom_vip_duplicate_role( $from_role, $to_role_slug, $to_role_name, $modified_caps ) {
	$caps = array_merge( wpcom_vip_get_role_caps( $from_role ), $modified_caps );
	wpcom_vip_add_role( $to_role_slug, $to_role_name, $caps );
}
endif;

if ( ! function_exists( 'wpcom_vip_add_role_caps' ) ) :
/**
 * Add capabilities to an existing role
 *
 * Usage:
 *     wpcom_vip_add_role_caps( 'contributor', array( 'upload_files' ) );
 */
function wpcom_vip_add_role_caps( $role, $caps ) {
	$filtered_caps = array();
	foreach ( (array) $caps as $cap ) {
		$filtered_caps[ $cap ] = true;
	}
	wpcom_vip_merge_role_caps( $role, $filtered_caps );
}
endif;

if ( ! function_exists( 'wpcom_vip_remove_role_caps' ) ) :
/**
 * Remove capabilities from an existing role
 *
 * Usage:
 *     wpcom_vip_remove_role_caps( 'author', array( 'publish_posts' ) );
 */
function wpcom_vip_remove_role_caps( $role, $caps ) {
	$filtered_caps = array();
	foreach ( (array) $caps as $cap ) {
		$filtered_caps[ $cap ] = false;
	}
	wpcom_vip_merge_role_caps( $role, $filtered_caps );
}
endif;

/**
 * Force refreshes the current user's capabilities if they belong to the specified role.
 * This is to prevent a race condition where the WP_User and its related caps are generated before or roles changes.
 */
function _wpcom_vip_maybe_refresh_current_user_caps( $role ) {
	if ( is_user_logged_in() && current_user_can( $role ) ) {
		wp_get_current_user()->get_role_caps();
	}
}
