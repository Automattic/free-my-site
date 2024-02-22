<?php
/*
Plugin Name: Free My Site
Description: Assist in liberating the data out of your existing platform
Version: 0.1
Author: DotOrg
*/


require_once( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php' );

new DotOrg\FreeMySite\Admin\UI();
