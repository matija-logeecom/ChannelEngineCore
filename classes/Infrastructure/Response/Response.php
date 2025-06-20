<?php

namespace ChannelEngineCore\Infrastructure\Response;

abstract class Response
{
    protected mixed $body;
    protected array $headers = [];
    protected int $statusCode = 200;

    /**
     * Constructs Response instance
     *
     * @param mixed $body
     * @param int $statusCode
     * @param array $headers
     */
    public function __construct(mixed $body, int $statusCode = 200, array $headers = [])
    {
        $this->body = $body;
        $this->headers = $headers;
        $this->statusCode = $statusCode;
    }

    /**
     * Getter for body
     *
     * @return mixed
     */
    public function getBody(): mixed
    {
        return $this->body;
    }

    /**
     * Setter for body
     *
     * @param mixed $body
     *
     * @return void
     */
    public function setBody(mixed $body): void
    {
        $this->body = $body;
    }

    /**
     * Getter for headers
     *
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Sends headers
     *
     * @return void
     */
    protected function sendHeaders(): void
    {
        if (headers_sent()) {
            error_log("Headers already sent");

            return;
        }

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
    }

    /**
     * Sends status code
     *
     * @return void
     */
    protected function sendStatusCode(): void
    {
        http_response_code($this->statusCode);
    }

    /**
     * Renders page
     *
     * @return void
     */
    public function view(): void
    {
        if (!headers_sent()) {
            $this->sendStatusCode();
            $this->sendHeaders();
        }
    }

    /**
     * Creates not found response
     *
     * @param string $message
     *
     * @return self
     */
    abstract static public function createNotFound(string $message = "Page not found."): self;

    /**
     * Creates bad request response
     *
     * @param string $message
     *
     * @return self
     */
    abstract static public function createBadRequest(string $message = "Bad Request."): self;

    /**
     * Creates internal server error response
     *
     * @param string $message
     *
     * @return self
     */
    abstract static public function createInternalServerError(string $message =
                                                              "An internal server error occurred."): self;
}
