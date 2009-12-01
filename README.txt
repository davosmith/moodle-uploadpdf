This plugin for the assignment module allows a teacher to annotate and
return PDF files that have been submitted by students.

This is heavily based on the 'Advanced Uploading of Files' Assignment
module and makes use of GhostScript and the FPDI and TCPDF libraries
for PDF manipulation; Mootools is used to help with the JavaScript (I
now know Moodle uses YUI as it's JavaScript framework, but I wrote
most of this before I read that, will possibly update to use that in
the future).

!! THERE ARE A FEW IMPORTANT ITEMS TO NOTE IN THE INSTALLATION, PLEASE
   READ CAREFULLY !!

==Installation==

* Unzip the plugin files to <siteroot>/mod/assignment/type/uploadpdf

* Download and install GhostScript ( http://pages.cs.wisc.edu/~ghost )
  - or install from standard respositories, if using Linux.
  Under Windows, do not install to a path with a space in it - that
  means you should install to something like 'c:\gs'
  NOT 'c:\Program Files\gs' (note you only need the files
  'gswin32c.exe' and the dll file from the 'bin' folder, all other
  files are unnecessary for this to work).

* Edit the file
  <siteroot>/mod/assignment/type/uploadpdf/uploadpdf_config.php to
  include the path to where you installed GhostScript (for Linux, you
  should be able to leave it as the default 'gs').

* Log in to Moodle as administrator, then click on 'Notifications'.

All being well, you should now be able to add assignments of type
'uploadpdf' to your courses.

==How to use==

* Add a new activity of the type 'Upload PDF' to a course. (This may
  well show up as '[[typeuploadpdf]]' see 'Known Issues', below).

* Configure all the usual settings - you should be aware of the
  following additions: 

  Coversheet - this is a PDF that will be automatically prepended to
  the start of any files submitted by your students

  Template - before submission your students can be (optionally) asked
  to fill in some text fields, the template is used to add these
  entries to the coversheet

  Edit Templates... - see section below 

  All files must be PDFs - set to 'No', if you want to collect in some
  supporting documents, which could not be marked as PDFs (e.g. a
  spreadsheet, with formulas you want to check)

  
* When a student uploads their files and clicks 'Submit' they will be
  checked to see if they are all PDFs (depending on the setting
  above), before combining them together into a single
  'submission.pdf'.

(Hint: to help students generate PDF files, either use OpenOffice.org
- http://www.openoffice.org - which has PDF export built in, or
install a PDF printer, such as PDF Creator -
http://sourceforge.net/projects/pdfcreator
Hint2: A copy of PDFTK
Builder - http://angusj.com/pdftk - will help students to combine
their PDF files together in the order they want; my 'uploadpdf' plugin
will just join them in the order they are uploaded).  

* The teacher can then log in, go to the usual marking screen (I
  particualarly recommend 'Allow quick grading') and click on
  'submission.pdf', which will bring up the first page of the PDF on
  screen.  

* Click anywhere on the image of the PDF to add a comment. Use the
  resize handle in the bottom-right corner of a comment to resize it,
  click & drag on a comment to move it. Click (without dragging) on a
  comment to edit it, delete all the text in a comment to remove it.
  
* Right-click on a comment to add it to a 'Comment Quicklist'. You can 
  then right-click anywhere on a page to insert comments from this
  'Comment Quicklist' (with the same text, width and background as the 
  original). Comments can be delete from the 'Comment Quicklist' by
  clicking on the 'X' to the right of the comment.

* You can add lines to the PDF by holding 'Ctrl' whilst you click and
  drag with the mouse (or alternatively hold 'Ctrl' then click once for 
  the start and once for the end of the line). Delete lines by clicking
  on them and pressing 'Delete' on the keyboard. Currently you can only
  draw lines in red, but other colours are on their way.

* Click on 'Save Draft and Close' (or just click on the Window's usual
  'close' button) to save the work in progress.

* Click on 'Generate Response' to create a new PDF with all your
  annotations present (that the student will be able to access).
  
* You can view the comments you have made on a student's previous
  submission by chosing that assignment from the drop-down list on the
  page.

* Add any feedback / grades to the usual form and save them.

* Note: If you have a problem with the new javascript based page navigation
  (added on 22 Nov 2009) or prefer having the list of pages to view,
  then change the setting in 'uploadpdf_config.php'. Note: the
  javascript method preloads pages to reduce the delay when changing
  from one page to the next (and probably reduces server load if you
  do a lot of switching back and forth between pages).

==Edit Templates==

* Click on the 'Edit Templates...' button on the 'Settings' page

* Choose the name of the Template to edit (or select 'New
  Template...')

* You can change the name of the template, delete the template or make
  it available to everyone on the site (administrators only, for this
  last option). Only administrators can edit site templates.
  Note: you cannot delete templates that are in use (click 'show' to
  find out where it is currently being used)

* The list at the bottom allows you to choose an item in the template
  to edit, or choose 'New Item...' to add a new one.

* The types of item you can add are:
  text - a block of text, which will re-flow at 'width' pixels
      'value' will be the prompt the student sees to fill this in
  shorttext - similar to text, but without word-wrapping
      useful for 'name' or 'type your initials to state this is all
      your own work'
  date - fills in the date that the assignment was submitted
      'value' is the format to record the date

* To position the items on the template, upload an example PDF 
  coversheet (using the bottom form) then type in the position
  you want to place the PDF (x position, y position, in pixels).
  Alternatively, click on the coversheet image to set the position of 
  that template item.

* When you are finished, save any items you have changed, then
  close the window. The list of templates on the 'settings'
  page should have been updated.

==Uninstall==

* Delete any instances of the module from courses.

* Delete the contents of <siteroot>/mod/assignment/type/uploadpdf.

* Use mysql/phpmyadmin to delete the following tables:
  'assignment_uploadpdf', 'assignment_uploadpdf_comment',
  'assignment_uploadpdf_tmpl', 'assignment_uploadpdf_tmplitm',
  'assignment_uploadpdf_qcklist' and 'assignment_uploadpdf_annot'
  (if someone can tell me a better way of doing this, which works with
  Moodle 1.8 and above, then please do so!).

==Known issues==

The plugin name is currently displayed as '[[typeuploadpdf]]', not
'Upload PDF'. This is a known Moodle bug (
http://tracker.moodle.org/browse/MDL-16796 ), that is now fixed in
Moodle version 1.9 and above - please update your version of Moodle
to avoid this problem.

There is no way of configuring the GhostScript path via the
Administration menu (again this is a Moodle limitation that I have not
yet found a solution to).

There is no way of deleting this from the Administration menu (Moodle
limitiation, again).

There is no way to annotate the PDFs without JavaScript (I may add
this in the future, but it would be *very* fiddly to operate)

==Contact==
moodle AT davosmith DOT co DOT uk
or find me in the developer list on the main moodle.org site 
(David Smith).

Davo
