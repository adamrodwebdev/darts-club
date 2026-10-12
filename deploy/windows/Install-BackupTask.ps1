<#
  Installe la copie automatique des sauvegardes sur l'ordinateur du bureau (Windows 10/11) :
  1. clé SSH dédiée (~\.ssh\skull-darts-71-backup) si elle n'existe pas ;
  2. script de copie dans %USERPROFILE%\.skull-darts-71 (hors AppData : certaines applications y redirigent l'écriture) ;
  3. tâche planifiée : chaque jour à 12 h 30 et à l'ouverture de session, rattrapée si l'ordinateur était éteint.

  Ensuite, autoriser la clé publique affichée sur le serveur (une ligne dans ~deploy/.ssh/authorized_keys,
  voir docs/deploiement.md § 10). Usage :
    powershell -ExecutionPolicy Bypass -File deploy\windows\Install-BackupTask.ps1 [-Destination "D:\Sauvegardes"]
#>
param(
    [string]$Destination = (Join-Path ([Environment]::GetFolderPath('MyDocuments')) 'Sauvegardes Skull Darts 71')
)

$ErrorActionPreference = 'Stop'
$key = Join-Path $HOME '.ssh\skull-darts-71-backup'
if (-not (Test-Path $key)) {
    New-Item -ItemType Directory -Force -Path (Split-Path $key) | Out-Null
    # Sans phrase de passe : la tâche tourne sans personne ; la clé ne permet que de lire les sauvegardes chiffrées
    & (Join-Path $env:WINDIR 'System32\OpenSSH\ssh-keygen.exe') -q -t ed25519 -N '""' -C 'skull-darts-71-backup-pull' -f $key
}

$home71 = Join-Path $HOME '.skull-darts-71'
New-Item -ItemType Directory -Force -Path $home71 | Out-Null
$script = Join-Path $home71 'Pull-Backups.ps1'
Copy-Item -Force (Join-Path $PSScriptRoot 'Pull-Backups.ps1') $script

$action = New-ScheduledTaskAction -Execute 'powershell.exe' -Argument "-NoProfile -NonInteractive -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$script`" -Destination `"$Destination`""
$triggers = @(
    (New-ScheduledTaskTrigger -Daily -At '12:30'),
    (New-ScheduledTaskTrigger -AtLogOn -User "$env:USERDOMAIN\$env:USERNAME")
)
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -RunOnlyIfNetworkAvailable -ExecutionTimeLimit (New-TimeSpan -Minutes 30) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
Register-ScheduledTask -Force -TaskName 'Skull Darts 71 - copie des sauvegardes' -Description 'Copie les sauvegardes chiffrées du serveur skulldarts71.fr (voir docs/deploiement.md § 10).' -Action $action -Trigger $triggers -Settings $settings | Out-Null

Write-Host 'Tâche planifiée installée. Clé publique à autoriser sur le serveur :'
Get-Content "$key.pub"
