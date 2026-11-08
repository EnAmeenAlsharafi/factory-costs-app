<?php

namespace App\Services;

use App\Models\CompanySetting;
use Illuminate\Support\Facades\File;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders a Blade document view to an A4 right-to-left PDF with the Cairo font and the company footer.
 */
class PdfDocumentService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function download(string $view, array $data, string $filename, string $title): Response
    {
        $pdf = $this->make($title);
        $pdf->WriteHTML(view($view, $data + ['forPdf' => true])->render());

        return response($pdf->Output($filename, Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    private function make(string $title): Mpdf
    {
        $tempDir = storage_path('app/mpdf');
        File::ensureDirectoryExists($tempDir);

        $defaultFontDirs = (new ConfigVariables)->getDefaults()['fontDir'];
        $defaultFontData = (new FontVariables)->getDefaults()['fontdata'];

        $pdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'directionality' => 'rtl',
            'margin_top' => 12,
            'margin_bottom' => 18,
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_footer' => 8,
            'tempDir' => $tempDir,
            'fontDir' => array_merge($defaultFontDirs, [resource_path('fonts')]),
            'fontdata' => $defaultFontData + [
                'cairo' => ['R' => 'Cairo-Regular.ttf', 'B' => 'Cairo-Bold.ttf', 'useOTL' => 0xFF, 'useKashida' => 75],
            ],
            'default_font' => 'cairo',
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
        ]);

        $company = CompanySetting::current();
        $pdf->SetTitle($title);
        $pdf->SetAuthor($company->name_ar);
        $pdf->SetHTMLFooter(
            '<table width="100%" style="font-size:8pt;color:#64748b;border-top:0.4pt solid #cbd5e1;padding-top:4pt;"><tr>'
            .'<td style="text-align:right;">'.e($company->name_ar).'</td>'
            .'<td style="text-align:left;direction:ltr;">{PAGENO} / {nbpg}</td>'
            .'</tr></table>'
        );

        return $pdf;
    }
}
