# tools/print_image.ps1
# Prints one image to a named Windows printer using System.Drawing (no extra
# software needed). Called by PrintManager on Windows.
#
#   powershell -NoProfile -ExecutionPolicy Bypass -File print_image.ps1 `
#       -Image C:\path\strip.png -Printer "Name" -Copies 1 -Paper 5R `
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
    '4x6' = @(400, 600); '5R' = @(500, 700); '5x7' = @(500, 700); '2x6' = @(200, 600)
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

    # The image is fitted to the requested paper. When the printer's driver offers
    # that size we use it and fill the whole sheet. Some drivers (e.g. the Epson
    # ESC/P-R V4 class driver) do not list 5R / 5x7 and silently fall back to
    # Letter, so in that case we print at the true requested size, centred on the
    # closest larger sheet the driver does offer.
    $fitW = 0; $fitH = 0   # 0 = fill the whole page
    $dim = $sizes[$Paper]
    if ($dim) {
        $w = $dim[0]; $h = $dim[1]
        $match = $null
        foreach ($ps in $doc.PrinterSettings.PaperSizes) {
            if ([Math]::Abs($ps.Width - $w) -le 8 -and [Math]::Abs($ps.Height - $h) -le 8) { $match = $ps; break }
        }
        if (-not $match) {
            $best = $null; $largest = $null
            foreach ($ps in $doc.PrinterSettings.PaperSizes) {
                $area = $ps.Width * $ps.Height
                if (-not $largest -or $area -gt ($largest.Width * $largest.Height)) { $largest = $ps }
                if ($ps.Width -ge ($w - 8) -and $ps.Height -ge ($h - 8)) {
                    if (-not $best -or $area -lt ($best.Width * $best.Height)) { $best = $ps }
                }
            }
            $match = if ($best) { $best } else { $largest }
            if ($match) {
                $fitW = $w; $fitH = $h
                Write-Output "NOTE: '$Paper' is not offered by this printer's driver; printing at true $Paper size centred on $($match.PaperName)."
            } else {
                $match = New-Object System.Drawing.Printing.PaperSize($Paper, $w, $h)
            }
        }
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
        if ($fitW -gt 0) {
            $rw = $fitW; $rh = $fitH
            if ($landscape) { $rw = $fitH; $rh = $fitW }
            $boxW = [Math]::Min($boxW, $rw - 2 * $m)
            $boxH = [Math]::Min($boxH, $rh - 2 * $m)
        }
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
