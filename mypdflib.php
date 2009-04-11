<?php

require_once('tcpdf/tcpdf.php');
require_once('fpdi/fpdi.php');

class MyPDFLib extends FPDI {

    var $currentpage = 0;
    var $pagecount = 0;
    var $scale = 0.0;

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

        $this->save_file($output);
    
        return $pagecount;
    }

    public function current_page() { return $this->currentpage; }

    public function load_pdf($filename) {
        $this->setPageUnit('pt');
        $this->scale = 72.0 / Config::$imageres;
        $this->SetFont('helvetica','', 12.0 * $this->scale);
        $this->SetFillColor(255, 255, 176);
        $this->SetDrawColor(0,0,0);
        $this->SetLineWidth(1.0 * $this->scale);
        $this->SetTextColor(0,0,0);
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->pagecount = $this->setSourceFile($filename);
    }
    
    public function copy_page() {		/* Copy next page from source file and set as current page */
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
        $x *= $this->scale;
        $y *= $this->scale;
        $width *= $this->scale;
        $this->MultiCell($width, 1.0, $text, 0, 'L', 0, 1, $x, $y); /* width, height, text, border, justify, fill, ln, x, y */
        $newy = $this->GetY();
        $this->Rect($x, $y, $width, $newy-$y, 'DF');
        $this->MultiCell($width, 1.0, $text, 0, 'L', 0, 1, $x, $y); /* width, height, text, border, justify, fill, ln, x, y */
    }
  
    public function save_file($filename) {
        $this->Output($filename, 'F');
    }


}
?>
