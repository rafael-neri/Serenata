<?php

namespace Serenata\Sockets;

/**
 * A response in JSON-RPC 2.0 format.
 *
 * Value object.
 */
final class JsonRpcResponse implements JsonRpcMessageInterface
{
    /**
     * @var string
     */
    private $jsonrpc;

    /**
     * @var string|int|null
     */
    private $id;

    /**
     * @var mixed|null
     */
    private $result;

    /**
     * @var JsonRpcError|null
     */
    private $error;

    /**
     * @param string|int|null   $id
     * @param mixed|null        $result
     * @param JsonRpcError|null $error
     * @param string            $jsonrpc
     */
    public function __construct($id, $result = null, ?JsonRpcError $error = null, string $jsonrpc = '2.0')
    {
        $this->id = $id;
        $this->result = $result;
        $this->error = $error;
        $this->jsonrpc = $jsonrpc;
    }

    /**
     * @return string
     */
    public function getJsonrpc(): string
    {
        return $this->jsonrpc;
    }

    /**
     * @return string|int|null
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed|null
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * @return JsonRpcError|null
     */
    public function getError(): ?JsonRpcError
    {
        return $this->error;
    }

    /**
     * @param array<string,mixed> $array
     *
     * @return static
     */
    public static function createFromArray(array $array)
    {
        $error = isset($array['error']) ?
            (new JsonRpcError($array['error']['code'], $array['error']['message'], $array['error']['data'] ?? null)) :
            null;

        return new static(
            $array['id'],
            isset($array['result']) ? $array['result'] : null,
            $error,
            $array['jsonrpc']
        );
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        $data = [
            'jsonrpc' => $this->getJsonrpc(),
            'id'      => $this->getId(),
        ];

        if ($this->getError() !== null) {
            $data['error'] = $this->getError();
        } else {
            $data['result'] = $this->getResult();
        }

        return $data;
    }
}
