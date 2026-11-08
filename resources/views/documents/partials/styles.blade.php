{{-- Shared by every printed document (screen preview and mPDF). Expects $company, and $forPdf when rendering a PDF. --}}
@php
    $primary = $company->primary_color ?: '#1d4ed8';
    $secondary = $company->secondary_color ?: '#f5b301';
@endphp
<style>
    .fo { font-family: cairo, 'Cairo', sans-serif; color: #0f172a; font-size: 10pt; direction: rtl; }
    .fo table { border-collapse: collapse; }
    .fo-head td { vertical-align: middle; }
    .fo-name { font-size: 17pt; font-weight: bold; color: {{ $primary }}; line-height: 1.25; }
    .fo-name-en { font-size: 9pt; color: #475569; direction: ltr; }
    .fo-place { font-size: 8.5pt; color: #475569; margin-top: 2pt; }
    .fo-legal td { font-size: 8.5pt; padding: 1.5pt 0; }
    .fo-legal .k { color: #64748b; }
    .fo-legal .v { font-weight: bold; color: #0f172a; }
    .fo-band { height: 3pt; background-color: {{ $secondary }}; }
    .fo-title { font-size: 15pt; font-weight: bold; color: {{ $primary }}; }
    .fo-docno { border: 1pt solid {{ $primary }}; padding: 5pt 9pt; text-align: center; }
    .fo-docno .k { font-size: 8pt; color: #64748b; }
    .fo-docno .v { font-size: 11pt; font-weight: bold; direction: ltr; }
    .fo-info td { padding: 6pt 9pt; border: 0.6pt solid #dbe2ea; vertical-align: top; width: 25%; }
    .fo-info .k { font-size: 8pt; color: #64748b; padding-bottom: 2pt; }
    .fo-info .v { font-size: 10.5pt; font-weight: bold; }
    .fo-info .sub { font-size: 8.5pt; color: #475569; }
    .fo-grid th { background-color: {{ $primary }}; color: #ffffff; font-weight: bold; font-size: 9pt; padding: 6pt 6pt; text-align: right; }
    .fo-grid td { padding: 6pt 6pt; border-bottom: 0.6pt solid #e2e8f0; font-size: 9.5pt; vertical-align: middle; }
    .fo-grid tr.alt td { background-color: #f8fafc; }
    .fo-grid .c { text-align: center; }
    .fo-grid .ltr { direction: ltr; }
    .fo-grid .muted { font-size: 8pt; color: #64748b; }
    .fo-grid tr.total td { background-color: #eef2f7; font-weight: bold; border-top: 1.2pt solid {{ $primary }}; border-bottom: 0; }
    .fo-section { font-size: 11pt; font-weight: bold; color: {{ $primary }}; padding: 14pt 0 5pt; }
    .fo-notes { border-right: 3pt solid {{ $secondary }}; background-color: #fffbeb; padding: 6pt 9pt; font-size: 9.5pt; }
    .fo-sign td.line { width: 30%; text-align: center; font-size: 9pt; color: #334155; border-top: 0.8pt solid #94a3b8; padding-top: 5pt; }
    .fo-sign td.gap { width: 5%; }
    .fo-foot { text-align: center; font-size: 8.5pt; color: #475569; border-top: 0.6pt solid #e2e8f0; padding-top: 6pt; }
</style>
