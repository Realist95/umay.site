<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // === Запуск воркера очереди через планировщик ===
        // Каждую минуту проверяем, запущен ли воркер. Если нет – запускаем,
        // обрабатываем все задачи и завершаемся (--stop-when-empty).
        // withoutOverlapping() не даёт запустить второй экземпляр.
        $schedule->command('queue:work database --stop-when-empty')
            ->everyMinute()
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
