<?php

function request(
    string $path,
    array &$cookies,
    int|array $expected = 200,
    ?array $input = null,
    string $method = 'POST',
): array {
    $header = ['Accept: application/json'];
    $header[] = 'Cookie: '.implode('; ', $cookies);
    $options = ['ignore_errors' => true, 'header' => $header];

    if ($input !== null) {
        $options['method'] = $method;
        $options['header'][] = 'Content-Type: application/json';
        $options['content'] = json_encode($input, JSON_THROW_ON_ERROR);
    }

    $options['follow_location'] = 0;
    $start = hrtime(true);
    $body = file_get_contents('http://127.0.0.1:8000'.$path, false, stream_context_create(['http' => $options]));
    $elapsed = (hrtime(true) - $start) / 1e6;
    $headers = http_get_last_response_headers();

    $status = (int) explode(' ', $headers[0] ?? '')[1];

    if (! in_array($status, (array) $expected, true)) {
        throw new RuntimeException("$path: ".($headers[0] ?? '').' '.substr((string) $body, 0, 500));
    }

    $metrics = [];
    foreach ($headers as $line) {
        if (preg_match('/^set-cookie: (([^=]+)=[^;]+)/i', $line, $matches)) {
            $cookies[$matches[2]] = $matches[1];
        }

        if (str_starts_with(strtolower($line), 'x-e03-metrics:')) {
            $metrics = json_decode(trim(explode(':', $line, 2)[1]), true, flags: JSON_THROW_ON_ERROR);
        }
    }

    return [json_decode((string) $body, true), [...$metrics, 'http_ms' => $elapsed], $status];
}

function verify(bool $condition): void
{
    if (! $condition) {
        throw new RuntimeException('Falha na verificação HTTP da linha '.debug_backtrace()[0]['line'].'.');
    }
}
