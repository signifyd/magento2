<?php

namespace Signifyd\Connect\Model;

use Magento\Framework\Serialize\Serializer\Json;
use Signifyd\Connect\Logger\Logger;

class JsonSerializer
{
    /**
     * @var Json
     */
    public $jsonSerializer;

    /**
     * @var Logger
     */
    public $logger;

    public function __construct(
        Json $jsonSerializer,
        Logger $logger
    ) {
        $this->jsonSerializer = $jsonSerializer;
        $this->logger = $logger;
    }

    /**
     * @param $data
     * @param $entity
     * @return bool|string
     */
    public function serialize($data, $entity = null)
    {
        try {
            return $this->jsonSerializer->serialize($data);
        } catch (\InvalidArgumentException $e) {
            $data = $this->fixInvalidUtf8Fields($data, $entity);
        }

        try {
            return $this->jsonSerializer->serialize($data);
        } catch (\InvalidArgumentException $e) {
            $this->logger->info(
                "Unable to serialize: " . print_r($data, true),
                ['entity' => $entity]
            );
            return $this->jsonSerializer->serialize([]);
        }
    }

    /**
     * @param $string
     * @return array|bool|float|int|mixed|string|null
     */
    public function unserialize($string)
    {
        return $this->jsonSerializer->unserialize($string);
    }

    /**
     * @param $data
     * @param $path
     * @return array
     */
    public function fixInvalidUtf8Fields(array &$data, $entity = null, string $path = ''): array
    {
        $invalidFields = [];

        foreach ($data as $key => &$value) {
            $currentPath = $path === '' ? $key : "$path->$key";

            if (is_array($value)) {
                $this->fixInvalidUtf8Fields($value, $entity, $currentPath);
            } elseif (is_string($value)) {
                if (!mb_check_encoding($value, 'UTF-8')) {
                    $invalidFields[$currentPath] = $value;
                    $value = preg_replace('/[^\x00-\x7F]/', '', $value);

                    if (isset($entity)) {
                        $this->logger->info(
                            "Fixed invalid UTF-8 value at path {$currentPath}: {$value}",
                            ['entity' => $entity]
                        );
                    }
                }
            }
        }

        return $data;
    }
}
