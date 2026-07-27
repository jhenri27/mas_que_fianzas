@echo off
title Servicio Desktop Chrome Visible - MAS QUE FIANZAS
cd /d "%~dp0"
cls
echo =======================================================================
echo  INICIANDO SERVICIO DESKTOP PARA NAVEGACION VISIBLE EN PANTALLA
echo =======================================================================
echo.
echo Este servicio permite que CUALQUIER prueba lanzada desde la Web (LABS-QA)
echo abra AUTOMATICAMENTE una ventana emergente de Google Chrome en su monitor.
echo.
echo Mantenga esta ventana abierta mientras realice pruebas en el sistema.
echo.
python backend\tests\bot_visual_e2e\desktop_runner_service.py
pause
