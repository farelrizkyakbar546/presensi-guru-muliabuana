@echo off
title Absensi PKM - QR Display

set "URL=http://localhost/absensi-pkm/qr/display.php"

if exist "C:\Program Files\Google\Chrome\Application\chrome.exe" (
    start "" "C:\Program Files\Google\Chrome\Application\chrome.exe" ^
        --kiosk ^
        --no-first-run ^
        --disable-session-crashed-bubble ^
        "%URL%"
    exit
)

if exist "C:\Program Files (x86)\Google\Chrome\Application\chrome.exe" (
    start "" "C:\Program Files (x86)\Google\Chrome\Application\chrome.exe" ^
        --kiosk ^
        --no-first-run ^
        --disable-session-crashed-bubble ^
        "%URL%"
    exit
)

start "" "%URL%"
exit