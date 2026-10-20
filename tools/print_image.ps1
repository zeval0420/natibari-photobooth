# tools/print_image.ps1
# Prints one image to a named Windows printer using System.Drawing (no extra
# software needed). Called by PrintManager on Windows.
#
#   powershell -NoProfile -ExecutionPolicy Bypass -File print_image.ps1 `
#       -Image C:\path\strip.png -Printer "Name" -Copies 1 -Paper A5 `
#       -MarginMm 5 -ScalePercent 100
#
# -OutFile is for testing only: when set, the job is written to that file
# (works with "Microsoft Print to PDF") instead of prompting/printing.
param(
    [Parameter(Mandatory = $true)][string]$Image,
    [Parameter(Mandatory = $true)][string]$Printer,
    [int]$Copies = 1,
    [string]$Paper = '4x6',
    [double]$MarginMm = 0,
    [int]$ScalePercent = 100,
    [string]$OutFile = ''
)

$ErrorActionPreference = 'Stop'
function Fail($msg, $code) { [Console]::Error.WriteLine($msg); exit $code }
Add-Type -AssemblyName System.Drawing

# Paper sizes in 1/100 inch (portrait).
$sizes = @{
    '4x6' = @(400, 600); '5x7' = @(500, 700); '2x6' = @(200, 600)
    'A4' = @(827, 1169); 'A5' = @(583, 827); 'A6' = @(413, 583); 'Letter' = @(850, 1100)
}

if (-not (Test-Path -LiteralPath $Image)) { Fail "Image not found: $Image" 2 }

$img = [System.Drawing.Image]::FromFile($Image)
try {
    $doc = New-Object System.Drawing.Printing.PrintDocument
    $doc.PrinterSettings.PrinterName = $Printer
    if (-not $doc.PrinterSettings.IsValid) { Fail "Printer not found: $Printer" 3 }
    $doc.PrinterSettings.Copies = [int16][Math]::Max(1, $Copies)
    if ($OutFile -ne '') {
        $doc.PrinterSettings.PrintToFile = $true
        $doc.PrinterSettings.PrintFileName = $OutFile
    }
    $doc.DocumentName = 'Photobooth strip'

    # Landscape sheet when the image is wider than tall (e.g. two strips side by side).
    $landscape = $img.Width -gt $img.Height

    $dim = $sizes[$Paper]
    if ($dim) {
        $w = $dim[0]; $h = $dim[1]
        $match = $null
        foreach ($ps in $doc.PrinterSettings.PaperSizes) {
            if ([Math]::Abs($ps.Width - $w) -le 8 -and [Math]::Abs($ps.Height - $h) -le 8) { $match = $ps; break }
        }
        if (-not $match) { $match = New-Object System.Drawing.Printing.PaperSize($Paper, $w, $h) }
        $doc.DefaultPageSettings.PaperSize = $match
    }
    $doc.DefaultPageSettings.Landscape = $landscape
    $doc.DefaultPageSettings.Margins = New-Object System.Drawing.Printing.Margins(0, 0, 0, 0)
    $doc.OriginAtMargins = $false

    $marginMm = $MarginMm
    $scale = [Math]::Max(1, $ScalePercent) / 100.0
    $doc.add_PrintPage({
        param($sender, $e)
        $e.Graphics.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
        $page = $e.PageBounds
        $m = $marginMm / 25.4 * 100
        $boxW = $page.Width - 2 * $m
        $boxH = $page.Height - 2 * $m
        $ratio = [Math]::Min($boxW / $img.Width, $boxH / $img.Height) * $scale
        $dw = $img.Width * $ratio
        $dh = $img.Height * $ratio
        $dx = $page.X + ($page.Width - $dw) / 2
        $dy = $page.Y + ($page.Height - $dh) / 2
        $e.Graphics.DrawImage($img, [single]$dx, [single]$dy, [single]$dw, [single]$dh)
        $e.HasMorePages = $false
    })
    $doc.Print()
    Write-Output "OK $Printer"
    exit 0
}
finally {
    $img.Dispose()
}
