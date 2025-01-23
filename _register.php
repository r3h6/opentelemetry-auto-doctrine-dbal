<?php

declare(strict_types=1);

use OpenTelemetry\SDK\Sdk;
use R3H6\OpentelemetryAutoDoctrineDbal\DoctrineDbalInstrumentation;

if (class_exists(Sdk::class) && Sdk::isInstrumentationDisabled(DoctrineDbalInstrumentation::NAME) === true) {
    return;
}

if (extension_loaded('opentelemetry') === false) {
    trigger_error('The opentelemetry extension must be loaded in order to autoload the OpenTelemetry PDO auto-instrumentation', E_USER_WARNING);

    return;
}

DoctrineDbalInstrumentation::register();