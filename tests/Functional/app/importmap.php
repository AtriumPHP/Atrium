<?php

declare(strict_types=1);

/*
 * Minimal importmap for the functional test app, so that the panel layout's
 * {{ importmap('app') }} call resolves during kernel-rendered route tests.
 */
return [
    'app' => ['path' => './assets/app.js', 'entrypoint' => true],
];
