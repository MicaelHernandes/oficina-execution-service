<?php

use App\Providers\AppServiceProvider;
use App\Providers\ExecutionServiceProvider;
use App\Providers\MetricsServiceProvider;

return [
    AppServiceProvider::class,
    ExecutionServiceProvider::class,
    MetricsServiceProvider::class,
];
