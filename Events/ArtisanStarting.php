<?php

namespace Satupersen\Console\Events;

class ArtisanStarting
{
    /**
     * The Artisan application instance.
     *
     * @var \Satupersen\Console\Application
     */
    public $artisan;

    /**
     * Create a new event instance.
     *
     * @param  \Satupersen\Console\Application  $artisan
     * @return void
     */
    public function __construct($artisan)
    {
        $this->artisan = $artisan;
    }
}
