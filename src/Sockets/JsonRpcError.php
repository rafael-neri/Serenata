<?php

namespace Serenata\Sockets;

use JsonSerializable;

/**
 * An error in JSON-RPC 2.0 format.
 *
 * Value object.
 */
final class JsonRpcError implements JsonSerializable
{
    /**
     * @var int
     */
    private $code;

    /**
     * @var string
     */
    private $message;

    /**
     * @var mixed|null
     */
    private $data;

    /**
     * @param int        $code
     * @param string     $message
     * @param mixed|null $data
     */
    public function __construct(int $code, string $message, $data = null)
    {
        $this->code = $code;
        $this->message = $message;
        $this->data = $data;
    }

    /**
     * @return int
     */
    public function getCode(): int
    {
        return $this->code;
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return mixed|null
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param array<string,mixed> $array
     *
     * @return static
     */
    public static function createFromArray(array $array)
    {
        return new static(
            $array['code'],
            $array['message'],
            isset($array['data']) ? $array['data'] : null
        );
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        $data = [
            'code'    => $this->getCode(),
            'message' => $this->getMessage(),
        ];

        if ($this->getData() !== null) {
            $data['data'] = $this->getData();
        }

        return $data;
    }
}
