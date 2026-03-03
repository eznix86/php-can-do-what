<?php

use App\Ai\Agents\BotAssistant;
use App\Ai\Agents\DwightAssistant;
use App\Ai\Agents\FinancialAssistant;
use App\Ai\Agents\JimmyCoachAssistant;
use App\Ai\Agents\MichealScottAssistant;
use App\Ai\Agents\NanoAgent;

return [
    'chat' => [
        'bot' => BotAssistant::class,
        'jimmy' => JimmyCoachAssistant::class,
        'micheal' => MichealScottAssistant::class,
        'dwight' => DwightAssistant::class,
        'financial' => FinancialAssistant::class,
        'nano' => NanoAgent::class,
    ],
];
