<?php

$CFG->gs_path = 'gs';
/* 
 **Linux server**: the path should be 'gs' (unless you have installed ghostscript outside of the PATH, in which case the full directory path might be needed 
 Make sure your server is configured to allow PHP to call 'exec' on this program 
*/

/* 
 **Windows server**: This should be the full path to the GhostScript executable ('gswin32c.exe') 
 The path should look something like: 'c:\gs\bin\gswin32c.exe' (assuming you have installed ghostscript to 'c:\gs') 
 The path should NOT have a SPACE in it - (eg installing to 'c:\Program Files\gs' WILL NOT WORK!) - try copying the files from the 'bin' folder, to 'c:\gs\bin' (or similar)
 Make sure your server is configured to allow PHP to call 'exec' on this program 
 If you are using IIS, you may need to carefully configure the permissions for 'gswin32c.exe' and 'gsdll32.dll' and their containing folder. For Apache, it should just work 
*/

$CFG->uploadpdf_js_navigation = true;
/* This uses javascript to change pages when annotating a PDF, rather than the old method of including links to all the different pages */
/* If you have problems with the new method or just don't like it, then change this value to 'false', with no quotes (default is 'true') */

?>