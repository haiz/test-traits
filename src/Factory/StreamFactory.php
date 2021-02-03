<?php

namespace Haiz\TestTrait\Factory;

use InvalidArgumentException;
use RuntimeException;
use Http\Stream;
use ValueError;

use function fopen;
use function fwrite;
use function is_resource;
use function restore_error_handler;
use function rewind;
use function set_error_handler;
use function sprintf;

class StreamFactory implements StreamFactoryInterface
{
    /**
     * {@inheritdoc}
     *
     * @throws RuntimeException
     */
    public function createStream($content = '')
    {
        $resource = fopen('php://temp', 'rw+');

        if (!is_resource($resource)) {
            throw new RuntimeException('StreamFactory::createStream() could not open temporary file stream.');
        }

        fwrite($resource, $content);
        rewind($resource);

        return $this->createStreamFromResource($resource);
    }

    /**
     * {@inheritdoc}
     */
    public function createStreamFromFile($filename, $mode = 'r', $cache = null)
    {
        // When fopen fails, PHP 7 normally raises a warning. Add an error
        // handler to check for errors and throw an exception instead.
        // On PHP 8, exceptions are thrown.
        $exc = null;

        // Would not be initialized if fopen throws on PHP >= 8.0
        $resource = null;

        $errorHandler = function ($errorMessage) use ($filename, $mode, &$exc) {
            $exc = new RuntimeException(sprintf(
                'Unable to open %s using mode %s: %s',
                $filename,
                $mode,
                $errorMessage
            ));
        };

        set_error_handler(function ($errno, $errstr) use ($errorHandler) {
            $errorHandler($errstr);
        });

        try {
            $resource = fopen($filename, $mode);
        // @codeCoverageIgnoreStart
        // (Can only be executed in PHP >= 8.0)
        } catch (ValueError $exception) {
            $errorHandler($exception->getMessage());
        }
        // @codeCoverageIgnoreEnd
        restore_error_handler();

        if ($exc) {
            /** @var RuntimeException $exc */
            throw $exc;
        }

        if (!is_resource($resource)) {
            throw new RuntimeException(
                "StreamFactory::createStreamFromFile() could not create resource from file `$filename`"
            );
        }

        return new Stream($resource, $cache);
    }

    /**
     * {@inheritdoc}
     */
    public function createStreamFromResource($resource, $cache = null)
    {
        if (!is_resource($resource)) {
            throw new InvalidArgumentException(
                'Parameter 1 of StreamFactory::createStreamFromResource() must be a resource.'
            );
        }

        return new Stream($resource, $cache);
    }
}
