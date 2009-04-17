<?php

$CFG->gs_path = 'gs';
/* This should be the full path to the GhostScript executable ('gs' for Linux, 'gswin32c.exe' for Windows) */
/* Make sure your server is configured to allow PHP to call 'exec' on this program */
/* If you are using IIS, you may need to carefully configure the permissions for 'gswin32c.exe' and 'gsdll32.dll' and their containing folder */
/* For Apache, it should just work */

?>