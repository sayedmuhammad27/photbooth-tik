@echo off
title Photobooth TIK - Mode Jaringan Lokal (Wi-Fi / LAN)
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0run-lokal.ps1"
pause
