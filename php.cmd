@echo off
set "PHP_BIN=%~dp0..\tools\php74\php.exe"
if not exist "%PHP_BIN%" (
  echo PHP 7.4 portable runtime not found at "%PHP_BIN%". 1>&2
  exit /b 1
)
"%PHP_BIN%" %*
