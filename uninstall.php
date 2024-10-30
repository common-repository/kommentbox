<?php
// this is the uninstall handler
// include unregister_setting, delete_option, and other uninstall behavior here

if (!defined('WP_UNINSTALL_PLUGIN'))
{
    die;
}
include( plugin_dir_path(__FILE__) . 'komentbox/wp-plugin.php');
$options = 'komentbox_options';
unregister_setting("${name}_group", $name);
WPPlugin::remove_options($options);

?>