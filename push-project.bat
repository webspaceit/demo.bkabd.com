@echo off
setlocal
cd /d "E:\wamp64\www\oop-exam"

echo Current Git changes:
git status --short
echo.
set /p "CONFIRM=Stage and push ALL listed changes? Type YES to continue: "
if /I not "%CONFIRM%"=="YES" goto cancelled

git add -A
if errorlevel 1 goto failed

git diff --cached --quiet
if errorlevel 1 goto commit
goto push

:commit
git commit -m "Update project"
if errorlevel 1 goto failed

:push
git push
if errorlevel 1 goto failed
echo.
echo Push completed successfully.
goto end

:cancelled
echo Push cancelled. No files were staged.
goto end

:failed
echo.
echo Push stopped because a Git command failed. Review the message above.

:end
echo.
pause
