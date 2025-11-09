param(
    [string]$IniPath = 'C:\FildocDev\php\php.ini'
)

if (-not (Test-Path $IniPath)) {
    Write-Error "php.ini not found at $IniPath"
    exit 2
}

# Backup
$bak = "$IniPath.bak"
Copy-Item -Path $IniPath -Destination $bak -Force

# Read content as single block
$content = Get-Content -Raw -LiteralPath $IniPath

# Normalize line endings to `n for regex operations
$content = $content -replace "`r`n", "`n"

# Un-comment extension=gd if commented
$content = $content -replace '^\s*;\s*(extension\s*=\s*gd)\s*$', '$1', 'Multiline'

# Ensure extension=gd exists (add if missing)
if ($content -notmatch '^\s*extension\s*=\s*gd\s*$') {
    $content += "`nextension=gd"
}

# Ensure extension_dir is set to the expected path (replace if exists, otherwise add)
$desiredExtDir = 'extension_dir="C:\FildocDev\php\ext"'
if ($content -match '^\s*extension_dir\s*=.*$') {
    $content = $content -replace '^\s*extension_dir\s*=.*$', $desiredExtDir, 'Multiline'
} else {
    $content += "`n$desiredExtDir"
}

# Convert back to CRLF for Windows php.ini
$content = $content -replace "`n", "`r`n"

# Write updated content (preserve encoding)
Set-Content -LiteralPath $IniPath -Value $content -Encoding UTF8 -Force

Write-Output "OK: php.ini updated and backup created at $bak"
