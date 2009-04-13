<?php

//require_once('../../../../config.php');
require_once('tcpdf/tcpdf.php');
require_once('fpdi/fpdi.php');

class MyPDFLib extends FPDI {

    var $currentpage = 0;
    var $pagecount = 0;
    var $scale = 0.0;
    var $imagefolder = null;
    var $filename = null;

    function combine_pdfs($basedir, $pdf_list, $output, $comments=null) {

        $this->setPrintHeader(false);
        $this->setPrintFooter(false);

        $totalpagecount = 0;
        foreach ($pdf_list as $key => $file) {
            $pagecount = $this->setSourceFile($basedir.'/'.$file);
            $totalpagecount += $pagecount;
            for ($i=1; $i<=$pagecount; $i++) {
                $template = $this->ImportPage($i);
                $size = $this->getTemplateSize($template);
                $this->AddPage('P', array($size['w'], $size['h']));
                $this->useTemplate($template);
            }
        }

        $this->save_pdf($output);
    
        return $totalpagecount;
    }

    public function current_page() { return $this->currentpage; }
    public function page_count() { return $this->pagecount; }

    public function load_pdf($filename) {
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
    
    public function copy_page() {		/* Copy next page from source file and set as current page */
        if (!$this->filename) {
            return false;
        }
        if ($this->currentpage >= $this->pagecount) {
            return false;
        }
        $this->currentpage++;
        $template = $this->importPage($this->currentpage);
        $size = $this->getTemplateSize($template);
        $this->AddPage('P', array($size['w'], $size['h']));
        $this->useTemplate($template);
        return true;
    }
  
    public function copy_remaining_pages() {	/* Copy all the rest of the pages in the file */
        while ($this->copy_page());
    }
  
    public function add_comment($text, $x, $y, $width) { /* Add a comment to the current page */
        if (!$this->filename) {
            return false;
        }
        $x *= $this->scale;
        $y *= $this->scale;
        $width *= $this->scale;
        $text = str_replace('&lt;','<', $text);
        $text = str_replace('&gt;','>', $text);
        $this->MultiCell($width, 1.0, $text, 0, 'L', 0, 1, $x, $y); /* width, height, text, border, justify, fill, ln, x, y */
        $newy = $this->GetY();
        $this->Rect($x, $y, $width, $newy-$y, 'DF');
        $this->MultiCell($width, 1.0, $text, 0, 'L', 0, 1, $x, $y); /* width, height, text, border, justify, fill, ln, x, y */
        return true;
    }
  
    public function save_pdf($filename) {
        $this->Output($filename, 'F');
    }

    public function set_image_folder($folder) {
        $this->imagefolder = $folder;
    }

    public function get_image($pageno) {
        if (!$this->filename) {
            //            echo 'no filename';
            return false;
        }

        if (!$this->imagefolder) {
            //            echo 'no image folder';
            return false;
        }

        if (!is_dir($this->imagefolder)) {
            //            echo 'bad folder: '.$this->imagefolder;
            return false;
        }

        $imagefile = $this->imagefolder.'/image_page'.$pageno.'.png';
        if (!file_exists($imagefile)) {
            $gsexec = 'gs';
            $imageres = 100;
            $filename = $this->filename;
            $command = "$gsexec -q -sDEVICE=png16m -dBATCH -dNOPAUSE -r$imageres -dFirstPage=$pageno -dLastPage=$pageno -dGraphicsAlphaBits=4 -dTextAlphaBits=4 -sOutputFile=$imagefile $filename 2>&1";
            $result = exec($command);
            if (!file_exists($imagefile)) {
                //                echo htmlspecialchars($command).'<br/>';
                //                echo htmlspecialchars($result).'<br/>';
                return false;
            }
        }
        
        return 'image_page'.$pageno.'.png';
    }

    public function clear_images() {
    }
}
?>
