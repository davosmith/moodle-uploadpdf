This plugin for the assignment module allows a teacher to annotate and return PDF files that have been submitted by students.
This is heavily based on the 'Advanced Uploading of Files' Assignment module and makes use of GhostScript and the FPDI and TCPDF libraries for PDF manipulation; Mootools is used to help with the JavaScript (I now know Moodle uses YUI as it's JavaScript framework, but I wrote most of this before I read that, will possibly update to use that in the future).

!! THERE ARE A FEW IMPORTANT ITEMS TO NOTE IN THE INSTALLATION, PLEASE READ CAREFULLY !!

Installation:
Unzip the plugin files to <siteroot>/mod/assignment/type/uploadpdf
The language file (assignment.php) should go in the folder <siteroot>/mod/assignment/lang/en_utf8 UNLESS THERE IS ALREADY A FILE THERE.
IF THERE IS ALREADY A LANGUAGE FILE IN THAT FOLDER (from a different plugin you have installed - it is not there by default), you will need to manually combine the two files - copy and paste the contents of the uploadpdf version of 'assignment.php' onto the end of the original 'assignment.php'.
(I recommend making a backup copy of any original 'assignment.php', just in case).
Download and install GhostScript ( http://pages.cs.wisc.edu/~ghost ) - or install from standard respositories, if using Linux.
Edit the file <siteroot>/mod/assignment/type/uploadpdf/uploadpdf_config.php to include the path to where you installed GhostScript (for Linux, you should be able to leave it as the default 'gs').
Log in to Moodle as administrator, then click on 'Notifications'.

All being well, you should now be able to add assignments of type 'uploadpdf' to your courses.

This has been tested with Moodle 1.8 (on Ubuntu 8.10 / Apache server) and Moodle 1.9 (MS Windows / Apache server). It has not (yet) been tested with IIS (but that should happen in a few weeks time).

How to use:
Add a new activity of the type 'Upload PDF' to a course.
Configure all the usual settings (unchanged from 'Advanced Uploading of Files').
When a student uploads their files and clicks 'Submit' they will be checked to see if they are all PDFs, before combining them together into a single 'submission.pdf'.
(Hint: to help students generate PDF files, either use OpenOffice.org - http://www.openoffice.org - which has PDF export built in, or install a PDF printer, such as PDF Creator - http://sourceforge.net/projects/pdfcreator
Hint2: A copy of PDFTK Builder - http://angusj.com/pdftk - will help students to combine their PDF files together in the order they want; my 'uploadpdf' plugin will just join them in alphabetical order).
The teacher can then log in, go to the usual marking screen (I particualarly recommend 'Allow quick grading') and click on 'submission.pdf', which will bring up the first page of the PDF on screen.
Click anywhere on the image of the PDF to add a comment. Use the resize handle in the bottom-right corner of a comment to resize it, click & drag on a comment to move it. Click (without dragging) on a comment to edit it, delete all the text in a comment to remove it.
Click on 'Save Draft and Close' (or just click on the Window's usual 'close' button) to save the work in progress.
Click on 'Generate Response' to create a new PDF with all your annotations present (that the student will be able to access).
Add any feedback / grades to the usual form and save them.

Uninstall:
Delete any instances of the module from courses.
Delete the contents of <siteroot>/mod/assignment/type/uploadpdf.
Delete the file <siteroot>/mod/assignment/lang/en_utf8 OR remove the entries you added to it manually during the installation phase.
Use mysql/phpmyadmin to delete the 'assignment_uploadpdf_comment' table (if someone can tell me a better way of doing this, which works with Moodle 1.8 and above, then please do so!).

Known issues:
There is no way of configuring the GhostScript path via the Administration menu (again this is a Moodle limitation that I have not yet found a solution to).
There is no way of deleting this from the Administration menu (Moodle limitiation, again).
The comment/annotation data is not included in a backup/restore/copy course process (not sure how to do this for a submodule plugin).

Contact:
moodle AT davosmith DOT co DOT uk
or find me in the developer list on the main moodle.org site (David Smith).
Davo
