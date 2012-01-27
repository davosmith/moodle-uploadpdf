<?php

$plugin->version  = 2012012700;  // The current plugin version (Date: YYYYMMDDXX)
$plugin->cron     = 0;          // Period for cron to check this plugin (secs)
$plugin->maturity = MATURITY_STABLE;
$plugin->release  = '2.x (Build: 2012012700)';
$plugin->requires = 2010112400;  // Moodle 2.0+
$plugin->component = 'assignment_uploadpdf';

$submodule->version  = $plugin->version;
$submodule->requires = $plugin->requires;
