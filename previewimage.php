<?php

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
require_once($CFG->libdir.'/filelib.php');

$context = required_param('context', PARAM_INT);

$fs = get_file_storage();
$file = $fs->get_file($context, 'mod_assignment', 'previewimage', 0, '/', 'preview.png');

send_stored_file($file);

?>