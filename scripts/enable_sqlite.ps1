param(
    [string]$IniPath = 'C:\FildocDev\php\php.ini'
)

if (-not (Test-Path $IniPath)) {
    Write-Error "php.ini not found at $IniPath"
    exit 2
}

# Backup
$bak = "$IniPath.sqlite.bak"
Copy-Item -Path $IniPath -Destination $bak -Force

# Read content as single block
$content = Get-Content -Raw -LiteralPath $IniPath

# Normalize line endings to `n for regex operations
$content = $content -replace "`r`n", "`n"

# Un-comment or add extension lines for sqlite3 and pdo_sqlite
$content = $content -replace '^\s*;\s*(extension\s*=\s*sqlite3)\s*$', '$1', 'Multiline'
$content = $content -replace '^\s*;\s*(extension\s*=\s*pdo_sqlite)\s*$', '$1', 'Multiline'

if ($content -notmatch '^\s*extension\s*=\s*sqlite3\s*$') {
    $content += "`nextension=sqlite3"
}
if ($content -notmatch '^\s*extension\s*=\s*pdo_sqlite\s*$') {
    $content += "`nextension=pdo_sqlite"
}

# Convert back to CRLF for Windows php.ini
$content = $content -replace "`n", "`r`n"

# Write updated content (preserve encoding)
Set-Content -LiteralPath $IniPath -Value $content -Encoding UTF8 -Force

Write-Output "OK: php.ini updated for sqlite (backup created at $bak)"
