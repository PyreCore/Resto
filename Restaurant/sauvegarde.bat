@echo off
rem ============================================
rem  LeDelise - Sauvegarde de la base de donnees
rem  Lance scripts\sauvegarde.php avec PHP (CLI)
rem ============================================

setlocal
cd /d "%~dp0"

rem Recherche de PHP : d'abord dans le PATH, puis dans les emplacements WAMP usuels
set "PHP="
for /f "delims=" %%i in ('where php 2^>nul') do (
    if not defined PHP set "PHP=%%i"
)
if not defined PHP if exist "C:\wamp64\bin\php\php8.5.0\php.exe" set "PHP=C:\wamp64\bin\php\php8.5.0\php.exe"
if not defined PHP for %%p in ("C:\wamp64\bin\php\php*.exe") do if not defined PHP set "PHP=%%~fp"

if not defined PHP (
    echo PHP introuvable. Installez PHP ou ajustez le chemin dans sauvegarde.bat.
    pause
    exit /b 1
)

"%PHP%" scripts\sauvegarde.php
pause
