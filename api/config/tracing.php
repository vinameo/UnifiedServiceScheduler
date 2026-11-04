<?php

return [
    'disabled' => filter_var(env('OTEL_SDK_DISABLED', true), FILTER_VALIDATE_BOOLEAN),
    'service_name' => env('OTEL_SERVICE_NAME', 'unified-service-scheduler'),
    'endpoint' => env('OTEL_EXPORTER_OTLP_ENDPOINT', 'http://localhost:4318'),
];
