@echo off
REM Ejecuta el script PowerShell para instalar y abrir la demo
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\install_demo.ps1"
pause
