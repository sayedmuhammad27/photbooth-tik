@echo off
title Photobooth TIK - All-in-One Online & Localhost Launcher
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0run-tunnel.ps1"
pause
