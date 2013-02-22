<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/pdflib.php');

if (file_exists($CFG->dirroot.'/mod/assign/submission/pdf/fpdi/fpdi.php')) {
    // If new pdf submission plugin exists - load the fpdi library from there, to avoid conflicts
    require_once($CFG->dirroot.'/mod/assign/submission/pdf/fpdi/fpdi.php');
} else {
    require_once($CFG->dirroot.'/mod/assignment/type/uploadpdf/fpdi/fpdi.php');
}
require_once('uploadpdf_config.php');

class MyPDFLib extends FPDI {

    var $currentpage = 0;
    var $pagecount = 0;
    var $scale = 0.0;
    var $imagefolder = null;
    var $filename = null;

    function combine_pdfs($pdf_list, $output, $coversheet=null, $comments=null) {

        $this->setPageUnit('pt');
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->scale = 72.0 / 100.0;
        $this->SetFont('helvetica','', 12.0 * $this->scale);
        $this->SetTextColor(0,0,0);

        $totalpagecount = 0;
        if ($coversheet) {
            $pagecount = $this->setSourceFile($coversheet);
            $totalpagecount += $pagecount;
            $this->create_page_from_source(1);
            if ($comments) {
                foreach ($comments as $c) {
                    $x = $c->xpos * $this->scale;
                    $y = $c->ypos * $this->scale;
                    $width = 0;

                    if ($c->type == 'text') {
                        $width = $c->width * $this->scale;
                        $text = $c->data;
                    } elseif ($c->type == 'shorttext') {
                        $text = $c->data;
                    } elseif ($c->type == 'date') {
                        $text = date($c->setting);
                    }

                    $text = str_replace('&lt;','<', $text);
                    $text = str_replace('&gt;','>', $text);
                    $this->MultiCell($width, 1.0, $text, 0, 'L', 0, 1, $x, $y); /* width, height, text, border, justify, fill, ln, x, y */
                }
            }

            for ($i=2; $i<=$pagecount; $i++) {
                $this->create_page_from_source($i);
            }
        }
        foreach ($pdf_list as $file) {
            $pagecount = $this->setSourceFile($file);
            $totalpagecount += $pagecount;
            for ($i=1; $i<=$pagecount; $i++) {
                $this->create_page_from_source($i);
            }
        }

        $this->save_pdf($output);

        return $totalpagecount;
    }

    function current_page() { return $this->currentpage; }
    function page_count() { return $this->pagecount; }

    function load_pdf($filename) {
        $this->setPageUnit('pt');
        $this->scale = 72.0 / 100.0;
        $this->SetFont('helvetica','', 12.0 * $this->scale);
        $this->SetFillColor(255, 255, 176);
        $this->SetDrawColor(0,0,0);
        $this->SetLineWidth(1.0 * $this->scale);
        $this->SetTextColor(0,0,0);
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->pagecount = $this->setSourceFile($filename);
        $this->filename = $filename;
        return $this->pagecount;
    }

    function set_pdf($filename, $pagecount=0) {
        if ($pagecount == 0) {
            return $this->load_pdf($filename);
        } else {
            $this->filename = $filename;
            $this->pagecount = $pagecount;
            return $pagecount;
        }
    }

    function copy_page() {		/* Copy next page from source file and set as current page */
        if (!$this->filename) {
            return false;
        }
        if ($this->currentpage >= $this->pagecount) {
            return false;
        }
        $this->currentpage++;
        $this->create_page_from_source($this->currentpage);
        return true;
    }

    protected function create_page_from_source($pageno) {
        // Get the size (and deduce the orientation) of the next page.
        $template = $this->importPage($pageno);
        $size = $this->getTemplateSize($template);
        $orientation = 'P';
        if ($size['w'] > $size['h']) {
            $orientation = 'L';
        }
        // Create a page of the required size / orientation.
        $this->AddPage($orientation, array($size['w'], $size['h']));
        // Prevent new page creation when comments are at the bottom of a page.
        $this->setPageOrientation($orientation, false, 0);
        // Fill in the page with the original contents from the student.
        $this->useTemplate($template);
    }

    function copy_remaining_pages() {	/* Copy all the rest of the pages in the file */
        while ($this->copy_page());
    }

