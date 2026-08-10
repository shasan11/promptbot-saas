<?php

namespace App\Services\AI;

use App\Exceptions\AI\AIProviderException;
use NeuronAI\Exceptions\HttpException;
use Throwable;

class ProviderErrorClassifier
{
    public function classify(Throwable $exception): AIProviderException
    {
        if ($exception instanceof AIProviderException) {
            return $exception;
        }

        $status = $exception instanceof HttpException ? $exception->response?->statusCode : null;

        return match (true) {
            in_array($status, [401, 403], true) => new AIProviderException('authentication_failed', 'The provider rejected the configured credentials.', false, $exception),
            $status === 429 => new AIProviderException('rate_limited', 'The provider rate limit was reached. Try again shortly.', true, $exception),
            is_int($status) && $status >= 500 => new AIProviderException('provider_unavailable', 'The provider is temporarily unavailable.', true, $exception),
            $status === 404 => new AIProviderException('model_or_endpoint_not_found', 'The configured model or endpoint was not found.', false, $exception),
            str_contains(strtolower($exception->getMessage()), 'timed out') => new AIProviderException('timeout', 'The provider did not respond before the timeout.', true, $exception),
            str_contains(strtolower($exception->getMessage()), 'could not resolve') => new AIProviderException('connection_failed', 'The provider endpoint could not be reached.', true, $exception),
            default => new AIProviderException('provider_error', 'The provider request failed. Review the configuration and try again.', false, $exception),
        };
    }
}
