<?php

namespace Admidio\Tests\Unit\Infrastructure\Support;

use Admidio\Infrastructure\SmtpOAuth;
use LogicException;
use ReflectionProperty;

/**
 * The real SmtpOAuth with the token endpoint replaced: every request is recorded and answered with
 * the next response the test scripted, so no request ever leaves the test.
 */
class ScriptedSmtpOAuth extends SmtpOAuth
{
    /** @var array<int,array{url:string,parameters:array<string,string>}> */
    public array $requests = array();
    /** @var array<int,array{status:int,body:string}> */
    public array $responses = array();

    /**
     * SmtpOAuth keeps the access tokens for the whole request, a test run is one request for all
     * tests. Each test therefore starts without them.
     */
    public static function forgetAccessTokens(): void
    {
        (new ReflectionProperty(SmtpOAuth::class, 'accessTokens'))->setValue(null, array());
    }

    /**
     * @param array<string,mixed> $fields The JSON fields of the answer.
     */
    public function answerWith(array $fields, int $status = 200): self
    {
        return $this->answerWithBody(json_encode($fields), $status);
    }

    public function answerWithBody(string $body, int $status): self
    {
        $this->responses[] = array('status' => $status, 'body' => $body);

        return $this;
    }

    protected function sendTokenRequest(string $url, array $parameters): array
    {
        $this->requests[] = array('url' => $url, 'parameters' => $parameters);

        if ($this->responses === array()) {
            throw new LogicException('The test scripted no answer for the request to ' . $url);
        }

        return array_shift($this->responses);
    }
}
