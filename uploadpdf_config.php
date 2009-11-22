<?php

$CFG->gs_path = 'gs';
/* This should be the full path to the GhostScript executable ('gs' for Linux, 'gswin32c.exe' for Windows) */
/* Under windows, the path should NOT have a SPACE in it - try copying the files from the 'bin' folder, to 'c:\gs\bin' (or similar) */
/* Make sure your server is configured to allow PHP to call 'exec' on this program */
/* If you are using IIS, you may need to carefully configure the permissions for 'gswin32c.exe' and 'gsdll32.dll' and their containing folder */
/* For Apache, it should just work */

$CFG->uploadpdf_js_navigation = true;
/* This uses javascript to change pages when annotating a PDF, rather than the old method of including links to all the different pages */
/* If you have problems with the new method or just don't like it, then change this value to 'false', with no quotes (default is 'true') */

?>