@echo off
if "%1" == "add" git %1 %2 %3 %4
if "%1" == "commit" git %1 %2 %3 %4
php %~dp0gitftp %1 %2 %3 %4