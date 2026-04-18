<?php

namespace App\Enums;

use App\Actions\FeatureAction;
use App\Actions\HorizonAction;
use App\Actions\OctaneAction;
use App\Actions\QueueAction;
use App\Actions\ReverbAction;
use App\Actions\ScoutAction;
use App\Actions\TaskSchedulingAction;

enum LaravelFeature: string
{
    case TASK_SCHEDULING = 'Task Scheduling';
    case HORIZON = 'Horizon (with Redis)';
    case QUEUES = 'Queues (without Redis)';
    case REVERB = 'Reverb';
    case SCOUT = 'Laravel Scout';
    case OCTANE = 'Octane (requires FrankenPHP)';

    public function action(): FeatureAction
    {
        return match ($this) {
            self::TASK_SCHEDULING => new TaskSchedulingAction,
            self::HORIZON => new HorizonAction,
            self::QUEUES => new QueueAction,
            self::REVERB => new ReverbAction,
            self::SCOUT => new ScoutAction,
            self::OCTANE => new OctaneAction
        };
    }
}
