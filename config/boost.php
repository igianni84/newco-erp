<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Agent Overrides
    |--------------------------------------------------------------------------
    |
    | Boost writes its composed AI guidelines to each agent's guideline file.
    | For Claude Code that default is CLAUDE.md, which in this repo is
    | protected loop infrastructure (decisions/2026-06-11-stack-versions-and-
    | filament-ai-tooling.md): Boost output must land in AGENTS.md instead.
    | Remaining Boost options merge from vendor/laravel/boost/config/boost.php.
    |
    */

    'agents' => [
        'claude_code' => [
            'guidelines_path' => 'AGENTS.md',
        ],
    ],

];
