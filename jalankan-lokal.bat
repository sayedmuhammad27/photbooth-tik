@echo off
title SnapTik - Mode Jaringan Lokal (Wi-Fi / LAN)
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0run-lokal.ps1"
pause
