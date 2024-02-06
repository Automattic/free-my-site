<?php
/*
Plugin Name: Free My Site
Description: Assist in liberating the data out of your existing platform
Version: 0.1
Author: DotOrg
*/


require_once( plugin_dir_path( __FILE__ ) . 'includes/parsedown.php' ); // markdown parsing lib
require_once( plugin_dir_path( __FILE__ ) . 'admin.php' );
require_once( plugin_dir_path( __FILE__ ) . 'storage.php' );
require_once( plugin_dir_path( __FILE__ ) . 'cms-detection.php' );
require_once( plugin_dir_path( __FILE__ ) . 'guide-sourcing.php' );
require_once( plugin_dir_path( __FILE__ ) . 'guide-parsing.php' );

use DotOrg\FreeMySite\UI;

new UI\AdminUI();
