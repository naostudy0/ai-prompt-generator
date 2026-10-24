<?php

namespace App\Application\PromptPreparation\Exceptions;

use RuntimeException;

final class HiddenCharacterLora extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('非表示の人物LoRAは差し替え候補として送信できません。');
    }
}
