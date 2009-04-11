<?php

require_once('tcpdf/tcpdf.php');
require_once('fpdi/fpdi.php');

function combine_pdfs($pdf_list, $output) {

    $pdf = new FPDI();
        
    foreach ($files as $key => $file) {
        $pagecount = $pdf->setSourceFile($file);
        for ($i=1; $i<=$pagecount; $i++) {
            $template = $pdf->ImportPage($i);
            $pdf->AddPage();
            $pdf->useTemplate($template);
        }
    }

    $pdf->Output($output, 'F');
    
    return true;
}

?>
