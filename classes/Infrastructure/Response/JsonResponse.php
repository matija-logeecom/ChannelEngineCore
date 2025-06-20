<?php

namespace ChannelEngineCore\Infrastructure\Response;

/*
 * Class for handling JSON response logic
 */

class JsonResponse extends Response
{
    /**
     * Constructs Json Response instance
     *
     * @param mixed $data
     * @param int $statusCode
     * @param array $headers
     */
    public function __construct(mixed $data, int $statusCode = 200, array $headers = [])
    {
        parent::__construct($data, $statusCode, $headers);

        if (!isset($this->headers['Content-Type'])) {
            $this->headers['Content-Type'] = 'application/json';
        }
    }

    /**
     * @inheritDoc
     */
    public function view(): void
    {
        parent::view();

        echo json_encode($this->body);
    }

    /**
     * @inheritDoc
     */
    public static function createNotFound(string $message = "Page not found."): self
    {
        return new self(['error' => $message], 404);
    }

    /**
     * @inheritDoc
     */
    public static function createBadRequest(string $message = "Bad Request."): self
    {
        return new self(['error' => $message], 400);
    }

    /**
     * @inheritDoc
     */
    public static function createInternalServerError(string $message = "Internal server error."): self
    {
        return new self(['error' => $message], 500);
    }
}
