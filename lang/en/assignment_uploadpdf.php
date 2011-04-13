<?php

$string['typeuploadpdf'] = 'Upload PDF';

//Configuration
$string['choosetemplate'] = 'Choose a template to edit';
$string['coversheet'] = 'Coversheet';
$string['coversheet_help'] = 'The file chosen here (which must be a PDF) will be automatically attached to the beginning of the files uploaded by the student.<br/>
There will be a link to this coversheet on the student\'s upload page, so that they are aware of what will be added.<br/>
<em>Note:</em> it is possible to automatically fill in details on the front page of this coversheet, by making use of templates (see the next item down on the settigs page).';
$string['coversheetnotpdf'] = 'Coversheet must be a PDF (or blank)';
$string['coversheettemplate'] = 'Template';
$string['coversheettemplate_help'] = 'If you select a template, then, before a student can submit their work, they will need to fill in a number of details (the exact details are specified in the template). These details will be automatically added to the front page of the coversheet, in the positions specified by the template.<br/>
You can create/delete/modify templates by clicking on the \'Edit Templates...\' button.<br/>
<em>Note:</em> Selecting a template, without specifying a coversheet will have no effect.';
$string['displaychecklist'] = 'Display checklist';
$string['edittemplate'] = 'Edit templates';
$string['edittemplatetip'] = 'Edit templates';
$string['newtemplate'] = 'New Template...';
$string['mustcompletechecklist'] = 'Checklist complete before submission';$string['notemplate'] = 'None';
$string['onlypdf'] = 'All files must be PDFs';
$string['onlypdf_help'] = "Selecting 'Yes' to this option will prevent students from submitting any files that are not PDFs.<br/>
If you select 'No', then students will receive a warning about non-PDF files, but will still be able to submit <em>as long as at least one file is a PDF</em>.<br/>
This second option may be useful if you want students to be able to submit supporting files with interactive elements still working (e.g. a spreadsheet with the formulas in it), but only the PDF files will be available for annotation.";

//Editing template
$string['select'] = 'Select';
$string['templateusecount'] = 'Warning: this template is currently used by {$a} assignment(s) - be careful when making changes';
$string['showwhereused'] = 'Show';
$string['showused'] = 'This template is used in the following assignments';
$string['templatenotused'] = 'This template is not currently being used';
$string['cannotedit'] = 'Only administrators can edit site templates';
$string['templatename'] = 'Template name';
$string['sitetemplate'] = 'Whole site template';
$string['sitetemplatehelp'] = '(only an administrator can edit this setting)';
$string['savetemplate'] = 'Save template';
$string['deletetemplate'] = 'Delete template';
$string['duplicatetemplate'] = 'Duplicate template';
$string['templatecopy'] = ' (Copy)';
$string['chooseitem'] = 'Choose an item to edit';
$string['newitem'] = 'New Item...';
$string['itemtype'] = 'Item type';
$string['itemdate'] = 'Date';
$string['itemtext'] = 'Text';
$string['itemshorttext'] = 'Short text';
$string['enterformtext'] = 'Enter form text';
$string['clicktosetposition'] = 'Click on the image below to set this position';
$string['itemx'] = 'X Position (pixels)';
$string['itemy'] = 'Y Position (pixels)';
$string['itemwidth'] = 'Width (pixels)';
$string['textonly'] = "(only 'Text' items)";
$string['itemsetting'] = 'Value';
$string['itemsettingmore'] = 'name of the field (for text fields) or date format (e.g. d/m/Y)';
$string['dateformatlink'] = 'Date format help';
$string['saveitem'] = 'Save item';
$string['deleteitem'] = 'Delete item';
$string['previewinstructions'] = 'Please upload a coversheet (PDF) to help preview this template';
$string['uploadpreview'] = 'Upload';

//Teacher marking
$string['annotatesubmission'] = 'Annotate submission';
$string['draftsaved'] = 'Draft saved';
$string['responseok'] = 'Response generated OK';
$string['responseproblem'] = 'There was a problem creating the response';
$string['errorloadingpdf'] = 'Error loading submitted PDF';
$string['errorgenerateimage'] = 'Unable to generate image from PDF - check ghostscript is installed and this module has been configured to use it (see README.txt for more details)';
$string['savedraft'] = 'Save Draft and Close';
$string['generateresponse'] = 'Generate Response';
$string['downloadoriginal'] = 'Download original submission PDF';
$string['isresubmission'] = 'This is a resubmission - ';
$string['downloadfirstsubmission'] = 'download the first submission';
$string['next'] = 'Next';
$string['previous'] = 'Prev';
$string['keyboardnext'] = 'n - next page';
$string['keyboardprev'] = 'p - previous page';
$string['showpreviousassignment'] = 'Compare to';
$string['previousnone'] = 'None';
$string['showprevious'] = 'Show';
$string['commentcolour'] = '[,] - comment background colour';
$string['linecolour'] = '{,} - line colour';
$string['colourred'] = 'Red';
$string['colouryellow'] = 'Yellow';
$string['colourgreen'] = 'Green';
$string['colourblue'] = 'Blue';
$string['colourwhite'] = 'White';
$string['colourclear'] = 'Clear';
$string['colourblack'] = 'Black';

$string['completedsubmission'] = 'Download completed submission';
$string['viewresponse'] = 'Download response';
$string['yourcompletedsubmission'] = 'Download your completed submission';

$string['findcomments'] = 'Find comments';
$string['nocomments'] = 'There are currently no comments on this student\'s submission.';
$string['pagenumber'] = 'Page';
$string['comment'] = 'Comment';

$string['servercommfailed'] = 'Server communication failed - do you want to resend the message?';
$string['resend'] = 'Resend';
$string['cancel'] = 'Cancel';
$string['errormessage'] = 'Error message: ';
$string['okagain'] = 'Click OK to try again';

$string['quicklist'] = 'Comment Quicklist';
$string['addquicklist'] = 'Add to comment Quicklist';
$string['deletecomment'] = 'Delete comment';
$string['emptyquicklist'] = 'No items in Quicklist';
$string['emptyquicklist_instructions'] = 'Right-click on a comment to copy it to the Quicklist';
$string['opennewwindow'] = 'Open this page in a new window';

$string['commenticon'] = "c - add comments\nHold Ctrl to draw a line";
$string['eraseicon'] = 'e - erase lines and shapes';
$string['freehandicon'] = 'f - freehand lines';
$string['lineicon'] = 'l - lines';
$string['ovalicon'] = 'o - ovals';
$string['rectangleicon'] = 'r - rectangles';

//Student upload
$string['coversheetnotice'] = 'The following coversheet will be automatically added to your submission';
$string['nonpdfheading'] = 'Non-PDF file found';
$string['filenotpdf'] = 'The file \'{$a}\' is not a PDF - you must resubmit it in that format';
$string['nopdf'] = 'None of the files submitted are PDFs, you must submit at least one file in that format';
$string['filenotpdf_continue'] = 'The file \'{$a}\' is not a PDF - are you sure you want to continue?';
$string['createsubmissionfailed'] = 'Unable to create submission PDF';
$string['heading_templatedatamissing'] = 'Coversheet information not filled in';
$string['templatedatamissing'] = 'You need to fill in all the requested details to create a coversheet for this assignment';
$string['checklistunfinishedheading'] = 'Checklist incomplete';
$string['checklistunfinished'] = 'Checklist incomplete - please tick-off all the items before submitting your work';

?>