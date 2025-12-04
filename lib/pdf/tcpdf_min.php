<?php
// Minimal TCPDF-like stub tailored for shared hosting without external deps
// This is not a full TCPDF port; it supports simple text content rendering only.
class TCPDF_MIN
{
    private $content = '';
    private $title = '';

    public function SetTitle($title)
    {
        $this->title = $title;
    }

    public function AddPage()
    {
        // no-op for single page minimal implementation
    }

    public function writeHTML($html)
    {
        $text = trim(strip_tags($html));
        $this->content .= $text . "\n";
    }

    public function Output($name = 'document.pdf', $dest = 'I')
    {
        $pdf = $this->renderPdf($name);
        if ($dest === 'S') {
            return $pdf;
        }
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename=' . $name);
        echo $pdf;
        return '';
    }

    private function renderPdf($name): string
    {
        $text = $this->content;
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $stream = "BT /F1 12 Tf 50 750 Td (" . $escaped . ") Tj ET";
        $len = strlen($stream);
        $objects = [];
        $objects[] = "1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj";
        $objects[] = "2 0 obj<< /Type /Pages /Kids [3 0 R] /Count 1 >>endobj";
        $objects[] = "3 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources<< /Font<< /F1 5 0 R>>>>>>endobj";
        $objects[] = "4 0 obj<< /Length $len >>stream\n$stream\nendstream endobj";
        $objects[] = "5 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>endobj";
        $xref = "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        $body = '';
        $offsets = [0];
        $cursor = 0;
        foreach ($objects as $obj) {
            $body .= $obj . "\n";
            $offsets[] = $cursor;
            $cursor += strlen($obj) + 1;
        }
        $xrefEntries = '';
        foreach (array_slice($offsets, 1) as $off) {
            $xrefEntries .= sprintf("%010d 00000 n \n", $off);
        }
        $xref .= $xrefEntries;
        $trailer = "trailer<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n$cursor\n%%EOF";
        return "%PDF-1.4\n" . $body . $xref . $trailer;
    }
}
