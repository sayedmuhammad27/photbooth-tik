@echo off
title Photobooth TIK - Cloudflare Online Server
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0run-tunnel.ps1"
pause
