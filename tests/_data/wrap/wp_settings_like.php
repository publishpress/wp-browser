<?php
/**
* Used to set up and fix common variables and include
* the WordPress procedural and class library.
*
* Allows for some configuration in wp-config.php (see default-constants.php)
*
* @package WordPress
*/

/**
* Stores the location of the WordPress directory of functions, classes, and core content.
*
* @since 1.0.0
*/
define( 'WPINC', 'wp-includes' );

// Include files required for initialization.
require( ABSPATH . WPINC . '/load.php' );
