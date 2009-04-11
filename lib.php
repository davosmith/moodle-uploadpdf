<?php

require_once($CFG->libdir,'/tcpdf/tcpdf.php');
require_once('fpdi/fpdi.php');

die("I was included");


function combine_pdfs($pdf_list, $output) {

    $pdf = new FPDI();
        
    foreach ($files as $key => $file) {
        $pagecount = $pdf->setSourceFile($file);
        for ($i=0; $i<$pagecount; $i++) {
            $pdf->AddPage();
            $template = $pdf->importPage($i);
            $pdf->useTemplate($template);
        }
    }

    $pdf->Output($output, 'F');
    
    return true;
}

?>