<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Kirim ringkasan alert WA setiap hari yang dikonfigurasi (default: Senin jam 08:00)
        $day  = config('services.fonnte.schedule_day', 'monday');
        $time = config('services.fonnte.schedule_time', '08:00');

        $schedule->command('wms:send-wa-alert')
            ->weeklyOn($this->dayToNumber($day), $time)
            ->withoutOverlapping()
            ->runInBackground();
    }

    private function dayToNumber(string $day): int
    {
        return match (strtolower($day)) {
            'sunday'    => 0,
            'monday'    => 1,
            'tuesday'   => 2,
            'wednesday' => 3,
            'thursday'  => 4,
            'friday'    => 5,
            'saturday'  => 6,
            default     => 1,
        };
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
