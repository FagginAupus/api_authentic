@echo off
echo 🚀 Iniciando sistema de polling para documentos...
echo.

echo ✅ Configurado para:
echo    • Verificar documentos a cada 5 minutos
echo    • Enviar emails para smart@aupusenergia.com.br
echo    • Logs salvos em storage/logs/polling.log
echo.

echo 💡 Comandos disponíveis:
echo    Testar agora:     php artisan documents:check-status
echo    Ver status:       php artisan schedule:list
echo    Parar polling:    Ctrl+C
echo.

echo 🔄 Iniciando polling em loop...
:loop
php artisan documents:check-status
echo.
echo ⏰ Aguardando 5 minutos... (próxima verificação às %time:~0,5%)
timeout /t 300 /nobreak > nul
goto loop