@echo off
title SnapTik - Stop All Services
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0stop-all.ps1"
pause
