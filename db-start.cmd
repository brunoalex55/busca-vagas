@echo off
REM Projeto open source do GitHub: https://github.com/brunoalex55/busca-vagas
REM
REM Autor: Bruno Alexandre
REM
REM Pix (doacao): bruno@brunoalexandre.com
REM
REM Este projeto e open source. Doe se quiser manter o projeto vivo.
REM
REM Mais informacoes no README.

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0tools\db.ps1" start
