<#
  Copie les sauvegardes chiffrées du serveur vers l'ordinateur du bureau.
  Lancé par la tâche planifiée « Skull Darts 71 - copie des sauvegardes »
  (voir Install-BackupTask.ps1) : chaque jour et à l'ouverture de session.

  - Clé SSH dédiée, limitée côté serveur à « list » et « get » (deploy/backup-share.sh).
  - Seules les sauvegardes absentes sont téléchargées, puis vérifiées (en-tête age).
  - Les $Keep plus récentes sont conservées ; les fichiers restent chiffrés.
#>
param(
    [string]$Destination = (Join-Path ([Environment]::GetFolderPath('MyDocuments')) 'Sauvegardes Skull Darts 71'),
    [string]$Server = 'deploy@15.235.100.59',
    [string]$Key = (Join-Path $HOME '.ssh\skull-darts-71-backup'),
    [int]$Keep = 30
)

$ErrorActionPreference = 'Stop'
$ssh = Join-Path $env:WINDIR 'System32\OpenSSH\ssh.exe'
$pattern = '^skull-darts-71-\d{8}-\d{6}\.tar\.gz\.age$'
New-Item -ItemType Directory -Force -Path $Destination | Out-Null
$log = Join-Path $Destination 'copie.log'

function Write-Log([string]$message) {
    $line = '{0:yyyy-MM-dd HH:mm:ss}  {1}' -f (Get-Date), $message
    Add-Content -Path $log -Value $line -Encoding UTF8
}

# Fichier chiffré par age : commence par « age-encryption.org/v1 »
function Test-AgeHeader([string]$path) {
    if (-not (Test-Path $path)) { return $false }
    $stream = [IO.File]::OpenRead($path)
    try {
        $buffer = New-Object byte[] 21
        $read = $stream.Read($buffer, 0, 21)
        return $read -eq 21 -and [Text.Encoding]::ASCII.GetString($buffer) -eq 'age-encryption.org/v1'
    }
    finally { $stream.Dispose() }
}

# Arguments communs : clé dédiée uniquement, jamais d'interaction
$options = "-i `"$Key`" -o BatchMode=yes -o IdentitiesOnly=yes -o ConnectTimeout=20"

try {
    $remote = & cmd.exe /c "`"$ssh`" $options $Server list 2>nul"
    if ($LASTEXITCODE -ne 0) { throw "serveur injoignable ou clé refusée (code $LASTEXITCODE)" }
    $names = @($remote | Where-Object { $_ -match $pattern })

    $copied = 0
    foreach ($name in $names) {
        $target = Join-Path $Destination $name
        if (Test-Path $target) { continue }
        $part = "$target.part"
        # Redirection par cmd.exe : flux binaire intact (PowerShell 5.1 le convertirait en texte)
        & cmd.exe /c "`"$ssh`" $options $Server get $name > `"$part`" 2>nul"
        $exit = $LASTEXITCODE
        if ($exit -ne 0 -or -not (Test-AgeHeader $part)) {
            Remove-Item -Force -ErrorAction SilentlyContinue $part
            throw "téléchargement de $name incomplet ou invalide"
        }
        Move-Item -Force $part $target
        $copied++
    }

    # Conservation : les $Keep plus récentes (le nom contient la date)
    $old = Get-ChildItem $Destination -File | Where-Object { $_.Name -match $pattern } | Sort-Object Name -Descending | Select-Object -Skip $Keep
    $old | Remove-Item -Force
    $total = @(Get-ChildItem $Destination -File | Where-Object { $_.Name -match $pattern }).Count
    Write-Log "OK : $copied nouvelle(s) sauvegarde(s), $total conservée(s)"
}
catch {
    Write-Log "ÉCHEC : $($_.Exception.Message)"
    exit 1
}
