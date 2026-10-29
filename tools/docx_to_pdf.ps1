<#
  Eksport DOCX -> PDF przez Microsoft Word (jak „Zapisz jako PDF"), z tagami struktury
  dokumentu dla ułatwień dostępu, zakładkami z nagłówków i osadzonymi czcionkami.

  Każdy plik w osobnym procesie Worda z limitem czasu — jeśli Word zawiśnie na oknie
  dialogowym, skrypt zamyka wyłącznie proces, który sam uruchomił.

  Użycie (PowerShell):
    powershell -ExecutionPolicy Bypass -File tools\docx_to_pdf.ps1 -InDir dist\publikacje\docx -OutDir dist\publikacje\pdf
#>
param(
    [Parameter(Mandatory = $true)][string]$InDir,
    [Parameter(Mandatory = $true)][string]$OutDir,
    [string]$Filter = '*.docx',
    [int]$TimeoutSec = 180
)
$ErrorActionPreference = 'Stop'
$InDir = (Resolve-Path $InDir).Path
New-Item -ItemType Directory -Force $OutDir | Out-Null
$OutDir = (Resolve-Path $OutDir).Path

$export = {
    param($src, $pdf)
    $word = New-Object -ComObject Word.Application
    $word.Visible = $false
    $word.DisplayAlerts = 0
    try {
        $doc = $word.Documents.Open($src, $false, $true)
        # Liczba stron przed eksportem: po eksporcie dokumentu ze spisem treści Word potrafi
        # zerwać połączenie COM (RPC_E_DISCONNECTED) — PDF jest wtedy zapisany, a Word sam się zamyka.
        $pages = $doc.ComputeStatistics(2)
        # 17 = PDF; 0 = do druku; 0 = cały dokument; 0 = treść; 1 = zakładki z nagłówków;
        # DocStructureTags — tagi struktury dla czytników ekranu; BitmapMissingFonts.
        $doc.ExportAsFixedFormat($pdf, 17, $false, 0, 0, 1, 1, 0, $true, $true, 1, $true, $true, $false)
        $pages
        try { $doc.Close($false) } catch { }
    }
    finally {
        try { $word.Quit() } catch { }
    }
}

# Instancje Worda uruchomione przez ten skrypt: nowe od chwili $before i otwarte przez
# automatyzację („/Automation"). Word otwarty w tym czasie przez użytkownika nie spełnia
# drugiego warunku, więc nigdy nie jest zamykany.
function Get-OwnWord($before) {
    @(Get-CimInstance Win32_Process -Filter "Name='WINWORD.EXE'" -ErrorAction SilentlyContinue |
        Where-Object { $before -notcontains $_.ProcessId -and $_.CommandLine -match '/Automation' })
}

function Stop-OwnWord($before, [int]$GraceSec) {
    $deadline = (Get-Date).AddSeconds($GraceSec)
    while ((Get-OwnWord $before).Count -and (Get-Date) -lt $deadline) { Start-Sleep -Milliseconds 500 }
    Get-OwnWord $before | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -Confirm:$false -ErrorAction SilentlyContinue }
}

Get-ChildItem $InDir -Filter $Filter | Sort-Object Name | ForEach-Object {
    $pdf = Join-Path $OutDir ($_.BaseName + '.pdf')
    Remove-Item $pdf -ErrorAction SilentlyContinue
    $before = @(Get-Process WINWORD -ErrorAction SilentlyContinue | ForEach-Object Id)
    $job = Start-Job -ScriptBlock $export -ArgumentList $_.FullName, $pdf
    if (Wait-Job $job -Timeout $TimeoutSec) {
        $pages = Receive-Job $job -ErrorAction SilentlyContinue
        if (Test-Path $pdf) {
            '{0,-64} {1,3} str.' -f $_.BaseName, ($pages | Select-Object -Last 1)
        }
        else {
            '{0,-64} BRAK PDF — eksport się nie udał' -f $_.BaseName
        }
        # Po zerwanym połączeniu COM Word potrafi zostać w tle — po chwili zamykamy go.
        Stop-OwnWord $before 15
    }
    else {
        Stop-Job $job
        Stop-OwnWord $before 0
        '{0,-64} PRZEKROCZONY CZAS — Word nie otworzył pliku (okno dialogowe?)' -f $_.BaseName
    }
    Remove-Job $job -Force
}
