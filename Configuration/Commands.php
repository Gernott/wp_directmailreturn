<?php

use Reelworx\WpDirectmailreturn\Command\AnalyzeMailCommand;

return [
    'wpdirectmailreturn:analyzemail' => [
        'class' => AnalyzeMailCommand::class,
        'schedulable' => true
    ],
];
