<?php

namespace Satupersen\Console\Scheduling;

use Satupersen\Console\Command;
use Satupersen\Console\Events\ScheduledTaskFinished;
use Satupersen\Console\Events\ScheduledTaskSkipped;
use Satupersen\Console\Events\ScheduledTaskStarting;
use Satupersen\Contracts\Events\Dispatcher;
use Satupersen\Support\Facades\Date;

class ScheduleRunCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'schedule:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the scheduled commands';

    /**
     * The schedule instance.
     *
     * @var \Satupersen\Console\Scheduling\Schedule
     */
    protected $schedule;

    /**
     * The 24 hour timestamp this scheduler command started running.
     *
     * @var \Satupersen\Support\Carbon
     */
    protected $startedAt;

    /**
     * Check if any events ran.
     *
     * @var bool
     */
    protected $eventsRan = false;

    /**
     * The event dispatcher.
     *
     * @var \Satupersen\Contracts\Events\Dispatcher
     */
    protected $dispatcher;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->startedAt = Date::now();

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @param  \Satupersen\Console\Scheduling\Schedule  $schedule
     * @param  \Satupersen\Contracts\Events\Dispatcher  $dispatcher
     * @return void
     */
    public function handle(Schedule $schedule, Dispatcher $dispatcher)
    {
        $this->schedule = $schedule;
        $this->dispatcher = $dispatcher;

        foreach ($this->schedule->dueEvents($this->laravel) as $event) {
            if (! $event->filtersPass($this->laravel)) {
                $this->dispatcher->dispatch(new ScheduledTaskSkipped($event));

                continue;
            }

            if ($event->onOneServer) {
                $this->runSingleServerEvent($event);
            } else {
                $this->runEvent($event);
            }

            $this->eventsRan = true;
        }

        if (! $this->eventsRan) {
            $this->info('No scheduled commands are ready to run.');
        }
    }

    /**
     * Run the given single server event.
     *
     * @param  \Satupersen\Console\Scheduling\Event  $event
     * @return void
     */
    protected function runSingleServerEvent($event)
    {
        if ($this->schedule->serverShouldRun($event, $this->startedAt)) {
            $this->runEvent($event);
        } else {
            $this->line('<info>Skipping command (has already run on another server):</info> '.$event->getSummaryForDisplay());
        }
    }

    /**
     * Run the given event.
     *
     * @param  \Satupersen\Console\Scheduling\Event  $event
     * @return void
     */
    protected function runEvent($event)
    {
        $this->line('<info>Running scheduled command:</info> '.$event->getSummaryForDisplay());

        $this->dispatcher->dispatch(new ScheduledTaskStarting($event));

        $start = microtime(true);

        $event->run($this->laravel);

        $this->dispatcher->dispatch(new ScheduledTaskFinished(
            $event,
            round(microtime(true) - $start, 2)
        ));

        $this->eventsRan = true;
    }
}
