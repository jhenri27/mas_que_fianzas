@echo off
title Lanzador Visual Playwright Chrome - MAS QUE FIANZAS
cd /d "%~dp0"
cls
echo =======================================================================
echo  LANZANDO DEMOSTRACION VISUAL EN SU PANTALLA (GOOGLE CHROME E2E)
echo =======================================================================
echo.
echo Abriendo Google Chrome interactivo... Por favor observe su pantalla.
echo.
python backend\tests\bot_visual_e2e\bot_visual_runner.py --perfil 1 --modulo polizas --escenario emision_individual --visible true
echo.
echo =======================================================================
echo  Prueba Visual Completada.
echo =======================================================================
pause