    function add_comment($text, $x, $y, $width, $colour='yellow') { /* Add a comment to the current page */
        if (!$this->filename) {
            return false;
        }
        switch($colour) {
        case 'red':
            $this->SetFillColor(255, 176, 176);
            break;
        case 'green':
            $this->SetFillColor(176, 255, 176);
            break;
        case 'blue':
            $this->SetFillColor(208, 208, 255);
            break;
        case 'white':
            $this->SetFillColor(255, 255, 255);
            break;
        default:                /* Yellow */
            $this->SetFillColor(255, 255, 176);
            break;
        }

        $x *= $this->scale;
        $y *= $this->scale;
        $width *= $this->scale;
        $text = str_replace('&lt;','<', $text);
        $text = str_replace('&gt;','>', $text);
        $this->MultiCell($width, 1.0, $text, 0, 'L', 0, 1, $x, $y); /* width, height, text, border, justify, fill, ln, x, y */
        if ($colour != 'clear') {
            $newy = $this->GetY();
            if (($newy - $y) < (24.0 * $this->scale)) {  /* Single line comment (ie less than 2*text height) */
                $width = $this->GetStringWidth($text) + 4.0; /* Resize box to the length of the text + 2 line widths */
            }
            $this->Rect($x, $y, $width, $newy-$y, 'DF');
            $this->MultiCell($width, 1.0, $text, 0, 'L', 0, 1, $x, $y); /* width, height, text, border, justify, fill, ln, x, y */
        }
        return true;
    }

    function add_annotation($sx, $sy, $ex, $ey, $colour='red', $type='line', $path=null) { /* Add an annotation to the current page */
        global $CFG;
        if (!$this->filename) {
            return false;
        }
        switch($colour) {
        case 'yellow':
            $this->SetDrawColor(255, 255, 0);
            break;
        case 'green':
            $this->SetDrawColor(0, 255, 0);
            break;
        case 'blue':
            $this->SetDrawColor(0, 0, 255);
            break;
        case 'white':
            $this->SetDrawColor(255, 255, 255);
            break;
        case 'black':
            $this->SetDrawColor(0, 0, 0);
            break;
        default:                /* Red */
            $colour = 'red';
            $this->SetDrawColor(255, 0, 0);
            break;
        }

        $sx *= $this->scale;
        $sy *= $this->scale;
        $ex *= $this->scale;
        $ey *= $this->scale;

        $this->SetLineWidth(3.0 * $this->scale);
        switch($type) {
        case 'oval':
            $rx = abs($sx - $ex)/2;
            $ry = abs($sy - $ey)/2;
            $sx = min($sx, $ex) + $rx;
            $sy = min($sy, $ey) + $ry;
            $this->Ellipse($sx, $sy, $rx, $ry);
            break;
        case 'rectangle':
            $w = abs($sx - $ex);
            $h = abs($sy - $ey);
            $sx = min($sx, $ex);
            $sy = min($sy, $ey);
            $this->Rect($sx, $sy, $w, $h);
            break;
        case 'highlight':
            $w = abs($sx - $ex);
            $h = 12.0 * $this->scale;
            $sx = min($sx, $ex);
            $sy = min($sy, $ey) - $h * 0.5;
            $imgfile = $CFG->dirroot.'/mod/assignment/type/uploadpdf/pix/trans'.$colour.'.png';
            $this->Image($imgfile, $sx, $sy, $w, $h);
            break;
        case 'freehand':
            if ($path) {
                $scalepath = array();
                foreach ($path as $point) {
                    $scalepath[] = intval($point) * $this->scale;
                }
                $this->PolyLine($scalepath, 'S');
            }
            break;
        case 'stamp':
            if (!$imgfile = self::get_stamp_file($path)) {
                break;
            }
            $w = abs($sx - $ex);
            $h = abs($sy - $ey);
            $sx = min($sx, $ex);
            $sy = min($sy, $ey);
            $this->Image($imgfile, $sx, $sy, $w, $h);
            break;
        default: // Line
            $this->Line($sx, $sy, $ex, $ey);
            break;
        }
        $this->SetDrawColor(0,0,0);
        $this->SetLineWidth(1.0 * $this->scale);

        return true;
    }

