@echo off
REM ============================================================
REM Create + import the oop_exam database (idempotent-ish).
REM Usage: database\import.bat
REM ============================================================
set MYSQL=E:\wamp64\bin\mysql\mysql8.4.7\bin\mysql.exe
set DB=oop_exam

%MYSQL% -u root -e "CREATE DATABASE IF NOT EXISTS `%DB%` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if errorlevel 1 exit /b 1

%MYSQL% -u root %DB% < "%~dp0schema.sql"
if errorlevel 1 exit /b 1

%MYSQL% -u root %DB% < "%~dp0seed_data.sql"
if errorlevel 1 exit /b 1

%MYSQL% -u root %DB% < "%~dp0demo_seed.sql"
if errorlevel 1 exit /b 1

echo Import complete.
