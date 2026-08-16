<?php

declare(strict_types=1);

return [
    'methodNotFound'     => 'No such method found',
    'invalidAuth'        => 'An API key is required',
    'invalidHost'        => 'The host must be a bare hostname such as "api.vrio.app", without a scheme or path',
    'notAssigned'        => 'Section and method must be assigned',
    'apiMethodInvoked'   => 'No API method has been invoked yet',
    'jsonFormatError'    => 'API response is not valid JSON',
    'requiredFields'     => 'Missing required fields',
    'debugFileRequired'  => 'Debug logging needs either a "debugFile" base path or a "debugSink" callable',
    'invalidDebugSink'   => 'The "debugSink" option must be callable',
    'invalidTimezone'    => 'The configured timezone is not a recognised IANA identifier',
];
