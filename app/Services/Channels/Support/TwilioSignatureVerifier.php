<?php

namespace App\Services\Channels\Support;

/**
 * Verifies Twilio's `X-Twilio-Signature` header: HMAC-SHA1 (base64) of the
 * full request URL with every POST parameter's key+value appended in sorted
 * key order, keyed by the account's Auth Token.
 *
 * @see https://www.twilio.com/docs/usage/security#validating-requests
 */
class TwilioSignatureVerifier
{
    /** @param  array<string, string>  $params */
    public function verify(string $url, array $params, ?string $signatureHeader, string $authToken): bool
    {
        if (! $signatureHeader) {
            return false;
        }

        ksort($params);

        $data = $url;

        foreach ($params as $key => $value) {
            $data .= $key.$value;
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $authToken, true));

        return hash_equals($expected, $signatureHeader);
    }
}
