<?php

namespace App\Domain\PromptPreparation\Models;

enum GenerationSettingKind: string
{
    case Checkpoint = 'checkpoint';
    case Sampler = 'sampler';
    case Scheduler = 'scheduler';
}
