<?php
define( 'WP_CACHE', false ); // By Speed Optimizer by SiteGround

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */
define('AI4EXPORTS_API_KEY', 'ai4x_9f3b2c1a7e8d4c6b5a2f0e9d8c7b6a5f4e3d2c1b0a98765fedcba1234567890ab');
// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'dbuij2fytvg5aw' );

/** Database username */
define( 'DB_USER', 'ubrx6evp6vyvf' );

/** Database password */
define( 'DB_PASSWORD', 'BiSaEli3!;:' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'Wx>F`/r$.kGvh<Q^*$h4|M8~UQqHd-b:tKjo_kxV*6ueQY,1<jo8Y6<dNZn[4F*A' );
define( 'SECURE_AUTH_KEY',   'm0^{%lJ_2^dU7Q=T$VgTOwo9e_Sf/3fn6m0.VL ?.w;t+5pBXO=Jzh?K!6kzuC0Y' );
define( 'LOGGED_IN_KEY',     '@ezE~}j7CmLw67XjSUR6%BBZ{/M{giyBUG$qS[Uz%3BOU8+(X[l]|75/KN.BK~x#' );
define( 'NONCE_KEY',         'Nkhhc~5*?s41%}?!+UMTg@L.4uU@^>@v33H55on7d4WkR1BCV*0JE~KrWnm)dNS7' );
define( 'AUTH_SALT',         '4Z$5|N}J;R7ux.ZKTWg-{V($Br#7E]v!L&tbtDJ.1L=u$W~t>@.?IT=8!2%m5?Ql' );
define( 'SECURE_AUTH_SALT',  'cn>>_oh1I}LF4v}4,1/W]zGl$`UQCM5ooi*#]M0!hpiiQ>7c754hTbUpjMh|Zdj*' );
define( 'LOGGED_IN_SALT',    '1zhr&f#it5e,ly=r[6as~f3!gYs*Vcc+}zQu3#t>pU|:S6s!{p$_Yq*RN4zD95eJ' );
define( 'NONCE_SALT',        'rRL{$k~/JB5uIx$m!onvH9Zkc3%2=:zg9FEk{po-_Wxp#b,yFv~((rcY%}|Ki#k$' );
define( 'WP_CACHE_KEY_SALT', 'F_u]F,]CoOvE{/GO1Skv2de2,9&M?Ja_r3IH6Ow&9~[T4/_C$EfD4P8>mhB@WyOc' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wrn_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
@ini_set( 'display_errors', 0 );

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

// Composer autoload - DEVE stare prima di wp-settings.php
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once ABSPATH . 'wp-settings.php';

/** Sets up WordPress vars and included files. */
@include_once('/var/lib/sec/wp-settings-pre.php'); // Added by SiteGround WordPress management system
require_once ABSPATH . 'wp-settings.php';
@include_once('/var/lib/sec/wp-settings.php'); // Added by SiteGround WordPress management system

if ( ! defined( 'OPENAI_API_KEY' ) ) {
    define( 'OPENAI_API_KEY', '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' );
}

if ( ! defined( 'OPENAI_AGENT_ID' ) ) {
    define( 'OPENAI_AGENT_ID', 'wf_69345f21eff88190aa18d73ae08ba4ca07d11a14c998cd90' );
}
