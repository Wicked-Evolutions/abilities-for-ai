<?php
/**
 * WE_Multisite_Ability — Custom ability class for multisite context switching.
 *
 * Extends WP_Ability to automatically wrap do_execute() with
 * switch_to_blog() / restore_current_blog() when blog_id is present
 * in the input. This eliminates the need for manual context switching
 * in each multisite ability callback.
 *
 * Abilities without blog_id in their input (e.g., network-level queries)
 * execute normally without any context switch.
 *
 * Copyright (C) 2026 Influencentricity | Wicked Evolutions
 * License: GPL-2.0-or-later
 *
 * @package Abilities_For_AI
 */

defined( 'ABSPATH' ) || exit;

class WE_Multisite_Ability extends WP_Ability {

	/**
	 * Execute the ability callback with automatic blog context switching.
	 *
	 * If input contains a blog_id, switches to that blog before executing
	 * and restores the original blog after. Uses try/finally to guarantee
	 * restore_current_blog() runs even if the callback throws.
	 *
	 * @param mixed $input Optional. The input data for the ability.
	 * @return mixed|WP_Error The result of the ability execution.
	 */
	protected function do_execute( $input = null ) {
		$blog_id = is_array( $input ) ? (int) ( $input['blog_id'] ?? 0 ) : 0;

		if ( $blog_id > 0 ) {
			switch_to_blog( $blog_id );
			try {
				return parent::do_execute( $input );
			} finally {
				restore_current_blog();
			}
		}

		return parent::do_execute( $input );
	}
}
