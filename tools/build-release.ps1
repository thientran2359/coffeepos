param(
    [string]$OutputDirectory = "build"
)

$ErrorActionPreference = "Stop"
$pluginRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$mainFile = Join-Path $pluginRoot "coffeepos.php"
$readmeFile = Join-Path $pluginRoot "readme.txt"
$composerFile = Join-Path $pluginRoot "composer.json"
$main = Get-Content -LiteralPath $mainFile -Raw
$readme = Get-Content -LiteralPath $readmeFile -Raw
$composer = Get-Content -LiteralPath $composerFile -Raw | ConvertFrom-Json

$version = [regex]::Match($main, '(?m)^ \* Version:\s*([0-9]+(?:\.[0-9]+)+)\s*$').Groups[1].Value
$constantVersion = [regex]::Match($main, "define\('COFFEEPOS_VERSION',\s*'([^']+)'\)").Groups[1].Value
$stableTag = [regex]::Match($readme, '(?m)^Stable tag:\s*([0-9]+(?:\.[0-9]+)+)\s*$').Groups[1].Value

if (-not $version -or $version -ne $constantVersion -or $version -ne $stableTag) {
    throw "Plugin Version, COFFEEPOS_VERSION, and Stable tag must match."
}
if ($composer.license -ne 'GPL-2.0-or-later' -or $readme -match '(?i)proprietary') {
    throw "Release metadata must declare GPL-2.0-or-later and contain no proprietary license."
}
if (-not (Test-Path -LiteralPath (Join-Path $pluginRoot 'vendor/autoload.php'))) {
    throw "vendor/autoload.php is required in the release artifact."
}

$outputRoot = if ([IO.Path]::IsPathRooted($OutputDirectory)) { $OutputDirectory } else { Join-Path $pluginRoot $OutputDirectory }
[IO.Directory]::CreateDirectory($outputRoot) | Out-Null
$archive = Join-Path $outputRoot ("coffeepos-{0}.zip" -f $version)

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$stream = [IO.File]::Open($archive, [IO.FileMode]::Create)
try {
    $zip = New-Object IO.Compression.ZipArchive($stream, [IO.Compression.ZipArchiveMode]::Create, $false)
    try {
        $runtimeFiles = @('coffeepos.php', 'uninstall.php', 'readme.txt', 'LICENSE')
        foreach ($directory in @('assets', 'includes', 'languages', 'templates', 'vendor')) {
            $runtimeFiles += Get-ChildItem -LiteralPath (Join-Path $pluginRoot $directory) -File -Recurse | ForEach-Object {
                $_.FullName.Substring($pluginRoot.Length + 1)
            }
        }

        foreach ($relative in ($runtimeFiles | Sort-Object -Unique)) {
            $source = Join-Path $pluginRoot $relative
            $entry = 'coffeepos/' + ($relative -replace '\\', '/')
            [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $source, $entry, [IO.Compression.CompressionLevel]::Optimal) | Out-Null
        }
    } finally {
        $zip.Dispose()
    }
} finally {
    $stream.Dispose()
}

$hash = (Get-FileHash -LiteralPath $archive -Algorithm SHA256).Hash.ToLowerInvariant()
Write-Output ("Created {0}" -f $archive)
Write-Output ("SHA256 {0}" -f $hash)
