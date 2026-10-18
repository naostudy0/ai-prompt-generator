<?php

namespace App\Domain\PromptPreparation\Exceptions;

use DomainException;

final class SystemManagedOptionPromptGroup extends DomainException
{
    public function __construct()
    {
        parent::__construct('The option prompt group is managed by the system.');
    }
}
