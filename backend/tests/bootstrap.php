<?php

declare(strict_types=1);

// The backend operates entirely in UTC; pin it so timestamp tests are
// deterministic regardless of the host timezone.
date_default_timezone_set('UTC');

require __DIR__ . '/../vendor/autoload.php';
