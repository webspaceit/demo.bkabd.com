@echo off
title BKABD Local Server
cd /d E:\wamp64\www\oop-exam
echo Starting BKABD dev server at http://127.0.0.1:8765 ...
echo Press Ctrl+C to stop.  Logs: php_server.log
start "" "http://127.0.0.1:8765"
php -S 127.0.0.1:8765 -t public/ > php_server.log 2>&1