    public static function get_stamps() {
        global $CFG;
        static $stamplist = null;
        if ($stamplist == null) {
            $stamplist = array();
            $basedir = $CFG->dirroot.'/mod/assignment/type/uploadpdf/pix/stamps';
            if ($dir = opendir($basedir)) {
                while (false !== ($file = readdir($dir))) {
                    $pathinfo = pathinfo($file);
                    if (isset($pathinfo['extension']) && strtolower($pathinfo['extension']) == 'png') {
                        $stamplist[$pathinfo['filename']] = $basedir.'/'.$file;
                    }
                }
            }
        }
        return $stamplist;
    }

    public static function get_stamp_file($path) {
        if (!$path) {
            return false;
        }
        $stamps = self::get_stamps();
        if (!array_key_exists($path, $stamps)) {
            return false;
        }
        return $stamps[$path];
    }

    function save_pdf($filename) {
        $this->Output($filename, 'F');
    }

    function set_image_folder($folder) {
        $this->imagefolder = $folder;
    }

    function get_image($pageno) {
        global $CFG;

        if (!$this->filename) {
            echo 'no filename';
            return false;
        }

        if (!$this->imagefolder) {
            echo 'no image folder';
            return false;
        }

        if (!is_dir($this->imagefolder)) {
            echo 'bad folder: '.$this->imagefolder;
            return false;
        }

        $imagefile = $this->imagefolder.'/image_page'.$pageno.'.png';
        $generate = true;
        if (file_exists($imagefile)) {
            if (filemtime($imagefile) > filemtime($this->filename)) {
                // Make sure the image is newer than the PDF file
                $generate = false;
            }
        }

        if ($generate) {
            $gsexec = $CFG->gs_path;
            $imageres = 100;
            $filename = $this->filename;
            $command = "$gsexec -q -sDEVICE=png16m -dSAFER -dBATCH -dNOPAUSE -r$imageres -dFirstPage=$pageno -dLastPage=$pageno -dGraphicsAlphaBits=4 -dTextAlphaBits=4 -sOutputFile=\"$imagefile\" \"$filename\" 2>&1";
            $result = exec($command);
            if (!file_exists($imagefile)) {
                echo htmlspecialchars($command).'<br/>';
                echo htmlspecialchars($result).'<br/>';
                return false;
            }
        }

        return 'image_page'.$pageno.'.png';
    }

    // Check to see if PDF is version 1.4 (or below); if not: use ghostscript to convert it
    // Return - false for invalid PDF, true for no change needed or if file has been updated
    static function ensure_pdf_compatible(stored_file $file) {
        global $CFG;

        $fp = $file->get_content_file_handle();
        $ident = fread($fp, 10);
        if (substr_compare('%PDF-', $ident, 0, 5) !== 0) {
            return false; // This is not a PDF file at all
        }
        $ident = substr($ident, 5); // Remove the '%PDF-' part
        $ident = explode('\x0A', $ident); // Truncate to first '0a' character
        list($major, $minor) = explode('.', $ident[0]); // Split the major / minor version
        $major = intval($major);
        $minor = intval($minor);
        if ($major == 0 || $minor == 0) {
            return false; // Not a valid PDF version number
        }
        if ($major = 1 && $minor <= 4) {
            return true; // We can handle this version - nothing else to do
        }

        $temparea = $CFG->dataroot.'/temp/uploadpdf';
        $hash = $file->get_contenthash();
        $tempsrc = $temparea."/src-$hash.pdf";
        $tempdst = $temparea."/dst-$hash.pdf";

        if (!file_exists($temparea)) {
            if (!mkdir($temparea, 0777, true)) {
                die("Unable to create temporary folder $temparea");
            }
        }

        $file->copy_content_to($tempsrc); // Copy the file

        $gsexec = $CFG->gs_path;
        $command = "$gsexec -q -sDEVICE=pdfwrite -dBATCH -dNOPAUSE -sOutputFile=\"$tempdst\" \"$tempsrc\" 2>&1";
        $result = exec($command);
        if (!file_exists($tempdst)) {
            return false; // Something has gone wrong in the conversion
        }

        $file->delete(); // Delete the original file
        $fs = get_file_storage();
        $fileinfo = array('contextid' => $file->get_contextid(),
                          'component' => $file->get_component(),
                          'filearea' => $file->get_filearea(),
                          'itemid' => $file->get_itemid(),
                          'filename' => $file->get_filename(),
                          'filepath' => $file->get_filepath());
        $fs->create_file_from_pathname($fileinfo, $tempdst); // Create replacement file
        @unlink($tempsrc); // Delete the temporary files
        @unlink($tempdst);

        return true;
    }
}

