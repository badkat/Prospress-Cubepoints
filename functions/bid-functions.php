<?php

/*
		================= Bid Functions =================	
*/

/**
* Hooks into validate_bid to check sufficient Cubepoints for bid
* 
*
* @package Prospress Cubepoints
* @since 0.1
*
* @uses CUBEPOINTS MODE
*/
function ppcp_validate_bid( $bid ) {

	global $market_systems;
	$max =	get_winning_bid( $bid['post_id'] );						
	$max_bid = $max->post_content;
	//case for increasing max bid

	$bid_value = ($bid['bidder_id'] == $max->post_author) ?($bid['bid_value'] - $max_bid):	(int)$bid['bid_value'];

	if ( $bid_value > cp_getPoints( $bid['bidder_id'] ) && $bid['bid_status'] != 'invalid' ) {
		$bid['bid_status'] = 'invalid';
		$market_systems[ 'auctions' ]->message_id = 'ppcp_insufficient_points';
	} else if ( !ctype_digit((string)$bid['bid_value']) && $bid['bid_status'] != 'invalid') {
		$bid['bid_status'] = 'invalid';
		$market_systems[ 'auctions' ]->message_id = 'ppcp_invalid';
	} else {
		$bid['bid_value'] = floor($bid['bid_value']);
	}
	
	ppcp_bid( $bid );
	
	return $bid;
}

/**
 * Prints appropriate bid message for unvalidated points?
 * 
 *
 * @package Prospress Cubepoints
 * @since 0.1
 *
 */
function ppcp_validate_post( $message, $message_id ) {

	switch( (string)$message_id ) {
		case 'ppcp_insufficient_points' : // user has insufficient cubepoints
			return  __( 'You do not have enough points for this bid.', 'ppcp' );
			break;
		case 'ppcp_invalid' : // message for ppcp invalid number format to whole number
			return  __( 'Invalid bid. Please enter a valid whole number. e.g. 7 or 58', 'ppcp' );
			break;
		case '7' : // override standard invalid number format to ppcp mode whole number
			return  __( 'Invalid bid. Please enter a valid whole number. e.g. 7 or 58', 'ppcp' );
			break;
		default:
			return $message;
			break;
	}
}

/**
 * Hooks into an auction a successful bid and awards points
 * 
 *
 * @package Prospress Cubepoints
 * @since 0.1
 *
 * @uses is_user_logged_in,get_option,cp_log,cp_currentUser
 * $bid = "post_id", "bidder_id", "bid_value", "bid_date", "bid_date_gmt"
 */
function ppcp_bid( $bid ) {	

	if ( 'winning' != $bid[bid_status] || !function_exists( 'cp_alterPoints' ) || !function_exists( 'cp_log' ) )
		return;
		
	$bidder_id = (int)$bid['bidder_id'];
	//get post info for cp_log and generate link to ended auction page
	$post = get_post($bid['post_id']);
	$title = $post->post_title;
	$source = '<a href="'.home_url().'?post_type=auctions&p='.$bid['post_id'].'">'.$title.'</a>';		

	//successful bid - award set points to winning bidder
	if ( is_ppcp_mode() ) {

		$bid_value = $bid['bid_value'];		
		//get previous winner bid details			
		$prev =	get_winning_bid( $bid['post_id'] );						
		$prev_bidder = $prev->post_author;
		$prev_bid = $prev->winning_bid_value;
		$max_bid = $prev->post_content;

		if(	$prev_bidder == $bidder_id ) {//increase max bid
			
			cp_points('custom', $bidder_id, $max_bid - $bid_value, sprintf(__('Maximum bid increased. Total of %s points held for item %s', 'ppcp' ), $bid_value, $source) );
		} else {//new winner
			
			//refund bid amount as points to user
			cp_points('custom', $prev_bidder, $max_bid, sprintf(__('Outbid! points unfrozen for item %s', 'ppcp'), $source ) );
			//remove bid amount as points from user
			cp_points('custom', $bidder_id, -$bid_value, sprintf( __('Successful Bid! points frozen for item %s', 'ppcp'), $source ) );	
		}
	} elseif ( isset($ppcp_bid_points['enabled']) ) {// Normal Cubepoints
		
		$bid_pts = (float)get_option('ppcp_bid_pts');
		
		if('on' == $bid_pts[enabled])		//award set points to bidder	
			cp_points('custom', $bidder_id, $bid_pts[value], sprintf(__('Successful bid on item %s','ppcp'), $source) );
	}
}

/**
 * Hooks to completed auction, 
 * Cubepoints mode disabled: iterates through registered users and allocates
 * pre-specified amount of cubepoints to winning users.
 * Cubepoints mode enabled: iterates through registered users and deducts amount
 * credits amount to the seller
 *
 * @package Prospress Cubepoints
 * @since 0.1
 *
 * @uses is_winning_bidder,cp_alterPoints,cp_log,is_user_logged_in
 * $args  'post_id', 'payer_id', 'payee_id', 'amount', 'status', 'type' 
 * cp_log ($type, $uid, $points, $source)
 */
function ppcp_win( $post_id ) {

	if ( !function_exists('cp_alterPoints') || !function_exists('cp_log') )
		return;

	$post = get_post($post_id);
	$seller = $post->post_author;
	
	$bid = get_winning_bid($post_id);					
	$bidder = $bid->post_author;
	$bid_value = $bid->winning_bid_value;
	$max_bid = $bid->post_content;
	$title = $post->post_title;
	
	if ( is_ppcp_mode() ) {
		//auction is won -  award points to seller & confirm to winner (final bid amount will be equal to purchase amount)						
		cp_points( 'custom', $seller, $bid_value, sprintf( __('Item %s sold.   Credited %s'), $title, pp_money_format($bid_value) ) );
		cp_points( 'custom', $bidder, $max_bid - $bid_value, sprintf(__( 'Item %s won for %s.   Unfroze %s', 'ppcp' ), $title,  pp_money_format($bid_value), pp_money_format($max_bid - $bid_value) ) );

	} else {
		
		$ppcp_win_points = get_option('ppcp_win_points');
		if ( checked($ppcp_sell_points['enabled'], 'on') )			//award set points to seller and log
			cp_points('custom', $seller, $ppcp_sell_points['value'], sprintf(__('Item %s sold','ppcp'), $title ) );
			
		if ( checked($ppcp_win_points['enabled'], 'on') )			//award set points to buyer and log			
			cp_points('custom', $bidder, $ppcp_win_points['value'], sprintf(__('Item %s won','ppcp'), $title ) );
	}
		
}

?>